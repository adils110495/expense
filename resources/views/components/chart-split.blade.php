@props(['summary'])

@php
    use App\Support\Money;

    $credit = (float) $summary['credit_total'];
    $expense = (float) $summary['expense_total'];
    $balance = (float) $summary['balance'];
    $total = $credit + $expense;

    // Donut geometry: credits and expenses share the ring proportionally.
    $radius = 68;
    $circumference = 2 * M_PI * $radius;
    $creditLen = $total > 0 ? ($credit / $total) * $circumference : 0;
    $expenseLen = $total > 0 ? ($expense / $total) * $circumference : 0;
@endphp

@if ($total <= 0)
    <p class="muted small" style="padding:26px 0;text-align:center;">
        No credits or expenses recorded in this period.
    </p>
@else
    <div style="display:flex;flex-wrap:wrap;gap:22px;align-items:center;justify-content:center;">
        <svg width="176" height="176" viewBox="0 0 176 176" role="img"
             aria-label="Credits versus expenses">
            <g transform="rotate(-90 88 88)">
                <circle cx="88" cy="88" r="{{ $radius }}" fill="none" stroke="#f1f5f9" stroke-width="20"/>
                <circle cx="88" cy="88" r="{{ $radius }}" fill="none" stroke="#059669" stroke-width="20"
                        stroke-dasharray="{{ round($creditLen, 2) }} {{ round($circumference, 2) }}">
                    <title>Credits: {{ Money::format($summary['credit_total']) }}</title>
                </circle>
                <circle cx="88" cy="88" r="{{ $radius }}" fill="none" stroke="#dc2626" stroke-width="20"
                        stroke-dasharray="{{ round($expenseLen, 2) }} {{ round($circumference, 2) }}"
                        stroke-dashoffset="{{ round(-$creditLen, 2) }}">
                    <title>Expenses: {{ Money::format($summary['expense_total']) }}</title>
                </circle>
            </g>
            <text x="88" y="83" text-anchor="middle" font-size="11" fill="#64748b">Balance</text>
            <text x="88" y="103" text-anchor="middle" font-size="15" font-weight="700"
                  fill="{{ $balance < 0 ? '#dc2626' : '#4f46e5' }}">
                {{ Money::format($summary['balance']) }}
            </text>
        </svg>

        {{-- min-width is capped to the container so a 320px phone cannot be
             pushed sideways by it. --}}
        <div class="bars" style="flex:1;min-width:min(220px, 100%);">
            @foreach ([
                ['Credits', $summary['credit_total'], $credit, '#059669'],
                ['Expenses', $summary['expense_total'], $expense, '#dc2626'],
            ] as [$name, $raw, $value, $color])
                <div>
                    <div class="bar__top">
                        <span>{{ $name }}</span>
                        <strong>{{ Money::format($raw) }}</strong>
                    </div>
                    <div class="bar__track">
                        <div class="bar__fill"
                             style="width:{{ $total > 0 ? round(($value / $total) * 100, 1) : 0 }}%;background:{{ $color }}"></div>
                    </div>
                </div>
            @endforeach

            <div>
                <div class="bar__top">
                    <span>Remaining balance</span>
                    <strong style="color:{{ $balance < 0 ? '#dc2626' : '#4f46e5' }}">
                        {{ Money::format($summary['balance']) }}
                    </strong>
                </div>
                <div class="bar__track">
                    <div class="bar__fill"
                         style="width:{{ $credit > 0 ? round(max(0, min(100, ($balance / $credit) * 100)), 1) : 0 }}%"></div>
                </div>
                @if ($balance < 0)
                    <span class="hint" style="color:#dc2626">Spending exceeds income for this period.</span>
                @endif
            </div>
        </div>
    </div>
@endif
