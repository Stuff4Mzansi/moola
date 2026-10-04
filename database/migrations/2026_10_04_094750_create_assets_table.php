<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 30);
            $table->string('institution', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('asset_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('date');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['asset_id', 'date']);
        });
        Schema::create('net_worth_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('assets_cents');
            $table->unsignedBigInteger('debts_cents');
            $table->bigInteger('net_worth_cents');
            $table->json('details');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('net_worth_snapshots');
        Schema::dropIfExists('asset_valuations');
        Schema::dropIfExists('assets');
    }
};
