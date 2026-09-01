<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which side a settlement pays off.
     *
     * Expenses and income are settled as two separate lists, so the same pair
     * of partners can have one payment open on each. Without this column the
     * two would be indistinguishable, and a recorded expense payment would
     * look like it had already covered the income one.
     *
     * 'net' is for a single payment that clears both sides at once, and is the
     * default so rows recorded before this column existed keep their meaning.
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->enum('kind', ['expense', 'credit', 'net'])
                ->default('net')
                ->after('to_person_id');

            $table->index(['project_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
