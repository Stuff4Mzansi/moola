<?php

use App\BudgetRecurringExpenses;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetGroup;
use App\Models\BudgetIncome;
use App\Models\BudgetNotificationPreference;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\FinancialNotification;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-04 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->budget = Budget::factory()->household()->create(['user_id' => $this->owner->id, 'include_subscriptions' => true]);
    $this->budget->members()->attach($this->member, ['role' => 'viewer']);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
    $this->category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Groceries', 'kind' => 'custom', 'allocated_cents' => 100000]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $this->actingAs($this->owner);
});

test('budget category and group warnings reach only eligible members once per level and period', function () {
    $outsider = User::factory()->create();
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'percentage_basis_points' => 10000]);
    $this->category->budget_group_id = $group->id;
    $this->category->save();
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 85000, 'date' => now()]);
    $this->artisan('moola:notify')->assertSuccessful();
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->count())->toBe(6);
    expect(FinancialNotification::query()->where('user_id', $outsider->id)->count())->toBe(0);
    expect(FinancialNotification::query()->where('type', 'group_limit')->count())->toBe(2);
    $expense->update(['amount_cents' => 110000]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(6);
    expect(FinancialNotification::query()->whereNotNull('resolved_at')->count())->toBe(6);
    expect(FinancialNotification::query()->whereNull('resolved_at')->first()->message)->toContain('R 100.00 over');
    $expense->delete();
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(0);
});

test('subscription reminders resolve when paid and cancelled private subscriptions never leak', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'name' => 'Streaming', 'next_billing_date' => '2026-10-06', 'status' => 'active', 'billing_frequency' => 'monthly']);
    Subscription::factory()->create(['user_id' => $this->member->id, 'name' => 'Private subscription', 'next_billing_date' => '2026-10-06', 'status' => 'active']);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->count())->toBe(2);
    $charge = $this->period->commitments()->where('subscription_id', $subscription->id)->sole();
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_commitment_id' => $charge->id, 'amount_cents' => 100]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(0);
    $this->actingAs($this->member)->get(route('notifications.index'))->assertOk()->assertDontSee('Private subscription');
});

test('recurring reminders become overdue once and stop when the schedule is paused', function () {
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Groceries', 'start_date' => '2026-10-06', 'billing_frequency' => 'monthly', 'is_active' => true]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->where('type', 'recurring')->count())->toBe(2);
    $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
    $this->artisan('moola:notify')->assertSuccessful();
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->where('type', 'recurring')->whereNull('resolved_at')->count())->toBe(2);
    expect(FinancialNotification::query()->whereNull('resolved_at')->first()->title)->toContain('overdue');
    $expense->update(['is_active' => false]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(0);
});

test('member preferences mute only that member and viewers cannot change shared rules', function () {
    $this->actingAs($this->member)->from(route('budgets.index', ['period' => $this->period->id, 'tab' => 'notifications']))->post(route('notifications.preferences', $this->budget), ['enabled_types' => ['recurring']])->assertRedirect();
    expect(BudgetNotificationPreference::query()->sole()->muted_types)->toContain('budget_limit');
    $this->post(route('notifications.settings', $this->budget), ['notifications_enabled' => 0, 'notification_threshold' => 80, 'reminder_days' => 3, 'period_reminder_days' => 3])->assertForbidden();
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 85000]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->where('user_id', $this->member->id)->count())->toBe(0);
    expect(FinancialNotification::query()->where('user_id', $this->owner->id)->count())->toBe(2);
});

test('notification history opening and read state are isolated and membership revocation hides old alerts', function () {
    $mine = FinancialNotification::factory()->create(['user_id' => $this->owner->id, 'budget_id' => $this->budget->id, 'budget_period_id' => $this->period->id]);
    $theirs = FinancialNotification::factory()->create(['user_id' => $this->member->id, 'budget_id' => $this->budget->id, 'budget_period_id' => $this->period->id]);
    $this->get(route('notifications.feed'))->assertOk()->assertJsonStructure(['html']);
    $this->post(route('notifications.open', $theirs->id))->assertNotFound();
    $this->post(route('notifications.open', $mine->id))->assertRedirect(route('budgets.index', ['period' => $this->period->id, 'tab' => 'overview']));
    expect($mine->fresh()->read_at)->not->toBeNull()->and($theirs->fresh()->read_at)->toBeNull();
    $this->budget->members()->detach($this->member);
    $this->actingAs($this->member)->post(route('notifications.open', $theirs->id))->assertNotFound();
    $this->get(route('notifications.index'))->assertOk()->assertDontSee($theirs->title);
});

test('period ending rules and disabled budgets are respected and settings require budget permission', function () {
    $this->period->update(['end_date' => '2026-10-06']);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->where('type', 'period_end')->count())->toBe(2);
    $settings = ['notifications_enabled' => 0, 'notification_threshold' => 75, 'reminder_days' => 2, 'period_reminder_days' => 1];
    $this->post(route('notifications.settings', $this->budget), $settings)->assertRedirect();
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(0);
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->post(route('notifications.preferences', $this->budget), [])->assertForbidden();
    $this->post(route('notifications.settings', $this->budget), $settings)->assertForbidden();
});

test('budget page updates generate warnings immediately and render the notifications tab and bell', function () {
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 85000]);
    $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'notifications']))->assertOk()->assertSee('Save my preferences')->assertSee('Save budget rules')->assertSee('data-notification-feed', false);
    expect(FinancialNotification::query()->count())->toBe(4);
});

test('read warnings keep current amounts without becoming unread or duplicating', function () {
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 85000]);
    $this->artisan('moola:notify')->assertSuccessful();
    $notification = FinancialNotification::query()->where('user_id', $this->owner->id)->where('type', 'budget_limit')->sole();
    $this->post(route('notifications.open', $notification->id))->assertRedirect();
    $expense->update(['amount_cents' => 90000]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect($notification->fresh()->message)->toContain('R 100.00 remains')->and($notification->fresh()->read_at)->not->toBeNull();
    expect(FinancialNotification::query()->count())->toBe(4);
});

test('cancelled subscriptions resolve reminders even after their scheduled date', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'next_billing_date' => '2026-10-06', 'status' => 'active', 'billing_frequency' => 'monthly']);
    $this->artisan('moola:notify')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
    $subscription->update(['status' => 'cancelled']);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(0);
});

test('personal budgets never notify attached members and far future reminders stay quiet', function () {
    $this->budget->update(['scope' => 'personal']);
    Subscription::factory()->create(['user_id' => $this->owner->id, 'next_billing_date' => '2026-10-20', 'status' => 'active', 'billing_frequency' => 'monthly']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 85000]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->where('type', 'subscription')->count())->toBe(0);
    expect(FinancialNotification::query()->where('user_id', $this->member->id)->count())->toBe(0);
    expect(FinancialNotification::query()->where('user_id', $this->owner->id)->count())->toBe(2);
});

test('editors can configure rules invalid thresholds are rejected and mark all read stays private', function () {
    $this->budget->members()->updateExistingPivot($this->member, ['role' => 'editor']);
    $settings = ['notifications_enabled' => 1, 'notification_threshold' => 75, 'reminder_days' => 2, 'period_reminder_days' => 1];
    $this->actingAs($this->member)->post(route('notifications.settings', $this->budget), $settings)->assertRedirect();
    expect($this->budget->fresh()->notification_threshold)->toBe(75);
    $this->post(route('notifications.settings', $this->budget), [...$settings, 'notification_threshold' => 101])->assertSessionHasErrors('notification_threshold');
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 76000]);
    $this->artisan('moola:notify')->assertSuccessful();
    $this->post(route('notifications.read-all'))->assertRedirect();
    expect(FinancialNotification::query()->where('user_id', $this->member->id)->whereNull('read_at')->count())->toBe(0);
    expect(FinancialNotification::query()->where('user_id', $this->owner->id)->whereNull('read_at')->count())->toBe(2);
});

test('overdue reminders in a recently ended period stop when their recurring schedule is paused', function () {
    $this->period->update(['end_date' => '2026-10-06']);
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Groceries', 'start_date' => '2026-10-06', 'billing_frequency' => 'monthly', 'is_active' => true]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect($this->period->recurringCharges()->count())->toBe(1);
    expect(count(app(BudgetRecurringExpenses::class)->expected($this->period->fresh())))->toBe(1);
    expect(FinancialNotification::query()->where('type', 'recurring')->whereNull('resolved_at')->count())->toBe(2);
    $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
    expect(count(app(BudgetRecurringExpenses::class)->expected($this->period->fresh())))->toBe(1);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->where('type', 'recurring')->whereNull('resolved_at')->count())->toBe(2);
    $expense->update(['is_active' => false]);
    $this->artisan('moola:notify')->assertSuccessful();
    expect(FinancialNotification::query()->whereNull('resolved_at')->count())->toBe(0);
});
