{{--
    Company / Project / Person for one transaction, each part linking to its
    own page. A row that predates the hierarchy shows plainly as unassigned
    rather than as three dashes, so it can be found and fixed.

    Expects: $row (a Transaction).
--}}
@if ($row->company_id || $row->project_id || $row->person_id)
    <span class="hier-path">
        <span class="lead">
            @if ($row->company)
                <a href="{{ route('admin.companies.show', $row->company_id) }}">{{ $row->company->name }}</a>
            @else
                --
            @endif
        </span>
        <span class="lead">
            @if ($row->project)
                <a href="{{ route('admin.projects.show', $row->project_id) }}">{{ $row->project->name }}</a>
            @else
                --
            @endif
        </span>
        <span>
            @if ($row->person)
                <a href="{{ route('admin.people.show', $row->person_id) }}">{{ $row->person->name }}</a>
            @else
                --
            @endif
        </span>
    </span>
@else
    <span class="badge badge--off" title="Not attached to a company, project and person">
        <span class="dot"></span>Unassigned
    </span>
@endif
