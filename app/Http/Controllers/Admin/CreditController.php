<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CreditRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;

class CreditController extends MoneyModuleController
{
    protected string $type = 'credit';

    protected string $routeName = 'admin.credits';

    protected string $label = 'Credit';

    protected bool $supportsExtras = true;

    /** Same underlying list as expenses, worded for money coming in. */
    protected string $payerLabel = 'Payment Received';

    public function store(CreditRequest $request): RedirectResponse
    {
        return $this->persistNew($request);
    }

    public function update(CreditRequest $request, Transaction $transaction): RedirectResponse
    {
        return $this->persistExisting($request, $transaction);
    }
}
