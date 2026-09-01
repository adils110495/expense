@extends('admin.layouts.app')

@php
    use App\Models\Settlement;
    use App\Support\Money;
@endphp

@section('title', 'Settlements')
@section('heading', 'Settlements')
@section('breadcrumbs')
    <span>Admin</span><span>Settlements</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Settlement history</h2>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.settlements.index') }}" class="row">
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
                            @foreach (Settlement::STATUSES as $value => $text)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field field--actions col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <button type="submit" class="btn btn--primary">Apply</button>
                        <a href="{{ route('admin.settlements.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>

            @if ($settlements->isEmpty())
                <x-empty-state
                    title="No settlements recorded"
                    message="Settlements are recorded from a project's Settlement page, where the plan works out who pays whom."
                    :action="route('admin.projects.index')"
                    action-label="Go to projects"/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>For</th>
                                <th>Project</th>
                                <th class="num">Amount</th>
                                <th class="num">Paid</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($settlements as $settlement)
                                <tr>
                                    <td class="title">
                                        @if ($settlement->from)
                                            <a href="{{ route('admin.people.show', $settlement->from) }}">{{ $settlement->from->name }}</a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td class="title">
                                        @if ($settlement->to)
                                            <a href="{{ route('admin.people.show', $settlement->to) }}">{{ $settlement->to->name }}</a>
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $settlement->kind === 'expense' ? 'expense' : ($settlement->kind === 'credit' ? 'credit' : 'muted') }}">
                                            <span class="dot"></span>{{ $settlement->kind_label }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($settlement->project)
                                            <a href="{{ route('admin.projects.settlement', $settlement->project) }}">{{ $settlement->project->name }}</a>
                                            @if ($settlement->project->company)
                                                <div class="sub">{{ $settlement->project->company->name }}</div>
                                            @endif
                                        @else
                                            --
                                        @endif
                                    </td>
                                    <td class="num">{{ Money::format($settlement->amount) }}</td>
                                    <td class="num">{{ Money::format($settlement->paid_amount) }}</td>
                                    <td>
                                        <span class="badge badge--{{ $settlement->status === 'paid' ? 'on' : ($settlement->status === 'cancelled' ? 'off' : 'muted') }}">
                                            <span class="dot"></span>{{ $settlement->status_label }}
                                        </span>
                                    </td>
                                    <td class="nowrap">{{ optional($settlement->settled_on)->format($dateFormat) ?? '--' }}</td>
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
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pagination-wrap">
                    {{ $settlements->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
