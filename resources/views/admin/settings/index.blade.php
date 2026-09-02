@extends('admin.layouts.app')

@section('title', 'Settings')
@section('heading', 'Settings')
@section('breadcrumbs')
    <span>Admin</span><span>Settings</span>
@endsection

@section('content')
    <div class="stack">
        @include('admin.partials.settings-tabs', ['active' => 'general'])

        {{-- Profile --}}
        <div class="card">
            <div class="card__head"><h2>Admin profile</h2></div>
            <form method="POST" action="{{ route('admin.settings.profile') }}">
                @csrf
                @method('PUT')
                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="name">Name <span class="req">*</span></label>
                            <input id="name" name="name" type="text" required maxlength="100"
                                   class="input @error('name') input--error @enderror"
                                   value="{{ old('name', $admin->name) }}">
                            <x-field-error name="name"/>
                        </div>
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="username">Username <span class="req">*</span></label>
                            <input id="username" name="username" type="text" required
                                   minlength="3" maxlength="50" autocomplete="username"
                                   class="input @error('username') input--error @enderror"
                                   value="{{ old('username', $admin->username) }}">
                            <x-field-error name="username"/>
                            <span class="hint">Letters, numbers, dot, underscore and hyphen.</span>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email">Email <span class="req">*</span></label>
                            <input id="email" name="email" type="email" required
                                   class="input @error('email') input--error @enderror"
                                   value="{{ old('email', $admin->email) }}">
                            <x-field-error name="email"/>
                            <span class="hint">You can sign in with either your username or this email.</span>
                        </div>
                    </div>
                </div>
                <div class="modal__foot" style="justify-content:flex-start;">
                    <button type="submit" class="btn btn--primary" data-busy="Saving...">Save profile</button>
                </div>
            </form>
        </div>

        {{-- Password --}}
        <div class="card">
            <div class="card__head"><h2>Change password</h2></div>
            <form method="POST" action="{{ route('admin.settings.password') }}">
                @csrf
                @method('PUT')
                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="current_password">Current password <span class="req">*</span></label>
                            <input id="current_password" name="current_password" type="password" required
                                   autocomplete="current-password"
                                   class="input @error('current_password') input--error @enderror">
                            <x-field-error name="current_password"/>
                        </div>
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="password">New password <span class="req">*</span></label>
                            <input id="password" name="password" type="password" required
                                   autocomplete="new-password"
                                   class="input @error('password') input--error @enderror">
                            <x-field-error name="password"/>
                            <span class="hint">At least 8 characters, with letters and numbers.</span>
                        </div>
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="password_confirmation">Confirm new password <span class="req">*</span></label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required
                                   autocomplete="new-password" class="input">
                        </div>
                    </div>
                </div>
                <div class="modal__foot" style="justify-content:flex-start;">
                    <button type="submit" class="btn btn--primary" data-busy="Updating...">Change password</button>
                </div>
            </form>
        </div>

        {{-- Preferences --}}
        <div class="card">
            <div class="card__head"><h2>Display preferences</h2></div>
            <form method="POST" action="{{ route('admin.settings.preferences') }}">
                @csrf
                @method('PUT')
                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency" class="select">
                                @foreach ($currencies as $code => $symbol)
                                    <option value="{{ $code }}" @selected(($settings['currency'] ?? 'INR') === $code)>
                                        {{ $code }} ({{ trim($symbol) }})
                                    </option>
                                @endforeach
                            </select>
                            <span class="hint">INR uses Indian grouping, e.g. {{ App\Support\Money::format('125500') }}.</span>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="date_format">Date format</label>
                            <select id="date_format" name="date_format" class="select">
                                @foreach ($dateFormats as $format => $example)
                                    <option value="{{ $format }}" @selected(($settings['date_format'] ?? 'd M Y') === $format)>
                                        {{ $example }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal__foot" style="justify-content:flex-start;">
                    <button type="submit" class="btn btn--primary" data-busy="Saving...">Save preferences</button>
                </div>
            </form>
        </div>

        {{-- Category shortcuts --}}
        <div class="card">
            <div class="card__head">
                <h2>Categories</h2>
                <a href="{{ route('admin.categories.index') }}" class="btn btn--sm">Manage all</a>
            </div>
            <div class="card__body">
                <p class="muted small" style="margin-top:0;">
                    Expense and credit categories are managed on their own screen, where you can
                    add, rename, activate or deactivate them.
                </p>
                <div class="btn-row">
                    <a href="{{ route('admin.categories.index', ['type' => 'expense']) }}" class="btn">Expense categories</a>
                    <a href="{{ route('admin.categories.index', ['type' => 'credit']) }}" class="btn">Credit categories</a>
                </div>
            </div>
        </div>
    </div>
@endsection
