@extends('admin.layouts.app')

@php
    use App\Support\Money;
@endphp

@section('title', $project->name)
@section('heading', $project->name)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.projects.index') }}">Projects</a></span>
    <span>{{ $project->name }}</span>
@endsection

@section('content')
    <div class="stack">
        @include('admin.partials.project-tabs', ['project' => $project, 'active' => 'overview'])

        <div class="card">
            <div class="card__head">
                <h2>
                    <span class="hier-path">
                        <span class="lead">
                            <a href="{{ route('admin.companies.show', $project->company_id) }}">
                                {{ $project->company?->name ?? 'No company' }}
                            </a>
                        </span>
                        <span>{{ $project->name }}</span>
                    </span>
                </h2>
                <div class="btn-row">
                    <a href="{{ route('admin.expenses.create', ['company_id' => $project->company_id, 'project_id' => $project->id]) }}"
                       class="btn btn--primary">+ Add Expense</a>
                    <a href="{{ route('admin.credits.create', ['company_id' => $project->company_id, 'project_id' => $project->id]) }}"
                       class="btn">+ Add Credit</a>
                    <a href="{{ route('admin.projects.settlement', $project) }}" class="btn">Settlement</a>
                    <a href="{{ route('admin.projects.edit', $project) }}" class="btn">Edit</a>
                </div>
            </div>

            <div class="card__body">
                @include('admin.partials.range-filter', [
                    'action' => route('admin.projects.show', $project),
                    'id' => 'project',
                ])

                <dl class="dl mt">
                    <div class="dl__row">
                        <dt>Company</dt>
                        <dd>
                            <a href="{{ route('admin.companies.show', $project->company_id) }}">
                                {{ $project->company?->name ?? '--' }}
                            </a>
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Status</dt>
                        <dd>
                            <span class="badge {{ $project->status ? 'badge--on' : 'badge--off' }}">
                                <span class="dot"></span>{{ $project->status ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Dates</dt>
                        <dd>
                            {{ optional($project->start_date)->format('d M Y') ?? '--' }}
                            &rarr;
                            {{ optional($project->end_date)->format('d M Y') ?? 'ongoing' }}
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Description</dt>
                        <dd>{{ $project->description ?: '--' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid grid--stats">
            <x-stat-card label="Total People" :value="number_format(count($people))"
                         meta="Assigned to this project"/>
            <x-stat-card label="Total Credits" :value="Money::format($summary['credit_total'])"
                         :meta="$summary['credit_count'].' credit(s)'" variant="credit"/>
            <x-stat-card label="Total Expenses" :value="Money::format($summary['expense_total'])"
                         :meta="$summary['expense_count'].' expense(s)'" variant="expense"/>
            <x-stat-card label="Project Balance" :value="Money::format($summary['balance'])"
                         meta="Credits less Expenses"
                         :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>
        </div>

        {{-- Person by person: the table the spec sketches, with a live balance
             per person on this project only. --}}
        <div class="card" id="partners">
            <div class="card__head">
                <h2>People on this project <span class="muted small">({{ count($people) }})</span></h2>
                <div class="btn-row">
                    @if ($assignable->isNotEmpty())
                        <button type="button" class="btn btn--primary btn--sm" data-modal-open="assign-modal">
                            + Assign People
                        </button>
                    @endif
                    <a href="{{ route('admin.people.create', ['project_id' => $project->id]) }}" class="btn btn--sm">
                        + New Person
                    </a>
                </div>
            </div>

            @if (empty($people))
                <x-empty-state
                    title="Nobody is assigned yet"
                    message="Assign people to this project so their credits and expenses can be recorded against it."
                    :action="route('admin.people.create', ['project_id' => $project->id])"
                    action-label="+ Add Person"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Person</th>
                                <th class="num">Credit</th>
                                <th class="num">Expense</th>
                                <th class="num">Balance</th>
                                <th class="num">Transactions</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($people as $person)
                                <tr>
                                    <td>
                                        <div class="title">
                                            <a href="{{ route('admin.people.show', $person['id']) }}">{{ $person['name'] }}</a>
                                        </div>
                                        <div class="sub">
                                            {{ $person['designation'] ?: 'No designation' }}
                                            @unless ($person['status']) &middot; inactive @endunless
                                        </div>
                                    </td>
                                    <td class="num amount--credit">{{ Money::format($person['totals']['credit']) }}</td>
                                    <td class="num amount--expense">{{ Money::format($person['totals']['expense']) }}</td>
                                    <td class="num amount--{{ ((float) $person['totals']['balance']) < 0 ? 'expense' : 'credit' }}">
                                        {{ Money::format($person['totals']['balance']) }}
                                    </td>
                                    <td class="num">{{ $person['totals']['count'] }}</td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.expenses.create', [
                                                    'company_id' => $project->company_id,
                                                    'project_id' => $project->id,
                                                    'person_id' => $person['id'],
                                               ]) }}" class="btn btn--sm">+ Expense</a>
                                            <a href="{{ route('admin.credits.create', [
                                                    'company_id' => $project->company_id,
                                                    'project_id' => $project->id,
                                                    'person_id' => $person['id'],
                                               ]) }}" class="btn btn--sm">+ Credit</a>

                                            <form method="POST"
                                                  action="{{ route('admin.projects.people.detach', [$project, $person['id']]) }}"
                                                  data-confirm="Remove {{ $person['name'] }} from this project?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--sm btn--danger"
                                                        data-busy="Removing..."
                                                        @disabled($person['totals']['count'] > 0)
                                                        title="{{ $person['totals']['count'] > 0
                                                            ? 'Has transactions on this project - move or delete those first'
                                                            : 'Remove from this project' }}">
                                                    Remove
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
            @endif
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Recent transactions</h2>
                <a href="{{ route('admin.transactions.index', array_merge($range->queryParams(), [
                        'company_id' => $project->company_id,
                        'project_id' => $project->id,
                   ])) }}" class="btn btn--sm">View all</a>
            </div>

            @if ($recent->isEmpty())
                <x-empty-state
                    title="No transactions in this period"
                    message="Nothing has been recorded against this project for the selected dates."/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Title</th>
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

    {{-- Assign existing people to this project. --}}
    @if ($assignable->isNotEmpty())
        <div class="modal" id="assign-modal" hidden role="dialog" aria-modal="true" aria-labelledby="assign-modal-title">
            <div class="modal__panel">
                <form method="POST" action="{{ route('admin.projects.people.attach', $project) }}">
                    @csrf

                    <div class="modal__head">
                        <h3 id="assign-modal-title">Assign people to {{ $project->name }}</h3>
                        <button type="button" class="btn btn--ghost btn--sm" data-modal-close aria-label="Close">&times;</button>
                    </div>

                    <div class="modal__body">
                        <div class="row">
                            @foreach ($assignable as $person)
                                <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                                    <label class="check">
                                        <input type="checkbox" name="people[]" value="{{ $person->id }}">
                                        {{ $person->name }}@if ($person->designation)
                                            <span class="muted small">&middot; {{ $person->designation }}</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <x-field-error name="people"/>
                    </div>

                    <div class="modal__foot">
                        <button type="button" class="btn" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn--primary" data-busy="Assigning...">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
