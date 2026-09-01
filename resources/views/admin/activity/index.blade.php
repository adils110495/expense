@extends('admin.layouts.app')

@php
    use App\Models\UserActivity;
    use App\Support\DateRange;
@endphp

@section('title', 'User Activity')
@section('heading', 'User Activity')
@section('breadcrumbs')
    <span>Admin</span><span>User Activity</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Filter activity</h2>
                <span class="badge badge--muted">Read only</span>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.activity.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="action">Action</label>
                        <select id="action" name="action" class="select">
                            <option value="">All actions</option>
                            @foreach (UserActivity::ACTIONS as $value => $text)
                                <option value="{{ $value }}" @selected(request('action') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="table_name">Table</label>
                        <select id="table_name" name="table_name" class="select">
                            <option value="">All tables</option>
                            @foreach ($tables as $table)
                                <option value="{{ $table }}" @selected(request('table_name') === $table)>{{ $table }}</option>
                            @endforeach
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
                        <a href="{{ route('admin.activity.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Activity <span class="muted small">({{ $activities->total() }})</span></h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>

            @if ($activities->isEmpty())
                <x-empty-state
                    title="No activity recorded"
                    message="Every add, edit and delete across the panel is written here as it happens. Nothing matches the current filters yet."/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data data--compact">
                            <thead>
                            <tr>
                                {{-- Record id, details, user and IP are still
                                     recorded on every entry and are still
                                     searchable and filterable above; the list
                                     itself shows only when, what and where. --}}
                                <th>Date &amp; Time</th>
                                <th>Action</th>
                                <th>Table</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($activities as $activity)
                                @php
                                    $tone = match ($activity->action) {
                                        'created', 'assigned', 'restored', 'login' => 'credit',
                                        'deleted', 'force_deleted', 'unassigned' => 'expense',
                                        default => 'muted',
                                    };
                                @endphp
                                <tr>
                                    <td class="nowrap">
                                        {{ $activity->created_at->format($dateFormat) }}
                                        <div class="sub">{{ $activity->created_at->format('H:i:s') }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $tone }}">
                                            <span class="dot"></span>{{ $activity->action_label }}
                                        </span>
                                    </td>
                                    <td class="nowrap">{{ $activity->table_name }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pagination-wrap">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>

        <p class="hint">
            The activity log is append only. Entries cannot be edited or deleted from here,
            or from anywhere else in the panel.
        </p>
    </div>
@endsection
