{{--
    Settings sub-navigation. Expects: $active.
--}}
@php
    $tabs = [
        'general' => ['label' => 'General', 'url' => route('admin.settings.index')],
        'whatsapp' => ['label' => 'WhatsApp', 'url' => route('admin.settings.whatsapp')],
        'email' => ['label' => 'Email', 'url' => route('admin.settings.email')],
        'templates' => ['label' => 'Notification Templates', 'url' => route('admin.settings.templates')],
    ];
@endphp

<nav class="tabs" aria-label="Settings sections">
    @foreach ($tabs as $key => $tab)
        <a href="{{ $tab['url'] }}"
           class="tab @if ($key === $active) tab--active @endif"
           @if ($key === $active) aria-current="page" @endif>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
