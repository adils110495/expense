<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every notification attempt, whatever became of it.
     *
     * The recipient's address is copied onto the row rather than only being
     * pointed at, because "who did we actually message" has to stay answerable
     * after a person is edited or removed.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            // The number or address the message was actually sent to.
            $table->string('recipient')->nullable();

            $table->enum('channel', ['whatsapp', 'email'])->index();
            $table->string('event', 60)->index();

            // What was sent, so a log entry is readable without re-rendering
            // the template against records that may since have changed.
            $table->string('subject')->nullable();
            $table->text('body')->nullable();

            // What it was about. All nullable - a test message is about nothing.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider', 40)->nullable();
            $table->string('provider_message_id')->nullable()->index();

            $table->enum('status', [
                'pending', 'sent', 'delivered', 'read', 'failed', 'bounced', 'cancelled',
            ])->default('pending')->index();

            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            // The log is read newest first and filtered by channel or status.
            $table->index(['channel', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
