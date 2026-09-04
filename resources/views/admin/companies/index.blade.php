@extends('admin.layouts.app')

@php
    use App\Services\HierarchyReport;
    use App\Support\DateRange;
    use App\Support\Money;
@endphp

@section('title', 'Companies')
@section('heading', 'Companies')
@section('breadcrumbs')
    <span>Admin</span><span>Companies</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Filter companies</h2>
                <div class="btn-row">
                    @admin
                        <a href="{{ route('admin.companies.create') }}" class="btn btn--primary">+ Add Company</a>
                    @endadmin
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.companies.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Company name or description">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="select">
                            <option value="">All statuses</option>
                            <option value="1" @selected(request('status') === '1')>Active</option>
                            <option value="0" @selected(request('status') === '0')>Inactive</option>
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="range">Totals for</label>
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
                        <a href="{{ route('admin.companies.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Companies <span class="muted small">({{ $companies->total() }})</span></h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>

            @if ($companies->isEmpty())
                <x-empty-state
                    title="No companies found"
                    message="A company is the top of the hierarchy - projects, people and their money all hang off it."
                    :action="route('admin.companies.create')"
                    action-label="+ Add Company"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Company</th>
                                <th class="num">Projects</th>
                                <th class="num">People</th>
                                <th class="num">Credits</th>
                                <th class="num">Expenses</th>
                                <th class="num">Balance</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($companies as $company)
                                @php $t = $totals[$company->id] ?? HierarchyReport::blank(); @endphp
                                <tr>
                                    <td>
                                        <div class="title">
                                            <a href="{{ route('admin.companies.show', $company) }}">{{ $company->name }}</a>
                                        </div>
                                        @if ($company->description)
                                            <div class="sub">{{ Str::limit($company->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td class="num">{{ $company->projects_count }}</td>
                                    <td class="num">{{ $peopleCounts[$company->id] ?? 0 }}</td>
                                    <td class="num amount--credit">{{ Money::format($t['credit']) }}</td>
                                    <td class="num amount--expense">{{ Money::format($t['expense']) }}</td>
                                    <td class="num amount--{{ ((float) $t['balance']) < 0 ? 'expense' : 'credit' }}">
                                        {{ Money::format($t['balance']) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $company->status ? 'badge--on' : 'badge--off' }}">
                                            <span class="dot"></span>{{ $company->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.companies.show', $company) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn--sm">Edit</a>

                                            <form method="POST" action="{{ route('admin.companies.toggle', $company) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn--sm"
                                                        data-busy="{{ $company->status ? 'Deactivating...' : 'Activating...' }}">
                                                    {{ $company->status ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}"
                                                  data-confirm="Delete the company &quot;{{ $company->name }}&quot;?">
                                                @csrf
                                                @method('DELETE')
                                                @php $inUse = $company->projects_count > 0 || $company->transactions_count > 0; @endphp
                                                <button type="submit" class="btn btn--sm btn--danger"
                                                        data-busy="Deleting..."
                                                        @disabled($inUse)
                                                        title="{{ $inUse
                                                            ? 'Has '.$company->projects_count.' project(s) and '.$company->transactions_count.' transaction(s) - deactivate instead'
                                                            : 'Delete this company' }}">
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
                    {{ $companies->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
