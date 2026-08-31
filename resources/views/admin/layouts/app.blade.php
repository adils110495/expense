<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v={{ $assetVersion }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ $assetVersion }}">
</head>
<body>
<div class="layout">
    @include('admin.partials.sidebar')

    <div class="scrim" data-nav-close></div>

    {{-- Outside #page so the swap cannot destroy it mid-animation. --}}
    <div id="nav-progress" aria-hidden="true"></div>

    {{-- #page is the region the AJAX layer swaps on navigation. --}}
    <div class="main" id="page">
        <header class="topbar">
            <button type="button" class="hamburger" data-nav-toggle aria-expanded="false" aria-label="Toggle navigation">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="topbar__title">
                <h1>@yield('heading', 'Dashboard')</h1>
                @hasSection('breadcrumbs')
                    <nav class="breadcrumbs" aria-label="Breadcrumb">@yield('breadcrumbs')</nav>
                @endif
            </div>

            <span class="avatar" title="{{ auth('admin')->user()->name }}">
                {{ Str::of(auth('admin')->user()->name)->substr(0, 1)->upper() }}
            </span>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>
</div>

@include('admin.partials.toasts')

<script src="{{ asset('js/admin.js') }}?v={{ $assetVersion }}"></script>
@stack('scripts')
</body>
</html>
