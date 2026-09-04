<?php

namespace App\Http\Controllers\Admin;

class ExpenseController extends MoneyModuleController
{
    protected string $type = 'expense';

    protected string $routeName = 'admin.expenses';

    protected string $label = 'Expense';
}
