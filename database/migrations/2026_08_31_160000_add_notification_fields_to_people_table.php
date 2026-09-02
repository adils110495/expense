<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where and whether to reach a partner.
     *
     * The WhatsApp number is kept apart from `phone` on purpose - the number
     * someone is called on is often not the one they use on WhatsApp, and
     * guessing wrong means messages about money go to a stranger.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('whatsapp_number', 30)->nullable()->after('phone');

            $table->boolean('whatsapp_enabled')->default(true)->after('whatsapp_number');
            $table->boolean('email_enabled')->default(true)->after('whatsapp_enabled');

            // Per-channel, per-event opt outs. A null column means "the
            // defaults", so an existing person needs no backfill and a new
            // event type does not silently arrive switched off.
            $table->json('notification_prefs')->nullable()->after('email_enabled');
        });

        // Seed the WhatsApp number from the phone number where one exists.
        // It is the best first guess, and it is editable per person.
        DB::table('people')
            ->whereNotNull('phone')
            ->update(['whatsapp_number' => DB::raw('phone')]);
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_number', 'whatsapp_enabled', 'email_enabled', 'notification_prefs',
            ]);
        });
    }
};
