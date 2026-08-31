<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Resolves the named ranges used by the dashboard and report filters into a
 * concrete [from, to] pair of Y-m-d strings.
 */
class DateRange
{
    public const PRESETS = [
        'today' => 'Today',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year' => 'This Year',
        'all' => 'All Time',
        'custom' => 'Custom',
    ];

    public function __construct(
        public readonly string $preset,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {}

    public static function fromRequest(Request $request, string $default = 'this_month'): self
    {
        $preset = $request->string('range')->toString() ?: $default;

        if (! array_key_exists($preset, self::PRESETS)) {
            $preset = $default;
        }

        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'last_3_months' => [$today->subMonths(2)->startOfMonth(), $today->endOfMonth()],
            'last_6_months' => [$today->subMonths(5)->startOfMonth(), $today->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'all' => [null, null],
            'custom' => [
                self::parse($request->input('from')),
                self::parse($request->input('to')),
            ],
        };

        // A custom range entered backwards is a user slip, not an error worth
        // rejecting - swap it so the query still returns what they meant.
        if ($preset === 'custom' && $from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            $preset,
            $from?->toDateString(),
            $to?->toDateString(),
        );
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function label(): string
    {
        if ($this->preset === 'custom' && ($this->from || $this->to)) {
            return trim(($this->from ?? '...').' to '.($this->to ?? '...'));
        }

        return self::PRESETS[$this->preset];
    }

    /** @return array{range: string, from: ?string, to: ?string} */
    public function queryParams(): array
    {
        return ['range' => $this->preset, 'from' => $this->from, 'to' => $this->to];
    }
}
