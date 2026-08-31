@extends('admin.layouts.app')

@section('title', 'Categories')
@section('heading', 'Categories')
@section('breadcrumbs')
    <span>Admin</span><span>Categories</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Category list</h2>
                <div class="btn-row">
                    <button type="button" class="btn btn--primary"
                            data-modal-open="category-modal"
                            data-action="{{ route('admin.categories.store') }}"
                            data-method="POST"
                            data-title="Add category"
                            data-field-name=""
                            data-field-type="expense"
                            data-field-status="1">
                        + Add Category
                    </button>
                </div>
            </div>

            <div class="card__body">
                <form method="GET" action="{{ route('admin.categories.index') }}" class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" class="input" value="{{ request('q') }}"
                               placeholder="Category name">
                    </div>
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="type">Type</label>
                        <select id="type" name="type" class="select" data-auto-submit>
                            <option value="">All types</option>
                            <option value="expense" @selected($activeType === 'expense')>Expense Category</option>
                            <option value="credit" @selected($activeType === 'credit')>Credit Category</option>
                        </select>
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
                        <a href="{{ route('admin.categories.index') }}" class="btn btn--secondary">Reset</a>
                    </div>
                </form>
            </div>

            @if ($categories->isEmpty())
                <x-empty-state
                    title="No categories found"
                    message="Categories classify every expense and credit. Add your first one to get started."/>
            @else
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="num">In use</th>
                                <th class="right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="title">{{ $category->name }}</td>
                                    <td>
                                        <span class="badge badge--{{ $category->type }}">
                                            <span class="dot"></span>{{ ucfirst($category->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $category->status ? 'badge--on' : 'badge--off' }}">
                                            <span class="dot"></span>{{ $category->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="num">{{ $category->transactions_count }}</td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" class="btn btn--sm"
                                                    data-modal-open="category-modal"
                                                    data-action="{{ route('admin.categories.update', $category) }}"
                                                    data-method="PUT"
                                                    data-title="Edit category"
                                                    data-field-name="{{ $category->name }}"
                                                    data-field-type="{{ $category->type }}"
                                                    data-field-status="{{ $category->status ? '1' : '0' }}">
                                                Edit
                                            </button>

                                            <form method="POST" action="{{ route('admin.categories.toggle', $category) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn--sm"
                                                        data-busy="{{ $category->status ? 'Deactivating...' : 'Activating...' }}">
                                                    {{ $category->status ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                                  data-confirm="Are you sure you want to delete the category &quot;{{ $category->name }}&quot;?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--sm btn--danger"
                                                        data-busy="Deleting..."
                                                        @disabled($category->transactions_count > 0)
                                                        title="{{ $category->transactions_count > 0
                                                            ? 'In use by '.$category->transactions_count.' transaction(s) - deactivate instead'
                                                            : 'Delete this category' }}">
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
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Shared add/edit modal. The trigger sets action, method and values. --}}
    <div class="modal" id="category-modal" hidden role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
        <div class="modal__panel">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div class="modal__head">
                    <h3 id="category-modal-title" data-modal-title>Add category</h3>
                    <button type="button" class="btn btn--ghost btn--sm" data-modal-close aria-label="Close">&times;</button>
                </div>

                <div class="modal__body">
                    <div class="row">
                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="cat-name">Name <span class="req">*</span></label>
                        <input id="cat-name" name="name" type="text" class="input" required maxlength="80"
                               value="{{ old('name') }}" placeholder="e.g. Marketing">
                        <x-field-error name="name"/>
                    </div>

                    <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        <label for="cat-type">Type <span class="req">*</span></label>
                        <select id="cat-type" name="type" class="select" required>
                            <option value="expense">Expense Category</option>
                            <option value="credit">Credit Category</option>
                        </select>
                        <x-field-error name="type"/>
                    </div>

                    <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                        {{-- Hidden 0 so an unchecked box still posts a value. --}}
                        <input type="hidden" name="status" value="0">
                        <label class="check">
                            <input type="checkbox" name="status" value="1" checked>
                            Active (available when creating transactions)
                        </label>
                    </div>
                    </div>
                </div>

                <div class="modal__foot">
                    <button type="button" class="btn" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn--primary" data-busy="Saving...">Save category</button>
                </div>
            </form>
        </div>
    </div>
@endsection
