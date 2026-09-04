<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel users get the same activate/deactivate switch admins already have.
 *
 * The `users` table is reused as-is - ids, names, emails, passwords and every
 * existing row stay exactly where they are. This adds one column and nothing
 * else. Guarded so a partially applied run can simply be repeated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Existing rows default to active: this migration must not lock
            // anybody out of an account they already had.
            $table->boolean('status')->default(true)->index()->after('password');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
