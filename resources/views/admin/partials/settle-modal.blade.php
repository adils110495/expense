{{--
    Payment details for one settlement.

    Rendered per row rather than as one shared modal, because a recorded
    payment carries its own receipts and those cannot be filled into a shared
    form by data attributes.

    Two modes, same fields:
      - no $settlement: recording a suggested payment for the first time
      - $settlement:    editing the payment already written down

    Expects: $id, $transfer, $kind, $project. Optional: $settlement.
--}}
@php
    use App\Models\Settlement;
    use App\Services\SettlementEngine;
    use App\Support\Money;

    $settlement = $settlement ?? null;
    $editing = $settlement !== null;

    $action = $editing
        ? route('admin.settlements.update', $settlement)
        : route('admin.projects.settlement.store', $project);

    $amount = $editing
        ? $settlement->amount
        : SettlementEngine::rupees($transfer['amount']);
@endphp

<div class="modal" id="{{ $id }}" hidden role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="modal__panel">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="modal__head">
                <h3 id="{{ $id }}-title">
                    {{ $editing ? 'Payment details' : 'Record payment' }}:
                    {{ $transfer['from']->name }} &rarr; {{ $transfer['to']->name }}
                </h3>
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="modal__body">
                @if ($editing && (float) $settlement->paid_amount > 0)
                    {{-- Where this payment has got to. Shown for a part payment
                         especially, where the row above is already down to what
                         is left and the original figure would otherwise be
                         nowhere on screen. --}}
                    <div class="pay-progress">
                        <span>
                            <span class="k">Agreed</span>
                            <span class="v">{{ Money::format($settlement->amount) }}</span>
                        </span>
                        <span>
                            <span class="k">Paid so far</span>
                            <span class="v amount--credit">{{ Money::format($settlement->paid_amount) }}</span>
                        </span>
                        <span>
                            <span class="k">Still to pay</span>
                            <span class="v amount--expense">{{ Money::format($settlement->outstanding) }}</span>
                        </span>
                    </div>
                @endif

                @unless ($editing)
                    {{-- Fixed by the plan, not chosen here. --}}
                    <input type="hidden" name="from_person_id" value="{{ $transfer['from']->id }}">
                    <input type="hidden" name="to_person_id" value="{{ $transfer['to']->id }}">
                    <input type="hidden" name="kind" value="{{ $kind }}">
                @endunless

                <div class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-amount">Amount <span class="req">*</span></label>
                        <div class="input-prefix">
                            <span class="sym">{{ Money::symbol() }}</span>
                            <input id="{{ $id }}-amount" name="amount" type="number" step="0.01" min="0.01"
                                   required inputmode="decimal" class="input" value="{{ $amount }}">
                        </div>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-status">Status <span class="req">*</span></label>
                        <select id="{{ $id }}-status" name="status" class="select" required>
                            @foreach (Settlement::STATUSES as $value => $text)
                                <option value="{{ $value }}"
                                        @selected(($settlement->status ?? 'paid') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-paid">Paid amount</label>
                        <div class="input-prefix">
                            <span class="sym">{{ Money::symbol() }}</span>
                            {{-- Capped at the agreed amount in the browser as
                                 well as on the server: paying more than the
                                 transfer is worth would invent a debt the
                                 other way. --}}
                            <input id="{{ $id }}-paid" name="paid_amount" type="number" step="0.01" min="0"
                                   max="{{ $amount }}" inputmode="decimal" class="input"
                                   value="{{ $settlement->paid_amount ?? '' }}">
                        </div>
                        <span class="hint">
                            Only read for a partially paid settlement, and never more
                            than {{ Money::format($amount) }}.
                        </span>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-method">How was it paid?</label>
                        <select id="{{ $id }}-method" name="payment_method" class="select">
                            <option value="">Select a method</option>
                            @foreach (Settlement::PAYMENT_METHODS as $value => $text)
                                <option value="{{ $value }}"
                                        @selected(($settlement->payment_method ?? '') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-date">Date</label>
                        <input id="{{ $id }}-date" name="settled_on" type="date" class="input"
                               max="{{ now()->toDateString() }}"
                               value="{{ optional($settlement?->settled_on)->format('Y-m-d') ?? now()->toDateString() }}">
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-location">Location</label>
                        <input id="{{ $id }}-location" name="location" type="text" maxlength="150" class="input"
                               placeholder="e.g. Andheri Office, Mumbai"
                               value="{{ $settlement->location ?? '' }}">
                    </div>

                    <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-notes">Notes</label>
                        <textarea id="{{ $id }}-notes" name="notes" rows="2" maxlength="2000" class="textarea"
                                  placeholder="Reference number, UPI id, anything worth remembering">{{ $settlement->notes ?? '' }}</textarea>
                    </div>

                    <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                        <label for="{{ $id }}-files">Receipt / proof of payment</label>

                        @if ($editing && $settlement->attachments->isNotEmpty())
                            <div class="attachments">
                                @foreach ($settlement->attachments as $attachment)
                                    <div class="attachment">
                                        <a class="attachment__thumb"
                                           href="{{ route('admin.attachments.show', $attachment) }}"
                                           target="_blank" rel="noopener" data-no-ajax
                                           title="Open {{ $attachment->original_name }}">
                                            @if ($attachment->isImage())
                                                <img src="{{ route('admin.attachments.show', $attachment) }}"
                                                     alt="{{ $attachment->original_name }}" loading="lazy">
                                            @else
                                                <span aria-hidden="true">PDF</span>
                                            @endif
                                        </a>
                                        <span class="attachment__meta">
                                            <a href="{{ route('admin.attachments.show', $attachment) }}"
                                               target="_blank" rel="noopener" data-no-ajax>
                                                {{ $attachment->original_name }}
                                            </a>
                                            <span class="muted small">{{ $attachment->readable_size }}</span>
                                        </span>
                                        <label class="attachment__remove check">
                                            <input type="checkbox" name="remove_attachments[]"
                                                   value="{{ $attachment->id }}">
                                            Remove
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <input id="{{ $id }}-files" name="attachments[]" type="file" multiple
                               accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                               class="input input--file"
                               data-file-preview>
                        <div class="attachments" data-file-preview-for="{{ $id }}-files" hidden></div>
                        <span class="hint">
                            JPG, PNG, WEBP, GIF or PDF. Up to 10 files, 5 MB each.
                            @if ($editing) Existing files stay unless you tick Remove. @endif
                        </span>
                    </div>
                </div>
            </div>

            <div class="modal__foot">
                <button type="button" class="btn" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn--primary" data-busy="Saving...">
                    {{ $editing ? 'Save payment details' : 'Record payment' }}
                </button>
            </div>
        </form>
    </div>
</div>
