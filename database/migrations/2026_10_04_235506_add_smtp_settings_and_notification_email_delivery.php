<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('security', 20)->default('starttls');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->default('Moola');
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
        });
        Schema::table('budget_notification_preferences', function (Blueprint $table): void {
            $table->boolean('email_enabled')->default(false);
            $table->unsignedBigInteger('email_after_notification_id')->default(0);
        });
        Schema::table('financial_notifications', function (Blueprint $table): void {
            $table->timestamp('email_sent_at')->nullable();
            $table->unsignedTinyInteger('email_attempts')->default(0);
            $table->timestamp('email_next_attempt_at')->nullable();
            $table->index(['email_sent_at', 'email_next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_notifications', function (Blueprint $table): void {
            $table->dropIndex(['email_sent_at', 'email_next_attempt_at']);
            $table->dropColumn(['email_sent_at', 'email_attempts', 'email_next_attempt_at']);
        });
        Schema::table('budget_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn(['email_enabled', 'email_after_notification_id']);
        });
        Schema::dropIfExists('mail_settings');
    }
};
