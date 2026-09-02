<?php

namespace App\Services\Notifications;

use App\Support\NotificationConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business Cloud API.
 *
 * Sends either a free-form text message or a pre-approved template. Which one
 * matters: outside the 24 hour window that a customer's own message opens,
 * Meta accepts approved templates alone, so a template name on the record is
 * used in preference to the body text.
 *
 * Never throws. Every failure comes back as a ChannelResult so the caller can
 * log it and carry on - see the note on that class.
 */
class WhatsAppService
{
    public function isReady(): bool
    {
        return NotificationConfig::ready('whatsapp');
    }

    /**
     * Checks the credentials by reading the configured phone number back from
     * the API. A cheap GET is the honest way to test a connection - sending a
     * message would prove more but costs money and bothers someone.
     */
    public function testConnection(): ChannelResult
    {
        $config = NotificationConfig::whatsapp();

        if (blank($config['access_token']) || blank($config['phone_number_id'])) {
            return ChannelResult::failed('Access token and phone number ID are both required.');
        }

        try {
            $response = Http::withToken($config['access_token'])
                ->timeout(config('notifications.timeout'))
                ->get($this->endpoint($config, $config['phone_number_id']), [
                    'fields' => 'display_phone_number,verified_name,quality_rating',
                ]);
        } catch (ConnectionException $e) {
            return ChannelResult::retryable('Could not reach WhatsApp: '.$e->getMessage());
        }

        if ($response->failed()) {
            return ChannelResult::failed($this->errorFrom($response->json()));
        }

        $number = $response->json('display_phone_number') ?? 'unknown number';
        $name = $response->json('verified_name') ?? 'unnamed';

        return ChannelResult::sent($name.' ('.$number.')');
    }

    /**
     * Sends a message and returns the provider's message id, which the webhook
     * later uses to move the log on to delivered or read.
     */
    public function send(string $to, string $body, ?string $templateName = null, string $language = 'en_US'): ChannelResult
    {
        $config = NotificationConfig::whatsapp();

        if (blank($config['access_token']) || blank($config['phone_number_id'])) {
            return ChannelResult::failed('WhatsApp is not configured.');
        }

        $number = $this->normalise($to, $config['default_country_code']);

        if ($number === null) {
            return ChannelResult::failed('"'.$to.'" is not a usable WhatsApp number.');
        }

        try {
            $response = Http::withToken($config['access_token'])
                ->timeout(config('notifications.timeout'))
                ->asJson()
                ->post(
                    $this->endpoint($config, $config['phone_number_id'].'/messages'),
                    $this->payload($number, $body, $templateName, $language),
                );
        } catch (ConnectionException $e) {
            return ChannelResult::retryable('Could not reach WhatsApp: '.$e->getMessage());
        }

        if ($response->successful()) {
            return ChannelResult::sent($response->json('messages.0.id'));
        }

        $error = $this->errorFrom($response->json());

        // 429 and 5xx are the provider's problem and may clear; a 4xx is ours
        // and will fail again identically.
        return $response->status() === 429 || $response->serverError()
            ? ChannelResult::retryable($error)
            : ChannelResult::failed($error);
    }

    /**
     * A template message when one is named, a plain text message otherwise.
     *
     * Template variables are passed positionally as body parameters, which is
     * what Meta expects for a {{1}}, {{2}} template.
     *
     * @return array<string, mixed>
     */
    private function payload(string $number, string $body, ?string $templateName, string $language): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $number,
        ];

        if (blank($templateName)) {
            return $base + [
                'type' => 'text',
                // Link previews off: these are financial notices, not adverts.
                'text' => ['preview_url' => false, 'body' => $body],
            ];
        }

        return $base + [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [['type' => 'text', 'text' => $body]],
                ]],
            ],
        ];
    }

    /**
     * Digits only, with a country code in front.
     *
     * Meta wants a bare international number - no plus, no spaces, no dashes.
     * A number short enough to be local gets the configured country code, which
     * is what makes "9876543210" work without every record being re-entered.
     */
    public function normalise(?string $number, ?string $countryCode = null): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        // Indian style 0-prefixed trunk numbers, and 00 international prefixes.
        $digits = preg_replace('/^0+/', '', $digits);

        if (strlen($digits) <= 10 && filled($countryCode)) {
            $digits = $countryCode.$digits;
        }

        // Shorter than this is not a reachable international number, and
        // longer than 15 is beyond what E.164 allows.
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    private function endpoint(array $config, string $path): string
    {
        return $config['api_base'].'/'.$config['api_version'].'/'.$path;
    }

    /**
     * Meta's error shape, reduced to something worth putting in a log. The
     * access token is never part of a response body, so nothing here can leak
     * a credential.
     */
    private function errorFrom(?array $json): string
    {
        $error = $json['error'] ?? null;

        if (! $error) {
            return 'WhatsApp rejected the request without giving a reason.';
        }

        return trim(implode(' ', array_filter([
            $error['message'] ?? null,
            isset($error['error_user_msg']) ? '('.$error['error_user_msg'].')' : null,
            isset($error['code']) ? '[code '.$error['code'].']' : null,
        ])));
    }
}
