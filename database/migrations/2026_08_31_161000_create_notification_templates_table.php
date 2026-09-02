<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One editable template per event per channel.
     *
     * Message wording is content, not code: it changes far more often than
     * the sending logic and should not need a deploy. The body holds
     * {{variable}} placeholders resolved at send time from the real records.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();

            $table->string('event', 60);
            $table->enum('channel', ['whatsapp', 'email']);

            // Email only; WhatsApp has no subject line.
            $table->string('subject')->nullable();
            $table->text('body');

            // WhatsApp only. Outside the 24 hour customer service window Meta
            // accepts pre-approved templates alone, so a free-form body is not
            // always deliverable - when this is set it is used instead.
            $table->string('whatsapp_template_name')->nullable();
            $table->string('language', 10)->default('en_US');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One template per event and channel.
            $table->unique(['event', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
