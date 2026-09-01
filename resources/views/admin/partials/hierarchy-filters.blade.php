{{--
    The same three dependent dropdowns as hierarchy-fields, in filter form:
    every level optional, and each one narrows the next. Deactivated records
    are listed here on purpose - an old transaction may point at one and you
    still want to be able to filter by it.

    Expects: $companies, $projects, $people, $personProjects.
--}}
@php
    $group = $group ?? 'filter';
    $span = $span ?? 'col-md-3 col-lg-3 col-sm-12 col-xs-12';
    $prefix = $prefix ?? 'f';
@endphp

<div class="field {{ $span }}">
    <label for="{{ $prefix }}-company_id">Company</label>
    <select id="{{ $prefix }}-company_id" name="company_id" class="select"
            data-hier="company" data-hier-group="{{ $group }}">
        <option value="">All companies</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>
                {{ $company->name }}@unless ($company->status) (inactive)@endunless
            </option>
        @endforeach
    </select>
</div>

<div class="field {{ $span }}">
    <label for="{{ $prefix }}-project_id">Project</label>
    <select id="{{ $prefix }}-project_id" name="project_id" class="select"
            data-hier="project" data-hier-group="{{ $group }}">
        <option value="">All projects</option>
        @foreach ($projects as $project)
            <option value="{{ $project->id }}" data-company="{{ $project->company_id }}"
                    @selected(request('project_id') == $project->id)>
                {{ $project->name }}@unless ($project->status) (inactive)@endunless
            </option>
        @endforeach
    </select>
</div>

<div class="field {{ $span }}">
    <label for="{{ $prefix }}-person_id">Person</label>
    <select id="{{ $prefix }}-person_id" name="person_id" class="select"
            data-hier="person" data-hier-group="{{ $group }}">
        <option value="">All people</option>
        @foreach ($people as $person)
            <option value="{{ $person->id }}"
                    data-projects="{{ implode(',', $personProjects[$person->id] ?? []) }}"
                    @selected(request('person_id') == $person->id)>
                {{ $person->name }}@unless ($person->status) (inactive)@endunless
            </option>
        @endforeach
    </select>
</div>
