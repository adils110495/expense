{{--
    The Company -> Project -> Person tree.

    Every node carries its own Credit / Expense / Balance, summed from the
    transactions for the active period - so the same branch shows different
    figures under a different date filter, and nothing has to be recalculated
    when a transaction is added, moved or deleted.

    Expects: $tree (from HierarchyReport::tree()). Optional: $id for the
    Expand all / Collapse all buttons to target.
--}}
@php
    $id = $id ?? 'hierarchy-tree';
@endphp

<ul class="tree" id="{{ $id }}">
    @foreach ($tree as $company)
        <li class="tree__node tree__node--company">
            <div class="tree__row">
                @if ($company['projects'])
                    <button type="button" class="tree__toggle" data-tree-toggle aria-expanded="true"
                            aria-label="Toggle {{ $company['name'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                @else
                    <span class="tree__toggle tree__toggle--leaf" aria-hidden="true">&bull;</span>
                @endif

                <span class="tree__label">
                    <span class="tree__name">
                        <a href="{{ route('admin.companies.show', $company['id']) }}">{{ $company['name'] }}</a>
                        @unless ($company['status'])
                            <span class="badge badge--off"><span class="dot"></span>Inactive</span>
                        @endunless
                    </span>
                    <span class="tree__meta">
                        {{ count($company['projects']) }} project(s) &middot;
                        {{ $company['totals']['count'] }} transaction(s)
                    </span>
                </span>

                <x-tree-money :totals="$company['totals']"/>
            </div>

            @if ($company['projects'])
                <ul class="tree__children">
                    @foreach ($company['projects'] as $project)
                        <li class="tree__node tree__node--project">
                            <div class="tree__row">
                                @if ($project['people'])
                                    <button type="button" class="tree__toggle" data-tree-toggle aria-expanded="true"
                                            aria-label="Toggle {{ $project['name'] }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M6 9l6 6 6-6"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="tree__toggle tree__toggle--leaf" aria-hidden="true">&bull;</span>
                                @endif

                                <span class="tree__label">
                                    <span class="tree__name">
                                        <a href="{{ route('admin.projects.show', $project['id']) }}">{{ $project['name'] }}</a>
                                        @unless ($project['status'])
                                            <span class="badge badge--off"><span class="dot"></span>Inactive</span>
                                        @endunless
                                    </span>
                                    <span class="tree__meta">
                                        {{ count($project['people']) }} person(s) &middot;
                                        {{ $project['totals']['count'] }} transaction(s)
                                    </span>
                                </span>

                                <x-tree-money :totals="$project['totals']"/>
                            </div>

                            @if ($project['people'])
                                <ul class="tree__children">
                                    @foreach ($project['people'] as $person)
                                        <li class="tree__node tree__node--person">
                                            <div class="tree__row">
                                                <span class="tree__toggle tree__toggle--leaf" aria-hidden="true">&bull;</span>

                                                <span class="tree__label">
                                                    <span class="tree__name">
                                                        <a href="{{ route('admin.people.show', $person['id']) }}">{{ $person['name'] }}</a>
                                                        @unless ($person['assigned'])
                                                            {{-- Money booked here before they were taken off
                                                                 the project. Shown rather than hidden, so it
                                                                 still adds up to the project total. --}}
                                                            <span class="badge badge--muted" title="No longer assigned to this project">
                                                                <span class="dot"></span>Unassigned
                                                            </span>
                                                        @endunless
                                                    </span>
                                                    <span class="tree__meta">
                                                        {{ $person['designation'] ?: 'No designation' }} &middot;
                                                        {{ $person['totals']['count'] }} transaction(s)
                                                    </span>
                                                </span>

                                                <x-tree-money :totals="$person['totals']"/>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="tree__children">
                                    <p class="tree__empty">
                                        Nobody is assigned to this project yet.
                                        <a href="{{ route('admin.projects.show', $project['id']) }}">Assign people</a>.
                                    </p>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="tree__children">
                    <p class="tree__empty">
                        No projects yet.
                        <a href="{{ route('admin.projects.create', ['company_id' => $company['id']]) }}">Add the first one</a>.
                    </p>
                </div>
            @endif
        </li>
    @endforeach
</ul>
