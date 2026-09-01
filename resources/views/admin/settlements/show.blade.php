@extends('admin.layouts.app')

@php
    use App\Models\Settlement;
    use App\Support\Money;
@endphp

@section('title', 'Settlement details')
@section('heading', 'Settlement details')
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.settlements.index') }}">Settlements</a></span>
    <span>{{ $settlement->from?->name }} &rarr; {{ $settlement->to?->name }}</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>
                    <span class="badge badge--{{ $settlement->status === 'paid' ? 'on' : ($settlement->status === 'cancelled' ? 'off' : 'muted') }}">
                        <span class="dot"></span>{{ $settlement->status_label }}
                    </span>
                </h2>
                <div class="btn-row">
                    @if ($settlement->project)
                        <a href="{{ route('admin.projects.settlement', $settlement->project) }}" class="btn">
                            Back to settlement
                        </a>
                    @endif
                </div>
            </div>

            <div class="card__body">
                <dl class="dl">
                    <div class="dl__row">
                        <dt>From</dt>
                        <dd>
                            @if ($settlement->from)
                                <a href="{{ route('admin.people.show', $settlement->from) }}">{{ $settlement->from->name }}</a>
                            @else
                                --
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>To</dt>
                        <dd>
                            @if ($settlement->to)
                                <a href="{{ route('admin.people.show', $settlement->to) }}">{{ $settlement->to->name }}</a>
                            @else
                                --
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Amount</dt>
                        <dd style="font-size:1.2rem;font-weight:650;">{{ Money::format($settlement->amount) }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Paid so far</dt>
                        <dd>
                            {{ Money::format($settlement->paid_amount) }}
                            @if ((float) $settlement->outstanding > 0)
                                <span class="muted small">
                                    &middot; {{ Money::format($settlement->outstanding) }} outstanding
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Settles</dt>
                        <dd>
                            <span class="badge badge--{{ $settlement->kind === 'expense' ? 'expense' : ($settlement->kind === 'credit' ? 'credit' : 'muted') }}">
                                <span class="dot"></span>{{ $settlement->kind_label }}
                            </span>
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Reason</dt>
                        <dd>Equal project distribution</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Project</dt>
                        <dd>
                            @if ($settlement->project)
                                <a href="{{ route('admin.projects.show', $settlement->project) }}">{{ $settlement->project->name }}</a>
                                @if ($settlement->project->company)
                                    <span class="muted small">&middot; {{ $settlement->project->company->name }}</span>
                                @endif
                            @else
                                --
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Paid by</dt>
                        <dd>
                            {{ $settlement->payment_method
                                ? (Settlement::PAYMENT_METHODS[$settlement->payment_method] ?? $settlement->payment_method)
                                : '--' }}
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Settlement date</dt>
                        <dd>{{ optional($settlement->settled_on)->format($dateFormat) ?? '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Location</dt>
                        <dd>{{ $settlement->location ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Notes</dt>
                        <dd>{{ $settlement->notes ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Recorded by</dt>
                        <dd>
                            {{ $settlement->creator?->name ?? '--' }}
                            <span class="muted small">&middot; {{ $settlement->created_at->format($dateFormat.' H:i') }}</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Receipts &amp; proof of payment</h2>
                <span class="badge badge--muted">{{ $settlement->attachments->count() }} file(s)</span>
            </div>

            @if ($settlement->attachments->isEmpty())
                <x-empty-state
                    title="No files attached"
                    message="Attach a receipt from the project's settlement screen, on this payment's row."/>
            @else
                <div class="card__body">
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
                                    <span class="muted small">
                                        {{ $attachment->readable_size }} &middot;
                                        {{ $attachment->created_at->format('d M Y') }}
                                    </span>
                                </span>
                                <span class="actions">
                                    <a href="{{ route('admin.attachments.download', $attachment) }}"
                                       class="btn btn--sm" data-no-ajax>Download</a>
                                    <form method="POST"
                                          action="{{ route('admin.attachments.destroy', $attachment) }}"
                                          data-confirm="Remove &quot;{{ $attachment->original_name }}&quot;? This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--sm btn--danger"
                                                data-busy="Removing...">Remove</button>
                                    </form>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Update this settlement</h2>
            </div>

            <form method="POST" action="{{ route('admin.settlements.update', $settlement) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="amount">Amount <span class="req">*</span></label>
                            <div class="input-prefix">
                                <span class="sym">{{ Money::symbol() }}</span>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
                                       inputmode="decimal"
                                       class="input @error('amount') input--error @enderror"
                                       value="{{ old('amount', $settlement->amount) }}">
                            </div>
                            <x-field-error name="amount"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="status">Status <span class="req">*</span></label>
                            <select id="status" name="status" required
                                    class="select @error('status') select--error @enderror">
                                @foreach (Settlement::STATUSES as $value => $text)
                                    <option value="{{ $value }}" @selected(old('status', $settlement->status) === $value)>
                                        {{ $text }}
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="status"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="paid_amount">Paid amount</label>
                            <div class="input-prefix">
                                <span class="sym">{{ Money::symbol() }}</span>
                                <input id="paid_amount" name="paid_amount" type="number" step="0.01" min="0"
                                       inputmode="decimal"
                                       class="input @error('paid_amount') input--error @enderror"
                                       value="{{ old('paid_amount', $settlement->paid_amount) }}">
                            </div>
                            <x-field-error name="paid_amount"/>
                            <span class="hint">Only read for a partially paid settlement.</span>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="payment_method">How was it paid?</label>
                            <select id="payment_method" name="payment_method"
                                    class="select @error('payment_method') select--error @enderror">
                                <option value="">Select a method</option>
                                @foreach (Settlement::PAYMENT_METHODS as $value => $text)
                                    <option value="{{ $value }}"
                                            @selected(old('payment_method', $settlement->payment_method) === $value)>
                                        {{ $text }}
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="payment_method"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="settled_on">Settlement date</label>
                            <input id="settled_on" name="settled_on" type="date"
                                   max="{{ now()->toDateString() }}"
                                   class="input @error('settled_on') input--error @enderror"
                                   value="{{ old('settled_on', optional($settlement->settled_on)->format('Y-m-d')) }}">
                            <x-field-error name="settled_on"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="location">Location</label>
                            <input id="location" name="location" type="text" maxlength="150"
                                   class="input @error('location') input--error @enderror"
                                   placeholder="e.g. Andheri Office, Mumbai"
                                   value="{{ old('location', $settlement->location) }}">
                            <x-field-error name="location"/>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="attachments">Add receipts</label>
                            <input id="attachments" name="attachments[]" type="file" multiple
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                                   class="input input--file @error('attachments') input--error @enderror"
                                   data-file-preview>
                            <div class="attachments" data-file-preview-for="attachments" hidden></div>
                            <x-field-error name="attachments"/>
                            @error('attachments.*')
                                <span class="error">{{ $message }}</span>
                            @enderror
                            <span class="hint">
                                JPG, PNG, WEBP, GIF or PDF. Up to 10 files, 5 MB each.
                                Existing files are listed above.
                            </span>
                        </div>

                        <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="2000"
                                      class="textarea @error('notes') textarea--error @enderror"
                                      placeholder="Reference number, method of payment, anything worth remembering">{{ old('notes', $settlement->notes) }}</textarea>
                            <x-field-error name="notes"/>
                        </div>
                    </div>
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving...">Update settlement</button>
                        @if ($settlement->project)
                            <a href="{{ route('admin.projects.settlement', $settlement->project) }}" class="btn">Cancel</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
