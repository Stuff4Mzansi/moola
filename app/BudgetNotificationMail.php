<?php

namespace App;

use App\Mail\BudgetReminderMail;
use App\Models\Budget;
use App\Models\BudgetNotificationPreference;
use App\Models\FinancialNotification;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BudgetNotificationMail
{
    public function configure(MailSetting $settings): void
    {
        config(['mail.mailers.moola-smtp' => ['transport' => 'smtp', 'scheme' => $settings->security === 'smtps' ? 'smtps' : 'smtp', 'host' => $settings->host, 'port' => $settings->port, 'username' => $settings->username, 'password' => $settings->password, 'auto_tls' => $settings->security !== 'none', 'require_tls' => $settings->security === 'starttls', 'timeout' => 10, 'local_domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost']]);
        Mail::purge('moola-smtp');
    }

    public function test(MailSetting $settings, User $admin): void
    {
        $this->configure($settings);
        Mail::mailer('moola-smtp')->to($admin->email)->send(new BudgetReminderMail('Moola email test', 'Your SMTP settings are working. Members can enable email reminders in their budget notification preferences.', route('notifications.index'), $settings->from_address, $settings->from_name));
    }

    public function deliver(): void
    {
        Cache::store('database')->lock('notification-mail', 900)->get(function (): void {
            $settings = MailSetting::query()->find(1);
            if ($settings === null || ! $settings->enabled) {
                return;
            }
            try {
                $this->configure($settings);
            } catch (Throwable) {
                Log::warning('Could not configure notification SMTP. Check saved mail settings.');

                return;
            }
            FinancialNotification::query()->whereExists(function (\Illuminate\Database\Query\Builder $query): void {
                $query->selectRaw('1')->from('budget_notification_preferences')
                    ->whereColumn('budget_notification_preferences.user_id', 'financial_notifications.user_id')
                    ->whereColumn('budget_notification_preferences.budget_id', 'financial_notifications.budget_id')
                    ->where('budget_notification_preferences.email_enabled', true)
                    ->whereColumn('financial_notifications.id', '>', 'budget_notification_preferences.email_after_notification_id');
            })->whereNull('resolved_at')->whereNull('email_sent_at')->where('email_attempts', '<', 5)->where(function (Builder $query): void {
                $query->whereNull('email_next_attempt_at')->orWhere('email_next_attempt_at', '<=', now());
            })->orderBy('id')->limit(50)->get()->each(function (FinancialNotification $notification) use ($settings): void {
                $user = User::query()->find($notification->user_id);
                $preference = BudgetNotificationPreference::query()->where('user_id', $notification->user_id)->where('budget_id', $notification->budget_id)->first();
                if ($user === null || $preference === null || ! $preference->email_enabled || $notification->id <= $preference->email_after_notification_id || in_array($notification->type, $preference->muted_types, true)) {
                    return;
                }
                if (! Budget::visibleTo($user)->where('id', $notification->budget_id)->where('notifications_enabled', true)->exists()) {
                    return;
                }
                try {
                    Mail::mailer('moola-smtp')->to($user->email)->send(new BudgetReminderMail($notification->title, $notification->message, $notification->url(), $settings->from_address, $settings->from_name));
                    $notification->update(['email_sent_at' => now(), 'email_next_attempt_at' => null]);
                    $settings->update(['last_delivery_at' => now()]);
                } catch (Throwable) {
                    $attempts = $notification->email_attempts + 1;
                    $notification->update(['email_attempts' => $attempts, 'email_next_attempt_at' => now()->addMinutes(min(60, 5 * 2 ** ($attempts - 1)))]);
                    Log::warning('Notification email failed; delivery will retry if eligible.', ['notification_id' => $notification->id, 'attempt' => $attempts]);
                }
            });
        });
    }
}
