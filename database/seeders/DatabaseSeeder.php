<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Both seeders are idempotent, so this is safe to run repeatedly.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CategorySeeder::class,
        ]);
    }
}
