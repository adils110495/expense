{{--
    Shared date-range control. $range is a DateRange; $action is the form target.
    Extra hidden inputs (search terms, category filters) are passed via $keep.
--}}
@php
    use App\Support\DateRange;
    $keep = $keep ?? [];
@endphp

<form method="GET" action="{{ $action }}" class="row">
    @foreach ($keep as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
        <label for="range-{{ $id ?? 'default' }}">Period</label>
        <select id="range-{{ $id ?? 'default' }}" name="range" class="select" data-range-select>
            @foreach (DateRange::PRESETS as $value => $label)
                <option value="{{ $value }}" @selected($range->preset === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12" data-range-custom>
        <label for="from-{{ $id ?? 'default' }}">From</label>
        <input id="from-{{ $id ?? 'default' }}" type="date" name="from" class="input" value="{{ $range->from }}">
    </div>

    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12" data-range-custom>
        <label for="to-{{ $id ?? 'default' }}">To</label>
        <input id="to-{{ $id ?? 'default' }}" type="date" name="to" class="input" value="{{ $range->to }}">
    </div>

    <div class="field field--actions col-md-3 col-lg-3 col-sm-12 col-xs-12">
        <button type="submit" class="btn btn--primary">Apply</button>
        <a href="{{ $action }}" class="btn btn--secondary">Reset</a>
    </div>
</form>
