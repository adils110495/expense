<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Person;
use App\Models\Setting;
use App\Support\NotificationConfig;
use Illuminate\Support\Facades\Log;

/**
 * The one way anything in this application sends a notification.
 *
 * Two halves. queue() decides whether a message should go at all, renders it,
 * writes a log row and hands it to the queue. deliver() is what the worker
 * calls to actually talk to the provider and record the outcome.
 *
 * The split is what keeps the promise in the spec: a financial operation only
 * ever touches queue(), which writes a row and returns. Nothing it does can
 * fail in a way that reaches the caller - every path is wrapped, and a broken
 * provider is discovered later, on a worker, against a log row that already
 * exists.
 */
class NotificationService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly EmailService $email,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * Queues a notification to one person on every channel they accept.
     *
     * @param  array<string, string|null>  $variables  Template placeholders.
     * @param  array<string, int|null>  $related  project_id, transaction_id, settlement_id.
     * @param  string[]|null  $channels  Limit to specific channels; null means both.
     * @param  bool  $force  A manual send by an admin: ignores preference
     *                       switches, but never sends without an address or
     *                       without the channel being configured.
     * @return NotificationLog[]
     */
    public function queue(
        Person $person,
        string $event,
        array $variables = [],
        array $related = [],
        ?array $channels = null,
        bool $force = false,
    ): array {
        $logs = [];

        foreach ($channels ?? ['whatsapp', 'email'] as $channel) {
            try {
                $log = $this->prepare($person, $event, $channel, $variables, $related, $force);

                if ($log) {
                    $logs[] = $log;
                    SendNotification::dispatch($log->id);
                }
            } catch (\Throwable $e) {
                // Nothing about a notification is worth failing the operation
                // that triggered it. Record it where an operator will see it
                // and carry on to the next channel.
                Log::error('Could not queue notification: '.$e->getMessage(), [
                    'event' => $event,
                    'channel' => $channel,
                    'person_id' => $person->id,
                ]);
            }
        }

        return $logs;
    }

    /**
     * Builds the log row, or returns null when the message should not be sent.
     *
     * Everything that decides "no" lives here, so there is one place to look
     * when a partner says they did not get something.
     */
    private function prepare(
        Person $person,
        string $event,
        string $channel,
        array $variables,
        array $related,
        bool $force,
    ): ?NotificationLog {
        // Not configured, or switched off globally: nothing to attempt.
        if (! NotificationConfig::ready($channel)) {
            return null;
        }

        $group = NotificationConfig::group($event);

        if (! $force) {
            if (! NotificationConfig::eventEnabled($event)) {
                return null;
            }

            if (! $person->acceptsNotification($channel, $group)) {
                return null;
            }
        }

        $to = $person->contactFor($channel);

        // A manual send still cannot invent an address.
        if (blank($to)) {
            return null;
        }

        $template = NotificationTemplate::resolve($event, $channel);

        if (! $template) {
            return null;
        }

        $rendered = $this->renderer->render($template, $this->withDefaults($person, $variables));

        return NotificationLog::create([
            'person_id' => $person->id,
            'recipient_name' => $person->name,
            'recipient' => $to,
            'channel' => $channel,
            'event' => $event,
            'subject' => $rendered['subject'] ?: null,
            'body' => $rendered['body'],
            'project_id' => $related['project_id'] ?? null,
            'transaction_id' => $related['transaction_id'] ?? null,
            'settlement_id' => $related['settlement_id'] ?? null,
            'provider' => $channel === 'whatsapp'
                ? 'whatsapp_cloud'
                : NotificationConfig::email()['provider'],
            'status' => 'pending',
            'created_by' => auth('admin')->id(),
        ]);
    }

    /**
     * Sends a prepared log row. Called from the queued job, and returns
     * whether another attempt is worth making.
     */
    public function deliver(NotificationLog $log): bool
    {
        // Already done, or deliberately stopped.
        if (in_array($log->status, ['sent', 'delivered', 'read', 'cancelled'], true)) {
            return false;
        }

        $log->increment('attempts');

        $result = $log->channel === 'whatsapp'
            ? $this->sendWhatsApp($log)
            : $this->sendEmail($log);

        if ($result->ok) {
            $log->update([
                'status' => 'sent',
                'provider_message_id' => $result->messageId,
                'error' => null,
                'sent_at' => now(),
            ]);

            return false;
        }

        // A retryable failure stays pending so the log reads as "still
        // trying"; a permanent one is closed off immediately.
        $log->update([
            'status' => $result->retryable ? 'pending' : 'failed',
            'error' => $result->error,
        ]);

        return $result->retryable;
    }

    /** Marks a log failed for good, once the queue has run out of attempts. */
    public function giveUp(NotificationLog $log, string $reason): void
    {
        $log->update(['status' => 'failed', 'error' => $reason]);
    }

    private function sendWhatsApp(NotificationLog $log): ChannelResult
    {
        $template = NotificationTemplate::resolve($log->event, 'whatsapp');

        return $this->whatsapp->send(
            $log->recipient,
            $this->renderer->forWhatsApp($log->body),
            // An approved template name, where one is configured, is what
            // makes the message deliverable outside the 24 hour window.
            $template?->whatsapp_template_name,
            $template?->language ?? 'en_US',
        );
    }

    private function sendEmail(NotificationLog $log): ChannelResult
    {
        $subject = $log->subject ?: 'Notification from '.config('app.name');

        return $this->email->send(
            $log->recipient,
            $subject,
            $this->renderer->toHtml($subject, $log->body, $this->companyName()),
            $log->body,
        );
    }

    /**
     * Variables every template can rely on, whatever raised the event.
     *
     * @param  array<string, string|null>  $variables
     * @return array<string, string|null>
     */
    private function withDefaults(Person $person, array $variables): array
    {
        return array_merge([
            'partner_name' => $person->name,
            'company_name' => $this->companyName(),
            'project_name' => '',
        ], $variables);
    }

    private function companyName(): string
    {
        return Setting::get('company_name') ?: config('app.name');
    }
}
