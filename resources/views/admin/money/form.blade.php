@extends('admin.layouts.app')

@php
    use App\Models\Transaction;
    use App\Support\Money;

    $editing = $record->exists;
    $heading = ($editing ? 'Edit ' : 'Add ').$label;
    $action = $editing
        ? route($routeName.'.update', $record)
        : route($routeName.'.store');
@endphp

@section('title', $heading)
@section('heading', $heading)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route($routeName.'.index') }}">{{ $label }}s</a></span>
    <span>{{ $editing ? 'Edit' : 'Add' }}</span>
@endsection

@section('content')
    <div class="stack">
        @if ($categories->isEmpty())
            <div class="alert alert--warn">
                There are no active {{ $type }} categories yet.
                <a href="{{ route('admin.categories.index', ['type' => $type]) }}">Add one first</a>
                so this {{ Str::lower($label) }} can be classified.
            </div>
        @endif

        @if ($companies->isEmpty())
            <div class="alert alert--warn">
                There are no active companies yet.
                <a href="{{ route('admin.companies.create') }}">Add a company</a>, give it a project,
                and assign people to that project - every {{ Str::lower($label) }} has to belong to
                a company, a project and a person.
            </div>
        @endif

        <div class="card">
            <div class="card__head">
                <h2>{{ $label }} details</h2>
                <a href="{{ route($routeName.'.index') }}" class="btn btn--sm">Back to list</a>
            </div>

            <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card__body">
                    <div class="row">
                        {{-- Company -> Project -> Person comes first: the rest
                             of the form describes money that has to belong
                             somewhere, and each dropdown narrows the next. --}}
                        @include('admin.partials.hierarchy-fields', [
                            'group' => 'money-form',
                            'record' => $record,
                        ])

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="title">{{ $label }} Title <span class="req">*</span></label>
                            <input id="title" name="title" type="text" maxlength="150" required
                                   class="input @error('title') input--error @enderror"
                                   placeholder="{{ $type === 'expense' ? 'Office Rent' : 'Client Payment' }}"
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

                        <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                            <label for="category_id">Category <span class="req">*</span></label>
                            <select id="category_id" name="category_id" required
                                    class="select @error('category_id') select--error @enderror">
                                <option value="">Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                            @selected(old('category_id', $record->category_id) == $category->id)>
                                        {{ $category->name }}@unless ($category->status) (inactive)@endunless
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="category_id"/>
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

                        @if ($extras)
                            <div class="field col-md-4 col-lg-4 col-sm-12 col-xs-12">
                                <label for="payment_by_id">{{ $payerLabel }}</label>
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
                                    <span class="hint">
                                        No entries yet -
                                        <a href="{{ route('admin.payment-bys.index') }}">add one</a>.
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
                                    Where the money was {{ $type === 'expense' ? 'spent' : 'received' }}.
                                </span>
                            </div>
                        @endif

                        {{-- Description pairs with Receipts on one row. Credits
                             have no receipts, so there it spans full width. --}}
                        <div class="field {{ $extras ? 'col-md-6 col-lg-6' : 'col-md-12 col-lg-12' }} col-sm-12 col-xs-12">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" maxlength="2000"
                                      class="textarea @error('description') textarea--error @enderror"
                                      placeholder="What was this {{ Str::lower($label) }} for?">{{ old('description', $record->description) }}</textarea>
                            <x-field-error name="description"/>
                        </div>

                        @if ($extras)
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
                        @endif

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
                                @disabled($categories->isEmpty() || $companies->isEmpty())>
                            {{ $editing ? 'Update' : 'Save' }} {{ $label }}
                        </button>
                        <a href="{{ route($routeName.'.index') }}" class="btn">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
