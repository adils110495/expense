<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransactionRequest;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\PaymentBy;
use App\Models\Transaction;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\DateRange;
use App\Support\HierarchyOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Expenses and credits are the same shape of record, so both modules share
 * this controller; subclasses supply the type and the route/label wording.
 */
abstract class MoneyModuleController extends Controller
{
    /** 'expense' or 'credit' */
    protected string $type;

    /** Route name prefix, e.g. 'admin.expenses' */
    protected string $routeName;

    /** Singular human label, e.g. 'Expense' */
    protected string $label;

    /** Location, Payment By and receipt uploads. */
    protected bool $supportsExtras = false;

    /** Wording for the payment-by dropdown; both modules share one list. */
    protected string $payerLabel = 'Payment By';

    protected const SORTABLE = ['transaction_date', 'amount', 'title', 'created_at'];

    public function __construct(protected readonly NotificationDispatcher $notifications) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'transaction_date';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $records = Transaction::query()
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

        return view($this->view('index'), [
            'records' => $records,
            'categories' => $this->categories(),
            ...HierarchyOptions::forFilters(),
            // Every entry, not just active ones - an old row may point at a
            // deactivated one and you still want to filter by it.
            'payers' => $this->supportsExtras ? PaymentBy::orderBy('name')->get() : collect(),
            'range' => $range,
            'sort' => $sort,
            'direction' => $direction,
            'type' => $this->type,
            'label' => $this->label,
            'routeName' => $this->routeName,
            'extras' => $this->supportsExtras,
            'payerLabel' => $this->payerLabel,
        ]);
    }

    public function create(Request $request): View
    {
        // Arriving from a company, project or person page carries that branch
        // into the form rather than making it be re-picked. Read as integers:
        // these are hand-editable query parameters, and a non-numeric one must
        // land as "nothing preselected" rather than anywhere near a type hint.
        $record = new Transaction([
            'type' => $this->type,
            'transaction_date' => now(),
            'company_id' => $request->integer('company_id') ?: null,
            'project_id' => $request->integer('project_id') ?: null,
            'person_id' => $request->integer('person_id') ?: null,
        ]);

        return view($this->view('form'), [
            'record' => $record,
            'categories' => $this->categories(),
            'payers' => $this->payers(),
            ...HierarchyOptions::forForm($record),
            'type' => $this->type,
            'label' => $this->label,
            'routeName' => $this->routeName,
            'extras' => $this->supportsExtras,
            'payerLabel' => $this->payerLabel,
        ]);
    }

    /**
     * Subclasses call this from their own store() so the concrete form request
     * (ExpenseRequest / CreditRequest) can be type hinted and resolved.
     */
    protected function persistNew(TransactionRequest $request): RedirectResponse
    {
        $transaction = Transaction::create([
            ...$this->attributes($request),
            'type' => $this->type,
            'created_by' => $request->user('admin')->id,
        ]);

        $this->syncAttachments($request, $transaction);

        // After the write, never before: a notification about a record that
        // then failed to save would be a lie. Nothing this call does can throw.
        $this->notifications->transactionCreated($transaction);

        return redirect()->route($this->routeName.'.index')
            ->with('success', $this->label.' saved successfully.');
    }

    public function show(Transaction $transaction): View
    {
        $this->guardType($transaction);

        return view($this->view('show'), [
            'record' => $transaction->load([
                'category', 'creator', 'attachments', 'paymentBy',
                'company', 'project', 'person',
            ]),
            'label' => $this->label,
            'routeName' => $this->routeName,
            'extras' => $this->supportsExtras,
            'payerLabel' => $this->payerLabel,
            'type' => $this->type,
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        $this->guardType($transaction);

        return view($this->view('form'), [
            'record' => $transaction->load('attachments'),
            'categories' => $this->categories($transaction->category_id),
            'payers' => $this->payers($transaction->payment_by_id),
            ...HierarchyOptions::forForm($transaction),
            'type' => $this->type,
            'label' => $this->label,
            'routeName' => $this->routeName,
            'extras' => $this->supportsExtras,
            'payerLabel' => $this->payerLabel,
        ]);
    }

    protected function persistExisting(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->guardType($transaction);

        // Read before the update, so the notification can say what changed.
        $previousAmount = (string) $transaction->amount;

        $transaction->update($this->attributes($request));

        $this->syncAttachments($request, $transaction);

        $this->notifications->transactionUpdated($transaction, $previousAmount);

        return redirect()->route($this->routeName.'.index')
            ->with('success', $this->label.' updated successfully.');
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
        if (! $this->supportsExtras) {
            return;
        }

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
                'uploaded_by' => $request->user('admin')->id,
            ]);
        }
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->guardType($transaction);

        // Soft delete: the row is only stamped with deleted_at, so it drops
        // out of every list and total but stays in the database. Attachments
        // are deliberately left on disk - they would be needed on a restore.
        $transaction->delete();

        // Only once the delete has committed.
        $this->notifications->transactionDeleted($transaction);

        return redirect()->route($this->routeName.'.index')
            ->with('success', $this->label.' deleted successfully.');
    }

    /**
     * Keeps /admin/expenses/{id} from reaching a credit row and vice versa.
     */
    protected function guardType(Transaction $transaction): void
    {
        abort_unless($transaction->type === $this->type, 404);
    }

    /**
     * Active categories, plus the record's current one so an edit form never
     * silently drops a category that was deactivated after the fact.
     */
    protected function categories(?int $include = null)
    {
        return Category::query()
            ->ofType($this->type)
            ->where(fn ($q) => $q->where('status', true)->when($include, fn ($w) => $w->orWhere('id', $include)))
            ->orderBy('name')
            ->get();
    }

    /**
     * Active "Payment By" entries, plus the record's current one so editing an
     * old expense never silently drops a value that was later deactivated.
     */
    protected function payers(?int $include = null)
    {
        if (! $this->supportsExtras) {
            return collect();
        }

        return PaymentBy::query()
            ->where(fn ($q) => $q->where('status', true)->when($include, fn ($w) => $w->orWhere('id', $include)))
            ->orderBy('name')
            ->get();
    }

    protected function view(string $name): string
    {
        return 'admin.money.'.$name;
    }
}
