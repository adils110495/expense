<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Payment By" values, managed exactly like categories: the admin adds
     * them, and every active one appears in the expense form's dropdown.
     */
    public function up(): void
    {
        Schema::create('payment_bys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_bys');
    }
};
