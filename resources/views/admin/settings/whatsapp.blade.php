@extends('admin.layouts.app')

@section('title', 'WhatsApp Settings')
@section('heading', 'WhatsApp')
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.settings.index') }}">Settings</a></span>
    <span>WhatsApp</span>
@endsection

@section('content')
    <div class="stack">
        @include('admin.partials.settings-tabs', ['active' => 'whatsapp'])

        <div class="card">
            <div class="card__head">
                <h2>WhatsApp Business Cloud API</h2>
                <span class="badge {{ $ready ? 'badge--on' : 'badge--off' }}">
                    <span class="dot"></span>{{ $ready ? 'Ready to send' : 'Not sending' }}
                </span>
            </div>

            <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}">
                @csrf
                @method('PUT')

                <div class="card__body">
                    <div class="row">
                        <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <input type="hidden" name="whatsapp_enabled" value="0">
                            <label class="check">
                                <input type="checkbox" name="whatsapp_enabled" value="1"
                                       @checked(($settings['whatsapp_enabled'] ?? '0') === '1')>
                                Enable WhatsApp notifications
                            </label>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="whatsapp_phone_number_id">Phone Number ID</label>
                            <input id="whatsapp_phone_number_id" name="whatsapp_phone_number_id" type="text"
                                   maxlength="60" class="input @error('whatsapp_phone_number_id') input--error @enderror"
                                   placeholder="e.g. 123456789012345"
                                   value="{{ old('whatsapp_phone_number_id', $settings['whatsapp_phone_number_id'] ?? '') }}">
                            <x-field-error name="whatsapp_phone_number_id"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="whatsapp_business_account_id">Business Account ID</label>
                            <input id="whatsapp_business_account_id" name="whatsapp_business_account_id" type="text"
                                   maxlength="60" class="input @error('whatsapp_business_account_id') input--error @enderror"
                                   value="{{ old('whatsapp_business_account_id', $settings['whatsapp_business_account_id'] ?? '') }}">
                            <x-field-error name="whatsapp_business_account_id"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="whatsapp_default_country_code">Default Country Code</label>
                            <input id="whatsapp_default_country_code" name="whatsapp_default_country_code" type="text"
                                   maxlength="5" class="input"
                                   placeholder="91"
                                   value="{{ old('whatsapp_default_country_code', $settings['whatsapp_default_country_code'] ?? '91') }}">
                            <span class="hint">Added to numbers stored without one.</span>
                        </div>

                        {{-- Credentials. The stored value is never rendered -
                             only a mask - and an empty field on save means
                             "keep what is there". --}}
                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="whatsapp_access_token">Access Token</label>
                            <input id="whatsapp_access_token" name="whatsapp_access_token" type="password"
                                   autocomplete="new-password" maxlength="1000"
                                   class="input @error('whatsapp_access_token') input--error @enderror"
                                   placeholder="{{ $tokenMask ? 'Stored: '.$tokenMask : 'Paste the permanent access token' }}">
                            <x-field-error name="whatsapp_access_token"/>
                            <span class="hint">
                                Encrypted at rest and never sent back to the browser.
                                Leave blank to keep the stored token.
                                @if ($tokenMask)
                                    <button type="submit" class="btn btn--sm btn--danger"
                                            form="forget-token" style="margin-top:6px;">Clear token</button>
                                @endif
                            </span>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="whatsapp_webhook_verify_token">Webhook Verify Token</label>
                            <input id="whatsapp_webhook_verify_token" name="whatsapp_webhook_verify_token" type="password"
                                   autocomplete="new-password" maxlength="255" class="input"
                                   placeholder="{{ $verifyMask ? 'Stored: '.$verifyMask : 'Any string you also paste into Meta' }}">
                            <span class="hint">
                                Also guards the email delivery callback. Leave blank to keep the stored value.
                            </span>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="whatsapp_api_base">API Base URL</label>
                            <input id="whatsapp_api_base" name="whatsapp_api_base" type="url" required maxlength="255"
                                   class="input @error('whatsapp_api_base') input--error @enderror"
                                   value="{{ old('whatsapp_api_base', $settings['whatsapp_api_base'] ?? 'https://graph.facebook.com') }}">
                            <x-field-error name="whatsapp_api_base"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="whatsapp_api_version">API Version</label>
                            <input id="whatsapp_api_version" name="whatsapp_api_version" type="text" required
                                   maxlength="10" class="input @error('whatsapp_api_version') input--error @enderror"
                                   placeholder="v21.0"
                                   value="{{ old('whatsapp_api_version', $settings['whatsapp_api_version'] ?? 'v21.0') }}">
                            <x-field-error name="whatsapp_api_version"/>
                        </div>
                    </div>
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving...">Save WhatsApp settings</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Kept outside the settings form: nesting forms is invalid HTML. --}}
        <form method="POST" action="{{ route('admin.settings.whatsapp.forget') }}" id="forget-token" hidden
              data-confirm="Clear the stored WhatsApp access token?">
            @csrf
            <input type="hidden" name="key" value="whatsapp_access_token">
        </form>

        <div class="card">
            <div class="card__head"><h2>Test tools</h2></div>

            <div class="card__body">
                <div class="row">
                    <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                        <label>Connection</label>
                        <p class="hint" style="margin:0 0 10px;">
                            Reads the configured phone number back from Meta. Costs nothing and
                            bothers nobody.
                        </p>
                        <form method="POST" action="{{ route('admin.settings.whatsapp.test') }}">
                            @csrf
                            <button type="submit" class="btn" data-busy="Checking...">Test WhatsApp connection</button>
                        </form>
                    </div>

                    <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                        <form method="POST" action="{{ route('admin.settings.whatsapp.send') }}">
                            @csrf
                            <label for="test_number">Send a test message</label>
                            <input id="test_number" name="test_number" type="text" maxlength="30" class="input"
                                   placeholder="9876543210 or +919876543210"
                                   value="{{ old('test_number') }}">
                            <x-field-error name="test_number"/>
                            <div class="btn-row mt">
                                <button type="submit" class="btn btn--primary" data-busy="Sending...">Send test WhatsApp</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2>Webhook</h2></div>
            <div class="card__body">
                <p class="hint" style="margin:0 0 10px;">
                    Point Meta's webhook at the URL below and use the verify token above. Without
                    it, a message can only ever be recorded as <em>sent</em> - delivered and read
                    statuses arrive here.
                </p>
                <div class="field">
                    <label for="webhook-url">Callback URL</label>
                    <input id="webhook-url" type="text" class="input" readonly
                           value="{{ $webhookUrl }}" onclick="this.select()">
                    <span class="hint">Subscribe to the <strong>messages</strong> field.</span>
                </div>
            </div>
        </div>
    </div>
@endsection
