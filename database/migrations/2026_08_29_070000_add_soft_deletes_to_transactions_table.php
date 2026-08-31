<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting a transaction only stamps deleted_at - the row, and its
     * attachments, stay in the database and can be restored.
     */
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'deleted_at')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
