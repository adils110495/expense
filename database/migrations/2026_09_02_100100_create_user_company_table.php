<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which companies a panel user may see.
 *
 * Many-to-many: a user can be mapped to several companies, and a company can
 * have several users. This is the authorisation boundary - every query in the
 * panel is filtered by the company ids a user is mapped to here, so a row in
 * this table is the only thing that grants access to a company's money.
 *
 * Checked against the existing schema first: no user_companies, company_users,
 * users_companies, user_company or memberships table exists, so this pivot is
 * genuinely new rather than a duplicate of something already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_company')) {
            return;
        }

        Schema::create('user_company', function (Blueprint $table) {
            $table->id();

            // Cascade on both sides: a deleted user or company leaves no
            // mapping behind that could later grant access to a recycled id.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // The same company cannot be mapped to the same user twice.
            $table->unique(['user_id', 'company_id']);
            // Lookups go both ways: "which companies may this user see" and
            // "who can see this company". The unique index above covers the
            // first, this one the second.
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_company');
    }
};
