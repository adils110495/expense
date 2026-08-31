<?php

namespace App\Http\Requests\Admin;

class CreditRequest extends TransactionRequest
{
    public function transactionType(): string
    {
        return 'credit';
    }
}
