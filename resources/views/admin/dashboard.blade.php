@extends('admin.layouts.app')

@php
    use App\Services\SettlementEngine;
    use App\Support\Money;
    use App\Models\Setting;
    $dateFormat = Setting::get('date_format') ?? 'd M Y';
@endphp

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('breadcrumbs')
    <span>Admin</span><span>Dashboard</span>
@endsection

@section('content')
    <div class="stack">

        {{-- Global period filter: everything below reflects this range. --}}
        <div class="card">
            <div class="card__head">
                <h2>Financial overview</h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>
            <div class="card__body">
                @include('admin.partials.range-filter', [
                    'action' => route('admin.dashboard'),
                    'id' => 'dash',
                ])
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="grid grid--stats">
            <x-stat-card
                label="Total Expense"
                :value="Money::format($summary['expense_total'])"
                :meta="$summary['expense_count'].' expense transaction(s)'"
                variant="expense"/>

            <x-stat-card
                label="Total Credit"
                :value="Money::format($summary['credit_total'])"
                :meta="$summary['credit_count'].' credit transaction(s)'"
                variant="credit"/>

            <x-stat-card
                label="Balance"
                :value="Money::format($summary['balance'])"
                meta="Total Credit less Total Expense"
                :variant="((float) $summary['balance']) < 0 ? 'negative' : 'balance'"/>

            <x-stat-card
                label="Transactions"
                :value="number_format($summary['total_count'])"
                :meta="$summary['expense_count'].' expenses / '.$summary['credit_count'].' credits'"/>
        </div>

        {{-- Charts --}}
        <div class="grid grid--halves">
            <div class="card">
                <div class="card__head"><h2>Expense vs Credit</h2></div>
                <div class="card__body">
                    <x-chart-split :summary="$summary"/>
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <h2>Monthly overview</h2>
                    <a href="{{ route('admin.reports.index', $range->queryParams()) }}" class="btn btn--sm">Full report</a>
                </div>
                <div class="card__body">
                    <x-chart-monthly :series="$monthly"/>
                </div>
            </div>
        </div>

        {{-- Settlement overview, on the same period as the rest of the page:
             the filter narrows the transactions that create the debt and the
             payments that have already settled part of it. --}}
        <div class="card">
            <div class="card__head">
                <h2>Settlement overview</h2>
                <div class="btn-row">
                    <span class="badge badge--muted">{{ $range->label() }}</span>
                    <a href="{{ route('admin.settlements.index') }}" class="btn btn--sm">History</a>
                </div>
            </div>

            <div class="card__body">
                <div class="grid grid--stats">
                    {{-- Costs and income shown apart, then the net that is
                         actually paid - one figure would hide both. --}}
                    <x-stat-card label="Expenses To Settle"
                                 :value="Money::format(SettlementEngine::rupees($settlementExpense))"
                                 meta="Reimbursement owed on shared costs"
                                 variant="expense"/>
                    <x-stat-card label="Profit To Distribute"
                                 :value="Money::format(SettlementEngine::rupees($settlementIncome))"
                                 meta="Income still owed to partners"
                                 variant="credit"/>
                    <x-stat-card label="Net If Settled Together"
                                 :value="Money::format(SettlementEngine::rupees($settlementTotal))"
                                 meta="Same result in fewer transfers"
                                 :variant="$settlementTotal > 0 ? 'negative' : 'credit'"/>
                    <x-stat-card label="Partners Who Need To Pay" :value="number_format($payers)"
                                 meta="Holding more than their share"/>
                    <x-stat-card label="Partners Who Need To Receive" :value="number_format($receivers)"
                                 meta="Holding less than their share"/>
                </div>
            </div>

            {{-- Two lists, never one merged total: what is owed on shared
                 costs and what is owed on shared credit are different debts
                 and are paid separately. --}}
            <div class="grid grid--halves" style="padding:0 18px 18px;">
                @foreach ([
                    ['Expense payments', $settlementExpenseTransfers, $settlementExpenseCount,
                     'Reimbursing whoever paid more than their share of the costs.', 'expense'],
                    ['Credit / profit payments', $settlementIncomeTransfers, $settlementIncomeCount,
                     'Passing on the share of credit each partner has not yet drawn.', 'credit'],
                ] as [$sideTitle, $sideTransfers, $sideCount, $sideNote, $sideVariant])
                    <div class="card">
                        <div class="card__head">
                            <h2>{{ $sideTitle }}</h2>
                            <span class="badge badge--{{ $sideVariant }}">
                                <span class="dot"></span>{{ $sideCount }}
                            </span>
                        </div>

                        <div class="card__body">
                            <p class="hint" style="margin:0 0 12px;">{{ $sideNote }}</p>

                            @if (empty($sideTransfers))
                                <p class="hint" style="margin:0;">Nothing outstanding on this side.</p>
                            @else
                                <div class="settle-list">
                                    @foreach ($sideTransfers as $transfer)
                                        <a class="settle settle--link"
                                           href="{{ route('admin.projects.settlement', $transfer['project']) }}">
                                            <span class="settle__flow">
                                                <span class="settle__who">{{ $transfer['from']->name }}</span>
                                                <span class="settle__arrow" aria-label="pays">&rarr;</span>
                                                <span class="settle__who">{{ $transfer['to']->name }}</span>
                                            </span>
                                            <span class="settle__amount">
                                                {{ Money::format(SettlementEngine::rupees($transfer['amount'])) }}
                                            </span>
                                            <span class="settle__action muted small">{{ $transfer['project']->name }}</span>
                                        </a>
                                    @endforeach
                                </div>

                                @if ($sideCount > count($sideTransfers))
                                    <p class="hint" style="margin:12px 0 0;">
                                        Showing the {{ count($sideTransfers) }} largest of {{ $sideCount }}.
                                        Open a project's Settlement tab for its full plan.
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Company -> Project -> Person, for the same period as everything
             else on the page. Every figure on it is summed from transactions
             at read time, so it cannot drift from the cards above. --}}
        <div class="card">
            <div class="card__head">
                <h2>Hierarchy</h2>
                <div class="btn-row">
                    <button type="button" class="btn btn--sm" data-tree-expand="dashboard-tree">Expand all</button>
                    <button type="button" class="btn btn--sm" data-tree-collapse="dashboard-tree">Collapse all</button>
                    <a href="{{ route('admin.hierarchy.index', $range->queryParams()) }}" class="btn btn--sm">Full view</a>
                </div>
            </div>

            @if ($unassigned > 0)
                <div class="card__body" style="padding-bottom:0;">
                    <div class="alert alert--warn" style="margin:0;">
                        {{ $unassigned }} transaction(s) in this period are not attached to a company,
                        project and person, so they are missing from the branches below and from
                        settlement. <a href="{{ route('admin.transactions.assign') }}">Assign them now</a>.
                    </div>
                </div>
            @endif

            @if (empty($tree))
                <x-empty-state
                    title="No companies yet"
                    message="Add a company, give it a project, and assign people to that project - their credits and expenses then roll up through the tree."
                    :action="route('admin.companies.create')"
                    action-label="+ Add Company"/>
            @else
                <div class="card__body">
                    @include('admin.partials.tree', ['tree' => $tree, 'id' => 'dashboard-tree'])
                </div>
            @endif
        </div>

        {{-- Where the money went --}}
        <div class="grid grid--halves">
            <div class="card">
                <div class="card__head"><h2>Top expense categories</h2></div>
                <div class="card__body">
                    @if (empty($expenseByCategory))
                        <p class="muted small" style="margin:0;">No expenses recorded in this period.</p>
                    @else
                        @php $top = (float) ($expenseByCategory[0]['total'] ?? 0); @endphp
                        <div class="bars">
                            @foreach (array_slice($expenseByCategory, 0, 6) as $row)
                                <div>
                                    <div class="bar__top">
                                        <span>{{ $row['name'] }} <span class="muted small">({{ $row['count'] }})</span></span>
                                        <strong>{{ Money::format($row['total']) }}</strong>
                                    </div>
                                    <div class="bar__track">
                                        <div class="bar__fill"
                                             style="width:{{ $top > 0 ? round(((float) $row['total'] / $top) * 100, 1) : 0 }}%;background:#dc2626"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card__head">
                    <h2>Quick actions</h2>
                </div>
                <div class="card__body">
                    <div class="btn-row">
                        <a href="{{ route('admin.transactions.create', ['type' => 'expense']) }}" class="btn btn--primary">+ Add Expense</a>
                        <a href="{{ route('admin.transactions.create', ['type' => 'credit']) }}" class="btn">+ Add Credit</a>
                        @admin
                            <a href="{{ route('admin.companies.create') }}" class="btn">+ Company</a>
                        @endadmin
                        <a href="{{ route('admin.projects.create') }}" class="btn">+ Project</a>
                        <a href="{{ route('admin.people.create') }}" class="btn">+ Person</a>
                    </div>
                    <p class="hint mt" style="margin-bottom:0;">
                        Every figure on this page is calculated live from the transactions table,
                        so totals update the moment a record is added, edited or deleted.
                    </p>
                </div>
            </div>
        </div>

        {{-- Recent transactions --}}
        <div class="card">
            <div class="card__head">
                <h2>Recent transactions</h2>
                <a href="{{ route('admin.transactions.index', $range->queryParams()) }}" class="btn btn--sm">
                    View All Transactions
                </a>
            </div>

            @if ($recent->isEmpty())
                <x-empty-state
                    title="No transactions found"
                    message="Nothing was recorded in this period. Add an expense or a credit to get started."
                    :action="route('admin.transactions.create', ['type' => 'expense'])"
                    action-label="+ Add Expense"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Company / Project / Person</th>
                                <th>Category</th>
                                <th class="num">Amount</th>
                                <th>Payment Method</th>
                                {{-- Neutral header: the list mixes both types,
                                     which label this "Payment By" / "Payment
                                     Received" on their own pages. --}}
                                <th>Payment By</th>
                                <th>Status</th>
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
                                    <td>
                                        <div class="title">{{ $row->title }}</div>
                                        @if ($row->description)
                                            <div class="sub">{{ Str::limit($row->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @include('admin.partials.hier-path', ['row' => $row])
                                    </td>
                                    <td>{{ $row->category?->name ?? '--' }}</td>
                                    <td class="num amount--{{ $row->type }}">
                                        {{ $row->type === 'expense' ? '-' : '+' }}{{ Money::format($row->amount) }}
                                    </td>
                                    <td class="nowrap">{{ $row->payment_method_label }}</td>
                                    <td>{{ $row->paymentBy?->name ?? '--' }}</td>
                                    <td><span class="badge badge--on"><span class="dot"></span>Recorded</span></td>
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
