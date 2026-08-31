@props([
    'label',
    'value',
    'meta' => null,
    'variant' => null,
])

<div {{ $attributes->merge(['class' => 'card stat '.($variant ? 'stat--'.$variant : '')]) }}>
    <div class="stat__label">{{ $label }}</div>
    <div class="stat__value">{{ $value }}</div>
    @if ($meta)
        <div class="stat__meta">{{ $meta }}</div>
    @endif
</div>
