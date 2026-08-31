@props(['series'])

@php
    use App\Support\Money;

    $labels = $series['labels'] ?? [];
    $credits = $series['credits'] ?? [];
    $expenses = $series['expenses'] ?? [];
    $balances = $series['balances'] ?? [];

    // Scale to the largest magnitude in view so bars always use the full height.
    $peak = 0.0;
    foreach ([$credits, $expenses, $balances] as $set) {
        foreach ($set as $value) {
            $peak = max($peak, abs((float) $value));
        }
    }
    $peak = $peak > 0 ? $peak : 1.0;

    $count = max(count($labels), 1);
    $chartW = 760;
    $chartH = 260;
    $padLeft = 8;
    $padBottom = 34;
    $plotH = $chartH - $padBottom - 10;
    $groupW = ($chartW - $padLeft * 2) / $count;
    $barW = min(18, max(6, ($groupW - 14) / 3));
@endphp

@if (empty($labels))
    <p class="muted small" style="padding:26px 0;text-align:center;">
        No transactions in this period, so there is nothing to chart yet.
    </p>
@else
    {{-- Height comes from .chart-box so the responsive rules can shrink it;
         an inline height would outrank every media query. --}}
    <div class="chart-box">
        <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="100%" height="100%"
             preserveAspectRatio="xMidYMid meet" role="img"
             aria-label="Monthly credits, expenses and balance">

            {{-- Horizontal guide lines --}}
            @for ($i = 0; $i <= 4; $i++)
                @php $y = 10 + $plotH - ($plotH / 4) * $i; @endphp
                <line x1="{{ $padLeft }}" y1="{{ $y }}" x2="{{ $chartW - $padLeft }}" y2="{{ $y }}"
                      stroke="#e2e8f0" stroke-width="1"/>
            @endfor

            @foreach ($labels as $index => $label)
                @php
                    $groupX = $padLeft + $groupW * $index;
                    $center = $groupX + $groupW / 2;
                    $bars = [
                        ['value' => (float) ($credits[$index] ?? 0), 'color' => '#059669', 'name' => 'Credit'],
                        ['value' => (float) ($expenses[$index] ?? 0), 'color' => '#dc2626', 'name' => 'Expense'],
                        ['value' => (float) ($balances[$index] ?? 0), 'color' => '#4f46e5', 'name' => 'Balance'],
                    ];
                @endphp

                @foreach ($bars as $slot => $bar)
                    @php
                        // Negative balances are drawn at minimum height in the
                        // bar colour so the month still reads as "in deficit".
                        $magnitude = abs($bar['value']);
                        $h = max($magnitude > 0 ? 2 : 0, ($magnitude / $peak) * $plotH);
                        $x = $center - ($barW * 1.5 + 4) + $slot * ($barW + 4);
                        $y = 10 + $plotH - $h;
                    @endphp
                    <rect x="{{ round($x, 2) }}" y="{{ round($y, 2) }}"
                          width="{{ round($barW, 2) }}" height="{{ round($h, 2) }}"
                          rx="3" fill="{{ $bar['color'] }}"
                          opacity="{{ $bar['value'] < 0 ? '.45' : '1' }}">
                        <title>{{ $label }} &middot; {{ $bar['name'] }}: {{ Money::format($bar['value']) }}</title>
                    </rect>
                @endforeach

                <text x="{{ round($center, 2) }}" y="{{ $chartH - 12 }}" text-anchor="middle"
                      font-size="11" fill="#64748b">{{ $label }}</text>
            @endforeach
        </svg>
    </div>

    <div class="legend">
        <span><i style="background:#059669"></i>Credits</span>
        <span><i style="background:#dc2626"></i>Expenses</span>
        <span><i style="background:#4f46e5"></i>Balance</span>
        <span class="muted">Peak: {{ Money::format($peak) }}</span>
    </div>
@endif
