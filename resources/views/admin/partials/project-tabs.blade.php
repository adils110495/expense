{{--
    Sub-navigation for one project. Expenses, Credits and Transactions point
    at the existing modules pre-filtered to this project, so there is one list
    implementation rather than a second copy living under the project.

    Expects: $project, $active.
--}}
@php
    $branch = ['company_id' => $project->company_id, 'project_id' => $project->id];

    $tabs = [
        'overview' => ['label' => 'Overview', 'url' => route('admin.projects.show', $project)],
        'partners' => ['label' => 'Partners', 'url' => route('admin.projects.show', $project).'#partners'],
        'expenses' => ['label' => 'Expenses', 'url' => route('admin.expenses.index', $branch)],
        'credits' => ['label' => 'Credits / Income', 'url' => route('admin.credits.index', $branch)],
        'transactions' => ['label' => 'Transactions', 'url' => route('admin.transactions.index', $branch)],
        'settlement' => ['label' => 'Settlement', 'url' => route('admin.projects.settlement', $project)],
        'reports' => ['label' => 'Reports', 'url' => route('admin.reports.index', $branch + ['range' => 'all'])],
    ];
@endphp

<nav class="tabs" aria-label="Project sections">
    @foreach ($tabs as $key => $tab)
        <a href="{{ $tab['url'] }}"
           class="tab @if ($key === $active) tab--active @endif"
           @if ($key === $active) aria-current="page" @endif>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
