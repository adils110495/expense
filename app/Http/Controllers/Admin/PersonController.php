<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PersonRequest;
use App\Models\Person;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
use App\Services\SettlementEngine;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $people = Person::query()
            ->forCompanies(CompanyAccess::scopeIds())
            ->search($request->query('q'))
            ->onProject($request->query('project_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (bool) $request->query('status')))
            ->with('projects.company')
            ->withCount(['projects', 'transactions'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.people.index', [
            'people' => $people,
            'totals' => HierarchyReport::forRange($range->from, $range->to)->totalsBy('person_id'),
            'projects' => Project::forCompanies(CompanyAccess::allowedIds())
                ->with('company')->orderBy('name')->get(),
            'range' => $range,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.people.form', [
            'person' => new Person(['status' => true]),
            'projects' => Project::active()->forCompanies(CompanyAccess::allowedIds())->with('company')->orderBy('name')->get(),
            // Pre-ticked when you arrive from a project's "add person" link.
            'assigned' => array_filter([$request->integer('project_id')]),
        ]);
    }

    public function store(PersonRequest $request): RedirectResponse
    {
        $person = Person::create($request->safe()->except('projects'));

        $person->projects()->sync($request->input('projects', []));

        return redirect()->route('admin.people.show', $person)
            ->with('success', 'Person added successfully.');
    }

    /**
     * Person details: the summary the spec asks for, then the complete
     * financial history - every credit and expense, newest first.
     */
    public function show(Request $request, Person $person): View
    {
        $range = DateRange::fromRequest($request, 'all');

        // Scoped as well as narrowed to the person. Someone can work on two
        // companies' projects at once, and reaching their page through the one
        // company you are mapped to must not lay out their money on the other.
        $base = Transaction::query()
            ->forCompanies(CompanyAccess::scopeIds())
            ->between($range->from, $range->to)
            ->where('person_id', $person->id);

        // For the same reason, the projects listed beside them are only the
        // ones inside the actor's companies.
        $person->load([
            'projects' => fn ($q) => $q->forCompanies(CompanyAccess::allowedIds())->with('company'),
        ]);

        // Per-project balance for this one person, from the same grouped query
        // the rest of the hierarchy uses.
        $perProject = (new HierarchyReport($base))->personTotalsPerProject();

        return view('admin.people.show', [
            'person' => $person,
            'range' => $range,
            'summary' => (new FinanceReport($range))->summary(clone $base),
            'projectTotals' => $perProject,
            // Settlement position per project, for the same period as the
            // rest of the page.
            'settlement' => $this->settlementFor($person, $range),
            'transactions' => (clone $base)
                ->with(['category', 'company', 'project', 'paymentBy', 'attachments'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    public function edit(Person $person): View
    {
        return view('admin.people.form', [
            'person' => $person,
            'projects' => Project::query()
                ->forCompanies(CompanyAccess::allowedIds())
                // Keep any project already assigned in the list even once it
                // is deactivated, so saving cannot silently unassign it.
                ->where(fn ($q) => $q->where('status', true)
                    ->orWhereHas('people', fn ($p) => $p->where('people.id', $person->id)))
                ->with('company')
                ->orderBy('name')
                ->get(),
            'assigned' => $person->projects()->pluck('projects.id')->all(),
        ]);
    }

    public function update(PersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($request->safe()->except('projects'));

        $this->syncProjects($person, $request->input('projects', []));

        return redirect()->route('admin.people.show', $person)
            ->with('success', 'Person updated successfully.');
    }

    public function toggle(Person $person): RedirectResponse
    {
        $person->update(['status' => ! $person->status]);

        return back()->with(
            'success',
            $person->name.' '.($person->status ? 'activated' : 'deactivated').'.'
        );
    }

    public function destroy(Person $person): RedirectResponse
    {
        if ($person->transactions()->exists()) {
            return back()->with(
                'error',
                'This person still has transactions. Deactivate them instead.'
            );
        }

        // Deleting one side of a settlement would leave the other side facing
        // a partner the page can no longer name.
        if ($person->settlements()->exists() || $person->settlementsReceived()->exists()) {
            return back()->with(
                'error',
                'This person is part of a recorded settlement. Deactivate them instead.'
            );
        }

        $person->projects()->detach();
        $person->delete();

        return redirect()->route('admin.people.index')
            ->with('success', 'Person deleted successfully.');
    }

    /**
     * Unticking a project the person still holds money on would leave that
     * money booked against someone who is no longer a valid choice there, so
     * those assignments are kept.
     */
    private function syncProjects(Person $person, array $ids): void
    {
        $keep = $person->transactions()
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id')
            ->all();

        $person->projects()->sync(array_unique([...array_map('intval', $ids), ...$keep]));
    }

    /**
     * This person's settlement position on every project they touch, for the
     * page's selected period.
     *
     * Projects they hold money on are included even when they are no longer
     * assigned - the engine counts them as a partner there either way, so
     * hiding the project would hide a real debt.
     *
     * @return array<int, array<string, mixed>>  one row per project
     */
    private function settlementFor(Person $person, DateRange $range): array
    {
        $strayProjectIds = $person->transactions()
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id');

        $projectIds = $person->projects->pluck('id')->merge($strayProjectIds)->unique();

        if ($projectIds->isEmpty()) {
            return [];
        }

        $projects = Project::forCompanies(CompanyAccess::allowedIds())
            ->with('company')->whereIn('id', $projectIds)->orderBy('name')->get();

        $rows = [];

        foreach (SettlementEngine::forProjects($projects, $range->from, $range->to) as $plan) {
            $position = $plan['by_person'][$person->id] ?? null;

            if ($position) {
                $rows[] = $position + ['project' => $plan['project']];
            }
        }

        return $rows;
    }
}
