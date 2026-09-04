<?php

namespace App\Http\Controllers\Admin;

class CreditController extends MoneyModuleController
{
    protected string $type = 'credit';

    protected string $routeName = 'admin.credits';

    protected string $label = 'Credit';
}
