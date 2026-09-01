@extends('admin.layouts.app')

@php
    use App\Support\DateRange;
    use App\Support\Money;
@endphp

@section('title', 'Hierarchy')
@section('heading', 'Hierarchy')
@section('breadcrumbs')
    <span>Admin</span><span>Hierarchy</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Company &rarr; Project &rarr; Person</h2>
                <div class="btn-row">
                    <a href="{{ route('admin.companies.create') }}" class="btn btn--sm">+ Company</a>
                    <a href="{{ route('admin.projects.create') }}" class="btn btn--sm">+ Project</a>
                    <a href="{{ route('admin.people.create') }}" class="btn btn--sm">+ Person</a>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.hierarchy.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="company_id">Company</label>
                        <select id="company_id" name="company_id" class="select">
                            <option value="">All companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected($companyId === $company->id)>
                                    {{ $company->name }}@unless ($company->status) (inactive)@endunless
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="type">Transaction type</label>
                        <select id="type" name="type" class="select">
                            <option value="">Credits &amp; expenses</option>
                            <option value="credit" @selected($type === 'credit')>Credits only</option>
                            <option value="expense" @selected($type === 'expense')>Expenses only</option>
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
                        <a href="{{ route('admin.hierarchy.index') }}" class="btn btn--secondary">Reset</a>
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
            <x-stat-card label="Companies" :value="number_format(count($tree))"
                         :meta="$range->label()"/>
        </div>

        @if ($unassigned > 0)
            <div class="alert alert--warn">
                {{ $unassigned }} transaction(s) in this period are not attached to a company,
                project and person, so they are missing from the branches below and from
                settlement.
                <a href="{{ route('admin.transactions.assign') }}">Assign them in bulk</a>.
            </div>
        @endif

        <div class="card">
            <div class="card__head">
                <h2>Breakdown <span class="muted small">({{ $range->label() }})</span></h2>
                <div class="btn-row">
                    <button type="button" class="btn btn--sm" data-tree-expand="hierarchy-tree">Expand all</button>
                    <button type="button" class="btn btn--sm" data-tree-collapse="hierarchy-tree">Collapse all</button>
                </div>
            </div>

            @if (empty($tree))
                <x-empty-state
                    title="No companies yet"
                    message="The hierarchy starts with a company. Add one, give it a project, put people on the project, and their credits and expenses roll up here."
                    :action="route('admin.companies.create')"
                    action-label="+ Add Company"/>
            @else
                <div class="card__body">
                    @include('admin.partials.tree', ['tree' => $tree, 'id' => 'hierarchy-tree'])
                </div>
            @endif
        </div>
    </div>
@endsection
