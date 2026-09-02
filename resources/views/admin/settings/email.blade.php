@extends('admin.layouts.app')

@section('title', 'Email Settings')
@section('heading', 'Email')
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.settings.index') }}">Settings</a></span>
    <span>Email</span>
@endsection

@section('content')
    <div class="stack">
        @include('admin.partials.settings-tabs', ['active' => 'email'])

        <div class="card">
            <div class="card__head">
                <h2>Email delivery</h2>
                <span class="badge {{ $ready ? 'badge--on' : 'badge--off' }}">
                    <span class="dot"></span>{{ $ready ? 'Ready to send' : 'Not sending' }}
                </span>
            </div>

            <form method="POST" action="{{ route('admin.settings.email.update') }}">
                @csrf
                @method('PUT')

                <div class="card__body">
                    <div class="row">
                        <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <input type="hidden" name="email_enabled" value="0">
                            <label class="check">
                                <input type="checkbox" name="email_enabled" value="1"
                                       @checked(($settings['email_enabled'] ?? '0') === '1')>
                                Enable email notifications
                            </label>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email_provider">Provider</label>
                            <select id="email_provider" name="email_provider" required
                                    class="select @error('email_provider') select--error @enderror">
                                @foreach ($providers as $value => $label)
                                    <option value="{{ $value }}"
                                            @selected(old('email_provider', $settings['email_provider'] ?? 'smtp') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="email_provider"/>
                            <span class="hint">
                                SMTP and SES go through Laravel's mail config; the rest use their REST API.
                            </span>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="email_api_key">API Key</label>
                            <input id="email_api_key" name="email_api_key" type="password"
                                   autocomplete="new-password" maxlength="500"
                                   class="input @error('email_api_key') input--error @enderror"
                                   placeholder="{{ $keyMask ? 'Stored: '.$keyMask : 'Paste the provider API key' }}">
                            <x-field-error name="email_api_key"/>
                            <span class="hint">
                                Encrypted at rest and never sent back to the browser. Leave blank to
                                keep the stored key. Not needed for SMTP.
                                @if ($keyMask)
                                    <button type="submit" class="btn btn--sm btn--danger"
                                            form="forget-key" style="margin-top:6px;">Clear key</button>
                                @endif
                            </span>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email_from_name">From Name</label>
                            <input id="email_from_name" name="email_from_name" type="text" maxlength="100" class="input"
                                   placeholder="{{ config('app.name') }}"
                                   value="{{ old('email_from_name', $settings['email_from_name'] ?? '') }}">
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email_from_address">From Email</label>
                            <input id="email_from_address" name="email_from_address" type="email" maxlength="255"
                                   class="input @error('email_from_address') input--error @enderror"
                                   placeholder="notifications@example.com"
                                   value="{{ old('email_from_address', $settings['email_from_address'] ?? '') }}">
                            <x-field-error name="email_from_address"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email_reply_to">Reply-To</label>
                            <input id="email_reply_to" name="email_reply_to" type="email" maxlength="255"
                                   class="input @error('email_reply_to') input--error @enderror"
                                   value="{{ old('email_reply_to', $settings['email_reply_to'] ?? '') }}">
                            <x-field-error name="email_reply_to"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email_domain">Mailgun Domain</label>
                            <input id="email_domain" name="email_domain" type="text" maxlength="255" class="input"
                                   placeholder="mg.example.com"
                                   value="{{ old('email_domain', $settings['email_domain'] ?? '') }}">
                            <span class="hint">Mailgun only.</span>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email_endpoint">Mailgun Endpoint</label>
                            <input id="email_endpoint" name="email_endpoint" type="url" maxlength="255"
                                   class="input @error('email_endpoint') input--error @enderror"
                                   placeholder="https://api.mailgun.net/v3"
                                   value="{{ old('email_endpoint', $settings['email_endpoint'] ?? '') }}">
                            <x-field-error name="email_endpoint"/>
                            <span class="hint">Use the EU host if your domain is there.</span>
                        </div>
                    </div>
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving...">Save email settings</button>
                    </div>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.settings.email.forget') }}" id="forget-key" hidden
              data-confirm="Clear the stored email API key?">
            @csrf
        </form>

        <div class="card">
            <div class="card__head"><h2>Test tools</h2></div>

            <div class="card__body">
                <div class="row">
                    <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                        <label>Connection</label>
                        <p class="hint" style="margin:0 0 10px;">
                            Checks the API key against the provider without sending anything.
                        </p>
                        <form method="POST" action="{{ route('admin.settings.email.test') }}">
                            @csrf
                            <button type="submit" class="btn" data-busy="Checking...">Test email connection</button>
                        </form>
                    </div>

                    <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                        <form method="POST" action="{{ route('admin.settings.email.send') }}">
                            @csrf
                            <label for="test_email">Send a test email</label>
                            <input id="test_email" name="test_email" type="email" maxlength="255" class="input"
                                   placeholder="you@example.com" value="{{ old('test_email') }}">
                            <x-field-error name="test_email"/>
                            <div class="btn-row mt">
                                <button type="submit" class="btn btn--primary" data-busy="Sending...">Send test email</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
