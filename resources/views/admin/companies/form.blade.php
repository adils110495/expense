@extends('admin.layouts.app')

@php
    $editing = $company->exists;
    $heading = ($editing ? 'Edit ' : 'Add ').'Company';
    $action = $editing
        ? route('admin.companies.update', $company)
        : route('admin.companies.store');
@endphp

@section('title', $heading)
@section('heading', $heading)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.companies.index') }}">Companies</a></span>
    <span>{{ $editing ? 'Edit' : 'Add' }}</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Company details</h2>
                <a href="{{ route('admin.companies.index') }}" class="btn btn--sm">Back to list</a>
            </div>

            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="name">Company Name <span class="req">*</span></label>
                            <input id="name" name="name" type="text" maxlength="150" required
                                   class="input @error('name') input--error @enderror"
                                   placeholder="e.g. ABC Company"
                                   value="{{ old('name', $company->name) }}">
                            <x-field-error name="name"/>
                        </div>

                        <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            {{-- Hidden 0 so an unticked box still posts a value. --}}
                            <input type="hidden" name="status" value="0">
                            <label class="check">
                                <input type="checkbox" name="status" value="1"
                                       @checked(old('status', $company->status ?? true))>
                                Active (available when adding projects and money)
                            </label>
                            <x-field-error name="status"/>
                        </div>

                        <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" maxlength="2000"
                                      class="textarea @error('description') textarea--error @enderror"
                                      placeholder="What this company is, or how it is used here.">{{ old('description', $company->description) }}</textarea>
                            <x-field-error name="description"/>
                        </div>
                    </div>

                    @if ($editing)
                        <p class="hint" style="margin:0;">
                            Created {{ $company->created_at->format('d M Y H:i') }}
                            @if ($company->updated_at->ne($company->created_at))
                                &middot; last updated {{ $company->updated_at->format('d M Y H:i') }}
                            @endif
                        </p>
                    @endif
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving...">
                            {{ $editing ? 'Update' : 'Save' }} Company
                        </button>
                        <a href="{{ route('admin.companies.index') }}" class="btn">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
