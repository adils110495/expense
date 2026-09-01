@props(['totals'])

{{-- The three figures every level of the hierarchy carries. Balance is
     Credits - Expenses, computed from the transactions, never stored. --}}
@php
    use App\Support\Money;
    $negative = ((float) $totals['balance']) < 0;
@endphp

<span class="tree__money">
    <span>
        <span class="k">Credit</span>
        <span class="v amount--credit">{{ Money::format($totals['credit']) }}</span>
    </span>
    <span>
        <span class="k">Expense</span>
        <span class="v amount--expense">{{ Money::format($totals['expense']) }}</span>
    </span>
    <span>
        <span class="k">Balance</span>
        <span class="v" style="color:{{ $negative ? 'var(--expense)' : 'var(--brand)' }};">
            {{ Money::format($totals['balance']) }}
        </span>
    </span>
</span>
