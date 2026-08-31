<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentBy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mirrors CategoryController: the admin manages the list, and every active
 * entry shows up in the expense form's "Payment By" dropdown.
 */
class PaymentByController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.payment-bys.index', [
            'payers' => PaymentBy::query()
                // Trashed rows still hold a foreign key, so they count towards
                // "in use" - see CategoryController for the same reasoning.
                ->withCount(['transactions' => fn ($q) => $q->withTrashed()])
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
                // Deleted rows are already excluded by the soft-delete scope;
                // this filter is only Active vs Inactive.
                ->when($this->statusFilter($request) !== null,
                    fn ($q) => $q->where('status', $this->statusFilter($request)))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'activeStatus' => $request->query('status'),
        ]);
    }

    /**
     * '1' => active, '0' => inactive, anything else => no filter.
     */
    private function statusFilter(Request $request): ?bool
    {
        return match ($request->query('status')) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    public function store(Request $request): RedirectResponse
    {
        PaymentBy::create($this->validated($request));

        return back()->with('success', 'Payment By added.');
    }

    public function update(Request $request, PaymentBy $paymentBy): RedirectResponse
    {
        $paymentBy->update($this->validated($request, $paymentBy));

        return back()->with('success', 'Payment By updated.');
    }

    public function toggle(PaymentBy $paymentBy): RedirectResponse
    {
        $paymentBy->update(['status' => ! $paymentBy->status]);

        return back()->with('success', sprintf(
            '"%s" %s.',
            $paymentBy->name,
            $paymentBy->status ? 'activated' : 'deactivated'
        ));
    }

    public function destroy(PaymentBy $paymentBy): RedirectResponse
    {
        // Same rule as categories: something in use is deactivated, not
        // deleted, so existing expenses keep their reference.
        if ($paymentBy->transactions()->withTrashed()->exists()) {
            return back()->with('error', sprintf(
                'Cannot delete "%s" - it is used by %d transaction(s). Deactivate it instead.',
                $paymentBy->name,
                $paymentBy->transactions()->withTrashed()->count()
            ));
        }

        $paymentBy->delete();

        return back()->with('success', 'Payment By deleted.');
    }

    private function validated(Request $request, ?PaymentBy $paymentBy = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                // whereNull('deleted_at') so a deleted name can be reused.
                Rule::unique('payment_bys', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($paymentBy?->id),
            ],
            'status' => ['nullable', 'boolean'],
        ]);

        // Forms post a hidden status=0 alongside the checkbox.
        $data['status'] = $request->boolean('status');

        return $data;
    }
}
