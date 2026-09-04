@extends('admin.layouts.app')

@php
    $editing = $project->exists;
    $heading = ($editing ? 'Edit ' : 'Add ').'Project';
    $action = $editing
        ? route('admin.projects.update', $project)
        : route('admin.projects.store');

    $checked = old('people', $assigned);
@endphp

@section('title', $heading)
@section('heading', $heading)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.projects.index') }}">Projects</a></span>
    <span>{{ $editing ? 'Edit' : 'Add' }}</span>
@endsection

@section('content')
    <div class="stack">
        @if ($companies->isEmpty())
            <div class="alert alert--warn">
                @admin
                    There are no active companies yet.
                    <a href="{{ route('admin.companies.create') }}">Add one first</a> -
                    a project must belong to a company.
                @else
                    You are not mapped to any company yet, and a project must belong to one.
                    Ask an administrator for access.
                @endadmin
            </div>
        @endif

        <div class="card">
            <div class="card__head">
                <h2>Project details</h2>
                <a href="{{ route('admin.projects.index') }}" class="btn btn--sm">Back to list</a>
            </div>

            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="company_id">Company <span class="req">*</span></label>
                            <select id="company_id" name="company_id" required
                                    class="select @error('company_id') select--error @enderror">
                                <option value="">Select a company</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}"
                                            @selected(old('company_id', $project->company_id) == $company->id)>
                                        {{ $company->name }}@unless ($company->status) (inactive)@endunless
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="company_id"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="name">Project Name <span class="req">*</span></label>
                            <input id="name" name="name" type="text" maxlength="150" required
                                   class="input @error('name') input--error @enderror"
                                   placeholder="e.g. Project 1"
                                   value="{{ old('name', $project->name) }}">
                            <x-field-error name="name"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="start_date">Start Date</label>
                            <input id="start_date" name="start_date" type="date"
                                   class="input @error('start_date') input--error @enderror"
                                   value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}">
                            <x-field-error name="start_date"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="end_date">End Date</label>
                            <input id="end_date" name="end_date" type="date"
                                   class="input @error('end_date') input--error @enderror"
                                   value="{{ old('end_date', optional($project->end_date)->format('Y-m-d')) }}">
                            <x-field-error name="end_date"/>
                            <span class="hint">Leave empty while the project is ongoing.</span>
                        </div>

                        <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <input type="hidden" name="status" value="0">
                            <label class="check">
                                <input type="checkbox" name="status" value="1"
                                       @checked(old('status', $project->status ?? true))>
                                Active (available when adding money)
                            </label>
                            <x-field-error name="status"/>
                        </div>

                        <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" maxlength="2000"
                                      class="textarea @error('description') textarea--error @enderror"
                                      placeholder="What this project covers.">{{ old('description', $project->description) }}</textarea>
                            <x-field-error name="description"/>
                        </div>
                    </div>
                </div>

                {{-- People assigned to the project. This list is exactly what
                     the Person dropdown offers on the expense and credit
                     forms once this project is chosen. --}}
                <div class="card__body" style="border-top:1px solid var(--border);">
                    <h3 style="margin-bottom:10px;">Assigned people</h3>

                    @if ($people->isEmpty())
                        <p class="hint" style="margin:0;">
                            No people yet - <a href="{{ route('admin.people.create') }}">add someone</a>
                            first, then assign them here.
                        </p>
                    @else
                        <div class="row">
                            @foreach ($people as $person)
                                <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                                    <label class="check">
                                        <input type="checkbox" name="people[]" value="{{ $person->id }}"
                                               @checked(in_array($person->id, (array) $checked))>
                                        {{ $person->name }}@if ($person->designation)
                                            <span class="muted small">&middot; {{ $person->designation }}</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <x-field-error name="people"/>
                        <p class="hint" style="margin:0;">
                            Anyone who already has transactions on this project stays assigned
                            even if you untick them - their money has to keep a valid person.
                        </p>
                    @endif
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving..."
                                @disabled($companies->isEmpty())>
                            {{ $editing ? 'Update' : 'Save' }} Project
                        </button>
                        <a href="{{ route('admin.projects.index') }}" class="btn">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
