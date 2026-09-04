@extends('admin.layouts.app')

@php
    use App\Services\HierarchyReport;
    use App\Services\SettlementEngine;
    use App\Support\Money;
@endphp

@section('title', $person->name)
@section('heading', $person->name)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.people.index') }}">People</a></span>
    <span>{{ $person->name }}</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>
                    <span class="badge {{ $person->status ? 'badge--on' : 'badge--off' }}">
                        <span class="dot"></span>{{ $person->status ? 'Active' : 'Inactive' }}
                    </span>
                    @if ($person->designation)
                        <span class="muted small">{{ $person->designation }}</span>
                    @endif
                </h2>
                <div class="btn-row">
                    <a href="{{ route('admin.transactions.create', ['type' => 'expense', 'person_id' => $person->id]) }}" class="btn btn--primary">+ Add Expense</a>
                    <a href="{{ route('admin.transactions.create', ['type' => 'credit', 'person_id' => $person->id]) }}" class="btn">+ Add Credit</a>
                    <a href="{{ route('admin.people.edit', $person) }}" class="btn">Edit</a>
                </div>
            </div>

            <div class="card__body">
                @include('admin.partials.range-filter', [
                    'action' => route('admin.people.show', $person),
                    'id' => 'person',
                ])

                <dl class="dl mt">
                    <div class="dl__row">
                        <dt>Email</dt>
                        <dd>{{ $person->email ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Phone</dt>
                        <dd>{{ $person->phone ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Notes</dt>
                        <dd>{{ $person->notes ?: '--' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Summary: Total Credit, Total Expense, Balance. --}}
        <div class="grid grid--stats">
            <x-stat-card label="Total Credit" :value="Money::format($summary['credit_total'])"
                         :meta="$summary['credit_count'].' credit(s)'" variant="credit"/>
            <x-stat-card label="Total Expense" :value="Money::format($summary['expense_total'])"
                         :meta="$summary['expense_count'].' expense(s)'" variant="expense"/>
            <x-stat-card label="Balance" :value="Money::format($summary['balance'])"
                         meta="Total Credit less Total Expense"
                         :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>
            <x-stat-card label="Projects" :value="number_format($person->projects->count())"
                         :meta="$range->label()"/>
        </div>

        {{-- What this partner owes or is owed after equal distribution, on the
             same period as the rest of the page. --}}
        @if (! empty($settlement))
            <div class="card">
                <div class="card__head">
                    <h2>Settlement position</h2>
                    <div class="btn-row">
                        <span class="badge badge--muted">{{ $range->label() }}</span>
                    </div>
                </div>

                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Project</th>
                                <th class="num">Spent</th>
                                <th class="num">Expense Position</th>
                                <th class="num">Received</th>
                                <th class="num">Profit Position</th>
                                <th class="num">Net Position</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($settlement as $row)
                                <tr>
                                    <td class="title">
                                        <a href="{{ route('admin.projects.settlement', $row['project']) }}">{{ $row['project']->name }}</a>
                                        @if ($row['project']->company)
                                            <div class="sub">{{ $row['project']->company->name }}</div>
                                        @endif
                                    </td>
                                    <td class="num amount--expense">{{ Money::format(SettlementEngine::rupees($row['spent'])) }}</td>
                                    <td class="num amount--{{ $row['expense_position'] < 0 ? 'expense' : 'credit' }}">
                                        {{ $row['expense_position'] > 0 ? '+' : '' }}{{ Money::format(SettlementEngine::rupees($row['expense_position'])) }}
                                    </td>
                                    <td class="num amount--credit">{{ Money::format(SettlementEngine::rupees($row['received'])) }}</td>
                                    <td class="num amount--{{ $row['income_position'] < 0 ? 'expense' : 'credit' }}">
                                        {{ $row['income_position'] > 0 ? '+' : '' }}{{ Money::format(SettlementEngine::rupees($row['income_position'])) }}
                                    </td>
                                    <td class="num amount--{{ $row['position'] < 0 ? 'expense' : 'credit' }}">
                                        <strong>{{ $row['position'] > 0 ? '+' : '' }}{{ Money::format(SettlementEngine::rupees($row['position'])) }}</strong>
                                    </td>
                                    <td>
                                        @if ($row['position'] < 0)
                                            <span class="badge badge--expense"><span class="dot"></span>Needs to pay</span>
                                            <div class="sub">
                                                @foreach ($row['pays'] as $t)
                                                    to {{ $t['to']->name }}
                                                    {{ Money::format(SettlementEngine::rupees($t['amount'])) }}@unless ($loop->last), @endunless
                                                @endforeach
                                            </div>
                                        @elseif ($row['position'] > 0)
                                            <span class="badge badge--credit"><span class="dot"></span>Needs to receive</span>
                                            <div class="sub">
                                                @foreach ($row['receives'] as $t)
                                                    from {{ $t['from']->name }}
                                                    {{ Money::format(SettlementEngine::rupees($t['amount'])) }}@unless ($loop->last), @endunless
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="badge badge--on"><span class="dot"></span>Settled</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- The same person can hold a different balance on each project. --}}
        <div class="card">
            <div class="card__head">
                <h2>Balance per project</h2>
            </div>

            @if ($person->projects->isEmpty())
                <x-empty-state
                    title="Not assigned to any project"
                    :message="$person->name.' needs to be on a project before credits or expenses can be recorded.'"
                    :action="route('admin.people.edit', $person)"
                    action-label="Assign projects"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data data--narrow">
                            <thead>
                            <tr>
                                <th>Project</th>
                                <th>Company</th>
                                <th class="num">Credit</th>
                                <th class="num">Expense</th>
                                <th class="num">Balance</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($person->projects as $project)
                                @php
                                    $t = $projectTotals[$project->id.':'.$person->id] ?? HierarchyReport::blank();
                                @endphp
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
                                    <td class="num amount--credit">{{ Money::format($t['credit']) }}</td>
                                    <td class="num amount--expense">{{ Money::format($t['expense']) }}</td>
                                    <td class="num amount--{{ ((float) $t['balance']) < 0 ? 'expense' : 'credit' }}">
                                        {{ Money::format($t['balance']) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Complete financial history. --}}
        <div class="card">
            <div class="card__head">
                <h2>Transactions <span class="muted small">({{ $transactions->total() }})</span></h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>

            @if ($transactions->isEmpty())
                <x-empty-state
                    title="No transactions in this period"
                    message="Nothing has been recorded against this person for the selected dates."
                    :action="route('admin.transactions.create', ['type' => 'expense', 'person_id' => $person->id])"
                    action-label="+ Add Expense"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Project</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="num">Amount</th>
                                <th>Payment Method</th>
                                <th>Receipt</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($transactions as $row)
                                <tr>
                                    <td class="nowrap">{{ $row->transaction_date->format($dateFormat) }}</td>
                                    <td>
                                        <span class="badge badge--{{ $row->type }}">
                                            <span class="dot"></span>{{ ucfirst($row->type) }}
                                        </span>
                                    </td>
                                    <td>{{ $row->project?->name ?? '--' }}</td>
                                    <td>{{ $row->category?->name ?? '--' }}</td>
                                    <td>
                                        <div class="title">{{ $row->title }}</div>
                                        @if ($row->description)
                                            <div class="sub">{{ Str::limit($row->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="num amount--{{ $row->type }}">
                                        {{ $row->type === 'expense' ? '-' : '+' }}{{ Money::format($row->amount) }}
                                    </td>
                                    <td class="nowrap">{{ $row->payment_method_label }}</td>
                                    <td>
                                        @if ($row->attachments->isNotEmpty())
                                            <a href="{{ route('admin.attachments.show', $row->attachments->first()) }}"
                                               target="_blank" rel="noopener" data-no-ajax>
                                                {{ $row->attachments->count() }} file(s)
                                            </a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="{{ route('admin.transactions.show', $row) }}" class="btn btn--sm">View</a>
                                            <a href="{{ route('admin.transactions.edit', $row) }}" class="btn btn--sm">Edit</a>

                                            <form method="POST" action="{{ route('admin.transactions.destroy', $row) }}"
                                                  data-confirm="Delete this {{ $row->type }}?">
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
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
