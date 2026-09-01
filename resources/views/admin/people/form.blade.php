@extends('admin.layouts.app')

@php
    $editing = $person->exists;
    $heading = ($editing ? 'Edit ' : 'Add ').'Person';
    $action = $editing
        ? route('admin.people.update', $person)
        : route('admin.people.store');

    $checked = old('projects', $assigned);
@endphp

@section('title', $heading)
@section('heading', $heading)
@section('breadcrumbs')
    <span>Admin</span>
    <span><a href="{{ route('admin.people.index') }}">People</a></span>
    <span>{{ $editing ? 'Edit' : 'Add' }}</span>
@endsection

@section('content')
    <div class="stack">
        <div class="card">
            <div class="card__head">
                <h2>Person details</h2>
                <a href="{{ route('admin.people.index') }}" class="btn btn--sm">Back to list</a>
            </div>

            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card__body">
                    <div class="row">
                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="name">Name <span class="req">*</span></label>
                            <input id="name" name="name" type="text" maxlength="150" required
                                   class="input @error('name') input--error @enderror"
                                   placeholder="e.g. Adil"
                                   value="{{ old('name', $person->name) }}">
                            <x-field-error name="name"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" maxlength="150"
                                   class="input @error('email') input--error @enderror"
                                   placeholder="name@example.com"
                                   value="{{ old('email', $person->email) }}">
                            <x-field-error name="email"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" type="tel" maxlength="30"
                                   class="input @error('phone') input--error @enderror"
                                   placeholder="+91 90000 00000"
                                   value="{{ old('phone', $person->phone) }}">
                            <x-field-error name="phone"/>
                        </div>

                        <div class="field col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <label for="designation">Designation</label>
                            <input id="designation" name="designation" type="text" maxlength="100"
                                   class="input @error('designation') input--error @enderror"
                                   placeholder="e.g. Site Engineer"
                                   value="{{ old('designation', $person->designation) }}">
                            <x-field-error name="designation"/>
                        </div>

                        <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                            <input type="hidden" name="status" value="0">
                            <label class="check">
                                <input type="checkbox" name="status" value="1"
                                       @checked(old('status', $person->status ?? true))>
                                Active (available when adding money)
                            </label>
                            <x-field-error name="status"/>
                        </div>

                        <div class="field col-md-12 col-lg-12 col-sm-12 col-xs-12">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                      class="textarea @error('notes') textarea--error @enderror"
                                      placeholder="Anything worth remembering about this person.">{{ old('notes', $person->notes) }}</textarea>
                            <x-field-error name="notes"/>
                        </div>
                    </div>
                </div>

                {{-- Project assignments. A person may be on several projects,
                     and this is what decides which projects can carry their
                     credits and expenses. --}}
                <div class="card__body" style="border-top:1px solid var(--border);">
                    <h3 style="margin-bottom:10px;">Assigned projects</h3>

                    @if ($projects->isEmpty())
                        <p class="hint" style="margin:0;">
                            No projects yet - <a href="{{ route('admin.projects.create') }}">add one</a>
                            first, then assign this person to it.
                        </p>
                    @else
                        <div class="row">
                            @foreach ($projects as $project)
                                <div class="field field--check col-md-3 col-lg-3 col-sm-12 col-xs-12">
                                    <label class="check">
                                        <input type="checkbox" name="projects[]" value="{{ $project->id }}"
                                               @checked(in_array($project->id, (array) $checked))>
                                        {{ $project->name }}
                                        @if ($project->company)
                                            <span class="muted small">&middot; {{ $project->company->name }}</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <x-field-error name="projects"/>
                        <p class="hint" style="margin:0;">
                            A project this person already has transactions on stays assigned even
                            if you untick it - that money has to keep a valid project.
                        </p>
                    @endif
                </div>

                <div class="card__body" style="border-top:1px solid var(--border);">
                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary" data-busy="Saving...">
                            {{ $editing ? 'Update' : 'Save' }} Person
                        </button>
                        <a href="{{ route('admin.people.index') }}" class="btn">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
