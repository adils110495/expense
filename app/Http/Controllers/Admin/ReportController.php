<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
use App\Support\DateRange;
use App\Support\HierarchyOptions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly TransactionController $transactions) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'this_month');
        $query = $this->transactions->filtered($request, $range);

        $report = new FinanceReport($range);

        // Hierarchical reporting runs off the same filtered query as the rest
        // of the page, so a company or person filter narrows every table here
        // in step rather than each one answering a different question.
        $hierarchy = new HierarchyReport(clone $query);

        return view('admin.reports.index', [
            'range' => $range,
            'summary' => $report->summary(clone $query),
            'monthly' => $report->monthlySeries(),
            'expenseByCategory' => $report->byCategory('expense'),
            'creditByCategory' => $report->byCategory('credit'),
            'byPaymentMethod' => $report->byPaymentMethod(),
            'byCompany' => $hierarchy->byCompany(),
            'byProject' => $hierarchy->byProject(),
            'byPerson' => $hierarchy->byPerson(),
            'categories' => Category::orderBy('type')->orderBy('name')->get(),
            ...HierarchyOptions::forFilters(),
            'rows' => (clone $query)
                ->with(['category', 'company', 'project', 'person'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
