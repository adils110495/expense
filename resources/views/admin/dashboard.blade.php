@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Models\Setting;
    $dateFormat = Setting::get('date_format') ?? 'd M Y';
@endphp

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('breadcrumbs')
    <span>Admin</span><span>Dashboard</span>
@endsection

@section('content')
    <div class="stack">

        {{-- Global period filter: everything below reflects this range. --}}
        <div class="card">
            <div class="card__head">
                <h2>Financial overview</h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>
            <div class="card__body">
                @include('admin.partials.range-filter', [
                    'action' => route('admin.dashboard'),
                    'id' => 'dash',
                ])
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="grid grid--stats">
            <x-stat-card
                label="Total Expense"
                :value="Money::format($summary['expense_total'])"
                :meta="$summary['expense_count'].' expense transaction(s)'"
                variant="expense"/>

            <x-stat-card
                label="Total Credit"
                :value="Money::format($summary['credit_total'])"
                :meta="$summary['credit_count'].' credit transaction(s)'"
                variant="credit"/>

            <x-stat-card
                label="Balance"
                :value="Money::format($summary['balance'])"
                meta="Total Credit less Total Expense"
                :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>

            <x-stat-card
                label="Transactions"
                :value="number_format($summary['total_count'])"
                :meta="$summary['expense_count'].' expenses / '.$summary['credit_count'].' credits'"/>
        </div>

        {{-- Charts --}}
        <div class="grid grid--halves">
            <div class="card">
                <div class="card__head"><h2>Expense vs Credit</h2></div>
                <div class="card__body">
                    <x-chart-split :summary="$summary"/>
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <h2>Monthly overview</h2>
                    <a href="{{ route('admin.reports.index', $range->queryParams()) }}" class="btn btn--sm">Full report</a>
                </div>
                <div class="card__body">
                    <x-chart-monthly :series="$monthly"/>
                </div>
            </div>
        </div>

        {{-- Where the money went --}}
        <div class="grid grid--halves">
            <div class="card">
                <div class="card__head"><h2>Top expense categories</h2></div>
                <div class="card__body">
                    @if (empty($expenseByCategory))
                        <p class="muted small" style="margin:0;">No expenses recorded in this period.</p>
                    @else
                        @php $top = (float) ($expenseByCategory[0]['total'] ?? 0); @endphp
                        <div class="bars">
                            @foreach (array_slice($expenseByCategory, 0, 6) as $row)
                                <div>
                                    <div class="bar__top">
                                        <span>{{ $row['name'] }} <span class="muted small">({{ $row['count'] }})</span></span>
                                        <strong>{{ Money::format($row['total']) }}</strong>
                                    </div>
                                    <div class="bar__track">
                                        <div class="bar__fill"
                                             style="width:{{ $top > 0 ? round(((float) $row['total'] / $top) * 100, 1) : 0 }}%;background:#dc2626"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <h2>Quick actions</h2>
                </div>
                <div class="card__body">
                    <div class="btn-row">
                        <a href="{{ route('admin.expenses.create') }}" class="btn btn--primary">+ Add Expense</a>
                        <a href="{{ route('admin.credits.create') }}" class="btn">+ Add Credit</a>
                        <a href="{{ route('admin.categories.index') }}" class="btn">Categories</a>
                    </div>
                    <p class="hint mt" style="margin-bottom:0;">
                        Every figure on this page is calculated live from the transactions table,
                        so totals update the moment a record is added, edited or deleted.
                    </p>
                </div>
            </div>
        </div>

        {{-- Recent transactions --}}
        <div class="card">
            <div class="card__head">
                <h2>Recent transactions</h2>
                <a href="{{ route('admin.transactions.index', $range->queryParams()) }}" class="btn btn--sm">
                    View All Transactions
                </a>
            </div>

            @if ($recent->isEmpty())
                <x-empty-state
                    title="No transactions found"
                    message="Nothing was recorded in this period. Add an expense or a credit to get started."
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
                                <th>Description</th>
                                <th>Category</th>
                                <th class="num">Amount</th>
                                <th>Payment Method</th>
                                {{-- Neutral header: the list mixes both types,
                                     which label this "Payment By" / "Payment
                                     Received" on their own pages. --}}
                                <th>Payment By</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($recent as $row)
                                <tr>
                                    <td class="nowrap">{{ $row->transaction_date->format($dateFormat) }}</td>
                                    <td>
                                        <span class="badge badge--{{ $row->type }}">
                                            <span class="dot"></span>{{ ucfirst($row->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="title">{{ $row->title }}</div>
                                        @if ($row->description)
                                            <div class="sub">{{ Str::limit($row->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $row->category?->name ?? '--' }}</td>
                                    <td class="num amount--{{ $row->type }}">
                                        {{ $row->type === 'expense' ? '-' : '+' }}{{ Money::format($row->amount) }}
                                    </td>
                                    <td class="nowrap">{{ $row->payment_method_label }}</td>
                                    <td>{{ $row->paymentBy?->name ?? '--' }}</td>
                                    <td><span class="badge badge--on"><span class="dot"></span>Recorded</span></td>
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
