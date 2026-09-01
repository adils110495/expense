{{--
    A who-pays-whom list with its actions.

    Used once per side - expenses and credit are settled as two separate
    lists, so the same pair of partners can have one payment open on each.
    $kind keeps them apart, both when recording and when looking up whether a
    suggested payment has already been written down.

    Every row opens a modal carrying the payment details: how it was paid, on
    what date, where, and the receipt.

    Expects: $transfers, $project, $openRecords, $kind. Optional: $empty.
--}}
@php
    use App\Services\SettlementEngine;
    use App\Support\Money;

    // Only records for this side count as "already recorded" here.
    $sideRecords = $openRecords->where('kind', $kind);
@endphp

@if (empty($transfers))
    <p class="hint" style="margin:0;">{{ $empty ?? 'Nothing to settle on this side.' }}</p>
@else
    <div class="settle-list">
        @foreach ($transfers as $transfer)
            @php
                $existing = $sideRecords
                    ->where('from_person_id', $transfer['from']->id)
                    ->where('to_person_id', $transfer['to']->id)
                    ->first();

                // Unique per side and per pair, so two modals never collide.
                $modalId = 'settle-'.$kind.'-'.$transfer['from']->id.'-'.$transfer['to']->id;
            @endphp

            <div class="settle">
                <div class="settle__flow">
                    <a href="{{ route('admin.people.show', $transfer['from']) }}" class="settle__who">
                        {{ $transfer['from']->name }}
                    </a>
                    <span class="settle__arrow" aria-label="pays">&rarr;</span>
                    <a href="{{ route('admin.people.show', $transfer['to']) }}" class="settle__who">
                        {{ $transfer['to']->name }}
                    </a>
                </div>

                <div class="settle__amount">
                    {{ Money::format(SettlementEngine::rupees($transfer['amount'])) }}
                </div>

                <div class="settle__action">
                    @if ($existing)
                        {{-- Already written down, so Record is gone. The chip
                             carries the status and is the way in to the
                             details; on a part payment it also says how much
                             has gone and how much is left. --}}
                        <button type="button"
                                class="badge badge--{{ $existing->status === 'partially_paid' ? 'expense' : 'muted' }} badge--button"
                                data-modal-open="{{ $modalId }}"
                                title="View and edit the payment details">
                            <span class="dot"></span>{{ $existing->status_label }}
                            @if ($existing->status === 'partially_paid')
                                &middot; {{ Money::format($existing->paid_amount) }} paid,
                                {{ Money::format($existing->outstanding) }} left
                            @endif
                            @if ($existing->attachments->isNotEmpty())
                                &middot; {{ $existing->attachments->count() }} file(s)
                            @endif
                        </button>

                        <form method="POST" action="{{ route('admin.settlements.paid', $existing) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn--sm btn--primary" data-busy="Saving...">
                                {{ $existing->status === 'partially_paid' ? 'Pay the rest' : 'Mark Paid' }}
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn--sm" data-modal-open="{{ $modalId }}">
                            Record
                        </button>

                        {{-- The one-click path, for a payment with no detail
                             worth capturing. --}}
                        <form method="POST" action="{{ route('admin.projects.settlement.store', $project) }}">
                            @csrf
                            <input type="hidden" name="from_person_id" value="{{ $transfer['from']->id }}">
                            <input type="hidden" name="to_person_id" value="{{ $transfer['to']->id }}">
                            <input type="hidden" name="amount" value="{{ SettlementEngine::rupees($transfer['amount']) }}">
                            <input type="hidden" name="kind" value="{{ $kind }}">
                            <input type="hidden" name="status" value="paid">
                            <button type="submit" class="btn btn--sm btn--primary" data-busy="Saving...">
                                Mark Paid
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Outside the list: a modal is a fixed overlay, and it has no business
         being a child of the grid that lays the rows out. --}}
    @foreach ($transfers as $transfer)
        @include('admin.partials.settle-modal', [
            'id' => 'settle-'.$kind.'-'.$transfer['from']->id.'-'.$transfer['to']->id,
            'transfer' => $transfer,
            'kind' => $kind,
            'project' => $project,
            'settlement' => $sideRecords
                ->where('from_person_id', $transfer['from']->id)
                ->where('to_person_id', $transfer['to']->id)
                ->first(),
        ])
    @endforeach
@endif
