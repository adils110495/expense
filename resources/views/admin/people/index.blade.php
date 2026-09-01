@extends('admin.layouts.app')

@php
    use App\Services\HierarchyReport;
    use App\Support\DateRange;
    use App\Support\Money;
@endphp

@section('title', 'People')
@section('heading', 'People / Employees')
@section('breadcrumbs')
    <span>Admin</span><span>People</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Filter people</h2>
                <div class="btn-row">
                    <a href="{{ route('admin.people.create') }}" class="btn btn--primary">+ Add Person</a>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.people.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Name, email, phone or designation">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="project_id">Project</label>
                        <select id="project_id" name="project_id" class="select">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                    {{ $project->name }}@if ($project->company) ({{ $project->company->name }})@endif
                                </option>
                            @endforeach
                        </select>
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
                        <a href="{{ route('admin.people.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>People <span class="muted small">({{ $people->total() }})</span></h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>

            @if ($people->isEmpty())
                <x-empty-state
                    title="No people found"
                    message="People are assigned to projects, and every credit and expense is booked against one of them."
                    :action="route('admin.people.create')"
                    action-label="+ Add Person"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Person</th>
                                <th>Contact</th>
                                <th>Projects</th>
                                <th class="num">Credit</th>
                                <th class="num">Expense</th>
                                <th class="num">Balance</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($people as $person)
                                @php $t = $totals[$person->id] ?? HierarchyReport::blank(); @endphp
                                <tr>
                                    <td>
                                        <div class="title">
                                            <a href="{{ route('admin.people.show', $person) }}">{{ $person->name }}</a>
                                        </div>
                                        @if ($person->designation)
                                            <div class="sub">{{ $person->designation }}</div>
                                        @endif
                                    </td>
                                    <td class="sub">
                                        {{ $person->email ?: '--' }}
                                        @if ($person->phone)
                                            <br>{{ $person->phone }}
                                        @endif
                                    </td>
                                    <td class="sub">
                                        @forelse ($person->projects as $project)
                                            <div>
                                                <a href="{{ route('admin.projects.show', $project) }}">{{ $project->name }}</a>
                                                @if ($project->company)
                                                    <span class="muted">&middot; {{ $project->company->name }}</span>
                                                @endif
                                            </div>
                                        @empty
                                            Not assigned
                                        @endforelse
                                    </td>
                                    <td class="num amount--credit">{{ Money::format($t['credit']) }}</td>
                                    <td class="num amount--expense">{{ Money::format($t['expense']) }}</td>
                                    <td class="num amount--{{ ((float) $t['balance']) < 0 ? 'expense' : 'credit' }}">
                                        {{ Money::format($t['balance']) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $person->status ? 'badge--on' : 'badge--off' }}">
                                            <span class="dot"></span>{{ $person->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.people.show', $person) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route('admin.people.edit', $person) }}" class="btn btn--sm">Edit</a>

                                            <form method="POST" action="{{ route('admin.people.toggle', $person) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn--sm"
                                                        data-busy="{{ $person->status ? 'Deactivating...' : 'Activating...' }}">
                                                    {{ $person->status ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.people.destroy', $person) }}"
                                                  data-confirm="Delete {{ $person->name }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--sm btn--danger"
                                                        data-busy="Deleting..."
                                                        @disabled($person->transactions_count > 0)
                                                        title="{{ $person->transactions_count > 0
                                                            ? 'Has '.$person->transactions_count.' transaction(s) - deactivate instead'
                                                            : 'Delete this person' }}">
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
                    {{ $people->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
