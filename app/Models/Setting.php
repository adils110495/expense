<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'currency' => 'INR',
        'date_format' => 'd M Y',
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
}
