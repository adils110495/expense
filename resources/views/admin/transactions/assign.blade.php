@extends('admin.layouts.app')

@php
    use App\Models\Transaction;
    use App\Support\DateRange;
    use App\Support\Money;
@endphp

@section('title', 'Assign transactions')
@section('heading', 'Assign transactions')
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.transactions.index') }}">Transactions</a></span>
    <span>Assign</span>
@endsection

@section('content')
    <div class="stack">
        <div class="alert {{ $outstanding > 0 ? 'alert--warn' : '' }}">
            @if ($outstanding > 0)
                <strong>{{ $outstanding }} transaction(s)</strong> are not filed under a
                company, project and person. Until they are, they stay out of the project
                and person totals and take no part in that project's settlement.
            @else
                Every transaction is filed under a company, project and person.
                You can still use this screen to move a batch somewhere else.
            @endif
        </div>

        {{-- Which transactions to work on --}}
        <div class="card">
            <div class="card__head">
                <h2>Find transactions</h2>
                <a href="{{ route('admin.transactions.index') }}" class="btn btn--sm">Back to transactions</a>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.transactions.assign') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="scope">Show</label>
                        <select id="scope" name="scope" class="select" data-auto-submit>
                            <option value="incomplete" @selected($incompleteOnly)>Needs assigning</option>
                            <option value="all" @selected(! $incompleteOnly)>All transactions</option>
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Title, description, notes">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="type">Type</label>
                        <select id="type" name="type" class="select">
                            <option value="">All</option>
                            <option value="expense" @selected(request('type') === 'expense')>Expense</option>
                            <option value="credit" @selected(request('type') === 'credit')>Credit</option>
                        </select>
                    </div>

                    {{-- Filtering by a branch is how you pick up everything
                         sitting on the "Unassigned" placeholder and move it. --}}
                    @include('admin.partials.hierarchy-filters', [
                        'group' => 'assign-filter',
                        'prefix' => 'af',
                    ])

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
                        <a href="{{ route('admin.transactions.assign') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if ($records->isEmpty())
            <x-empty-state
                title="Nothing to assign"
                :message="$incompleteOnly
                    ? 'No transactions are missing a company, project or person for these filters.'
                    : 'No transactions match these filters.'"
                :action="route('admin.transactions.index')"
                action-label="Back to transactions"/>
        @else
            {{-- One form wraps both the destination and the tick boxes, so the
                 whole batch posts together. --}}
            <form method="POST" action="{{ route('admin.transactions.assign.update') }}"
                  data-confirm="Assign the selected transactions to this company, project and person?">
                @csrf
                @method('PUT')

                <div class="stack">
                    <div class="card">
                        <div class="card__head">
                            <h2>Assign to</h2>
                        </div>

                        <div class="card__body">
                            <div class="row">
                                {{-- The same dependent dropdowns as the expense
                                     form, and the same server-side rules behind
                                     them: a bulk move cannot create a pairing
                                     the add form would have refused. --}}
                                @include('admin.partials.hierarchy-fields', [
                                    'group' => 'assign-form',
                                    'record' => $record,
                                ])
                            </div>

                            <p class="hint" style="margin:0;">
                                The person list only offers people assigned to the chosen project.
                                If someone is missing, add them to the project first.
                            </p>
                        </div>

                        <div class="card__body" style="border-top:1px solid var(--border);">
                            <div class="btn-row">
                                <button type="submit" class="btn btn--primary" data-busy="Assigning...">
                                    Assign selected
                                </button>
                                <span class="hint" data-bulk-count="assign-rows" aria-live="polite"></span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__head">
                            <h2>
                                Transactions
                                <span class="muted small">({{ $records->total() }})</span>
                            </h2>
                            <label class="check">
                                <input type="checkbox" data-bulk-toggle="assign-rows">
                                Select all on this page
                            </label>
                        </div>

                        <div class="card__body card__body--flush">
                            <div class="table-wrap">
                                <table class="data" id="assign-rows">
                                    <thead>
                                    <tr>
                                        <th class="pick">Pick</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Title</th>
                                        <th>Currently filed under</th>
                                        <th>Category</th>
                                        <th class="num">Amount</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($records as $row)
                                        <tr>
                                            <td class="pick">
                                                <label class="check">
                                                    <input type="checkbox" name="transactions[]"
                                                           value="{{ $row->id }}" data-bulk-item>
                                                    <span class="sr-only">Select {{ $row->title }}</span>
                                                </label>
                                            </td>
                                            <td class="nowrap">{{ $row->transaction_date->format($dateFormat) }}</td>
                                            <td>
                                                <span class="badge badge--{{ $row->type }}">
                                                    <span class="dot"></span>{{ ucfirst($row->type) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="title">{{ $row->title }}</div>
                                                @if ($row->description)
                                                    <div class="sub">{{ Str::limit($row->description, 50) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @include('admin.partials.hier-path', ['row' => $row])
                                            </td>
                                            <td>{{ $row->category?->name ?? '--' }}</td>
                                            <td class="num amount--{{ $row->type }}">
                                                {{ $row->type === 'expense' ? '-' : '+' }}{{ Money::format($row->amount) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @error('transactions')
                            <div class="card__body" style="padding-bottom:0;">
                                <span class="error">{{ $message }}</span>
                            </div>
                        @enderror

                        <div class="pagination-wrap">
                            {{ $records->links() }}
                        </div>
                    </div>
                </div>
            </form>

            <p class="hint">
                Selection covers this page only - assign a page, then move to the next.
            </p>
        @endif
    </div>
@endsection
