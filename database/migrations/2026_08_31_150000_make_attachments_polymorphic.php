<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opens receipts up to settlements as well as transactions.
     *
     * The storage pipeline - private disk, authenticated streaming route,
     * delete-with-file - is already right and worth having once rather than
     * twice, so the table becomes polymorphic instead of a second one being
     * created alongside it. Existing rows are pointed at Transaction, so no
     * receipt moves or is lost.
     */
    public function up(): void
    {
        // Dropped before the rename: Laravel derives the constraint name from
        // the current table name, so this has to happen while it still matches.
        Schema::table('transaction_attachments', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
        });

        Schema::rename('transaction_attachments', 'attachments');

        Schema::table('attachments', function (Blueprint $table) {
            $table->string('attachable_type')->nullable()->after('id');
            $table->unsignedBigInteger('attachable_id')->nullable()->after('attachable_type');
        });

        DB::table('attachments')->update([
            'attachable_type' => 'App\Models\Transaction',
            'attachable_id' => DB::raw('transaction_id'),
        ]);

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['attachable_type', 'attachable_id']);
            $table->unsignedBigInteger('transaction_id')->nullable()->after('id');
        });

        // Only the transaction receipts can go back; anything attached to a
        // settlement has no column to return to and is removed with its owner.
        DB::table('attachments')
            ->where('attachable_type', 'App\Models\Transaction')
            ->update(['transaction_id' => DB::raw('attachable_id')]);

        DB::table('attachments')->whereNull('transaction_id')->delete();

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['attachable_type', 'attachable_id']);
        });

        Schema::rename('attachments', 'transaction_attachments');

        Schema::table('transaction_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable(false)->change();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }
};
