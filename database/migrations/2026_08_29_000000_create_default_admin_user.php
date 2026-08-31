<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    private const EMAIL = 'nazimsaifi0033@gmail.com';

    /**
     * Creates the default super admin so a fresh database is immediately
     * usable. Keyed on the email address, so running this on a database that
     * already holds the account updates it instead of duplicating it.
     */
    public function up(): void
    {
        DB::table('admins')->updateOrInsert(
            ['email' => self::EMAIL],
            [
                'name' => 'superadmin',
                'password' => Hash::make('123456'),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('admins')->where('email', self::EMAIL)->delete();
    }
};
