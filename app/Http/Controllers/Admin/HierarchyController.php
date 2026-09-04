<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Transaction;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The expandable Company -> Project -> Person tree.
 *
 * Every figure on it is summed from the transactions table for the chosen
 * period, so the tree is a live view of the data rather than a cached shape.
 */
class HierarchyController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        // Integer or nothing - a hand-edited company_id must not narrow to a
        // half-valid 0 that then reads as "all companies" inconsistently. An
        // id outside the actor's companies narrows to nothing rather than
        // widening anything: forCompanies() below is the outer bound, and this
        // only ever tightens it further.
        $companyId = $request->integer('company_id') ?: null;
        $type = in_array($request->query('type'), Transaction::TYPES, true)
            ? $request->query('type')
            : null;

        $base = Transaction::query()
            ->forCompanies(CompanyAccess::scopeIds())
            ->between($range->from, $range->to)
            ->ofType($type)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $report = new HierarchyReport($base);

        return view('admin.hierarchy.index', [
            'range' => $range,
            // The page's own filter wins; with none, fall back to whatever the
            // header selector is set to.
            'tree' => $report->tree($companyId ?? CompanyAccess::selectedId()),
            'summary' => (new FinanceReport($range))->summary(clone $base),
            'companies' => Company::forCompanies(CompanyAccess::allowedIds())->orderBy('name')->get(),
            'companyId' => $companyId,
            'type' => $type,
            // Legacy rows the backfill could not place. Normally zero; shown
            // so they can never be quietly missing from the tree's totals.
            // Only an admin can see unfiled rows, so only an admin is told.
            'unassigned' => CompanyAccess::isAdmin() ? (clone $base)->unassigned()->count() : 0,
        ]);
    }
}
