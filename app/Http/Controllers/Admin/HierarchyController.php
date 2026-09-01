<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Transaction;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
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
        // half-valid 0 that then reads as "all companies" inconsistently.
        $companyId = $request->integer('company_id') ?: null;
        $type = in_array($request->query('type'), Transaction::TYPES, true)
            ? $request->query('type')
            : null;

        $base = Transaction::query()
            ->between($range->from, $range->to)
            ->ofType($type)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $report = new HierarchyReport($base);

        return view('admin.hierarchy.index', [
            'range' => $range,
            'tree' => $report->tree($companyId),
            'summary' => (new FinanceReport($range))->summary(clone $base),
            'companies' => Company::orderBy('name')->get(),
            'companyId' => $companyId,
            'type' => $type,
            // Legacy rows the backfill could not place. Normally zero; shown
            // so they can never be quietly missing from the tree's totals.
            'unassigned' => (clone $base)->unassigned()->count(),
        ]);
    }
}
