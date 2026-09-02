<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'currency' => 'INR',
        'date_format' => 'd M Y',

        // Both channels start switched off: nothing should be able to message
        // a partner about money until someone has deliberately turned it on
        // and tested it.
        'whatsapp_enabled' => '0',
        'whatsapp_api_base' => 'https://graph.facebook.com',
        'whatsapp_api_version' => 'v21.0',
        'whatsapp_default_country_code' => '91',

        'email_enabled' => '0',
        'email_provider' => 'smtp',

        // Global per-event switches, on top of each partner's own preference.
        'notify_expense' => '1',
        'notify_credit' => '1',
        'notify_settlement' => '1',
        'notify_summary' => '0',
    ];

    public static function all_settings(): array
    {
        return Cache::rememberForever('app_settings', function () {
            return array_merge(self::DEFAULTS, self::query()->pluck('value', 'key')->all());
        });
    }

    public static function get(string $key): ?string
    {
        return self::all_settings()[$key] ?? null;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings');
    }

    /* ===================== Credentials ===================== */

    /**
     * Keys holding a credential. These are encrypted at rest and are never
     * handed to a view in full - see masked().
     */
    public const SECRET_KEYS = [
        'whatsapp_access_token',
        'whatsapp_webhook_verify_token',
        'email_api_key',
    ];

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    /**
     * Stores a credential encrypted with the app key.
     *
     * A blank value means "leave what is there" rather than "erase it", so
     * saving the settings form without retyping a token does not wipe it -
     * the form only ever shows a mask, so a blank field is the normal case.
     */
    public static function putSecret(string $key, ?string $value): void
    {
        if (blank($value)) {
            return;
        }

        self::put($key, Crypt::encryptString($value));
    }

    /** Clears a credential outright. */
    public static function forgetSecret(string $key): void
    {
        self::put($key, null);
    }

    /**
     * The decrypted credential, for use by the sending services only.
     *
     * Returns null rather than throwing on a value that will not decrypt -
     * that happens when APP_KEY has been rotated, and a notification failing
     * to send is far better than every settings page throwing a 500.
     */
    public static function secret(string $key): ?string
    {
        $stored = self::get($key);

        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * What the admin UI shows in place of a credential: enough to recognise
     * which key is in use, never enough to use it.
     */
    public static function masked(string $key): ?string
    {
        $value = self::secret($key);

        if (blank($value)) {
            return null;
        }

        return str_repeat('•', 8).' '.substr($value, -4);
    }
}
