<?php

namespace App\Http\Requests\Admin;

class ExpenseRequest extends TransactionRequest
{
    public function transactionType(): string
    {
        return 'expense';
    }
}
