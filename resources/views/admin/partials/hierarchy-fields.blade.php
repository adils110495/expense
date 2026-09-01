{{--
    Company -> Project -> Person, as three dependent dropdowns.

    Choosing a company narrows the project list; choosing a project narrows
    the people list. The narrowing happens in the browser from the data-*
    attributes below, so there is no request between the three selects. With
    JavaScript off every option stays visible and TransactionRequest still
    refuses a project that is not the company's, or a person who is not on the
    project - the picker is a convenience, not the guard.

    Expects: $companies, $projects, $people, $personProjects, $record.
--}}
@php
    $group = $group ?? 'form';
    $span = $span ?? 'col-md-4 col-lg-4 col-sm-12 col-xs-12';

    $companyValue = old('company_id', $record->company_id);
    $projectValue = old('project_id', $record->project_id);
    $personValue = old('person_id', $record->person_id);
@endphp

<div class="field {{ $span }}">
    <label for="company_id">Company <span class="req">*</span></label>
    <select id="company_id" name="company_id" required
            class="select @error('company_id') select--error @enderror"
            data-hier="company" data-hier-group="{{ $group }}">
        <option value="">Select a company</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected($companyValue == $company->id)>
                {{ $company->name }}@unless ($company->status) (inactive)@endunless
            </option>
        @endforeach
    </select>
    <x-field-error name="company_id"/>
    @if ($companies->isEmpty())
        <span class="hint">
            No companies yet - <a href="{{ route('admin.companies.create') }}">add one</a> first.
        </span>
    @endif
</div>

<div class="field {{ $span }}">
    <label for="project_id">Project <span class="req">*</span></label>
    <select id="project_id" name="project_id" required
            class="select @error('project_id') select--error @enderror"
            data-hier="project" data-hier-group="{{ $group }}"
            data-hier-empty="No projects in this company">
        <option value="">Select a project</option>
        @foreach ($projects as $project)
            <option value="{{ $project->id }}" data-company="{{ $project->company_id }}"
                    @selected($projectValue == $project->id)>
                {{ $project->name }}@unless ($project->status) (inactive)@endunless
            </option>
        @endforeach
    </select>
    <x-field-error name="project_id"/>
    <span class="hint" data-hier-hint="project" hidden></span>
</div>

<div class="field {{ $span }}">
    <label for="person_id">Person <span class="req">*</span></label>
    <select id="person_id" name="person_id" required
            class="select @error('person_id') select--error @enderror"
            data-hier="person" data-hier-group="{{ $group }}"
            data-hier-empty="Nobody is assigned to this project yet">
        <option value="">Select a person</option>
        @foreach ($people as $person)
            {{-- Built in PHP rather than as two inline conditionals. Blade
                 will not compile a directive that starts immediately after
                 the previous one ends, with no character between them - the
                 second is left as literal text while its closing tag still
                 compiles, which is a syntax error in the generated PHP. --}}
            @php
                $personLabel = $person->name
                    .($person->designation ? ' - '.$person->designation : '')
                    .($person->status ? '' : ' (inactive)');
            @endphp
            <option value="{{ $person->id }}"
                    data-projects="{{ implode(',', $personProjects[$person->id] ?? []) }}"
                    @selected($personValue == $person->id)>{{ $personLabel }}</option>
        @endforeach
    </select>
    <x-field-error name="person_id"/>
    <span class="hint" data-hier-hint="person" hidden></span>
</div>
