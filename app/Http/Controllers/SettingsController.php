<?php

namespace App\Http\Controllers;

use App\BudgetNotificationMail;
use App\CurrencySettings;
use App\Http\Requests\MailSettingsRequest;
use App\Models\AppSetting;
use App\Models\FinancialNotification;
use App\Models\MailSetting;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['mailSettings' => MailSetting::query()->find(1) ?? new MailSetting(['enabled' => false, 'port' => 587, 'security' => 'starttls', 'from_name' => config('app.name')]), 'failedEmails' => FinancialNotification::query()->whereNull('resolved_at')->whereNull('email_sent_at')->where('email_attempts', '>', 0)->count(), 'currencyCode' => app(CurrencySettings::class)->code(), 'currencies' => CurrencySettings::CURRENCIES]);
    }

    public function updateCurrency(Request $request, CurrencySettings $currencySettings): RedirectResponse
    {
        $data = $request->validate(['currency' => ['required', Rule::in(array_keys(CurrencySettings::CURRENCIES))]]);

        DB::transaction(function () use ($data, $currencySettings): void {
            AppSetting::query()->updateOrCreate(['id' => 1], ['currency' => $data['currency']]);
            Subscription::query()->update(['currency' => $data['currency']]);
            $currencySettings->select($data['currency']);
        });

        return to_route('settings.edit')->with('status', 'Currency saved. Existing amounts were not converted.');
    }

    public function update(MailSettingsRequest $request): RedirectResponse
    {
        Cache::store('database')->lock('mail-settings', 30)->block(5, function () use ($request): void {
            DB::transaction(function () use ($request): void {
                $settings = MailSetting::query()->find(1) ?? new MailSetting;
                $settings->id = 1;
                $data = $request->safe()->except(['password', 'clear_password']);
                $settings->fill($data);
                if ($request->boolean('clear_password')) {
                    $settings->password = null;
                } elseif ($request->filled('password')) {
                    $settings->password = $request->validated('password');
                }
                $settings->last_test_at = null;
                $settings->last_test_success = null;
                $settings->save();
                FinancialNotification::query()->whereNull('email_sent_at')->where('email_attempts', '>', 0)->update(['email_attempts' => 0, 'email_next_attempt_at' => null]);
            });
        });

        return to_route('settings.edit')->with('status', 'Email settings saved. Send a test email to check the connection.');
    }

    public function test(Request $request, BudgetNotificationMail $delivery): RedirectResponse
    {
        $settings = MailSetting::query()->find(1);
        if ($settings === null || ! $settings->enabled) {
            return to_route('settings.edit')->with('mail_error', 'Save and enable SMTP settings before sending a test email.');
        }
        try {
            $delivery->test($settings, $request->user());
            $settings->update(['last_test_at' => now(), 'last_test_success' => true]);

            return to_route('settings.edit')->with('status', 'Test email sent to your account email address. Check your inbox and spam folder.');
        } catch (Throwable) {
            $settings->update(['last_test_at' => now(), 'last_test_success' => false]);

            return to_route('settings.edit')->with('mail_error', 'Could not send the test email. Check the SMTP host, port, security, credentials, and sender address.');
        }
    }
}
