<?php

use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a settlement was actually paid.
     *
     * A settlement is a real movement of money between two partners, so it
     * deserves the same detail an expense gets: the method, where it happened,
     * and a receipt. The date already exists as settled_on.
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Nullable: a pending settlement has not been paid by any method
            // yet. The same list as transactions, so the two read alike.
            $table->enum('payment_method', array_keys(Transaction::PAYMENT_METHODS))
                ->nullable()
                ->after('paid_amount');

            $table->string('location', 150)->nullable()->after('settled_on');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'location']);
        });
    }
};
