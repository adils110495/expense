<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assignment pivot. A person can work on several projects, and a project
     * has several people - this table is what the expense/credit forms read
     * to decide which people belong to the chosen project.
     */
    public function up(): void
    {
        Schema::create('project_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'person_id']);
            $table->index('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_person');
    }
};
