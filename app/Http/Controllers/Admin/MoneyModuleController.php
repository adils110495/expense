<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PaymentBy;
use App\Models\Transaction;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use App\Support\HierarchyOptions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Expenses and Credits list pages.
 *
 * Both are the same list of the same table filtered to one type, so the two
 * modules share this controller and the subclasses only supply the type and
 * the wording. There is no form here: adding, editing, viewing and deleting
 * all happen on the one combined transaction form, where the type is a field
 * rather than a property of the route.
 */
abstract class MoneyModuleController extends Controller
{
    /** 'expense' or 'credit' */
    protected string $type;

    /** Route name prefix, e.g. 'admin.expenses' */
    protected string $routeName;

    /** Singular human label, e.g. 'Expense' */
    protected string $label;

    protected const SORTABLE = ['transaction_date', 'amount', 'title', 'created_at'];

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'transaction_date';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $records = Transaction::query()
            // Same boundary as the combined transactions list.
            ->forCompanies(CompanyAccess::scopeIds())
            ->where('type', $this->type)
            ->with(['category', 'creator', 'paymentBy', 'company', 'project', 'person'])
            ->search($request->query('q'))
            ->between($range->from, $range->to)
            // Company -> Project -> Person, each level optional and cumulative.
            ->inHierarchy(
                $request->query('company_id'),
                $request->query('project_id'),
                $request->query('person_id'),
            )
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->query('payment_method')))
            ->when($request->filled('payment_by_id'), fn ($q) => $q->where('payment_by_id', $request->query('payment_by_id')))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.money.index', [
            'records' => $records,
            // Every entry, not just active ones - an old row may point at a
            // deactivated category or payer and you still want to filter by it.
            'categories' => Category::ofType($this->type)->orderBy('name')->get(),
            'payers' => PaymentBy::orderBy('name')->get(),
            ...HierarchyOptions::forFilters(),
            'range' => $range,
            'sort' => $sort,
            'direction' => $direction,
            'type' => $this->type,
            'label' => $this->label,
            'routeName' => $this->routeName,
            'payerLabel' => TransactionController::PAYER_LABELS[$this->type],
        ]);
    }
}
