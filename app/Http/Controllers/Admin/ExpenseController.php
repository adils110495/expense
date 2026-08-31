<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;

class ExpenseController extends MoneyModuleController
{
    protected string $type = 'expense';

    protected string $routeName = 'admin.expenses';

    protected string $label = 'Expense';

    protected bool $supportsExtras = true;

    protected string $payerLabel = 'Payment By';

    public function store(ExpenseRequest $request): RedirectResponse
    {
        return $this->persistNew($request);
    }

    public function update(ExpenseRequest $request, Transaction $transaction): RedirectResponse
    {
        return $this->persistExisting($request, $transaction);
    }
}
