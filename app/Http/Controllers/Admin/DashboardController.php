<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinanceReport;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'this_month');
        $report = new FinanceReport($range);

        $recent = $report->query()
            ->with(['category', 'creator', 'paymentBy'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('admin.dashboard', [
            'range' => $range,
            'summary' => $report->summary(),
            'monthly' => $report->monthlySeries(),
            'expenseByCategory' => $report->byCategory('expense'),
            'recent' => $recent,
        ]);
    }
}
