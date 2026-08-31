@props([
    'title' => 'Nothing here yet',
    'message' => null,
    'action' => null,
    'actionLabel' => 'Add new',
])

<div class="empty">
    <div class="empty__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7h16v12H4zM4 7l2-3h12l2 3M9 12h6"/>
        </svg>
    </div>
    <h3>{{ $title }}</h3>
    @if ($message)
        <p>{{ $message }}</p>
    @endif
    @if ($action)
        <a href="{{ $action }}" class="btn btn--primary">{{ $actionLabel }}</a>
    @endif
</div>
