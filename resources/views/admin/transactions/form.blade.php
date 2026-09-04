@extends('admin.layouts.app')

@php
    use App\Models\Transaction;
    use App\Support\Money;

    $editing = $record->exists;
    $heading = $editing ? 'Edit Transaction' : 'Add Transaction';
    $action = $editing
        ? route('admin.transactions.update', $record)
        : route('admin.transactions.store');
@endphp

@section('title', $heading)
@section('heading', $heading)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.transactions.index') }}">Transactions</a></span>
    <span>{{ $editing ? 'Edit' : 'Add' }}</span>
@endsection

@section('content')
    <div class="stack">
        {{-- Only warns about the type currently chosen. Switching type refills
             the dropdown over AJAX, and the JS shows this same warning then if
             the other type has nothing active either. --}}
        <div class="alert alert--warn" data-tx-no-categories @if ($categories->isNotEmpty()) hidden @endif>
            There are no active <span data-tx-type-word>{{ $type }}</span> categories yet.
            @admin <a href="{{ route('admin.categories.index', ['type' => $type]) }}"
                    data-tx-category-link="{{ route('admin.categories.index') }}">Add one first</a> @endadmin
            so this transaction can be classified.
        </div>

        @if ($companies->isEmpty())
            <div class="alert alert--warn">
                @admin
                    There are no active companies yet.
                    <a href="{{ route('admin.companies.create') }}">Add a company</a>, give it a project,
                    and assign people to that project - every transaction has to belong to
                    a company, a project and a person.
                @else
                    You are not mapped to any company yet, so there is nothing to record a
                    transaction against. Ask an administrator to give you access.
                @endadmin
            </div>
        @endif

        <div class="card">
            <div class="card__head">
                <h2>Transaction details</h2>
                <a href="{{ route('admin.transactions.index') }}" class="btn btn--sm">Back to list</a>
            </div>

            <form method="POST" action="{{ $action }}" enctype="multipart/form-data"
                  data-tx-form data-tx-categories-url="{{ route('admin.transactions.categories') }}">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card__body">
                    <div class="row">
                        {{-- Type comes first: it decides which categories the
                             next-but-one dropdown may offer, and the wording
                             of a couple of the fields below. --}}
                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="type">Type <span class="req">*</span></label>
                            <select id="type" name="type" required
                                    class="select @error('type') select--error @enderror"
                                    data-tx-type>
                                @foreach (Transaction::TYPES as $value)
                                    <option value="{{ $value }}" @selected($type === $value)>
                                        {{ ucfirst($value) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="type"/>
                            <span class="hint">
                                Expense is money going out, Credit is money coming in.
                            </span>
                        </div>

                        {{-- Company -> Project -> Person: the rest of the form
                             describes money that has to belong somewhere, and
                             each dropdown narrows the next. --}}
                        @include('admin.partials.hierarchy-fields', [
                            'group' => 'tx-form',
                            'record' => $record,
                        ])

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="title">Title <span class="req">*</span></label>
                            <input id="title" name="title" type="text" maxlength="150" required
                                   class="input @error('title') input--error @enderror"
                                   placeholder="{{ $type === 'expense' ? 'Office Rent' : 'Client Payment' }}"
                                   data-tx-placeholder-expense="Office Rent"
                                   data-tx-placeholder-credit="Client Payment"
                                   value="{{ old('title', $record->title) }}">
                            <x-field-error name="title"/>
                        </div>

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="amount">Amount <span class="req">*</span></label>
                            <div class="input-prefix">
                                <span class="sym">{{ Money::symbol() }}</span>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
                                       inputmode="decimal"
                                       class="input @error('amount') input--error @enderror"
                                       placeholder="{{ $type === 'expense' ? '25000' : '50000' }}"
                                       data-tx-placeholder-expense="25000"
                                       data-tx-placeholder-credit="50000"
                                       value="{{ old('amount', $record->amount) }}">
                            </div>
                            <x-field-error name="amount"/>
                        </div>

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="transaction_date">Date <span class="req">*</span></label>
                            <input id="transaction_date" name="transaction_date" type="date" required
                                   max="{{ now()->toDateString() }}"
                                   class="input @error('transaction_date') input--error @enderror"
                                   value="{{ old('transaction_date', optional($record->transaction_date)->format('Y-m-d') ?? now()->toDateString()) }}">
                            <x-field-error name="transaction_date"/>
                        </div>

                        {{-- Rendered for the current type, and refetched from
                             admin.transactions.categories whenever the type
                             changes. TransactionRequest re-checks the pairing
                             on save, so this dropdown is a convenience rather
                             than the guard. --}}
                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="category_id">Category <span class="req">*</span></label>
                            <select id="category_id" name="category_id" required
                                    class="select @error('category_id') select--error @enderror"
                                    data-tx-category
                                    data-tx-loading="Loading categories...">
                                <option value="">Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                            @selected(old('category_id', $record->category_id) == $category->id)>
                                        {{ $category->name }}@unless ($category->status) (inactive)@endunless
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="category_id"/>
                            <span class="hint">Depends on the type chosen above.</span>
                        </div>

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="payment_method">Payment Method <span class="req">*</span></label>
                            <select id="payment_method" name="payment_method" required
                                    class="select @error('payment_method') select--error @enderror">
                                @foreach (Transaction::PAYMENT_METHODS as $value => $text)
                                    <option value="{{ $value }}"
                                            @selected(old('payment_method', $record->payment_method) === $value)>
                                        {{ $text }}
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="payment_method"/>
                        </div>

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            {{-- One list, worded for the direction the money
                                 is moving; the JS swaps the text with type. --}}
                            <label for="payment_by_id" data-tx-payer-label>{{ $payerLabel }}</label>
                            <select id="payment_by_id" name="payment_by_id"
                                    class="select @error('payment_by_id') select--error @enderror">
                                <option value="">Select</option>
                                @foreach ($payers as $payer)
                                    <option value="{{ $payer->id }}"
                                            @selected(old('payment_by_id', $record->payment_by_id) == $payer->id)>
                                        {{ $payer->name }}@unless ($payer->status) (inactive)@endunless
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="payment_by_id"/>
                            @if ($payers->isEmpty())
                                {{-- Keep a space in front of the directive.
                                     Blade only recognises one when the
                                     character before the @ is not a letter or
                                     digit, so running it straight on from a
                                     word leaves the opening half as plain text
                                     while the closing half still compiles -
                                     which is a stray endif and a dead view. --}}
                                <span class="hint">
                                    No entries yet.
                                    @admin
                                        <a href="{{ route('admin.payment-bys.index') }}">Add one</a>.
                                    @endadmin
                                </span>
                            @endif
                        </div>

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="location">Location</label>
                            <input id="location" name="location" type="text" maxlength="150"
                                   class="input @error('location') input--error @enderror"
                                   placeholder="e.g. Andheri Office, Mumbai"
                                   value="{{ old('location', $record->location) }}">
                            <x-field-error name="location"/>
                            <span class="hint">
                                Where the money was
                                <span data-tx-direction-word>{{ $type === 'expense' ? 'spent' : 'received' }}</span>.
                            </span>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" maxlength="2000"
                                      class="textarea @error('description') textarea--error @enderror"
                                      placeholder="What was this transaction for?">{{ old('description', $record->description) }}</textarea>
                            <x-field-error name="description"/>
                        </div>

                        <div class="field col-md-6 col-lg-6 col-sm-12 col-xs-12">
                            <label for="attachments">Receipts / Invoices</label>

                            @if ($editing && $record->attachments->isNotEmpty())
                                <div class="attachments">
                                    {{-- A div, not a label: wrapping the whole row in a
                                         label made clicking the filename toggle Remove. --}}
                                    @foreach ($record->attachments as $attachment)
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

                            <input id="attachments" name="attachments[]" type="file" multiple
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                                   class="input input--file @error('attachments') input--error @enderror"
                                   data-file-preview
                                   @error('attachments.*') data-invalid @enderror>

                            {{-- Thumbnails of the files just picked, before
                                 they are uploaded. Filled in by admin.js. --}}
                            <div class="attachments" data-file-preview-for="attachments" hidden></div>
                            <x-field-error name="attachments"/>
                            @error('attachments.*')
                                <span class="error">{{ $message }}</span>
                            @enderror
                            <span class="hint">
                                JPG, PNG, WEBP, GIF or PDF. Up to 10 files, 5 MB each.
                                @if ($editing) Existing files stay unless you tick Remove. @endif
                            </span>
                        </div>

                        <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                            <label for="notes">Notes <span class="muted small">(optional)</span></label>
                            <textarea id="notes" name="notes" rows="2" maxlength="2000"
                                      class="textarea @error('notes') textarea--error @enderror"
                                      placeholder="Reference number, invoice id, anything worth remembering">{{ old('notes', $record->notes) }}</textarea>
                            <x-field-error name="notes"/>
                        </div>
                    </div>
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary"
                                data-busy="Saving..."
                                @disabled($companies->isEmpty())>
                            {{ $editing ? 'Update' : 'Save' }} Transaction
                        </button>
                        <a href="{{ route('admin.transactions.index') }}" class="btn">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
