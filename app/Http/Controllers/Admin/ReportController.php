<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\FinanceReport;
use App\Support\DateRange;
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

        return view('admin.reports.index', [
            'range' => $range,
            'summary' => $report->summary(clone $query),
            'monthly' => $report->monthlySeries(),
            'expenseByCategory' => $report->byCategory('expense'),
            'creditByCategory' => $report->byCategory('credit'),
            'byPaymentMethod' => $report->byPaymentMethod(),
            'categories' => Category::orderBy('type')->orderBy('name')->get(),
            'rows' => (clone $query)
                ->with(['category'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
