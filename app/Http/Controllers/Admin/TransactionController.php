<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransactionRequest;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\PaymentBy;
use App\Models\Transaction;
use App\Services\FinanceReport;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use App\Support\HierarchyOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The combined money screen.
 *
 * Expenses and credits are the same record with a different sign, so there is
 * one form for both: the type is a field on it, and picking a type refills the
 * category dropdown over AJAX. The per-type list pages still exist, but they
 * are lists only - every add, edit, view and delete lands here.
 */
class TransactionController extends Controller
{
    public const SORTABLE = ['transaction_date', 'amount', 'title'];

    /** Wording for the payment-by dropdown; both types share one list. */
    public const PAYER_LABELS = [
        'expense' => 'Payment By',
        'credit' => 'Payment Received',
    ];

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
            // First, and before anything the request can influence: the rows
            // this actor may see at all, narrowed by the header's company
            // selector. The export controller reuses this method, so the
            // download is bounded by exactly the same clause as the screen -
            // a hand-edited ?company_id cannot widen it.
            ->forCompanies(CompanyAccess::scopeIds())
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

    /* ---------------------- The single add/edit form ---------------------- */

    public function create(Request $request): View
    {
        // Arriving from a company, project or person page - or from one of the
        // per-type lists - carries that context into the form rather than
        // making it be re-picked. Read as integers: these are hand-editable
        // query parameters, and a non-numeric one must land as "nothing
        // preselected" rather than anywhere near a type hint.
        $record = new Transaction([
            'type' => $this->requestedType($request) ?? 'expense',
            'transaction_date' => now(),
            'company_id' => $request->integer('company_id') ?: null,
            'project_id' => $request->integer('project_id') ?: null,
            'person_id' => $request->integer('person_id') ?: null,
        ]);

        return view('admin.transactions.form', $this->formData($record));
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $transaction = Transaction::create([
            ...$this->attributes($request),
            // Null for a panel user: the column is a foreign key into admins, so
            // only an admin can be named here. Who actually did it is recorded
            // either way in the activity log, which knows both guards.
            'created_by' => auth('admin')->id(),
        ]);

        $this->syncAttachments($request, $transaction);

        return redirect()->route('admin.transactions.index')
            ->with('success', $this->label($transaction->type).' saved successfully.');
    }

    public function show(Transaction $transaction): View
    {
        return view('admin.transactions.show', [
            'record' => $transaction->load([
                'category', 'creator', 'attachments', 'paymentBy',
                'company', 'project', 'person',
            ]),
            'label' => $this->label($transaction->type),
            'payerLabel' => self::PAYER_LABELS[$transaction->type] ?? 'Payment By',
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        return view('admin.transactions.form', $this->formData($transaction->load('attachments')));
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $transaction->update($this->attributes($request));

        $this->syncAttachments($request, $transaction);

        return redirect()->route('admin.transactions.index')
            ->with('success', $this->label($transaction->type).' updated successfully.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $label = $this->label($transaction->type);

        // Soft delete: the row is only stamped with deleted_at, so it drops
        // out of every list and total but stays in the database. Attachments
        // are deliberately left on disk - they would be needed on a restore.
        $transaction->delete();

        return redirect()->route('admin.transactions.index')
            ->with('success', $label.' deleted successfully.');
    }

    /**
     * The category list for one type, as JSON.
     *
     * This is what the form calls when the type dropdown changes. An unknown
     * type returns an empty list rather than an error - the form only has to
     * clear the dropdown, and the save is guarded by TransactionRequest
     * whatever this returns.
     */
    public function categories(Request $request): JsonResponse
    {
        $type = $this->requestedType($request);

        $categories = $type
            ? Category::query()->ofType($type)->active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return response()->json([
            'type' => $type,
            'payer_label' => self::PAYER_LABELS[$type] ?? 'Payment By',
            'categories' => $categories,
        ]);
    }

    /* ------------------------------ Internals ------------------------------ */

    /** Everything the form view needs, for both create and edit. */
    protected function formData(Transaction $record): array
    {
        // On a failed submit the form comes back with the type the user had
        // chosen, so the category list has to match that rather than what is
        // still stored on the record.
        $type = old('type', $record->type);
        $type = in_array($type, Transaction::TYPES, true) ? $type : 'expense';

        return [
            'record' => $record,
            'type' => $type,
            'categories' => $this->categoriesFor($type, $record->category_id),
            'payers' => $this->payers($record->payment_by_id),
            'payerLabel' => self::PAYER_LABELS[$type],
            ...HierarchyOptions::forForm($record),
        ];
    }

    /** The requested type, or null when it is missing or not one of ours. */
    protected function requestedType(Request $request): ?string
    {
        $type = $request->query('type');

        return in_array($type, Transaction::TYPES, true) ? $type : null;
    }

    protected function label(string $type): string
    {
        return ucfirst($type);
    }

    /**
     * Active categories of one type, plus the record's current one so an edit
     * form never silently drops a category that was deactivated after the
     * fact. ofType() keeps that extra one out when the type has been switched,
     * which is what forces a fresh choice on the way from expense to credit.
     */
    protected function categoriesFor(string $type, ?int $include = null)
    {
        return Category::query()
            ->ofType($type)
            ->where(fn ($q) => $q->where('status', true)->when($include, fn ($w) => $w->orWhere('id', $include)))
            ->orderBy('name')
            ->get();
    }

    /**
     * Active "Payment By" entries, plus the record's current one so editing an
     * old transaction never silently drops a value that was later deactivated.
     */
    protected function payers(?int $include = null)
    {
        return PaymentBy::query()
            ->where(fn ($q) => $q->where('status', true)->when($include, fn ($w) => $w->orWhere('id', $include)))
            ->orderBy('name')
            ->get();
    }

    /**
     * Validated data minus the keys that are not columns on `transactions`.
     */
    protected function attributes(TransactionRequest $request): array
    {
        return $request->safe()->except(['attachments', 'remove_attachments']);
    }

    /**
     * Removes the attachments the user ticked, then stores any new uploads.
     * Files go on the private disk, so they are only reachable through the
     * authenticated download route.
     */
    protected function syncAttachments(TransactionRequest $request, Transaction $transaction): void
    {
        $removeIds = $request->input('remove_attachments', []);

        if ($removeIds) {
            // Scoped to this transaction so an id from another record cannot
            // be passed in to delete someone else's file.
            $transaction->attachments()->whereIn('id', $removeIds)->get()
                ->each(fn (Attachment $attachment) => $attachment->deleteWithFile());
        }

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('attachments/'.$transaction->id, 'local');

            $transaction->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                // Kept only for display and the download filename - never used
                // to build a storage path.
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth('admin')->id(),
            ]);
        }
    }
}
