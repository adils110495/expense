<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'payment_by_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            // Nullable so existing rows stay valid; restricted on delete so a
            // value that is already in use cannot be removed underneath them.
            $table->foreignId('payment_by_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('payment_bys')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_by_id']);
            $table->dropColumn('payment_by_id');
        });
    }
};
