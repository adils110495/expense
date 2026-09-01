@extends('admin.layouts.app')

@php
    use App\Models\Transaction;
    use App\Models\Setting;
    use App\Support\DateRange;
    use App\Support\Money;

    $plural = $label.'s';
    $dateFormat = Setting::get('date_format') ?? 'd M Y';

    // Preserved across sort links so filters survive a column click.
    $carry = array_filter([
        'q' => request('q'),
        'company_id' => request('company_id'),
        'project_id' => request('project_id'),
        'person_id' => request('person_id'),
        'category_id' => request('category_id'),
        'payment_method' => request('payment_method'),
        'payment_by_id' => request('payment_by_id'),
        'range' => $range->preset,
        'from' => $range->from,
        'to' => $range->to,
    ], fn ($v) => filled($v));
@endphp

@section('title', $plural)
@section('heading', $plural)
@section('breadcrumbs')
    <span>Admin</span><span>{{ $plural }}</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Filter {{ Str::lower($plural) }}</h2>
                <div class="btn-row">
                    <a href="{{ route($routeName.'.create') }}" class="btn btn--primary">+ Add {{ $label }}</a>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route($routeName.'.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Title, company, project, person, category">
                    </div>

                    {{-- Company -> Project -> Person, each narrowing the next. --}}
                    @include('admin.partials.hierarchy-filters', [
                        'group' => 'money-filter',
                        'prefix' => 'money',
                    ])

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="select">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                                    {{ $category->name }}
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

                    @if ($extras)
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="payment_by_id">{{ $payerLabel }}</label>
                            <select id="payment_by_id" name="payment_by_id" class="select">
                                <option value="">All</option>
                                @foreach ($payers as $payer)
                                    <option value="{{ $payer->id }}"
                                            @selected(request('payment_by_id') == $payer->id)>
                                        {{ $payer->name }}@unless ($payer->status) (inactive)@endunless
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

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
                        <a href="{{ route($routeName.'.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>{{ $plural }} <span class="muted small">({{ $records->total() }})</span></h2>
                <span class="badge badge--{{ $type }}"><span class="dot"></span>{{ $range->label() }}</span>
            </div>

            @if ($records->isEmpty())
                <x-empty-state
                    :title="'No '.Str::lower($plural).' found'"
                    :message="request()->hasAny(['q', 'company_id', 'project_id', 'person_id', 'category_id', 'payment_method', 'payment_by_id']) || $range->preset !== 'all'
                        ? 'No records match the current filters. Try widening the period or clearing the search.'
                        : 'Nothing has been recorded yet.'"
                    :action="route($routeName.'.create')"
                    :action-label="'+ Add '.$label"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                @include('admin.partials.sort-th', [
                                    'column' => 'transaction_date', 'text' => 'Date',
                                    'url' => route($routeName.'.index'), 'carry' => $carry,
                                ])
                                @include('admin.partials.sort-th', [
                                    'column' => 'title', 'text' => $label.' Title',
                                    'url' => route($routeName.'.index'), 'carry' => $carry,
                                ])
                                <th>Company / Project / Person</th>
                                <th>Category</th>
                                @include('admin.partials.sort-th', [
                                    'column' => 'amount', 'text' => 'Amount', 'num' => true,
                                    'url' => route($routeName.'.index'), 'carry' => $carry,
                                ])
                                <th>Payment Method</th>
                                @if ($extras)
                                    <th>{{ $payerLabel }}</th>
                                @endif
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($records as $row)
                                <tr>
                                    <td class="nowrap">{{ $row->transaction_date->format($dateFormat) }}</td>
                                    <td class="title">
                                        {{ $row->title }}
                                        @if ($row->description)
                                            <div class="sub">{{ Str::limit($row->description, 40) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @include('admin.partials.hier-path', ['row' => $row])
                                    </td>
                                    <td>{{ $row->category?->name ?? '--' }}</td>
                                    <td class="num amount--{{ $row->type }}">{{ Money::format($row->amount) }}</td>
                                    <td class="nowrap">{{ $row->payment_method_label }}</td>
                                    @if ($extras)
                                        <td>{{ $row->paymentBy?->name ?? '--' }}</td>
                                    @endif
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route($routeName.'.show', $row) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route($routeName.'.edit', $row) }}" class="btn btn--sm">Edit</a>
                                            <form method="POST" action="{{ route($routeName.'.destroy', $row) }}"
                                                  data-confirm="Are you sure you want to delete this {{ Str::lower($label) }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--sm btn--danger" data-busy="Deleting...">
                                                    Delete
                                                </button>
                                            </form>
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
