@extends('admin.layouts.app')

@php
    use App\Models\Setting;
    use App\Support\Money;
    $dateFormat = Setting::get('date_format') ?? 'd M Y';
@endphp

@section('title', $label.' details')
@section('heading', $record->title)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.transactions.index') }}">Transactions</a></span>
    <span>Details</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>
                    <span class="badge badge--{{ $record->type }}">
                        <span class="dot"></span>{{ ucfirst($record->type) }}
                    </span>
                </h2>
                <div class="btn-row">
                    <a href="{{ route('admin.transactions.edit', $record) }}" class="btn">Edit</a>
                    <form method="POST" action="{{ route('admin.transactions.destroy', $record) }}"
                          data-confirm="Are you sure you want to delete this {{ Str::lower($label) }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger" data-busy="Deleting...">Delete</button>
                    </form>
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn--ghost">Back</a>
                </div>
            </div>

            <div class="card__body">
                <dl class="dl">
                    <div class="dl__row">
                        <dt>Amount</dt>
                        <dd class="amount--{{ $record->type }}" style="font-size:1.2rem;">
                            {{ Money::format($record->amount) }}
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>{{ $label }} Title</dt>
                        <dd>{{ $record->title }}</dd>
                    </div>

                    {{-- Where this money belongs. Spelled out level by level
                         rather than as one path, so there is never any doubt
                         which company, project and person it rolls up to. --}}
                    <div class="dl__row">
                        <dt>Company</dt>
                        <dd>
                            @if ($record->company)
                                <a href="{{ route('admin.companies.show', $record->company_id) }}">{{ $record->company->name }}</a>
                            @else
                                <span class="badge badge--off"><span class="dot"></span>Unassigned</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Project</dt>
                        <dd>
                            @if ($record->project)
                                <a href="{{ route('admin.projects.show', $record->project_id) }}">{{ $record->project->name }}</a>
                            @else
                                <span class="badge badge--off"><span class="dot"></span>Unassigned</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Person</dt>
                        <dd>
                            @if ($record->person)
                                <a href="{{ route('admin.people.show', $record->person_id) }}">{{ $record->person->name }}</a>
                            @else
                                <span class="badge badge--off"><span class="dot"></span>Unassigned</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dl__row">
                        <dt>Date</dt>
                        <dd>{{ $record->transaction_date->format($dateFormat) }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Category</dt>
                        <dd>{{ $record->category?->name ?? '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Payment Method</dt>
                        <dd>{{ $record->payment_method_label }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>{{ $payerLabel }}</dt>
                        <dd>{{ $record->paymentBy?->name ?? '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Location</dt>
                        <dd>{{ $record->location ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Description</dt>
                        <dd>{{ $record->description ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Notes</dt>
                        <dd>{{ $record->notes ?: '--' }}</dd>
                    </div>
                    <div class="dl__row">
                        <dt>Recorded On</dt>
                        <dd>{{ $record->created_at->format($dateFormat.' H:i') }}</dd>
                    </div>
                    @if ($record->updated_at->ne($record->created_at))
                        <div class="dl__row">
                            <dt>Last Updated</dt>
                            <dd>{{ $record->updated_at->format($dateFormat.' H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card__head">
                <h2>Receipts &amp; invoices</h2>
                <span class="badge badge--muted">{{ $record->attachments->count() }} file(s)</span>
            </div>

            @if ($record->attachments->isEmpty())
                <x-empty-state
                    title="No files attached"
                    :message="'Add a receipt or invoice by editing this '.Str::lower($label).'.'"
                    :action="route('admin.transactions.edit', $record)"
                    :action-label="'Edit '.Str::lower($label)"/>
            @else
                <div class="card__body">
                    <div class="attachments">
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
    </div>
@endsection
