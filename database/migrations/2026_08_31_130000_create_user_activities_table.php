<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only activity log: what was done, to which table, by whom, when.
     *
     * Deliberately not soft deleted and never updated. An audit trail that can
     * be edited or tidied away is not an audit trail, so nothing in the admin
     * panel offers either - the model refuses both as well.
     */
    public function up(): void
    {
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            // A copy of the name as it was at the time. The foreign key goes
            // null if the admin is ever removed, and a log entry that cannot
            // say who acted is worth very little.
            $table->string('admin_name')->nullable();

            $table->string('action', 40)->index();
            $table->string('table_name', 60)->index();
            // The row acted on. Null for actions that are not about one row,
            // such as signing in.
            $table->unsignedBigInteger('record_id')->nullable();

            $table->string('description')->nullable();
            // 45 characters holds an IPv6 address in full.
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            // The list is read newest first, and filtered by table or action.
            $table->index('created_at');
            $table->index(['table_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
