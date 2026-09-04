<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for every financial total in the app. Balance is
 * always derived from transaction rows - no stored running total exists,
 * so edits and deletes stay consistent automatically.
 *
 * It is also where the company boundary meets the money: query() is the base
 * every dashboard figure, chart and report total is built on, so restricting
 * it here restricts all of them at once rather than one screen at a time.
 */
class FinanceReport
{
    public function __construct(private readonly DateRange $range) {}

    public function query(): Builder
    {
        return Transaction::query()
            // Before the date range and before anything a caller adds: no
            // total on any screen can include a company the actor is not
            // mapped to, whatever else is stacked on top.
            ->forCompanies(CompanyAccess::scopeIds())
            ->between($this->range->from, $this->range->to);
    }

    /**
     * @return array{expense_total: string, credit_total: string, balance: string,
     *               expense_count: int, credit_count: int, total_count: int}
     */
    public function summary(?Builder $base = null): array
    {
        $base ??= $this->query();

        // One grouped query rather than four - totals summed in SQL as DECIMAL.
        $rows = (clone $base)
            ->toBase()
            ->select('type', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as row_count'))
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $expense = (string) ($rows['expense']->total ?? '0');
        $credit = (string) ($rows['credit']->total ?? '0');
        $expenseCount = (int) ($rows['expense']->row_count ?? 0);
        $creditCount = (int) ($rows['credit']->row_count ?? 0);

        return [
            'expense_total' => $expense,
            'credit_total' => $credit,
            'balance' => bcsub($credit, $expense, 2),
            'expense_count' => $expenseCount,
            'credit_count' => $creditCount,
            'total_count' => $expenseCount + $creditCount,
        ];
    }

    /**
     * Month-by-month credit/expense/balance series for the chart.
     *
     * @return array{labels: string[], credits: float[], expenses: float[], balances: float[]}
     */
    public function monthlySeries(): array
    {
        $rows = $this->query()
            ->toBase()
            ->select(
                DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as period"),
                'type',
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('period', 'type')
            ->orderBy('period')
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $buckets[$row->period] ??= ['credit' => '0', 'expense' => '0'];
            $buckets[$row->period][$row->type] = (string) $row->total;
        }

        ksort($buckets);

        $labels = $credits = $expenses = $balances = [];

        foreach ($buckets as $period => $totals) {
            $labels[] = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->format('M Y');
            $credits[] = (float) $totals['credit'];
            $expenses[] = (float) $totals['expense'];
            $balances[] = (float) bcsub($totals['credit'], $totals['expense'], 2);
        }

        return compact('labels', 'credits', 'expenses', 'balances');
    }

    /**
     * Spend grouped by category - answers "where did the money go?".
     *
     * @return array<int, array{name: string, total: string, count: int}>
     */
    public function byCategory(string $type): array
    {
        return $this->query()
            ->where('transactions.type', $type)
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->toBase()
            ->select(
                'categories.name',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'total' => (string) $row->total,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, total: string, count: int}>
     */
    public function byPaymentMethod(?string $type = null): array
    {
        return $this->query()
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->toBase()
            ->select(
                'payment_method',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => Transaction::PAYMENT_METHODS[$row->payment_method] ?? $row->payment_method,
                'total' => (string) $row->total,
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
