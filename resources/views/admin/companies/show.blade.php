@extends('admin.layouts.app')

@php
    use App\Services\HierarchyReport;
    use App\Services\SettlementEngine;
    use App\Support\Money;
@endphp

@section('title', $company->name)
@section('heading', $company->name)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.companies.index') }}">Companies</a></span>
    <span>{{ $company->name }}</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>
                    <span class="badge {{ $company->status ? 'badge--on' : 'badge--off' }}">
                        <span class="dot"></span>{{ $company->status ? 'Active' : 'Inactive' }}
                    </span>
                </h2>
                <div class="btn-row">
                    <a href="{{ route('admin.projects.create', ['company_id' => $company->id]) }}" class="btn btn--primary">+ Add Project</a>
                    <a href="{{ route('admin.expenses.create', ['company_id' => $company->id]) }}" class="btn">+ Add Expense</a>
                    <a href="{{ route('admin.credits.create', ['company_id' => $company->id]) }}" class="btn">+ Add Credit</a>
                    <a href="{{ route('admin.companies.edit', $company) }}" class="btn">Edit</a>
                </div>
            </div>

            <div class="card__body">
                @include('admin.partials.range-filter', [
                    'action' => route('admin.companies.show', $company),
                    'id' => 'company',
                ])

                @if ($company->description)
                    <p class="muted small mt" style="margin-bottom:0;">{{ $company->description }}</p>
                @endif
            </div>
        </div>

        {{-- Company summary: the five figures the spec asks for. --}}
        <div class="grid grid--stats">
            <x-stat-card label="Total Projects" :value="number_format($projects->count())"
                         :meta="$projects->where('status', true)->count().' active'"/>
            <x-stat-card label="Total People" :value="number_format($peopleCount)"
                         meta="Across every project"/>
            <x-stat-card label="Total Credits" :value="Money::format($summary['credit_total'])"
                         :meta="$summary['credit_count'].' credit(s)'" variant="credit"/>
            <x-stat-card label="Total Expenses" :value="Money::format($summary['expense_total'])"
                         :meta="$summary['expense_count'].' expense(s)'" variant="expense"/>
            <x-stat-card label="Current Balance" :value="Money::format($summary['balance'])"
                         meta="Credits less Expenses"
                         :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>
            {{-- A roll-up only. Each project still settles on its own numbers;
                 one project's balance never nets off against another's. --}}
            <x-stat-card label="Pending Settlement"
                         :value="Money::format(SettlementEngine::rupees($settlementTotal))"
                         meta="Across every project, this period"
                         :variant="$settlementTotal > 0 ? 'negative' : 'credit'"/>
        </div>

        {{-- Projects in this company --}}
        <div class="card">
            <div class="card__head">
                <h2>Projects <span class="muted small">({{ $projects->count() }})</span></h2>
                <a href="{{ route('admin.projects.create', ['company_id' => $company->id]) }}" class="btn btn--sm">+ Add Project</a>
            </div>

            @if ($projects->isEmpty())
                <x-empty-state
                    title="No projects yet"
                    :message="$company->name.' has no projects. Add one, then assign people to it so their credits and expenses can be recorded.'"
                    :action="route('admin.projects.create', ['company_id' => $company->id])"
                    action-label="+ Add Project"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Project</th>
                                <th class="num">People</th>
                                <th class="num">Credits</th>
                                <th class="num">Expenses</th>
                                <th class="num">Balance</th>
                                <th class="num">To Settle</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($projects as $project)
                                @php
                                    $t = $projectTotals[$project->id] ?? HierarchyReport::blank();
                                    $plan = $settlementPlans[$project->id] ?? null;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="title">
                                            <a href="{{ route('admin.projects.show', $project) }}">{{ $project->name }}</a>
                                        </div>
                                        @if ($project->start_date || $project->end_date)
                                            <div class="sub">
                                                {{ optional($project->start_date)->format('d M Y') ?? '--' }}
                                                &rarr;
                                                {{ optional($project->end_date)->format('d M Y') ?? 'ongoing' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="num">{{ $project->people_count }}</td>
                                    <td class="num amount--credit">{{ Money::format($t['credit']) }}</td>
                                    <td class="num amount--expense">{{ Money::format($t['expense']) }}</td>
                                    <td class="num amount--{{ ((float) $t['balance']) < 0 ? 'expense' : 'credit' }}">
                                        {{ Money::format($t['balance']) }}
                                    </td>
                                    <td class="num">
                                        @if ($plan && $plan['to_settle'] > 0)
                                            <a href="{{ route('admin.projects.settlement', $project) }}">
                                                {{ Money::format(SettlementEngine::rupees($plan['to_settle'])) }}
                                            </a>
                                        @else
                                            <span class="muted">--</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $project->status ? 'badge--on' : 'badge--off' }}">
                                            <span class="dot"></span>{{ $project->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.projects.show', $project) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route('admin.projects.settlement', $project) }}" class="btn btn--sm">Settle</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- The branch below this company --}}
        <div class="card">
            <div class="card__head">
                <h2>Breakdown</h2>
                <div class="btn-row">
                    <button type="button" class="btn btn--sm" data-tree-expand="company-tree">Expand all</button>
                    <button type="button" class="btn btn--sm" data-tree-collapse="company-tree">Collapse all</button>
                </div>
            </div>
            <div class="card__body">
                @include('admin.partials.tree', ['tree' => $tree, 'id' => 'company-tree'])
            </div>
        </div>

        {{-- Latest activity anywhere in the company --}}
        <div class="card">
            <div class="card__head">
                <h2>Recent transactions</h2>
                <a href="{{ route('admin.transactions.index', array_merge($range->queryParams(), ['company_id' => $company->id])) }}"
                   class="btn btn--sm">View all</a>
            </div>

            @if ($recent->isEmpty())
                <x-empty-state
                    title="No transactions in this period"
                    message="Nothing has been recorded against this company for the selected dates."
                    :action="route('admin.expenses.create', ['company_id' => $company->id])"
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
                                <th>Project</th>
                                <th>Person</th>
                                <th>Category</th>
                                <th class="num">Amount</th>
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
                                    <td class="title">
                                        <a href="{{ route('admin.'.$row->type.'s.show', $row) }}">{{ $row->title }}</a>
                                    </td>
                                    <td>{{ $row->project?->name ?? '--' }}</td>
                                    <td>{{ $row->person?->name ?? '--' }}</td>
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
            @endif
        </div>
    </div>
@endsection
