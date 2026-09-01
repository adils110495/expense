<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hangs every transaction off Company -> Project -> Person.
     *
     * The columns are nullable so the migration cannot fail on a database
     * that already holds transactions; the very next migration backfills
     * those rows, and TransactionRequest requires all three from then on, so
     * no new row can be ambiguous.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('type')
                ->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->after('company_id')
                ->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->nullable()->after('project_id')
                ->constrained('people')->restrictOnDelete();

            // The hierarchy pages total by person within a period, and by
            // project/company within a period - one index per level.
            $table->index(['company_id', 'type', 'transaction_date'], 'transactions_company_type_date_index');
            $table->index(['project_id', 'type', 'transaction_date'], 'transactions_project_type_date_index');
            $table->index(['person_id', 'type', 'transaction_date'], 'transactions_person_type_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_company_type_date_index');
            $table->dropIndex('transactions_project_type_date_index');
            $table->dropIndex('transactions_person_type_date_index');

            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
