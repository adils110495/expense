<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EMAIL = 'nazimsaifi0033@gmail.com';

    /**
     * Pins the default account's username, in case the backfill derived
     * something else from an edited display name.
     */
    public function up(): void
    {
        DB::table('admins')
            ->where('email', self::EMAIL)
            ->update(['username' => 'superadmin', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Nothing to reverse - the column itself is dropped by its own migration.
    }
};
