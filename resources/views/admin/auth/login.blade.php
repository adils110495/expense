<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login &middot; {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v={{ $assetVersion }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ $assetVersion }}">
</head>
<body>
<div class="login">
    <div class="login__card">
        <div class="login__brand">
            <span class="mark">₹</span>
            <span>{{ config('app.name') }}</span>
        </div>
        <p class="sub">Sign in to the admin panel</p>

        @if ($errors->any())
            <div class="alert alert--error" style="margin-bottom:16px;">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert--warn" style="margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        {{-- Real submit: this page has no #page region, and a successful login
             needs a full document swap into the panel anyway. --}}
        {{-- The auth card is a fixed 410px column, so these span the full
             width. A col-md-3 here would be a 100px input. --}}
        <form method="POST" action="{{ route('admin.login.attempt') }}" data-no-ajax>
            @csrf

            <div class="row">
                <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <label for="login">Email or Username</label>
                    <input id="login" name="login" type="text" autocomplete="username"
                           class="input @error('login') input--error @enderror"
                           placeholder="superadmin"
                           value="{{ old('login') }}" required autofocus>
                    <x-field-error name="login"/>
                </div>

                <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           class="input @error('password') input--error @enderror" required>
                    <x-field-error name="password"/>
                </div>

                {{-- No field--check here: these columns are already full
                     width, so there is no neighbouring label to align to. --}}
                <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <label class="check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        Remember me
                    </label>
                </div>

                <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <button type="submit" class="btn btn--primary" style="width:100%" data-busy="Signing in...">
                        Sign in
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/admin.js') }}?v={{ $assetVersion }}"></script>
</body>
</html>
