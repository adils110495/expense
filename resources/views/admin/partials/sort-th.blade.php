{{-- Sortable column header. Toggles direction and preserves active filters. --}}
@php
    $isActive = ($sort ?? null) === $column;
    $next = $isActive && ($direction ?? 'desc') === 'asc' ? 'desc' : 'asc';
    $num = $num ?? false;
@endphp

<th class="sortable @if ($num) num @endif">
    <a href="{{ $url }}?{{ http_build_query(array_merge($carry ?? [], ['sort' => $column, 'direction' => $next])) }}">
        {{ $text }}
        @if ($isActive)
            <span class="arrow" aria-hidden="true">{!! ($direction ?? "desc") === "asc" ? "&uarr;" : "&darr;" !!}</span>
            <span class="sr-only">sorted {{ $direction ?? 'desc' }}ending</span>
        @endif
    </a>
</th>
