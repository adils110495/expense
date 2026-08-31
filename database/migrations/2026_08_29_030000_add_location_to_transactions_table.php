<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'location')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            // Where the money was spent. Used by the expense module; credits
            // simply leave it null.
            $table->string('location', 150)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
