<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 30);
            $table->string('institution', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('liability_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liability_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('date');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['liability_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_valuations');
        Schema::dropIfExists('liabilities');
    }
};
