<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Person;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// CompanyAccess needs no import - it is in this same App\Support namespace.

/**
 * The three lists behind every Company -> Project -> Person picker.
 *
 * The dependent dropdowns are filtered in the browser rather than over the
 * network: each project option carries its company id and each person option
 * the projects they are assigned to, so choosing a company narrows the
 * projects instantly and with no request. It also means the picker still
 * works with JavaScript off - the form just shows every option and the
 * server-side rules in TransactionRequest reject a mismatched combination.
 *
 * Every list here is company-scoped before it leaves this class, so an
 * unauthorised company can never appear as an option in the first place. That
 * is convenience rather than the guard: ValidatesHierarchy re-checks the
 * chosen company on the way in, because an option list is only ever as
 * trustworthy as the request that comes back.
 */
class HierarchyOptions
{
    /**
     * Lists for an add/edit form: active records only, plus whatever the
     * record being edited already points at, so an entry that was
     * deactivated after the fact is never silently dropped on save.
     *
     * @return array{companies: mixed, projects: mixed, people: mixed, personProjects: array<int, int[]>}
     */
    public static function forForm(?Transaction $record = null): array
    {
        // Authority, not the header selection: picking one company to look at
        // must not stop you filing a transaction against another of yours.
        $allowed = CompanyAccess::allowedIds();

        $companies = Company::query()
            ->forCompanies($allowed)
            ->where(self::activeOr($record?->company_id))
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $projects = Project::query()
            ->forCompanies($allowed)
            ->where(self::activeOr($record?->project_id))
            ->orderBy('name')
            ->get(['id', 'company_id', 'name', 'status']);

        $people = Person::query()
            ->forCompanies($allowed)
            ->where(self::activeOr($record?->person_id))
            ->orderBy('name')
            ->get(['id', 'name', 'designation', 'status']);

        return [
            'companies' => $companies,
            'projects' => $projects,
            'people' => $people,
            'personProjects' => self::personProjects($people->pluck('id')->all()),
        ];
    }

    /**
     * Lists for a filter bar: everything within reach, including deactivated
     * records - an old transaction may point at one and you still want to
     * filter by it.
     *
     * @return array{companies: mixed, projects: mixed, people: mixed, personProjects: array<int, int[]>}
     */
    public static function forFilters(): array
    {
        $allowed = CompanyAccess::allowedIds();

        $people = Person::forCompanies($allowed)->orderBy('name')->get(['id', 'name', 'designation', 'status']);

        return [
            'companies' => Company::forCompanies($allowed)->orderBy('name')->get(['id', 'name', 'status']),
            'projects' => Project::forCompanies($allowed)->orderBy('name')->get(['id', 'company_id', 'name', 'status']),
            'people' => $people,
            'personProjects' => self::personProjects($people->pluck('id')->all()),
        ];
    }

    /**
     * person id => the project ids they are assigned to. One query, and it is
     * what the person dropdown filters on.
     *
     * @param  int[]  $personIds
     * @return array<int, int[]>
     */
    public static function personProjects(array $personIds = []): array
    {
        return DB::table('project_person')
            ->when($personIds, fn ($q) => $q->whereIn('person_id', $personIds))
            ->get(['person_id', 'project_id'])
            ->groupBy('person_id')
            ->map(fn ($rows) => $rows->pluck('project_id')->map(fn ($id) => (int) $id)->all())
            ->all();
    }

    /** Active rows, plus one specific id whatever its status. */
    private static function activeOr(?int $include): callable
    {
        return fn (Builder $q) => $q
            ->where('status', true)
            ->when($include, fn (Builder $w) => $w->orWhere('id', $include));
    }
}
