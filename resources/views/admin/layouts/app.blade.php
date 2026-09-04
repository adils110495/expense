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

            @php
                use App\Support\CompanyAccess;

                // Either guard - an admin or a panel user is signing this page.
                $actor = CompanyAccess::actor();
                $selectableCompanies = CompanyAccess::selectable();
                $selectedCompanyId = CompanyAccess::selectedId();
            @endphp

            {{-- The company selector. Shown only when there is a real choice
                 to make: someone mapped to a single company has nothing to
                 switch between, and an empty dropdown would only puzzle them.
                 It is a view filter, never a grant - the server re-checks the
                 stored id against the live mapping on every read. --}}
            @if ($selectableCompanies->count() > 1)
                <form method="POST" action="{{ route('admin.company-scope') }}" class="topbar__scope">
                    @csrf
                    <label for="company-scope" class="topbar__scope-label">Company</label>
                    <select id="company-scope" name="company_id" class="select select--sm" data-auto-submit>
                        <option value="">All companies</option>
                        @foreach ($selectableCompanies as $company)
                            <option value="{{ $company->id }}" @selected($selectedCompanyId === $company->id)>
                                {{ $company->name }}@unless ($company->status) (inactive)@endunless
                            </option>
                        @endforeach
                    </select>
                </form>
            @elseif ($selectableCompanies->count() === 1)
                {{-- Nothing to choose, but saying which company you are in
                     still beats an unlabelled screen. --}}
                <span class="topbar__scope-single">{{ $selectableCompanies->first()->name }}</span>
            @endif

            <span class="avatar" title="{{ $actor?->name }}{{ CompanyAccess::isAdmin() ? ' (admin)' : '' }}">
                {{ Str::of($actor?->name ?? '?')->substr(0, 1)->upper() }}
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
