<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Support\NotificationConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Delivery callbacks from WhatsApp and the email providers.
 *
 * These are the only public, unauthenticated routes in the panel, so each one
 * proves who it is before it is allowed to change anything - WhatsApp by the
 * verify token, the email providers by a shared secret on the URL.
 *
 * An API call returning 200 only means the provider accepted the message. What
 * actually happened to it arrives here, minutes or hours later.
 */
class WebhookController extends Controller
{
    /**
     * Meta's subscription handshake: echo the challenge back, but only when
     * the token matches the one configured.
     */
    public function verifyWhatsApp(Request $request): Response
    {
        $expected = NotificationConfig::whatsapp()['webhook_verify_token'];

        if (blank($expected) || ! hash_equals($expected, (string) $request->query('hub_verify_token'))) {
            return response('Verification failed.', 403);
        }

        return response((string) $request->query('hub_challenge'), 200);
    }

    /**
     * Status updates for messages already sent.
     *
     * Always answers 200. A webhook that returns an error gets retried and
     * eventually disabled by Meta, and a status update we could not match is
     * not worth losing the subscription over.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $this->applyWhatsAppStatus($status);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyWhatsAppStatus(array $status): void
    {
        $log = NotificationLog::where('provider_message_id', $status['id'] ?? '')->first();

        if (! $log) {
            return;
        }

        match ($status['status'] ?? '') {
            'sent' => $this->advance($log, 'sent', ['sent_at' => now()]),
            'delivered' => $this->advance($log, 'delivered', ['delivered_at' => now()]),
            'read' => $this->advance($log, 'read', ['read_at' => now()]),
            'failed' => $log->update([
                'status' => 'failed',
                'error' => $status['errors'][0]['title'] ?? 'WhatsApp reported a delivery failure.',
            ]),
            default => null,
        };
    }

    /**
     * Email provider events.
     *
     * The shape differs per provider, so this reads the few fields they have
     * in common and ignores the rest. The secret in the URL is what makes the
     * route safe to leave open.
     */
    public function email(Request $request, string $secret): JsonResponse
    {
        $expected = NotificationConfig::whatsapp()['webhook_verify_token'];

        // The same shared secret guards both callbacks - one thing to rotate.
        if (blank($expected) || ! hash_equals($expected, $secret)) {
            return response()->json(['error' => 'Unauthorised.'], 403);
        }

        // SendGrid posts an array of events; the others post a single object.
        $events = array_is_list($request->all()) ? $request->all() : [$request->all()];

        foreach ($events as $event) {
            $this->applyEmailEvent($event);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function applyEmailEvent(array $event): void
    {
        $messageId = $event['sg_message_id'] ?? $event['MessageID'] ?? $event['message-id']
            ?? $event['data']['email_id'] ?? null;

        if (! $messageId) {
            return;
        }

        // SendGrid appends a suffix to the id it gave at send time.
        $log = NotificationLog::where('provider_message_id', $messageId)
            ->orWhere('provider_message_id', explode('.', (string) $messageId)[0])
            ->first();

        if (! $log) {
            return;
        }

        $type = strtolower((string) ($event['event'] ?? $event['RecordType'] ?? $event['type'] ?? ''));

        match (true) {
            str_contains($type, 'delivered') => $this->advance($log, 'delivered', ['delivered_at' => now()]),
            str_contains($type, 'open') => $this->advance($log, 'read', ['read_at' => now()]),
            str_contains($type, 'bounce') => $log->update([
                'status' => 'bounced',
                'error' => $event['reason'] ?? $event['Description'] ?? 'The address bounced.',
            ]),
            str_contains($type, 'spam'), str_contains($type, 'complaint') => $log->update([
                'status' => 'failed',
                'error' => 'Marked as spam by the recipient.',
            ]),
            str_contains($type, 'dropped'), str_contains($type, 'failed') => $log->update([
                'status' => 'failed',
                'error' => $event['reason'] ?? 'The provider dropped the message.',
            ]),
            default => null,
        };
    }

    /**
     * Moves a log forward but never backward.
     *
     * Provider events arrive out of order often enough that a late "sent"
     * would otherwise undo an already recorded "read".
     *
     * @param  array<string, mixed>  $attributes
     */
    private function advance(NotificationLog $log, string $status, array $attributes = []): void
    {
        $order = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

        if (($order[$status] ?? 0) <= ($order[$log->status] ?? 0)) {
            // Still worth recording the timestamp, just not the status.
            $log->update($attributes);

            return;
        }

        $log->update($attributes + ['status' => $status]);
    }
}
