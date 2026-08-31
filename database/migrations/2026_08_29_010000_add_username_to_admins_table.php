<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Admins can sign in with either their email or a username. The username
     * is a separate column from `name` so renaming the display name never
     * breaks someone's login.
     */
    public function up(): void
    {
        // Added nullable first so existing rows can be backfilled before the
        // unique index goes on. Each step is guarded so a partially applied
        // run can simply be repeated.
        if (! Schema::hasColumn('admins', 'username')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('username', 50)->nullable()->after('name');
            });
        }

        $this->backfill();

        if (! $this->hasUniqueIndex()) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    private function hasUniqueIndex(): bool
    {
        foreach (Schema::getIndexes('admins') as $index) {
            if ($index['columns'] === ['username'] && $index['unique']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derives a username from each existing admin's name, keeping them unique.
     * Only fills rows that do not already have one.
     */
    private function backfill(): void
    {
        $taken = DB::table('admins')->whereNotNull('username')->pluck('username')->all();

        $rows = DB::table('admins')->whereNull('username')->orderBy('id')->get(['id', 'name', 'email']);

        foreach ($rows as $admin) {
            $base = Str::slug($admin->name, '') ?: Str::before($admin->email, '@');
            $base = Str::lower(Str::limit(preg_replace('/[^A-Za-z0-9_.]/', '', $base), 40, ''));
            $base = $base !== '' ? $base : 'admin'.$admin->id;

            $candidate = $base;
            $suffix = 1;

            while (in_array($candidate, $taken, true)) {
                $candidate = $base.(++$suffix);
            }

            $taken[] = $candidate;

            DB::table('admins')->where('id', $admin->id)->update(['username' => $candidate]);
        }
    }
};
