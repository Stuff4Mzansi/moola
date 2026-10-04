<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_payments', function (Blueprint $table): void {
            $table->boolean('interest_is_estimated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('debt_payments', function (Blueprint $table): void {
            $table->dropColumn('interest_is_estimated');
        });
    }
};
