@extends('admin.layouts.app')

@php
    use App\Models\NotificationLog;
    use App\Support\DateRange;
@endphp

@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('breadcrumbs')
    <span>Admin</span><span>Notifications</span>
@endsection

@section('content')
    <div class="stack">
        @if (! $channels['whatsapp'] && ! $channels['email'])
            <div class="alert alert--warn">
                Neither channel is switched on yet. Nothing will be sent until
                <a href="{{ route('admin.settings.whatsapp') }}">WhatsApp</a> or
                <a href="{{ route('admin.settings.email') }}">Email</a> is configured and enabled.
            </div>
        @endif

        <div class="grid grid--stats">
            <x-stat-card label="Total Sent" :value="number_format($summary['succeeded'])"
                         :meta="$summary['total'].' attempt(s) in this period'"/>
            <x-stat-card label="WhatsApp" :value="number_format($summary['whatsapp'])"
                         :meta="$channels['whatsapp'] ? 'Channel ready' : 'Channel off'"/>
            <x-stat-card label="Email" :value="number_format($summary['email'])"
                         :meta="$channels['email'] ? 'Channel ready' : 'Channel off'"/>
            <x-stat-card label="Delivered" :value="number_format($summary['delivered'] + $summary['read'])"
                         meta="Confirmed by the provider" variant="credit"/>
            <x-stat-card label="Pending" :value="number_format($summary['pending'])"
                         meta="Queued or still retrying"/>
            <x-stat-card label="Failed" :value="number_format($summary['failed'])"
                         meta="Gave up or was rejected"
                         :variant="$summary['failed'] > 0 ? 'negative' : null"/>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Filter notifications</h2>
                <div class="btn-row">
                    <form method="POST" action="{{ route('admin.notifications.remind') }}"
                          data-confirm="Queue a settlement reminder to every partner who owes or is owed, on every active project?">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--sm" data-busy="Queueing...">
                            Send settlement reminders to all
                        </button>
                    </form>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.notifications.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Recipient, number, address or subject">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="channel">Channel</label>
                        <select id="channel" name="channel" class="select">
                            <option value="">All channels</option>
                            <option value="whatsapp" @selected(request('channel') === 'whatsapp')>WhatsApp</option>
                            <option value="email" @selected(request('channel') === 'email')>Email</option>
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="select">
                            <option value="">All statuses</option>
                            @foreach (NotificationLog::STATUSES as $value => $text)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="event">Type</label>
                        <select id="event" name="event" class="select">
                            <option value="">All types</option>
                            @foreach ($events as $value => $text)
                                <option value="{{ $value }}" @selected(request('event') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="project_id">Project</label>
                        <select id="project_id" name="project_id" class="select">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                                    {{ $project->name }}
                                </option>
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
                        <a href="{{ route('admin.notifications.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Notification log <span class="muted small">({{ $logs->total() }})</span></h2>
                <span class="badge badge--muted">{{ $range->label() }}</span>
            </div>

            @if ($logs->isEmpty())
                <x-empty-state
                    title="Nothing sent in this period"
                    message="Notifications are queued whenever an expense, credit or settlement changes, and every attempt is recorded here."/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Sent</th>
                                <th>Recipient</th>
                                <th>Channel</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($logs as $log)
                                @php
                                    $tone = match ($log->status) {
                                        'delivered', 'read' => 'credit',
                                        'failed', 'bounced' => 'expense',
                                        'sent' => 'on',
                                        default => 'muted',
                                    };
                                @endphp
                                <tr>
                                    <td class="nowrap">
                                        {{ $log->created_at->format($dateFormat) }}
                                        <div class="sub">{{ $log->created_at->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        <div class="title">{{ $log->recipient_name ?: '--' }}</div>
                                        <div class="sub">{{ $log->recipient }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $log->channel === 'whatsapp' ? 'credit' : 'muted' }}">
                                            <span class="dot"></span>{{ $log->channel === 'whatsapp' ? 'WhatsApp' : 'Email' }}
                                        </span>
                                    </td>
                                    <td class="nowrap">{{ $events[$log->event] ?? $log->event }}</td>
                                    <td>
                                        <div class="title">{{ $log->subject ?: Str::limit($log->body, 48) }}</div>
                                        @if ($log->project)
                                            <div class="sub">{{ $log->project->name }}</div>
                                        @endif
                                        @if ($log->error)
                                            <div class="sub" style="color:var(--expense);">{{ Str::limit($log->error, 90) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $tone }}">
                                            <span class="dot"></span>{{ $log->status_label }}
                                        </span>
                                        @if ($log->attempts > 1)
                                            <div class="sub">{{ $log->attempts }} attempts</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="actions">
                                            @if (in_array($log->status, ['failed', 'bounced'], true))
                                                <form method="POST" action="{{ route('admin.notifications.retry', $log) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn--sm btn--primary" data-busy="Queueing...">
                                                        Retry
                                                    </button>
                                                </form>
                                            @elseif ($log->status === 'pending')
                                                <form method="POST" action="{{ route('admin.notifications.cancel', $log) }}"
                                                      data-confirm="Stop this notification from being sent?">
                                                    @csrf
                                                    <button type="submit" class="btn btn--sm" data-busy="Cancelling...">
                                                        Cancel
                                                    </button>
                                                </form>
                                            @else
                                                <span class="muted small">--</span>
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
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
