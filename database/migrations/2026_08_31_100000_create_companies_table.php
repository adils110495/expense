<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            // Soft deletes, like every other business table here - a company
            // that once carried transactions must stay resolvable.
            $table->softDeletes();

            // A plain index, not unique: MySQL treats every NULL deleted_at as
            // distinct, so a (name, deleted_at) unique would not actually stop
            // two live rows sharing a name. Uniqueness among the non deleted
            // rows is enforced in CompanyRequest instead.
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
