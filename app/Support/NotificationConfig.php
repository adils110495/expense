<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The one place a channel credential is resolved.
 *
 * Settings saved in the admin panel win; anything not set there falls back to
 * config/notifications.php, which reads the environment. That gives a
 * deployment two honest options - provision from the environment, or type it
 * into the panel - without the services having to know which was used.
 *
 * Nothing here is exposed to a view. The settings screens read Setting::masked()
 * for display and only these methods return a usable credential.
 */
class NotificationConfig
{
    /**
     * @return array<string, string|null>
     */
    public static function whatsapp(): array
    {
        return [
            'access_token' => self::secret('whatsapp_access_token', 'whatsapp.access_token'),
            'phone_number_id' => self::value('whatsapp_phone_number_id', 'whatsapp.phone_number_id'),
            'business_account_id' => self::value('whatsapp_business_account_id', 'whatsapp.business_account_id'),
            'webhook_verify_token' => self::secret('whatsapp_webhook_verify_token', 'whatsapp.webhook_verify_token'),
            'api_base' => rtrim((string) self::value('whatsapp_api_base', 'whatsapp.api_base'), '/'),
            'api_version' => self::value('whatsapp_api_version', 'whatsapp.api_version'),
            'default_country_code' => preg_replace(
                '/\D/', '',
                (string) self::value('whatsapp_default_country_code', 'whatsapp.default_country_code'),
            ),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function email(): array
    {
        return [
            'provider' => self::value('email_provider', 'email.provider'),
            'api_key' => self::secret('email_api_key', 'email.api_key'),
            'from_name' => self::value('email_from_name', 'email.from_name'),
            'from_address' => self::value('email_from_address', 'email.from_address'),
            'reply_to' => self::value('email_reply_to', 'email.reply_to'),
            'domain' => self::value('email_domain', 'email.domain'),
            'endpoint' => self::value('email_endpoint', 'email.endpoint'),
        ];
    }

    /** Whether a channel is switched on AND has what it needs to send. */
    public static function ready(string $channel): bool
    {
        if (! self::enabled($channel)) {
            return false;
        }

        if ($channel === 'whatsapp') {
            $config = self::whatsapp();

            return filled($config['access_token']) && filled($config['phone_number_id']);
        }

        $config = self::email();

        // SMTP is configured in Laravel's own mail config, so it needs no key
        // here; every API provider does.
        return filled($config['from_address'])
            && ($config['provider'] === 'smtp' || filled($config['api_key']));
    }

    public static function enabled(string $channel): bool
    {
        return Setting::get($channel === 'whatsapp' ? 'whatsapp_enabled' : 'email_enabled') === '1';
    }

    /**
     * The global switch for a family of events, on top of each partner's own
     * preference. Both have to say yes.
     */
    public static function eventEnabled(string $event): bool
    {
        return Setting::get('notify_'.self::group($event)) === '1';
    }

    /**
     * Which family an event belongs to. Preferences are expressed per family
     * rather than per event, so adding an event does not orphan every saved
     * preference.
     */
    public static function group(string $event): string
    {
        return match (true) {
            str_starts_with($event, 'credit_') => 'credit',
            str_starts_with($event, 'settlement_') => 'settlement',
            $event === 'monthly_summary' => 'summary',
            default => 'expense',
        };
    }

    private static function value(string $settingKey, string $configKey): ?string
    {
        $stored = Setting::get($settingKey);

        return filled($stored) ? $stored : config('notifications.'.$configKey);
    }

    private static function secret(string $settingKey, string $configKey): ?string
    {
        $stored = Setting::secret($settingKey);

        return filled($stored) ? $stored : config('notifications.'.$configKey);
    }
}
