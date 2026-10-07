<?php

use App\BudgetNotificationMail;
use App\BudgetNotifications;
use App\Mail\BudgetReminderMail;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetIncome;
use App\Models\BudgetNotificationPreference;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\FinancialNotification;
use App\Models\MailSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
    $this->settings = ['enabled' => 1, 'host' => 'smtp.example.com', 'port' => 587, 'security' => 'starttls', 'username' => 'smtp-user', 'password' => 'private-smtp-password', 'from_address' => 'reminders@example.com', 'from_name' => 'Moola'];
});

test('only admins can view configure and test SMTP settings', function () {
    $this->get(route('settings.edit'))->assertOk()->assertSee('Email &amp; SMTP', false);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('settings.edit'))->assertOk();
    $member = User::factory()->create();
    $this->actingAs($member)->get(route('settings.edit'))->assertForbidden();
    $this->put(route('settings.mail.update'), $this->settings)->assertForbidden();
    $this->put(route('settings.currency.update'), ['currency' => 'USD'])->assertForbidden();
    $this->post(route('settings.mail.test'))->assertForbidden();
    expect(MailSetting::query()->count())->toBe(0);
});

test('admin can change the household currency without converting stored subscription amounts', function () {
    $subscription = Subscription::factory()->for($this->admin)->create(['amount_cents' => 15999, 'currency' => 'ZAR']);

    $this->put(route('settings.currency.update'), ['currency' => 'USD'])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Currency saved. Existing amounts were not converted.');

    expect(AppSetting::query()->sole()->currency)->toBe('USD')
        ->and($subscription->fresh()->currency)->toBe('USD')
        ->and($subscription->fresh()->amount_cents)->toBe(15999);

    $this->get(route('subscriptions.show', $subscription))->assertOk()->assertSee('$159.99');
});

test('SMTP passwords are encrypted retained when blank removable and never displayed or flashed', function () {
    $this->put(route('settings.mail.update'), $this->settings)->assertRedirect(route('settings.edit'));
    $settings = MailSetting::query()->sole();
    $encrypted = $settings->getRawOriginal('password');
    expect($settings->password)->toBe('private-smtp-password')->and($encrypted)->not->toBe('private-smtp-password');
    expect($settings->toArray())->not->toHaveKey('password');
    $this->get(route('settings.edit'))->assertOk()->assertDontSee('private-smtp-password')->assertSee('Saved; leave blank to keep');
    $this->put(route('settings.mail.update'), [...$this->settings, 'password' => ''])->assertRedirect();
    expect($settings->fresh()->getRawOriginal('password'))->toBe($encrypted);
    $this->put(route('settings.mail.update'), [...$this->settings, 'port' => 99999])->assertSessionHasErrors('port');
    expect(session('_old_input.password'))->toBeNull();
    $this->put(route('settings.mail.update'), [...$this->settings, 'password' => '', 'clear_password' => 1])->assertRedirect();
    expect($settings->fresh()->password)->toBeNull();
});

test('transport configuration respects strict STARTTLS implicit TLS and relay mode', function () {
    $settings = MailSetting::factory()->create(['password' => 'saved-secret']);
    app(BudgetNotificationMail::class)->configure($settings);
    expect(config('mail.mailers.moola-smtp.scheme'))->toBe('smtp')->and(config('mail.mailers.moola-smtp.require_tls'))->toBeTrue()->and(config('mail.mailers.moola-smtp.password'))->toBe('saved-secret');
    $transport = Mail::mailer('moola-smtp')->getSymfonyTransport();
    expect($transport->isAutoTls())->toBeTrue()->and($transport->getStream()->getTimeout())->toBe(10.0);
    $settings->security = 'smtps';
    $settings->port = 465;
    app(BudgetNotificationMail::class)->configure($settings);
    expect(config('mail.mailers.moola-smtp.scheme'))->toBe('smtps');
    expect(Mail::mailer('moola-smtp')->getSymfonyTransport()->getStream()->isTLS())->toBeTrue();
    $settings->security = 'none';
    app(BudgetNotificationMail::class)->configure($settings);
    expect(config('mail.mailers.moola-smtp.auto_tls'))->toBeFalse()->and(config('mail.mailers.moola-smtp.require_tls'))->toBeFalse();
    expect(Mail::mailer('moola-smtp')->getSymfonyTransport()->isAutoTls())->toBeFalse();
});

test('test email uses saved sender and reaches only the logged in admin', function () {
    Mail::fake();
    $this->put(route('settings.mail.update'), $this->settings)->assertRedirect();
    $this->post(route('settings.mail.test'), ['recipient' => 'outsider@example.com'])->assertRedirect()->assertSessionHas('status');
    Mail::assertSent(BudgetReminderMail::class, fn (BudgetReminderMail $mail): bool => $mail->hasTo($this->admin->email) && $mail->envelope()->from->address === 'reminders@example.com');
    Mail::assertSentCount(1);
    expect(MailSetting::query()->sole()->last_test_success)->toBeTrue();
});

test('SMTP test failures are actionable without leaking credentials or transport details', function () {
    MailSetting::factory()->create(['enabled' => true]);
    Mail::shouldReceive('purge')->once()->with('moola-smtp');
    Mail::shouldReceive('mailer')->once()->with('moola-smtp')->andThrow(new RuntimeException('AUTH credentials private-smtp-password'));
    $response = $this->post(route('settings.mail.test'));
    $response->assertRedirect()->assertSessionHas('mail_error');
    expect(session('mail_error'))->not->toContain('private-smtp-password');
    expect(MailSetting::query()->sole()->last_test_success)->toBeFalse();
});

test('scheduled warnings send opted in email once and preserve in app notifications', function () {
    Mail::fake();
    MailSetting::factory()->create(['enabled' => true]);
    $period = BudgetPeriod::factory()->create();
    $category = BudgetCategory::factory()->create(['budget_period_id' => $period->id, 'allocated_cents' => 100000]);
    BudgetIncome::factory()->create(['budget_period_id' => $period->id, 'expected_cents' => 100000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $period->id, 'budget_category_id' => $category->id, 'amount_cents' => 85000]);
    BudgetNotificationPreference::factory()->create(['user_id' => $period->budget->user_id, 'budget_id' => $period->budget_id, 'email_enabled' => true]);
    $this->artisan('moola:notify')->assertSuccessful();
    $this->artisan('moola:notify')->assertSuccessful();
    Mail::assertSentCount(2);
    expect(FinancialNotification::query()->count())->toBe(2);
    expect(FinancialNotification::query()->whereNotNull('email_sent_at')->count())->toBe(2);
    expect(FinancialNotification::query()->whereNull('read_at')->count())->toBe(2);
});

test('opting into email excludes existing alerts and preferences remain private', function () {
    Mail::fake();
    MailSetting::factory()->create(['enabled' => true]);
    $old = FinancialNotification::factory()->create(['user_id' => $this->admin->id]);
    $period = BudgetPeriod::query()->findOrFail($old->budget_period_id);
    $period->budget->user_id = $this->admin->id;
    $period->budget->save();
    $this->post(route('notifications.preferences', $period->budget), ['enabled_types' => array_keys(BudgetNotifications::TYPES), 'email_enabled' => 1])->assertRedirect();
    $new = FinancialNotification::factory()->create(['user_id' => $this->admin->id, 'budget_id' => $period->budget_id, 'budget_period_id' => $period->id]);
    app(BudgetNotificationMail::class)->deliver();
    Mail::assertSentCount(1);
    expect($old->fresh()->email_sent_at)->toBeNull()->and($new->fresh()->email_sent_at)->not->toBeNull();
});

test('resolved muted and inaccessible alerts never send email', function () {
    Mail::fake();
    MailSetting::factory()->create(['enabled' => true]);
    $notification = FinancialNotification::factory()->create();
    BudgetNotificationPreference::factory()->create(['user_id' => $notification->user_id, 'budget_id' => $notification->budget_id, 'email_enabled' => true, 'muted_types' => ['budget_limit']]);
    app(BudgetNotificationMail::class)->deliver();
    Mail::assertNothingSent();
    $preference = BudgetNotificationPreference::query()->sole();
    $preference->update(['muted_types' => []]);
    $notification->update(['resolved_at' => now()]);
    app(BudgetNotificationMail::class)->deliver();
    Mail::assertNothingSent();
    $notification->update(['resolved_at' => null, 'user_id' => $this->admin->id]);
    $preference->update(['user_id' => $this->admin->id]);
    app(BudgetNotificationMail::class)->deliver();
    Mail::assertNothingSent();
});

test('failed delivery preserves the alert waits before retry and resets after settings are corrected', function () {
    $settings = MailSetting::factory()->create(['enabled' => true]);
    $notification = FinancialNotification::factory()->create();
    BudgetNotificationPreference::factory()->create(['user_id' => $notification->user_id, 'budget_id' => $notification->budget_id, 'email_enabled' => true]);
    Mail::shouldReceive('purge')->twice();
    Mail::shouldReceive('mailer')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    app(BudgetNotificationMail::class)->deliver();
    expect($notification->fresh()->email_attempts)->toBe(1)->and($notification->fresh()->email_sent_at)->toBeNull()->and($notification->fresh()->resolved_at)->toBeNull();
    app(BudgetNotificationMail::class)->deliver();
    expect($notification->fresh()->email_attempts)->toBe(1);
    $this->put(route('settings.mail.update'), $this->settings)->assertRedirect();
    expect($notification->fresh()->email_attempts)->toBe(0)->and($notification->fresh()->email_next_attempt_at)->toBeNull();
});

test('email delivery stays disabled until configured and members cannot opt in early', function () {
    Mail::fake();
    $period = BudgetPeriod::factory()->create(['budget_id' => Budget::factory()->create(['user_id' => $this->admin->id])->id]);
    $this->post(route('notifications.preferences', $period->budget), ['enabled_types' => ['recurring'], 'email_enabled' => 1])->assertSessionHasErrors('email_enabled');
    $this->post(route('settings.mail.test'))->assertSessionHas('mail_error');
    app(BudgetNotificationMail::class)->deliver();
    Mail::assertNothingSent();
});

test('email view renders escaped reminder content and the app link', function () {
    $mail = new BudgetReminderMail('Groceries warning', 'ZAR 850.00 spent. <script>alert(1)</script>', 'https://money.example.com/budgets?period=1', 'reminders@example.com', 'Moola');
    $html = $mail->render();
    expect($html)->toContain('Groceries warning')->toContain('https://money.example.com/budgets?period=1')->not->toContain('<script>alert(1)</script>');
});

test('temporarily disabled SMTP preserves existing member email preferences', function () {
    Mail::fake();
    $settings = MailSetting::factory()->create(['enabled' => true]);
    $period = BudgetPeriod::factory()->create(['budget_id' => Budget::factory()->create(['user_id' => $this->admin->id])->id]);
    $payload = ['enabled_types' => array_keys(BudgetNotifications::TYPES), 'email_enabled' => 1];
    $this->post(route('notifications.preferences', $period->budget), $payload)->assertRedirect();
    $settings->update(['enabled' => false]);
    $this->get(route('budgets.index', ['period' => $period->id]))->assertOk()->assertSee('Email delivery is paused');
    $this->post(route('notifications.preferences', $period->budget), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect(BudgetNotificationPreference::query()->sole()->email_enabled)->toBeTrue();
    app(BudgetNotificationMail::class)->deliver();
    Mail::assertNothingSent();
});
