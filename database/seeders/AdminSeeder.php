<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Guarantees the default super admin exists with known credentials.
     * Safe to re-run: it is keyed on the email, so it repairs the existing
     * row (username, name, password, status) rather than adding a second one.
     *
     * Fresh installs get this account from the
     * 2026_08_29_000000_create_default_admin_user migration; this seeder is
     * the way to reset it afterwards.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'nazimsaifi0033@gmail.com'],
            [
                'name' => 'superadmin',
                'username' => 'superadmin',
                // Hashed by the model's 'hashed' cast.
                'password' => '123456',
                'status' => true,
            ],
        );
    }
}
