<?php

namespace App\Services\Notifications;

use App\Support\NotificationConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Email delivery, one provider at a time.
 *
 * Every supported provider is the same shape - post a JSON body to an
 * endpoint with a bearer key - so they share one request path and differ only
 * in the payload each expects. Adding another is a case for match(), not a new
 * class hierarchy, and swapping provider changes nothing above this file.
 *
 * SMTP is the exception and goes through Laravel's own mailer, which is what
 * makes local development work with MAIL_MAILER=log and no key at all.
 *
 * Never throws; every outcome is a ChannelResult.
 */
class EmailService
{
    public const PROVIDERS = [
        'smtp' => 'SMTP / Laravel mailer',
        'sendgrid' => 'SendGrid',
        'mailgun' => 'Mailgun',
        'resend' => 'Resend',
        'postmark' => 'Postmark',
        'ses' => 'Amazon SES',
    ];

    /** Providers that go through Laravel's mailer rather than a REST call. */
    private const MAILER_PROVIDERS = ['smtp', 'ses'];

    public function isReady(): bool
    {
        return NotificationConfig::ready('email');
    }

    /**
     * Verifies the key without sending anything, where the provider offers a
     * cheap read to do it with. SMTP has no such call, so it reports what it
     * is configured to do instead of pretending to have checked.
     */
    public function testConnection(): ChannelResult
    {
        $config = NotificationConfig::email();
        $provider = $config['provider'] ?? 'smtp';

        if (blank($config['from_address'])) {
            return ChannelResult::failed('A From address is required before email can be sent.');
        }

        if (in_array($provider, self::MAILER_PROVIDERS, true)) {
            return ChannelResult::sent(
                'Using the "'.config('mail.default').'" mailer from Laravel mail config. '
                .'Send a test email to confirm it actually delivers.'
            );
        }

        if (blank($config['api_key'])) {
            return ChannelResult::failed('An API key is required for '.self::PROVIDERS[$provider].'.');
        }

        $probe = match ($provider) {
            'sendgrid' => ['GET', 'https://api.sendgrid.com/v3/scopes', ['Authorization' => 'Bearer '.$config['api_key']]],
            'resend' => ['GET', 'https://api.resend.com/domains', ['Authorization' => 'Bearer '.$config['api_key']]],
            'postmark' => ['GET', 'https://api.postmarkapp.com/server', ['X-Postmark-Server-Token' => $config['api_key']]],
            'mailgun' => ['GET', $this->mailgunBase($config).'/domains', []],
            default => null,
        };

        if ($probe === null) {
            return ChannelResult::failed('No connection test is available for this provider.');
        }

        [$method, $url, $headers] = $probe;

        try {
            $request = Http::withHeaders($headers)->timeout(config('notifications.timeout'));

            // Mailgun authenticates with basic auth rather than a bearer token.
            if ($provider === 'mailgun') {
                $request = $request->withBasicAuth('api', $config['api_key']);
            }

            $response = $request->send($method, $url);
        } catch (ConnectionException $e) {
            return ChannelResult::retryable('Could not reach '.self::PROVIDERS[$provider].': '.$e->getMessage());
        }

        return $response->successful()
            ? ChannelResult::sent(self::PROVIDERS[$provider].' accepted the API key.')
            : ChannelResult::failed($this->errorFrom($provider, $response));
    }

    /**
     * Sends one email. $html is the rendered template; $text is the plain
     * fallback, which matters for clients that refuse HTML and for spam
     * scoring.
     */
    public function send(string $to, string $subject, string $html, string $text): ChannelResult
    {
        $config = NotificationConfig::email();
        $provider = $config['provider'] ?? 'smtp';

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ChannelResult::failed('"'.$to.'" is not a valid email address.');
        }

        if (blank($config['from_address'])) {
            return ChannelResult::failed('Email is not configured: no From address.');
        }

        if (in_array($provider, self::MAILER_PROVIDERS, true)) {
            return $this->sendViaMailer($config, $to, $subject, $html, $text);
        }

        if (blank($config['api_key'])) {
            return ChannelResult::failed('Email is not configured: no API key.');
        }

        try {
            $response = $this->post($provider, $config, $to, $subject, $html, $text);
        } catch (ConnectionException $e) {
            return ChannelResult::retryable('Could not reach the email provider: '.$e->getMessage());
        }

        if ($response->successful()) {
            return ChannelResult::sent($this->messageIdFrom($provider, $response));
        }

        $error = $this->errorFrom($provider, $response);

        return $response->status() === 429 || $response->serverError()
            ? ChannelResult::retryable($error)
            : ChannelResult::failed($error);
    }

    /**
     * The one REST call, with the body each provider expects.
     */
    private function post(string $provider, array $config, string $to, string $subject, string $html, string $text): Response
    {
        $request = Http::timeout(config('notifications.timeout'))->asJson();
        $from = $config['from_address'];
        $fromName = $config['from_name'] ?: config('app.name');
        $replyTo = $config['reply_to'];

        return match ($provider) {
            'sendgrid' => $request
                ->withToken($config['api_key'])
                ->post('https://api.sendgrid.com/v3/mail/send', array_filter([
                    'personalizations' => [['to' => [['email' => $to]]]],
                    'from' => ['email' => $from, 'name' => $fromName],
                    'reply_to' => $replyTo ? ['email' => $replyTo] : null,
                    'subject' => $subject,
                    'content' => [
                        ['type' => 'text/plain', 'value' => $text],
                        ['type' => 'text/html', 'value' => $html],
                    ],
                ])),

            'resend' => $request
                ->withToken($config['api_key'])
                ->post('https://api.resend.com/emails', array_filter([
                    'from' => $fromName.' <'.$from.'>',
                    'to' => [$to],
                    'reply_to' => $replyTo,
                    'subject' => $subject,
                    'html' => $html,
                    'text' => $text,
                ])),

            'postmark' => $request
                ->withHeaders(['X-Postmark-Server-Token' => $config['api_key']])
                ->post('https://api.postmarkapp.com/email', array_filter([
                    'From' => $fromName.' <'.$from.'>',
                    'To' => $to,
                    'ReplyTo' => $replyTo,
                    'Subject' => $subject,
                    'HtmlBody' => $html,
                    'TextBody' => $text,
                ])),

            // Mailgun takes form fields rather than JSON.
            'mailgun' => Http::timeout(config('notifications.timeout'))
                ->withBasicAuth('api', $config['api_key'])
                ->asForm()
                ->post($this->mailgunBase($config).'/'.$config['domain'].'/messages', array_filter([
                    'from' => $fromName.' <'.$from.'>',
                    'to' => $to,
                    'h:Reply-To' => $replyTo,
                    'subject' => $subject,
                    'html' => $html,
                    'text' => $text,
                ])),

            default => throw new \InvalidArgumentException('Unsupported email provider: '.$provider),
        };
    }

    /**
     * SMTP and SES go through Laravel's mailer, so they honour whatever is in
     * config/mail.php - including the log driver during development.
     */
    private function sendViaMailer(array $config, string $to, string $subject, string $html, string $text): ChannelResult
    {
        try {
            Mail::html($html, function ($message) use ($config, $to, $subject, $text) {
                $message->to($to)
                    ->subject($subject)
                    ->from($config['from_address'], $config['from_name'] ?: config('app.name'))
                    ->text($text);

                if (filled($config['reply_to'])) {
                    $message->replyTo($config['reply_to']);
                }
            });
        } catch (\Throwable $e) {
            // A refused connection is worth retrying; a rejected address is
            // not. Telling them apart reliably is not possible here, so this
            // errs towards retrying and lets the attempt limit stop it.
            return ChannelResult::retryable('Mailer error: '.$e->getMessage());
        }

        return ChannelResult::sent();
    }

    /** Mailgun's EU customers are on a different host. */
    private function mailgunBase(array $config): string
    {
        return rtrim($config['endpoint'] ?: 'https://api.mailgun.net/v3', '/');
    }

    private function messageIdFrom(string $provider, Response $response): ?string
    {
        return match ($provider) {
            'sendgrid' => $response->header('X-Message-Id') ?: null,
            'resend' => $response->json('id'),
            'postmark' => $response->json('MessageID'),
            'mailgun' => trim((string) $response->json('id'), '<>') ?: null,
            default => null,
        };
    }

    /**
     * The provider's reason, reduced to one line. Deliberately reads only the
     * documented error fields rather than dumping the whole body, which can
     * echo back request content.
     */
    private function errorFrom(string $provider, Response $response): string
    {
        $json = $response->json();

        $message = match ($provider) {
            'sendgrid' => $json['errors'][0]['message'] ?? null,
            'resend' => $json['message'] ?? null,
            'postmark' => $json['Message'] ?? null,
            'mailgun' => $json['message'] ?? null,
            default => null,
        };

        return $message
            ? self::PROVIDERS[$provider].': '.$message
            : self::PROVIDERS[$provider].' returned HTTP '.$response->status().'.';
    }
}
