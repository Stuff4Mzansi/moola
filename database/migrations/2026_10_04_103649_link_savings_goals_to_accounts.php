<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_goals', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('savings_contributions', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('money_origin', 20)->default('existing');
        });
        Schema::table('asset_reserves', function (Blueprint $table): void {
            $table->boolean('is_automatic')->default(false);
        });
        Schema::table('asset_valuations', function (Blueprint $table): void {
            $table->unsignedBigInteger('movement_cutoff_id')->default(0);
        });
        Schema::create('asset_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('savings_contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_cents');
            $table->date('date');
            $table->timestamps();
            $table->index(['asset_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
        Schema::table('asset_valuations', function (Blueprint $table): void {
            $table->dropColumn('movement_cutoff_id');
        });
        Schema::table('asset_reserves', function (Blueprint $table): void {
            $table->dropColumn('is_automatic');
        });
        Schema::table('savings_contributions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_id');
            $table->dropColumn('money_origin');
        });
        Schema::table('savings_goals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
