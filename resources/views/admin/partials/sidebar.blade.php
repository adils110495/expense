@php
    // Each item names its own section, so adding one never means recounting
    // indexes to keep the group headings in the right place.
    $nav = [
        ['group' => 'Overview', 'route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin/dashboard',
         'icon' => 'M3 12h6v9H3zM10 3h4v18h-4zM15 8h6v13h-6z'],
        ['group' => 'Overview', 'route' => 'admin.hierarchy.index', 'label' => 'Hierarchy', 'pattern' => 'admin/hierarchy*',
         'icon' => 'M12 3v4M5 21v-4M19 21v-4M5 17h14M12 7v4M5 11h14v6M9 11V7h6v4'],

        ['group' => 'Structure', 'route' => 'admin.companies.index', 'label' => 'Companies', 'pattern' => 'admin/companies*',
         'icon' => 'M4 21V6l7-3v18M11 21h9V10l-9-3M14 12h3M14 16h3M7 10h1M7 14h1'],
        ['group' => 'Structure', 'route' => 'admin.projects.index', 'label' => 'Projects', 'pattern' => 'admin/projects*',
         'icon' => 'M3 7a2 2 0 012-2h4l2 2h7a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z'],
        ['group' => 'Structure', 'route' => 'admin.people.index', 'label' => 'People', 'pattern' => 'admin/people*',
         'icon' => 'M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 10a4 4 0 100-8 4 4 0 000 8zM22 20v-2a4 4 0 00-3-3.9M16 2.1a4 4 0 010 7.8'],

        ['group' => 'Money', 'route' => 'admin.expenses.index', 'label' => 'Expenses', 'pattern' => 'admin/expenses*',
         'icon' => 'M12 5v14M19 12l-7 7-7-7'],
        ['group' => 'Money', 'route' => 'admin.credits.index', 'label' => 'Credits / Income', 'pattern' => 'admin/credits*',
         'icon' => 'M12 19V5M5 12l7-7 7 7'],
        ['group' => 'Money', 'route' => 'admin.transactions.index', 'label' => 'Transactions', 'pattern' => 'admin/transactions*',
         'icon' => 'M4 7h16M4 7l4-4M4 7l4 4M20 17H4M20 17l-4-4M20 17l-4 4'],
        ['group' => 'Money', 'route' => 'admin.settlements.index', 'label' => 'Settlements', 'pattern' => 'admin/settlements*',
         'icon' => 'M12 3v18M8 7h6a3 3 0 010 6H9a3 3 0 000 6h7'],

        ['group' => 'Manage', 'route' => 'admin.categories.index', 'label' => 'Categories', 'pattern' => 'admin/categories*',
         'icon' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
        ['group' => 'Manage', 'route' => 'admin.payment-bys.index', 'label' => 'Payment By', 'pattern' => 'admin/payment-bys*',
         'icon' => 'M3 7h18v10H3zM3 11h18M7 15h4'],
        ['group' => 'Manage', 'route' => 'admin.reports.index', 'label' => 'Reports', 'pattern' => 'admin/reports*',
         'icon' => 'M6 20V10M12 20V4M18 20v-6'],
        ['group' => 'Manage', 'route' => 'admin.activity.index', 'label' => 'User Activity', 'pattern' => 'admin/activity*',
         'icon' => 'M12 8v4l3 3M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5'],
        ['group' => 'Manage', 'route' => 'admin.settings.index', 'label' => 'Settings', 'pattern' => 'admin/settings*',
         'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1l2-1.6-2-3.4-2.4 1a7 7 0 00-1.7-1L14.5 3h-4l-.3 2.6a7 7 0 00-1.7 1l-2.4-1-2 3.4 2 1.6a7 7 0 000 2l-2 1.6 2 3.4 2.4-1a7 7 0 001.7 1l.3 2.6h4l.3-2.6a7 7 0 001.7-1l2.4 1 2-3.4-2-1.6c.07-.33.1-.66.1-1z'],
    ];
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <span class="mark">₹</span>
        <span>{{ config('app.name') }}</span>

        {{-- Only rendered as visible on tablet/mobile, where the sidebar is a
             drawer. Hidden on desktop so the brand area is unchanged. --}}
        <button type="button" class="sidebar__close" data-nav-close aria-label="Close menu">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </div>

    <nav class="sidebar__nav" aria-label="Main navigation">
        @php $group = null; @endphp

        @foreach ($nav as $item)
            @if ($item['group'] !== $group)
                @php $group = $item['group']; @endphp
                <div class="sidebar__label">{{ $group }}</div>
            @endif

            <a href="{{ route($item['route']) }}"
               class="navlink @if (request()->is($item['pattern'])) navlink--active @endif"
               data-nav-close>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach

        <div class="sidebar__label">Session</div>

        {{-- Logout lands on the login screen, which has no #page region to
             swap into - let it be a real navigation. --}}
        <form method="POST" action="{{ route('admin.logout') }}" data-no-ajax>
            @csrf
            <button type="submit" class="navlink" style="width:100%;background:none;border:0;cursor:pointer;font:inherit;text-align:left;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 17l5-5-5-5M21 12H9M12 19H5a2 2 0 01-2-2V7a2 2 0 012-2h7"/>
                </svg>
                Logout
            </button>
        </form>
    </nav>
</aside>
