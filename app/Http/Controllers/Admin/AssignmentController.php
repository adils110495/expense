<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkAssignRequest;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\UserActivity;
use App\Support\DateRange;
use App\Support\HierarchyOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bulk assignment of existing transactions onto Company -> Project -> Person.
 *
 * Records created before the hierarchy existed, or left on the placeholder the
 * backfill migration made, sit outside the project and person totals and take
 * no part in settlement. This screen is how they are brought in.
 *
 * It doubles as a reassignment tool: filter to any branch and move the whole
 * batch somewhere else. Nothing is recalculated afterwards because nothing is
 * stored - every total and every settlement is derived, so the moment a row
 * moves, every figure that depends on it already reflects the change.
 */
class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        // Default view is the records that actually need attention; clearing
        // the toggle turns the page into a general reassignment tool.
        $incompleteOnly = ! $request->has('scope') || $request->query('scope') === 'incomplete';

        $records = Transaction::query()
            ->with(['category', 'company', 'project', 'person'])
            ->search($request->query('q'))
            ->between($range->from, $range->to)
            ->when($incompleteOnly, fn ($q) => $q->unassigned())
            ->when(
                $request->query('type') && in_array($request->query('type'), Transaction::TYPES, true),
                fn ($q) => $q->where('type', $request->query('type'))
            )
            ->inHierarchy(
                $request->query('company_id'),
                $request->query('project_id'),
                $request->query('person_id'),
            )
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.transactions.assign', [
            'records' => $records,
            'range' => $range,
            'incompleteOnly' => $incompleteOnly,
            // The running count of what still needs a home, whatever is
            // currently filtered on screen.
            'outstanding' => Transaction::query()->unassigned()->count(),
            'record' => new Transaction([
                'company_id' => $request->integer('company_id') ?: null,
                'project_id' => $request->integer('project_id') ?: null,
                'person_id' => $request->integer('person_id') ?: null,
            ]),
            ...HierarchyOptions::forFilters(),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    /**
     * Files the selected transactions under one company, project and person.
     *
     * A single UPDATE rather than a save per row: this is a data tidy-up that
     * may cover hundreds of records, and there are no model events on
     * Transaction that a mass update would skip.
     */
    public function update(BulkAssignRequest $request): RedirectResponse
    {
        $ids = $request->validated('transactions');

        Transaction::query()
            ->whereIn('id', $ids)
            ->update([
                'company_id' => $request->validated('company_id'),
                'project_id' => $request->validated('project_id'),
                'person_id' => $request->validated('person_id'),
            ]);

        // A mass update fires no model events, so the activity log would never
        // hear about it - one entry for the batch, written by hand.
        UserActivity::record(
            'updated',
            'transactions',
            null,
            'Bulk assigned '.count($ids).' transaction(s) to a company, project and person',
        );

        // Counting what was selected rather than what update() returns: MySQL
        // reports rows *changed*, so a row already filed there would go
        // uncounted and the message would read as if it had been skipped.
        return back()->with(
            'success',
            count($ids).' transaction(s) assigned. Every total and settlement now reflects the change.'
        );
    }
}
