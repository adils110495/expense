<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
use App\Services\SettlementEngine;
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
            ->with(['category', 'creator', 'paymentBy', 'company', 'project', 'person'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $hierarchy = new HierarchyReport($report->query());

        return view('admin.dashboard', [
            'range' => $range,
            'summary' => $report->summary(),
            'monthly' => $report->monthlySeries(),
            'expenseByCategory' => $report->byCategory('expense'),
            'recent' => $recent,
            // The Company -> Project -> Person tree for the same period as
            // everything else on the page.
            'tree' => $hierarchy->tree(),
            'unassigned' => $report->query()->unassigned()->count(),
            // Settlement follows the same period as the rest of the page.
            ...$this->settlement($range),
        ]);
    }

    /**
     * Settlement across every project, for the dashboard overview.
     *
     * One engine call covers all projects at a fixed query cost, so this does
     * not get slower as projects are added. Every figure is derived, never
     * stored, so it is right the moment an expense or partner changes.
     *
     * Scoped to the page's date range like everything else on the dashboard,
     * so "This Month" answers who owes whom on this month's activity.
     *
     * @return array<string, mixed>
     */
    private function settlement(DateRange $range): array
    {
        $plans = SettlementEngine::forProjects(
            Project::with('company')->orderBy('name')->get(),
            $range->from,
            $range->to,
        );

        $total = 0;
        // Kept apart so the overview shows what is owed on costs and what is
        // owed on credit, rather than one figure that hides both.
        $expense = 0;
        $income = 0;
        $expenseTransfers = [];
        $incomeTransfers = [];
        $payers = [];
        $receivers = [];

        foreach ($plans as $plan) {
            $total += $plan['to_settle'];
            $expense += $plan['expense_to_settle'];
            $income += $plan['income_to_settle'];

            foreach (['expense_transfers', 'income_transfers'] as $side) {
                foreach ($plan[$side] as $transfer) {
                    $row = $transfer + ['project' => $plan['project']];

                    if ($side === 'expense_transfers') {
                        $expenseTransfers[] = $row;
                    } else {
                        $incomeTransfers[] = $row;
                    }
                }
            }

            // Head-count is taken from the net position: a partner who owes on
            // one side and is owed the same on the other is square overall and
            // should not be listed as both.
            foreach ($plan['transfers'] as $transfer) {
                $payers[$transfer['from']->id] = true;
                $receivers[$transfer['to']->id] = true;
            }
        }

        // Biggest movements first - those are the ones worth chasing.
        $biggestFirst = fn (array $a, array $b) => $b['amount'] <=> $a['amount'];
        usort($expenseTransfers, $biggestFirst);
        usort($incomeTransfers, $biggestFirst);

        return [
            'settlementTotal' => $total,
            'settlementExpense' => $expense,
            'settlementIncome' => $income,
            'settlementExpenseTransfers' => array_slice($expenseTransfers, 0, 6),
            'settlementIncomeTransfers' => array_slice($incomeTransfers, 0, 6),
            'settlementExpenseCount' => count($expenseTransfers),
            'settlementIncomeCount' => count($incomeTransfers),
            'payers' => count($payers),
            'receivers' => count($receivers),
        ];
    }
}
