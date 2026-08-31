<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['expense', 'credit'])->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // Decimal, never float - money must not accumulate binary rounding error.
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date')->index();
            $table->enum('payment_method', [
                'cash', 'bank_transfer', 'upi', 'credit_card', 'debit_card', 'other',
            ])->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            // Dashboard/report queries always slice by type within a date window.
            $table->index(['type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
