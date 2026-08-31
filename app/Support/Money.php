<?php

namespace App\Support;

use App\Models\Setting;

class Money
{
    public const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AED' => 'AED ',
    ];

    public static function symbol(): string
    {
        $currency = Setting::get('currency') ?? 'INR';

        return self::SYMBOLS[$currency] ?? $currency.' ';
    }

    /**
     * Format an amount using the Indian grouping system (last 3 digits, then
     * pairs) for INR, and standard thousands grouping for everything else.
     */
    public static function format(int|float|string|null $amount, bool $withSymbol = true): string
    {
        $amount = (string) ($amount ?? '0');
        $negative = str_starts_with($amount, '-');
        $absolute = ltrim($amount, '-');

        $parts = explode('.', $absolute, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $decimals = str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');

        $currency = Setting::get('currency') ?? 'INR';
        $grouped = $currency === 'INR' ? self::groupIndian($whole) : number_format((float) $whole);

        return ($negative ? '-' : '')
            .($withSymbol ? self::symbol() : '')
            .$grouped.'.'.$decimals;
    }

    private static function groupIndian(string $whole): string
    {
        if (strlen($whole) <= 3) {
            return $whole;
        }

        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

        return $rest.','.$last3;
    }
}
