@extends('admin.layouts.app')

@php
    use App\Models\NotificationTemplate;
@endphp

@section('title', 'Notification Templates')
@section('heading', 'Notification Templates')
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.settings.index') }}">Settings</a></span>
    <span>Templates</span>
@endsection

@section('content')
    <div class="stack">
        @include('admin.partials.settings-tabs', ['active' => 'templates'])

        {{-- The switches above every partner's own preference. Off here means
             off for everyone, whatever they have chosen. --}}
        <div class="card">
            <div class="card__head"><h2>What gets sent at all</h2></div>

            <form method="POST" action="{{ route('admin.settings.templates.globals') }}">
                @csrf
                @method('PUT')

                <div class="card__body">
                    <div class="row">
                        @foreach ([
                            'notify_expense' => 'Expense notifications',
                            'notify_credit' => 'Credit notifications',
                            'notify_settlement' => 'Settlement notifications',
                            'notify_summary' => 'Monthly summary',
                        ] as $key => $label)
                            <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                                <input type="hidden" name="{{ $key }}" value="0">
                                <label class="check">
                                    <input type="checkbox" name="{{ $key }}" value="1"
                                           @checked(($globals[$key] ?? '0') === '1')>
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <p class="hint" style="margin:0;">
                        Each partner can still opt out of anything left on here, on their own record.
                    </p>
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving...">Save switches</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card__head"><h2>Available variables</h2></div>
            <div class="card__body">
                <p class="hint" style="margin:0 0 12px;">
                    Write these in a template as <code>&#123;&#123;name&#125;&#125;</code>. Anything the
                    system cannot fill is removed before sending, so a stale placeholder never
                    reaches anyone as literal braces.
                </p>
                <div class="var-list">
                    @foreach ($variables as $name => $description)
                        <span class="var">
                            <code>&#123;&#123;{{ $name }}&#125;&#125;</code>
                            <span class="muted small">{{ $description }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        @foreach ($events as $event => $eventLabel)
            @php $group = $templates[$event] ?? collect(); @endphp

            @if ($group->isNotEmpty())
                <div class="card">
                    <div class="card__head">
                        <h2>{{ $eventLabel }}</h2>
                        <span class="badge badge--muted">{{ $event }}</span>
                    </div>

                    <div class="card__body">
                        <div class="grid grid--halves">
                            @foreach ($group as $template)
                                <form method="POST" action="{{ route('admin.settings.templates.update', $template) }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="tpl">
                                        <div class="tpl__head">
                                            <strong>{{ NotificationTemplate::CHANNELS[$template->channel] }}</strong>
                                            <label class="check">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1"
                                                       @checked($template->is_active)>
                                                Active
                                            </label>
                                        </div>

                                        @if ($template->channel === 'email')
                                            <div class="field">
                                                <label for="subject-{{ $template->id }}">Subject</label>
                                                <input id="subject-{{ $template->id }}" name="subject" type="text"
                                                       maxlength="255" class="input"
                                                       value="{{ $template->subject }}">
                                            </div>
                                        @else
                                            <div class="row">
                                                <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                                                    <label for="tplname-{{ $template->id }}">Approved template name</label>
                                                    <input id="tplname-{{ $template->id }}" name="whatsapp_template_name"
                                                           type="text" maxlength="120" class="input"
                                                           placeholder="Optional"
                                                           value="{{ $template->whatsapp_template_name }}">
                                                </div>
                                                <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                                                    <label for="lang-{{ $template->id }}">Language</label>
                                                    <input id="lang-{{ $template->id }}" name="language" type="text"
                                                           maxlength="10" class="input"
                                                           value="{{ $template->language }}">
                                                </div>
                                            </div>
                                            <p class="hint" style="margin:0 0 10px;">
                                                Outside the 24 hour window WhatsApp accepts approved templates only.
                                                Name one here and the text below is passed as its first variable.
                                            </p>
                                        @endif

                                        <div class="field">
                                            <label for="body-{{ $template->id }}">Message</label>
                                            <textarea id="body-{{ $template->id }}" name="body" rows="8"
                                                      maxlength="5000" class="textarea" required>{{ $template->body }}</textarea>
                                        </div>

                                        <div class="btn-row">
                                            <button type="submit" class="btn btn--primary btn--sm" data-busy="Saving...">
                                                Save {{ $template->channel }} template
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection
