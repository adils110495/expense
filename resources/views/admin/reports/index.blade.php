@extends('admin.layouts.app')

@php
    use App\Models\Setting;
    use App\Models\Transaction;
    use App\Support\DateRange;
    use App\Support\Money;

    $dateFormat = Setting::get('date_format') ?? 'd M Y';

    $carry = array_filter([
        'q' => request('q'),
        'type' => request('type'),
        'category_id' => request('category_id'),
        'payment_method' => request('payment_method'),
        'range' => $range->preset,
        'from' => $range->from,
        'to' => $range->to,
    ], fn ($v) => filled($v));
@endphp

@section('title', 'Reports')
@section('heading', 'Reports')
@section('breadcrumbs')
    <span>Admin</span><span>Reports</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Report criteria</h2>
                <div class="btn-row">
                    <a href="{{ route('admin.transactions.export', array_merge(['format' => 'csv'], $carry)) }}"
                       class="btn btn--sm">Export CSV</a>
                    <a href="{{ route('admin.transactions.export', array_merge(['format' => 'excel'], $carry)) }}"
                       class="btn btn--sm">Export Excel</a>
                    <button type="button" class="btn btn--sm" onclick="window.print()">Print / PDF</button>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="type">Transaction type</label>
                        <select id="type" name="type" class="select">
                            <option value="">Expense &amp; Credit</option>
                            <option value="expense" @selected(request('type') === 'expense')>Expense only</option>
                            <option value="credit" @selected(request('type') === 'credit')>Credit only</option>
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="select">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                                    {{ $category->name }} ({{ $category->type }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="payment_method">Payment method</label>
                        <select id="payment_method" name="payment_method" class="select">
                            <option value="">All methods</option>
                            @foreach (Transaction::PAYMENT_METHODS as $value => $text)
                                <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="range">Date range</label>
                        <select id="range" name="range" class="select" data-range-select>
                            @foreach (DateRange::PRESETS as $value => $text)
                                <option value="{{ $value }}" @selected($range->preset === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12" data-range-custom>
                        <label for="from">From</label>
                        <input id="from" type="date" name="from" class="input" value="{{ $range->from }}">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12" data-range-custom>
                        <label for="to">To</label>
                        <input id="to" type="date" name="to" class="input" value="{{ $range->to }}">
                    </div>

                    <div class="field field--actions col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <button type="submit" class="btn btn--primary">Generate</button>
                        <a href="{{ route('admin.reports.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid--stats">
            <x-stat-card label="Total Credit" :value="Money::format($summary['credit_total'])"
                         :meta="$summary['credit_count'].' credit(s)'" variant="credit"/>
            <x-stat-card label="Total Expense" :value="Money::format($summary['expense_total'])"
                         :meta="$summary['expense_count'].' expense(s)'" variant="expense"/>
            <x-stat-card label="Balance" :value="Money::format($summary['balance'])"
                         meta="Credits less Expenses"
                         :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>
            <x-stat-card label="Transactions" :value="number_format($summary['total_count'])"
                         :meta="$range->label()"/>
        </div>

        <div class="card">
            <div class="card__head"><h2>Monthly overview</h2></div>
            <div class="card__body">
                <x-chart-monthly :series="$monthly"/>
            </div>
        </div>

        <div class="grid grid--halves">
            {{-- Named $categoryRows, not $rows: $rows is the transaction
                 collection used further down and must not be shadowed. --}}
            @foreach ([
                ['Expense by category', $expenseByCategory, '#dc2626'],
                ['Credit by category', $creditByCategory, '#059669'],
            ] as [$groupTitle, $categoryRows, $color])
                <div class="card">
                    <div class="card__head"><h2>{{ $groupTitle }}</h2></div>
                    <div class="card__body">
                        @if (empty($categoryRows))
                            <p class="muted small" style="margin:0;">Nothing recorded in this period.</p>
                        @else
                            @php $peak = (float) ($categoryRows[0]['total'] ?? 0); @endphp
                            <div class="bars">
                                @foreach ($categoryRows as $categoryRow)
                                    <div>
                                        <div class="bar__top">
                                            <span>{{ $categoryRow['name'] }} <span class="muted small">({{ $categoryRow['count'] }})</span></span>
                                            <strong>{{ Money::format($categoryRow['total']) }}</strong>
                                        </div>
                                        <div class="bar__track">
                                            <div class="bar__fill"
                                                 style="width:{{ $peak > 0 ? round(((float) $categoryRow['total'] / $peak) * 100, 1) : 0 }}%;background:{{ $color }}"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card__head"><h2>By payment method</h2></div>
            @if (empty($byPaymentMethod))
                <x-empty-state title="No data" message="No transactions in this period."/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data data--compact">
                            <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="num">Transactions</th>
                                <th class="num">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($byPaymentMethod as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="num">{{ $row['count'] }}</td>
                                    <td class="num">{{ Money::format($row['total']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Transaction detail</h2>
                <span class="badge badge--muted">
                    {{ $rows->count() >= 100 ? 'Showing latest 100 - export for the full set' : $rows->count().' record(s)' }}
                </span>
            </div>

            @if ($rows->isEmpty())
                <x-empty-state
                    title="No transactions match this report"
                    message="Adjust the criteria above, or record a transaction first."
                    :action="route('admin.expenses.create')"
                    action-label="+ Add Expense"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th class="num">Amount</th>
                                <th>Payment Method</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="nowrap">{{ $row->transaction_date->format($dateFormat) }}</td>
                                    <td>
                                        <span class="badge badge--{{ $row->type }}">
                                            <span class="dot"></span>{{ ucfirst($row->type) }}
                                        </span>
                                    </td>
                                    <td class="title">{{ $row->title }}</td>
                                    <td>{{ $row->category?->name ?? '--' }}</td>
                                    <td class="num amount--{{ $row->type }}">
                                        {{ $row->type === 'expense' ? '-' : '+' }}{{ Money::format($row->amount) }}
                                    </td>
                                    <td class="nowrap">{{ $row->payment_method_label }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
