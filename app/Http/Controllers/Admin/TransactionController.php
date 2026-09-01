<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinanceReport;
use App\Support\DateRange;
use App\Support\HierarchyOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public const SORTABLE = ['transaction_date', 'amount', 'title'];

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');
        $query = $this->filtered($request, $range);

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'transaction_date';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $records = (clone $query)
            ->with(['category', 'creator', 'company', 'project', 'person'])
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Totals reflect the current filter set, not the whole table.
        $summary = (new FinanceReport($range))->summary(clone $query);

        return view('admin.transactions.index', [
            'records' => $records,
            'summary' => $summary,
            'range' => $range,
            'categories' => Category::orderBy('type')->orderBy('name')->get(),
            'sort' => $sort,
            'direction' => $direction,
            ...HierarchyOptions::forFilters(),
        ]);
    }

    /**
     * Shared filter pipeline - the export controller reuses it so a download
     * always matches exactly what the screen is showing.
     */
    public function filtered(Request $request, DateRange $range): Builder
    {
        $type = in_array($request->query('type'), Transaction::TYPES, true)
            ? $request->query('type')
            : null;

        return Transaction::query()
            ->ofType($type)
            ->search($request->query('q'))
            ->between($range->from, $range->to)
            // Company -> Project -> Person. Each level is optional, and they
            // narrow cumulatively, so the reports and the export always match
            // exactly the branch shown on screen.
            ->inHierarchy(
                $request->query('company_id'),
                $request->query('project_id'),
                $request->query('person_id'),
            )
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->query('payment_method')))
            ->when($request->filled('payment_by_id'), fn ($q) => $q->where('payment_by_id', $request->query('payment_by_id')));
    }
}
