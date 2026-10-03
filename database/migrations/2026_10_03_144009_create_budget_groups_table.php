<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('percentage_basis_points')->nullable();
            $table->timestamps();
            $table->unique(['budget_period_id', 'name']);
        });
        Schema::table('budget_categories', function (Blueprint $table): void {
            $table->foreignId('budget_group_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('budget_group_id');
        });
        Schema::dropIfExists('budget_groups');
    }
};
