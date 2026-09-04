<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Company;
use App\Models\Person;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\UserActivity;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $projects = Project::query()
            ->forCompanies(CompanyAccess::scopeIds())
            ->search($request->query('q'))
            ->ofCompany($request->query('company_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (bool) $request->query('status')))
            ->with('company')
            ->withCount(['people', 'transactions'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'totals' => HierarchyReport::forRange($range->from, $range->to)->totalsBy('project_id'),
            'companies' => Company::forCompanies(CompanyAccess::allowedIds())->orderBy('name')->get(),
            'range' => $range,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.projects.form', [
            // Pre-selected when you arrive from a company page, so the chain
            // carries over instead of being retyped.
            'project' => new Project([
                'status' => true,
                'company_id' => $request->integer('company_id') ?: null,
            ]),
            'companies' => $this->companies(),
            'people' => Person::active()->forCompanies(CompanyAccess::allowedIds())->orderBy('name')->get(),
            'assigned' => [],
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->safe()->except('people'));

        $project->people()->sync($request->input('people', []));

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    /**
     * The project dashboard: the project's own totals, then a person-by-person
     * credit / expense / balance table for everyone on it.
     */
    public function show(Request $request, Project $project): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $project->load(['company', 'people']);

        $base = Transaction::query()
            ->between($range->from, $range->to)
            ->where('project_id', $project->id);

        // One grouped query gives every person's credit, expense and balance
        // on this project; the people list itself is a second query.
        $personTotals = (new HierarchyReport($base))->personTotalsPerProject();

        $people = $project->people->map(fn (Person $person) => [
            'id' => $person->id,
            'name' => $person->name,
            'designation' => $person->designation,
            'status' => (bool) $person->status,
            'totals' => $personTotals[$project->id.':'.$person->id] ?? HierarchyReport::blank(),
        ])->all();

        return view('admin.projects.show', [
            'project' => $project,
            'range' => $range,
            'summary' => (new FinanceReport($range))->summary(clone $base),
            'people' => $people,
            'recent' => (clone $base)
                ->with(['category', 'person'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            // For the "assign people" picker - everyone not already on it.
            'assignable' => Person::active()->forCompanies(CompanyAccess::allowedIds())
                ->whereDoesntHave('projects', fn ($q) => $q->where('projects.id', $project->id))
                ->orderBy('name')
                ->get(),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    public function edit(Project $project): View
    {
        return view('admin.projects.form', [
            'project' => $project,
            'companies' => $this->companies($project->company_id),
            'people' => Person::query()
                ->forCompanies(CompanyAccess::allowedIds())
                // Keep anyone already assigned in the list even if they were
                // deactivated afterwards, so saving cannot silently drop them.
                ->where(fn ($q) => $q->where('status', true)
                    ->orWhereHas('projects', fn ($p) => $p->where('projects.id', $project->id)))
                ->orderBy('name')
                ->get(),
            'assigned' => $project->people()->pluck('people.id')->all(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->safe()->except('people'));

        $this->syncPeople($project, $request->input('people', []));

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function toggle(Project $project): RedirectResponse
    {
        $project->update(['status' => ! $project->status]);

        return back()->with(
            'success',
            'Project '.($project->status ? 'activated' : 'deactivated').'.'
        );
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->transactions()->exists()) {
            return back()->with(
                'error',
                'This project still has transactions. Deactivate it instead.'
            );
        }

        // A settlement only means anything against its project, so it goes
        // with it rather than being left pointing at a deleted one.
        $project->settlements()->delete();
        $project->people()->detach();
        $project->delete();

        return redirect()->route('admin.projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    /* ===================== Assignments ===================== */

    /** Adds people to the project without disturbing the existing ones. */
    public function attachPeople(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'people' => ['required', 'array', 'min:1'],
            // Same test the picker above it is built from, so an id posted by
            // hand cannot pull someone in from another company.
            'people.*' => ['integer', function (string $attribute, mixed $value, \Closure $fail) {
                $visible = Person::query()
                    ->forCompanies(CompanyAccess::allowedIds())
                    ->whereKey($value)
                    ->exists();

                if (! $visible) {
                    $fail('Please choose a person you have access to.');
                }
            }],
        ], [
            'people.required' => 'Choose at least one person to assign.',
        ], ['people' => 'people']);

        // syncWithoutDetaching, not sync: this form only ever adds.
        $changed = $project->people()->syncWithoutDetaching($validated['people']);

        // Pivot writes fire no model events, so assignment changes are logged
        // here or not at all.
        if ($changed['attached']) {
            UserActivity::record(
                'assigned',
                'project_person',
                $project->id,
                count($changed['attached']).' person(s) assigned to project: '.$project->name,
            );
        }

        return back()->with('success', 'Assigned '.count($validated['people']).' person(s) to this project.');
    }

    /**
     * Removes one person from the project - refused while they still hold
     * transactions on it, because that would leave money booked against a
     * person who is no longer a valid choice for the project.
     */
    public function detachPerson(Project $project, Person $person): RedirectResponse
    {
        $held = $project->transactions()->where('person_id', $person->id)->count();

        if ($held > 0) {
            return back()->with(
                'error',
                $person->name.' still has '.$held.' transaction(s) on this project. '
                .'Move or delete those first.'
            );
        }

        $project->people()->detach($person->id);

        UserActivity::record(
            'unassigned',
            'project_person',
            $project->id,
            $person->name.' removed from project: '.$project->name,
        );

        return back()->with('success', $person->name.' removed from this project.');
    }

    /**
     * Same guard as detachPerson, applied to the checkbox list on the edit
     * form: anyone who is being unticked but still holds money stays.
     */
    private function syncPeople(Project $project, array $ids): void
    {
        $keep = $project->transactions()
            ->whereNotNull('person_id')
            ->distinct()
            ->pluck('person_id')
            ->all();

        $project->people()->sync(array_unique([...array_map('intval', $ids), ...$keep]));
    }

    /** Active companies, plus the project's current one if it was deactivated. */
    /**
     * The company dropdown on the project form: active companies within the
     * actor's mapping, plus whichever one the project already belongs to.
     *
     * allowedIds() rather than scopeIds() - narrowing the header to one
     * company is about what you are looking at, and must not stop you moving a
     * project between two companies that are both yours.
     */
    private function companies(?int $include = null)
    {
        return Company::query()
            ->forCompanies(CompanyAccess::allowedIds())
            ->where(fn ($q) => $q->where('status', true)->when($include, fn ($w) => $w->orWhere('id', $include)))
            ->orderBy('name')
            ->get();
    }
}
