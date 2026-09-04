<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In &middot; {{ config('app.name') }}</title>
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
        <p class="sub">Sign in to your companies</p>

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
        <form method="POST" action="{{ route('user.login.attempt') }}" data-no-ajax>
            @csrf

            <div class="row">
                <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="username"
                           class="input @error('email') input--error @enderror"
                           placeholder="you@example.com"
                           value="{{ old('email') }}" required autofocus>
                    <x-field-error name="email"/>
                </div>

                <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           class="input @error('password') input--error @enderror" required>
                    <x-field-error name="password"/>
                </div>

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

        {{-- The two doors cross-link, so someone who bookmarked the wrong one
             is never stuck guessing which sign-in is theirs. --}}
        <p class="sub" style="margin-top:14px;text-align:center;">
            Administrator? <a href="{{ route('admin.login') }}">Sign in here</a>.
        </p>
    </div>
</div>

<script src="{{ asset('js/admin.js') }}?v={{ $assetVersion }}"></script>
</body>
</html>
