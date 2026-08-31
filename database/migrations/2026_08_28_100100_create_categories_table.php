<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['expense', 'credit'])->index();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();

            // A name may be reused across types (e.g. "Salary" is both).
            $table->unique(['name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
