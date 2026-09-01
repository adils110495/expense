@extends('admin.layouts.app')

@php
    use App\Services\HierarchyReport;
    use App\Support\DateRange;
    use App\Support\Money;
@endphp

@section('title', 'Projects')
@section('heading', 'Projects')
@section('breadcrumbs')
    <span>Admin</span><span>Projects</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Filter projects</h2>
                <div class="btn-row">
                    <a href="{{ route('admin.projects.create') }}" class="btn btn--primary">+ Add Project</a>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.projects.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Project, description or company">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="company_id">Company</label>
                        <select id="company_id" name="company_id" class="select">
                            <option value="">All companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>
                                    {{ $company->name }}@unless ($company->status) (inactive)@endunless
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
                        <a href="{{ route('admin.projects.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Projects <span class="muted small">({{ $projects->total() }})</span></h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>

            @if ($projects->isEmpty())
                <x-empty-state
                    title="No projects found"
                    message="A project belongs to one company and holds the people whose credits and expenses roll up to it."
                    :action="route('admin.projects.create')"
                    action-label="+ Add Project"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Project</th>
                                <th>Company</th>
                                <th>Dates</th>
                                <th class="num">People</th>
                                <th class="num">Credits</th>
                                <th class="num">Expenses</th>
                                <th class="num">Balance</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($projects as $project)
                                @php $t = $totals[$project->id] ?? HierarchyReport::blank(); @endphp
                                <tr>
                                    <td class="title">
                                        <a href="{{ route('admin.projects.show', $project) }}">{{ $project->name }}</a>
                                    </td>
                                    <td>
                                        @if ($project->company)
                                            <a href="{{ route('admin.companies.show', $project->company) }}">{{ $project->company->name }}</a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td class="sub nowrap">
                                        {{ optional($project->start_date)->format('d M Y') ?? '--' }}
                                        &rarr;
                                        {{ optional($project->end_date)->format('d M Y') ?? 'ongoing' }}
                                    </td>
                                    <td class="num">{{ $project->people_count }}</td>
                                    <td class="num amount--credit">{{ Money::format($t['credit']) }}</td>
                                    <td class="num amount--expense">{{ Money::format($t['expense']) }}</td>
                                    <td class="num amount--{{ ((float) $t['balance']) < 0 ? 'expense' : 'credit' }}">
                                        {{ Money::format($t['balance']) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $project->status ? 'badge--on' : 'badge--off' }}">
                                            <span class="dot"></span>{{ $project->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.projects.show', $project) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn--sm">Edit</a>

                                            <form method="POST" action="{{ route('admin.projects.toggle', $project) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn--sm"
                                                        data-busy="{{ $project->status ? 'Deactivating...' : 'Activating...' }}">
                                                    {{ $project->status ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}"
                                                  data-confirm="Delete the project &quot;{{ $project->name }}&quot;?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--sm btn--danger"
                                                        data-busy="Deleting..."
                                                        @disabled($project->transactions_count > 0)
                                                        title="{{ $project->transactions_count > 0
                                                            ? 'Has '.$project->transactions_count.' transaction(s) - deactivate instead'
                                                            : 'Delete this project' }}">
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
                    {{ $projects->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
