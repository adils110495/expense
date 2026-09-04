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
        'company_id' => request('company_id'),
        'project_id' => request('project_id'),
        'person_id' => request('person_id'),
        'category_id' => request('category_id'),
        'payment_method' => request('payment_method'),
        'range' => $range->preset,
        'from' => $range->from,
        'to' => $range->to,
    ], fn ($v) => filled($v));
@endphp

@section('title', 'Transactions')
@section('heading', 'Transactions')
@section('breadcrumbs')
    <span>Admin</span><span>Transactions</span>
@endsection

@section('content')
    <div class="stack">

        {{-- Totals for the current filter set --}}
        <div class="grid grid--stats">
            <x-stat-card label="Total Credit" :value="Money::format($summary['credit_total'])"
                         :meta="$summary['credit_count'].' credit(s)'" variant="credit"/>
            <x-stat-card label="Total Expense" :value="Money::format($summary['expense_total'])"
                         :meta="$summary['expense_count'].' expense(s)'" variant="expense"/>
            <x-stat-card label="Balance" :value="Money::format($summary['balance'])"
                         meta="Credits less Expenses"
                         :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>
            <x-stat-card label="Matching Records" :value="number_format($summary['total_count'])"
                         :meta="$range->label()"/>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Search &amp; filter</h2>
                <div class="btn-row">
                    <a href="{{ route('admin.transactions.export', array_merge(['format' => 'csv'], $carry)) }}"
                       class="btn btn--sm">Export CSV</a>
                    <a href="{{ route('admin.transactions.export', array_merge(['format' => 'excel'], $carry)) }}"
                       class="btn btn--sm">Export Excel</a>
                    <button type="button" class="btn btn--sm" onclick="window.print()">Print / PDF</button>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.transactions.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Title, company, project, person, category">
                    </div>

                    {{-- Company -> Project -> Person, each narrowing the next. --}}
                    @include('admin.partials.hierarchy-filters', [
                        'group' => 'tx-filter',
                        'prefix' => 'tx',
                    ])

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="type">Type</label>
                        <select id="type" name="type" class="select">
                            <option value="">All</option>
                            <option value="expense" @selected(request('type') === 'expense')>Expense</option>
                            <option value="credit" @selected(request('type') === 'credit')>Credit</option>
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
                        <label for="range">Period</label>
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
                        <button type="submit" class="btn btn--primary">Apply</button>
                        <a href="{{ route('admin.transactions.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>All transactions <span class="muted small">({{ $records->total() }})</span></h2>
                <div class="btn-row">
                    {{-- One form for both: the type is picked on it. --}}
                    <a href="{{ route('admin.transactions.create') }}" class="btn btn--sm btn--primary">+ Transaction</a>
                    {{-- Bulk assign works on transactions with no company yet,
                         which only an administrator can see. --}}
                    @admin
                        <a href="{{ route('admin.transactions.assign') }}" class="btn btn--sm">Bulk assign</a>
                    @endadmin
                </div>
            </div>

            @if ($records->isEmpty())
                <x-empty-state
                    title="No transactions found"
                    message="Nothing matches the current filters. Try a wider period, or record a new entry."
                    :action="route('admin.transactions.create')"
                    action-label="+ Add Transaction"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                @include('admin.partials.sort-th', [
                                    'column' => 'transaction_date', 'text' => 'Date',
                                    'url' => route('admin.transactions.index'), 'carry' => $carry,
                                ])
                                <th>Type</th>
                                @include('admin.partials.sort-th', [
                                    'column' => 'title', 'text' => 'Description',
                                    'url' => route('admin.transactions.index'), 'carry' => $carry,
                                ])
                                <th>Company / Project / Person</th>
                                <th>Category</th>
                                @include('admin.partials.sort-th', [
                                    'column' => 'amount', 'text' => 'Amount', 'num' => true,
                                    'url' => route('admin.transactions.index'), 'carry' => $carry,
                                ])
                                <th>Payment Method</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($records as $row)
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
                                    <td>
                                        @include('admin.partials.hier-path', ['row' => $row])
                                    </td>
                                    <td>{{ $row->category?->name ?? '--' }}</td>
                                    <td class="num amount--{{ $row->type }}">
                                        {{ $row->type === 'expense' ? '-' : '+' }}{{ Money::format($row->amount) }}
                                    </td>
                                    <td class="nowrap">{{ $row->payment_method_label }}</td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.transactions.show', $row) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route('admin.transactions.edit', $row) }}" class="btn btn--sm">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pagination-wrap">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
