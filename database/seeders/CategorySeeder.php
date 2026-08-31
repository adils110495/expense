<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    private const EXPENSE = [
        'Office', 'Travel', 'Food', 'Salary', 'Marketing', 'Software', 'Utilities', 'Other',
    ];

    private const CREDIT = [
        'Salary', 'Client Payment', 'Refund', 'Investment', 'Business Income', 'Other',
    ];

    public function run(): void
    {
        foreach (self::EXPENSE as $name) {
            Category::firstOrCreate(['name' => $name, 'type' => 'expense'], ['status' => true]);
        }

        foreach (self::CREDIT as $name) {
            Category::firstOrCreate(['name' => $name, 'type' => 'credit'], ['status' => true]);
        }
    }
}
