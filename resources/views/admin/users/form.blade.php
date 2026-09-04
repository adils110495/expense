@extends('admin.layouts.app')

@php
    $editing = $user->exists;
    $heading = ($editing ? 'Edit ' : 'Add ').'User';
    $action = $editing
        ? route('admin.users.update', $user)
        : route('admin.users.store');

    $checked = old('companies', $mapped);
@endphp

@section('title', $heading)
@section('heading', $heading)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.users.index') }}">Users</a></span>
    <span>{{ $editing ? 'Edit' : 'Add' }}</span>
@endsection

@section('content')
    <div class="stack">
        @if ($companies->isEmpty())
            <div class="alert alert--warn">
                There are no active companies yet.
                <a href="{{ route('admin.companies.create') }}">Add a company</a> first -
                a user with no company mapped can sign in but will see nothing.
            </div>
        @endif

        <div class="card">
            <div class="card__head">
                <h2>User details</h2>
                <a href="{{ route('admin.users.index') }}" class="btn btn--sm">Back to list</a>
            </div>

            {{-- Named, because the company checkboxes and the Save button sit
                 in the next card and associate back to it with form="". One
                 submit then writes the user and the mapping together. --}}
            <form method="POST" action="{{ $action }}" id="user-form">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="name">Name <span class="req">*</span></label>
                            <input id="name" name="name" type="text" maxlength="100" required
                                   class="input @error('name') input--error @enderror"
                                   placeholder="Nazim"
                                   value="{{ old('name', $user->name) }}">
                            <x-field-error name="name"/>
                        </div>

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="email">Email <span class="req">*</span></label>
                            <input id="email" name="email" type="email" maxlength="255" required
                                   autocomplete="off"
                                   class="input @error('email') input--error @enderror"
                                   placeholder="nazim@example.com"
                                   value="{{ old('email', $user->email) }}">
                            <x-field-error name="email"/>
                            <span class="hint">This is what they sign in with.</span>
                        </div>

                        <div class="field field--check col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <input type="hidden" name="status" value="0">
                            <label class="check">
                                <input type="checkbox" name="status" value="1"
                                       @checked(old('status', $user->status ?? true))>
                                Active (can sign in)
                            </label>
                            <x-field-error name="status"/>
                            <span class="hint">
                                Deactivating refuses the login outright, password or not.
                            </span>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="password">
                                Password
                                @if ($editing)
                                    <span class="muted small">(leave blank to keep the current one)</span>
                                @else
                                    <span class="req">*</span>
                                @endif
                            </label>
                            <input id="password" name="password" type="password"
                                   autocomplete="new-password"
                                   class="input @error('password') input--error @enderror"
                                   @required(! $editing)>
                            <x-field-error name="password"/>
                            <span class="hint">At least 8 characters, with letters and numbers.</span>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="password_confirmation">Confirm Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   autocomplete="new-password" class="input"
                                   @required(! $editing)>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Company mapping is part of the same form as the details above; the
             card is separate only so the boundary it draws is impossible to
             miss on the screen that hands it out. --}}
        <div class="card">
            <div class="card__head">
                <h2>Company access</h2>
                <span class="muted small">
                    This user will see only the companies ticked here - and every
                    project, person, transaction and settlement underneath them.
                </span>
            </div>

            <div class="card__body">
                @if ($companies->isEmpty())
                    <p class="muted">No companies to map yet.</p>
                @else
                    <div class="row">
                        @foreach ($companies as $company)
                            <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                                <label class="check">
                                    {{-- form= ties these back to the details
                                         form above, so one Save writes both the
                                         user and the mapping. --}}
                                    <input type="checkbox" name="companies[]" value="{{ $company->id }}"
                                           form="user-form"
                                           @checked(in_array($company->id, (array) $checked))>
                                    {{ $company->name }}@unless ($company->status)
                                        <span class="muted small">&middot; inactive</span>
                                    @endunless
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <x-field-error name="companies"/>
                @endif
            </div>

            <div class="card__body" style="border-top:1px solid var(--border);">
                <div class="btn-row">
                    <button type="submit" form="user-form" class="btn btn--primary" data-busy="Saving...">
                        {{ $editing ? 'Update' : 'Save' }} User
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn">Cancel</a>
                </div>
            </div>
        </div>

        @if ($editing)
            <div class="card">
                <div class="card__head">
                    <h2>Reset password</h2>
                    <span class="muted small">
                        There is no self-service reset for users, so this is how
                        someone locked out gets back in.
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.users.password', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="card__body">
                        <div class="row">
                            <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                                <label for="new_password">New password <span class="req">*</span></label>
                                <input id="new_password" name="password" type="password" required
                                       autocomplete="new-password" class="input">
                            </div>

                            <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                                <label for="new_password_confirmation">Confirm <span class="req">*</span></label>
                                <input id="new_password_confirmation" name="password_confirmation"
                                       type="password" required autocomplete="new-password" class="input">
                            </div>

                            <div class="field field--actions col-md-4 col-lg-4 col-sm-12 col-xs-12">
                                <button type="submit" class="btn btn--danger" data-busy="Resetting...">
                                    Reset password
                                </button>
                            </div>
                        </div>
                        <span class="hint">
                            Any "remember me" session this user has on another device stops working.
                        </span>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
