@extends('admin.layouts.app')

@section('title', 'Payment By')
@section('heading', 'Payment By')
@section('breadcrumbs')
    <span>Admin</span><span>Payment By</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Payment By list</h2>
                <div class="btn-row">
                    <button type="button" class="btn btn--primary"
                            data-modal-open="payment-by-modal"
                            data-action="{{ route('admin.payment-bys.store') }}"
                            data-method="POST"
                            data-title="Add Payment By"
                            data-field-name=""
                            data-field-status="1">
                        + Add Payment By
                    </button>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.payment-bys.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Name">
                    </div>
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="select" data-auto-submit>
                            <option value="">All statuses</option>
                            <option value="1" @selected($activeStatus === '1')>Active</option>
                            <option value="0" @selected($activeStatus === '0')>Inactive</option>
                        </select>
                    </div>

                    <div class="field field--actions col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <button type="submit" class="btn btn--primary">Apply</button>
                        <a href="{{ route('admin.payment-bys.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>

            @if ($payers->isEmpty())
                <x-empty-state
                    title="No Payment By entries found"
                    message="Add the people or accounts that pay for expenses. Every active entry appears in the expense form's Payment By dropdown."/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data data--narrow">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th class="num">In use</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($payers as $payer)
                                <tr>
                                    <td class="title">{{ $payer->name }}</td>
                                    <td>
                                        <span class="badge {{ $payer->status ? 'badge--on' : 'badge--off' }}">
                                            <span class="dot"></span>{{ $payer->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="num">{{ $payer->transactions_count }}</td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" class="btn btn--sm"
                                                    data-modal-open="payment-by-modal"
                                                    data-action="{{ route('admin.payment-bys.update', $payer) }}"
                                                    data-method="PUT"
                                                    data-title="Edit Payment By"
                                                    data-field-name="{{ $payer->name }}"
                                                    data-field-status="{{ $payer->status ? '1' : '0' }}">
                                                Edit
                                            </button>

                                            <form method="POST" action="{{ route('admin.payment-bys.toggle', $payer) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn--sm"
                                                        data-busy="{{ $payer->status ? 'Deactivating...' : 'Activating...' }}">
                                                    {{ $payer->status ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.payment-bys.destroy', $payer) }}"
                                                  data-confirm="Are you sure you want to delete &quot;{{ $payer->name }}&quot;?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--sm btn--danger"
                                                        data-busy="Deleting..."
                                                        @disabled($payer->transactions_count > 0)
                                                        title="{{ $payer->transactions_count > 0
                                                            ? 'In use by '.$payer->transactions_count.' transaction(s) - deactivate instead'
                                                            : 'Delete this entry' }}">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pagination-wrap">
                    {{ $payers->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Shared add/edit modal. The trigger sets action, method and values. --}}
    <div class="modal" id="payment-by-modal" hidden role="dialog" aria-modal="true"
         aria-labelledby="payment-by-modal-title">
        <div class="modal__panel">
            <form method="POST" action="{{ route('admin.payment-bys.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div class="modal__head">
                    <h3 id="payment-by-modal-title" data-modal-title>Add Payment By</h3>
                    <button type="button" class="btn btn--ghost btn--sm" data-modal-close aria-label="Close">&times;</button>
                </div>

                <div class="modal__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="pb-name">Name <span class="req">*</span></label>
                            <input id="pb-name" name="name" type="text" class="input" required maxlength="100"
                                   value="{{ old('name') }}" placeholder="e.g. Company Account">
                            <x-field-error name="name"/>
                        </div>

                        <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            {{-- Hidden 0 so an unchecked box still posts a value. --}}
                            <input type="hidden" name="status" value="0">
                            <label class="check">
                                <input type="checkbox" name="status" value="1" checked>
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal__foot">
                    <button type="button" class="btn" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn--primary" data-busy="Saving...">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection
