<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recorded partner-to-partner payments.
     *
     * This table is deliberately NOT a cache of the settlement plan. The plan
     * itself is always recalculated from transactions (see SettlementEngine),
     * so it can never go stale. What is stored here is the opposite: real
     * money that actually moved between two partners, which the engine then
     * feeds back in so a paid debt stops being suggested again.
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            // Settlement is per project - Project 1's balances never leak into
            // Project 2's, so every row is anchored to one project.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->foreignId('from_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('to_person_id')->constrained('people')->restrictOnDelete();

            // Decimal, never float - same rule as transactions.
            $table->decimal('amount', 15, 2);
            // What has actually changed hands. This, not `status`, is what the
            // engine counts, so a half-paid transfer settles half the debt.
            $table->decimal('paid_amount', 15, 2)->default(0);

            $table->enum('status', ['pending', 'partially_paid', 'paid', 'cancelled'])
                ->default('pending');

            $table->date('settled_on')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index('from_person_id');
            $table->index('to_person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
