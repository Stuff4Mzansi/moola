<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table): void {
            $table->boolean('notifications_enabled')->default(true);
            $table->unsignedTinyInteger('notification_threshold')->default(80);
            $table->unsignedTinyInteger('reminder_days')->default(3);
            $table->unsignedTinyInteger('period_reminder_days')->default(3);
        });
        Schema::create('budget_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->json('muted_types');
            $table->timestamps();
            $table->unique(['user_id', 'budget_id']);
        });
        Schema::create('financial_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_period_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('type', 40);
            $table->string('title');
            $table->text('message');
            $table->string('tab', 30);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'read_at', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_notifications');
        Schema::dropIfExists('budget_notification_preferences');
        Schema::table('budgets', function (Blueprint $table): void {
            $table->dropColumn(['notifications_enabled', 'notification_threshold', 'reminder_days', 'period_reminder_days']);
        });
    }
};
