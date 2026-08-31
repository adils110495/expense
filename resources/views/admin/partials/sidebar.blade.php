@php
    $nav = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin/dashboard',
         'icon' => 'M3 12h6v9H3zM10 3h4v18h-4zM15 8h6v13h-6z'],
        ['route' => 'admin.expenses.index', 'label' => 'Expenses', 'pattern' => 'admin/expenses*',
         'icon' => 'M12 5v14M19 12l-7 7-7-7'],
        ['route' => 'admin.credits.index', 'label' => 'Credits / Income', 'pattern' => 'admin/credits*',
         'icon' => 'M12 19V5M5 12l7-7 7 7'],
        ['route' => 'admin.transactions.index', 'label' => 'Transactions', 'pattern' => 'admin/transactions*',
         'icon' => 'M4 7h16M4 7l4-4M4 7l4 4M20 17H4M20 17l-4-4M20 17l-4 4'],
        ['route' => 'admin.categories.index', 'label' => 'Categories', 'pattern' => 'admin/categories*',
         'icon' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
        ['route' => 'admin.payment-bys.index', 'label' => 'Payment By', 'pattern' => 'admin/payment-bys*',
         'icon' => 'M3 7h18v10H3zM3 11h18M7 15h4'],
        ['route' => 'admin.reports.index', 'label' => 'Reports', 'pattern' => 'admin/reports*',
         'icon' => 'M6 20V10M12 20V4M18 20v-6'],
        ['route' => 'admin.settings.index', 'label' => 'Settings', 'pattern' => 'admin/settings*',
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
        <div class="sidebar__label">Overview</div>

        @foreach ($nav as $item)
            @if ($loop->index === 1)
                <div class="sidebar__label">Money</div>
            @elseif ($loop->index === 4)
                <div class="sidebar__label">Manage</div>
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
