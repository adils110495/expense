<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft deletes for the two lookup tables.
     *
     * The unique indexes have to be rebuilt to include deleted_at: without
     * it a deleted "Travel" would keep the name reserved forever, and adding
     * it back would fail against a row the admin cannot even see. MySQL
     * treats NULLs as distinct, so this still allows only one live row per
     * name while any number of deleted ones can pile up behind it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'deleted_at')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->softDeletes()->index();
                $table->dropUnique(['name', 'type']);
                $table->unique(['name', 'type', 'deleted_at']);
            });
        }

        if (! Schema::hasColumn('payment_bys', 'deleted_at')) {
            Schema::table('payment_bys', function (Blueprint $table) {
                $table->softDeletes()->index();
                $table->dropUnique(['name']);
                $table->unique(['name', 'deleted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name', 'type', 'deleted_at']);
            $table->unique(['name', 'type']);
            $table->dropSoftDeletes();
        });

        Schema::table('payment_bys', function (Blueprint $table) {
            $table->dropUnique(['name', 'deleted_at']);
            $table->unique(['name']);
            $table->dropSoftDeletes();
        });
    }
};
