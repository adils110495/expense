@extends('admin.layouts.app')

@php
    use App\Services\SettlementEngine;
    use App\Support\Money;

    $rupees = fn (int $paise) => Money::format(SettlementEngine::rupees($paise));

    // A suggested payment counts as already written down when an open record
    // exists between the same two partners *on the same side*, so recording
    // the expense half never hides the credit half.
    $openRecords = $recorded->filter(fn ($s) => in_array($s->status, ['pending', 'partially_paid'], true));
@endphp

@section('title', 'Settlement - '.$project->name)
@section('heading', 'Settlement')
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.projects.index') }}">Projects</a></span>
    <span><a href="{{ route('admin.projects.show', $project) }}">{{ $project->name }}</a></span>
    <span>Settlement</span>
@endsection

@section('content')
    <div class="stack">
        @include('admin.partials.project-tabs', ['project' => $project, 'active' => 'settlement'])

        {{-- Payment details are entered in a modal, and the page swap that
             follows a failed save closes it - so the reasons are listed here,
             where they stay visible. --}}
        @if ($errors->any())
            <div class="alert alert--error">
                <strong>That payment could not be saved.</strong>
                <ul style="margin:8px 0 0;padding-left:18px;">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card__head">
                <h2>Settlement period</h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>
            <div class="card__body">
                @include('admin.partials.range-filter', [
                    'action' => route('admin.projects.settlement', $project),
                    'id' => 'settle',
                ])
                <p class="hint" style="margin:10px 0 0;">
                    Narrowing the period settles only that period's expenses and credit, and
                    counts only the payments made within it.
                </p>
            </div>
        </div>

        {{-- Expenses and income are two different questions, so they get two
             summaries rather than one netted figure. --}}
        <div class="grid grid--stats">
            <x-stat-card label="Total Expenses" :value="$rupees($plan['total_spent'])"
                         meta="Paid out of partners' pockets" variant="expense"/>
            <x-stat-card label="Expense Share Per Partner" :value="$rupees($plan['expense_share'])"
                         meta="What each partner should bear" variant="expense"/>
            <x-stat-card label="Total Credit / Profit" :value="$rupees($plan['total_received'])"
                         meta="Credit the project took in" variant="credit"/>
            <x-stat-card label="Credit Share Per Partner" :value="$rupees($plan['income_share'])"
                         meta="What each partner should get" variant="credit"/>
        </div>

        <div class="grid grid--stats">
            <x-stat-card label="Partners" :value="number_format($plan['partner_count'])"
                         meta="Everyone on this project"/>
            <x-stat-card label="Net Project Amount" :value="$rupees($plan['pool'])"
                         meta="Profit less expenses"
                         :variant="$plan['pool'] < 0 ? 'negative' : 'balance'"/>
            <x-stat-card label="Net Share Per Partner" :value="$rupees($plan['share'])"
                         meta="Profit share less expense share"
                         :variant="$plan['share'] < 0 ? 'negative' : 'balance'"/>
            <x-stat-card label="Total Amount To Settle" :value="$rupees($plan['to_settle'])"
                         :meta="count($plan['transfers']).' payment(s) required'"
                         :variant="$plan['to_settle'] > 0 ? 'negative' : 'credit'"/>
        </div>

        @if ($plan['partner_count'] === 0)
            <x-empty-state
                title="No partners on this project"
                message="Assign people to the project before a share can be worked out - the equal share is the project amount divided by the number of partners."
                :action="route('admin.projects.show', $project)"
                action-label="Assign partners"/>
        @else
            {{-- Expenses and credit settle as two separate lists. Each side is
                 shared equally in its own right, so a partner can owe on one
                 and be owed on the other; both are paid, and both are tracked
                 separately in the history. --}}
            <div class="card">
                <div class="card__head">
                    <h2>Expense payments required</h2>
                    <span class="badge {{ empty($plan['expense_transfers']) ? 'badge--on' : 'badge--expense' }}">
                        <span class="dot"></span>
                        {{ empty($plan['expense_transfers'])
                            ? 'Costs are square'
                            : $rupees($plan['expense_to_settle']).' outstanding' }}
                    </span>
                </div>

                <div class="card__body">
                    <p class="hint" style="margin:0 0 14px;">
                        Total expenses {{ $rupees($plan['total_spent']) }} shared across
                        {{ $plan['partner_count'] }} partner(s) is
                        <strong>{{ $rupees($plan['expense_share']) }}</strong> each.
                        Whoever paid more than that is owed the difference back.
                    </p>

                    @include('admin.partials.settle-transfers', [
                        'transfers' => $plan['expense_transfers'],
                        'kind' => 'expense',
                        'empty' => 'Everyone has already borne an equal share of the costs.',
                    ])
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <h2>Credit / profit payments required</h2>
                    <span class="badge {{ empty($plan['income_transfers']) ? 'badge--on' : 'badge--credit' }}">
                        <span class="dot"></span>
                        {{ empty($plan['income_transfers'])
                            ? 'Credit is square'
                            : $rupees($plan['income_to_settle']).' outstanding' }}
                    </span>
                </div>

                <div class="card__body">
                    <p class="hint" style="margin:0 0 14px;">
                        Total credit {{ $rupees($plan['total_received']) }} shared across
                        {{ $plan['partner_count'] }} partner(s) is
                        <strong>{{ $rupees($plan['income_share']) }}</strong> each.
                        Whoever drew more than that owes the difference back.
                    </p>

                    @include('admin.partials.settle-transfers', [
                        'transfers' => $plan['income_transfers'],
                        'kind' => 'credit',
                        'empty' => 'No credit has been recorded on this project yet.',
                    ])
                </div>
            </div>

            {{-- The same money in one payment per pair, for anyone who would
                 rather transfer once than twice. Recording is deliberately not
                 offered here: mixing net payments with the two side lists
                 would settle the same debt twice over. --}}
            <div class="card">
                <div class="card__head">
                    <h2>Net alternative <span class="muted small">(same result, fewer transfers)</span></h2>
                    <span class="badge {{ $plan['is_settled'] ? 'badge--on' : 'badge--muted' }}">
                        <span class="dot"></span>
                        {{ $plan['is_settled'] ? 'Fully settled' : $rupees($plan['to_settle']).' net' }}
                    </span>
                </div>

                <div class="card__body">
                    @if ($plan['is_settled'])
                        <p class="hint" style="margin:0;">
                            Every partner is holding exactly their equal share. Nothing needs to move.
                        </p>
                    @else
                        <p class="hint" style="margin:0 0 14px;">
                            Paying the two lists above moves
                            {{ $rupees($plan['expense_to_settle'] + $plan['income_to_settle']) }} in total.
                            Settling net instead moves {{ $rupees($plan['to_settle']) }}, because a
                            partner who owes on one side and is owed on the other cancels out.
                            Both leave every partner in exactly the same final position - pay one way
                            or the other, not both.
                        </p>

                        <div class="settle-list">
                            @foreach ($plan['transfers'] as $transfer)
                                <div class="settle settle--muted">
                                    <div class="settle__flow">
                                        <span class="settle__who">{{ $transfer['from']->name }}</span>
                                        <span class="settle__arrow" aria-label="pays">&rarr;</span>
                                        <span class="settle__who">{{ $transfer['to']->name }}</span>
                                    </div>
                                    <div class="settle__amount">{{ $rupees($transfer['amount']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Per-partner settlement table --}}
            <div class="card">
                <div class="card__head">
                    <h2>Partner positions</h2>
                    <span class="badge badge--muted">Net share {{ $rupees($plan['share']) }}</span>
                </div>

                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Partner</th>
                                <th class="num">Spent</th>
                                <th class="num">Expense Share</th>
                                <th class="num">Expense Position</th>
                                <th class="num">Received</th>
                                <th class="num">Profit Share</th>
                                <th class="num">Profit Position</th>
                                <th class="num">Settled</th>
                                <th class="num">Net Position</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($plan['partners'] as $row)
                                <tr>
                                    <td>
                                        <div class="title">
                                            <a href="{{ route('admin.people.show', $row['person']) }}">{{ $row['person']->name }}</a>
                                        </div>
                                        @if ($row['person']->designation)
                                            <div class="sub">{{ $row['person']->designation }}</div>
                                        @endif
                                    </td>
                                    <td class="num amount--expense">{{ $rupees($row['spent']) }}</td>
                                    <td class="num muted">{{ $rupees($row['expense_share']) }}</td>
                                    <td class="num amount--{{ $row['expense_position'] < 0 ? 'expense' : 'credit' }}">
                                        {{ $row['expense_position'] > 0 ? '+' : '' }}{{ $rupees($row['expense_position']) }}
                                    </td>

                                    <td class="num amount--credit">{{ $rupees($row['received']) }}</td>
                                    <td class="num muted">{{ $rupees($row['income_share']) }}</td>
                                    <td class="num amount--{{ $row['income_position'] < 0 ? 'expense' : 'credit' }}">
                                        {{ $row['income_position'] > 0 ? '+' : '' }}{{ $rupees($row['income_position']) }}
                                    </td>

                                    <td class="num">
                                        @if ($row['settled_in'] || $row['settled_out'])
                                            <span class="muted small">
                                                +{{ $rupees($row['settled_in']) }} /
                                                -{{ $rupees($row['settled_out']) }}
                                            </span>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td class="num amount--{{ $row['position'] < 0 ? 'expense' : 'credit' }}">
                                        <strong>{{ $row['position'] > 0 ? '+' : '' }}{{ $rupees($row['position']) }}</strong>
                                    </td>
                                    <td>
                                        @if ($row['position'] < 0)
                                            <span class="badge badge--expense"><span class="dot"></span>Pays</span>
                                            <div class="sub">
                                                @foreach ($row['pays'] as $t)
                                                    {{ $t['to']->name }} {{ $rupees($t['amount']) }}@unless ($loop->last), @endunless
                                                @endforeach
                                            </div>
                                        @elseif ($row['position'] > 0)
                                            <span class="badge badge--credit"><span class="dot"></span>Receives</span>
                                            <div class="sub">
                                                @foreach ($row['receives'] as $t)
                                                    {{ $t['from']->name }} {{ $rupees($t['amount']) }}@unless ($loop->last), @endunless
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

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <p class="hint" style="margin:0;">
                        Expense position is what a partner paid less their share of the costs.
                        Profit position is their share of the income less what they drew.
                        Net position is the two added together, plus anything already settled.
                        Positive means they are owed money, negative means they owe it - and
                        each of the three columns sums to zero on its own.
                    </p>
                </div>
            </div>

            {{-- Recorded settlements for this project --}}
            <div class="card">
                <div class="card__head">
                    <h2>Settlement history <span class="muted small">({{ $recorded->count() }})</span></h2>
                    <a href="{{ route('admin.settlements.index', ['project_id' => $project->id]) }}" class="btn btn--sm">
                        View all
                    </a>
                </div>

                @if ($recorded->isEmpty())
                    <x-empty-state
                        title="Nothing recorded yet"
                        message="Record a payment from the list above to start tracking it."/>
                @else
                    <div class="card__body card__body--flush">
                        <div class="table-wrap">
                            <table class="data data--narrow">
                                <thead>
                                <tr>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>For</th>
                                    <th class="num">Amount</th>
                                    <th class="num">Paid</th>
                                    <th class="num">Outstanding</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="right">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($recorded as $settlement)
                                    <tr>
                                        <td class="title">{{ $settlement->from?->name ?? '--' }}</td>
                                        <td class="title">{{ $settlement->to?->name ?? '--' }}</td>
                                        <td>
                                            <span class="badge badge--{{ $settlement->kind === 'expense' ? 'expense' : ($settlement->kind === 'credit' ? 'credit' : 'muted') }}">
                                                <span class="dot"></span>{{ $settlement->kind_label }}
                                            </span>
                                        </td>
                                        <td class="num">{{ Money::format($settlement->amount) }}</td>
                                        <td class="num amount--credit">{{ Money::format($settlement->paid_amount) }}</td>
                                        <td class="num {{ (float) $settlement->outstanding > 0 ? 'amount--expense' : 'muted' }}">
                                            {{ Money::format($settlement->outstanding) }}
                                        </td>
                                        <td>
                                            <span class="badge badge--{{ $settlement->status === 'paid' ? 'on' : ($settlement->status === 'cancelled' ? 'off' : 'muted') }}">
                                                <span class="dot"></span>{{ $settlement->status_label }}
                                            </span>
                                        </td>
                                        <td class="nowrap">
                                            {{ optional($settlement->settled_on)->format($dateFormat) ?? '--' }}
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="{{ route('admin.settlements.show', $settlement) }}" class="btn btn--sm">View</a>

                                                @if ($settlement->status !== 'paid' && $settlement->status !== 'cancelled')
                                                    <form method="POST" action="{{ route('admin.settlements.paid', $settlement) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn--sm btn--primary" data-busy="Saving...">
                                                            Mark Paid
                                                        </button>
                                                    </form>
                                                @endif

                                                <form method="POST" action="{{ route('admin.settlements.destroy', $settlement) }}"
                                                      data-confirm="Remove this settlement record? The plan will be recalculated.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn--sm btn--danger" data-busy="Removing...">
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
                @endif
            </div>
        @endif
    </div>
@endsection
