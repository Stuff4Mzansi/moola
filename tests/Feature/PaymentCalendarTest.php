<?php

use App\BillingFrequency;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->budget = Budget::factory()->create(['user_id' => $this->owner->id, 'name' => 'My calendar budget']);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
    $this->category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id]);
});

test('calendar combines remaining income subscriptions recurring bills and debt minima without writes or duplicate linked debt events', function () {
    Subscription::factory()->create(['user_id' => $this->owner->id, 'name' => 'Streaming', 'next_billing_date' => '2026-10-10', 'amount_cents' => 10000]);
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'name' => 'Electricity', 'start_date' => '2026-10-11', 'amount_cents' => 30000]);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'name' => 'Car loan', 'due_anchor' => '2026-10-12', 'minimum_payment_cents' => 20000]);
    DebtPayment::factory()->create(['debt_id' => $debt->id, 'date' => '2026-10-03', 'amount_cents' => 5000, 'interest_cents' => 0]);
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'debt_id' => $debt->id, 'name' => 'Car loan duplicate schedule', 'start_date' => '2026-10-12', 'amount_cents' => 20000]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000, 'received_cents' => 25000, 'expected_date' => '2026-10-15']);

    $response = $this->get(route('payment-calendar.index'))->assertOk()->assertSee('Payment calendar')->assertSee('Upcoming agenda');
    expect($response->viewData('events'))->toHaveCount(4);
    expect($response->viewData('incoming'))->toBe(75000)->and($response->viewData('outgoing'))->toBe(55000);
    expect($response->viewData('events')->where('type', 'debt')->sole()['amount'])->toBe(15000);
    expect(BudgetCommitment::query()->count())->toBe(0)->and(BudgetRecurringCharge::query()->count())->toBe(0)->and($this->period->fresh()->version)->toBe(1);
});

test('recorded subscription and recurring payments are excluded including early payments and restored soft deleted payments', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'next_billing_date' => '2026-10-10']);
    $commitment = BudgetCommitment::factory()->create(['subscription_id' => $subscription->id, 'budget_period_id' => $this->period->id, 'scheduled_date' => '2026-10-10']);
    $payment = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_commitment_id' => $commitment->id, 'date' => '2026-10-04']);
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-10-11']);
    $charge = BudgetRecurringCharge::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_recurring_expense_id' => $expense->id, 'scheduled_date' => '2026-10-11']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_recurring_charge_id' => $charge->id, 'date' => '2026-10-04']);
    $this->get(route('payment-calendar.index'))->assertViewHas('events', fn (Collection $events): bool => $events->isEmpty());
    $payment->delete();
    $this->get(route('payment-calendar.index'))->assertViewHas('events', fn (Collection $events): bool => $events->where('type', 'subscription')->count() === 1);
    $payment->restore();
    $this->get(route('payment-calendar.index'))->assertViewHas('events', fn (Collection $events): bool => $events->isEmpty());
});

test('calendar immediately reflects changed subscription and recurring schedules and ignores inactive snapshots', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'next_billing_date' => '2026-10-10', 'amount_cents' => 10000]);
    BudgetCommitment::factory()->create(['subscription_id' => $subscription->id, 'budget_period_id' => $this->period->id, 'scheduled_date' => '2026-10-10']);
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-10-11']);
    BudgetRecurringCharge::factory()->create(['budget_period_id' => $this->period->id, 'budget_recurring_expense_id' => $expense->id, 'scheduled_date' => '2026-10-11']);
    $subscription->update(['next_billing_date' => '2026-10-20', 'amount_cents' => 15000]);
    $expense->update(['is_active' => false]);
    $response = $this->get(route('payment-calendar.index'))->assertOk();
    expect($response->viewData('events')->sole())->toMatchArray(['date' => '2026-10-20', 'amount' => 15000, 'type' => 'subscription']);
    $subscription->update(['status' => SubscriptionStatus::Paused]);
    $this->get(route('payment-calendar.index'))->assertViewHas('events', fn (Collection $events): bool => $events->isEmpty());
});

test('calendar includes every weekly occurrence and inclusive month end while filters update totals', function () {
    Subscription::factory()->create(['user_id' => $this->owner->id, 'billing_frequency' => BillingFrequency::Weekly, 'next_billing_date' => '2026-10-10', 'amount_cents' => 1000]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_date' => '2026-10-31', 'expected_cents' => 50000]);
    $response = $this->get(route('payment-calendar.index'))->assertOk();
    expect($response->viewData('events'))->toHaveCount(5)->and($response->viewData('outgoing'))->toBe(4000);
    $filtered = $this->get(route('payment-calendar.index', ['type' => 'income']))->assertOk();
    expect($filtered->viewData('events')->sole()['date'])->toBe('2026-10-31')->and($filtered->viewData('outgoing'))->toBe(0);
});

test('calendar shows shared budget income and schedules without exposing unrelated private subscriptions or debts', function () {
    $other = User::factory()->create();
    $shared = Budget::factory()->household()->create(['user_id' => $other->id, 'name' => 'Shared family']);
    $shared->members()->attach($this->owner, ['role' => 'viewer']);
    $period = BudgetPeriod::factory()->create(['budget_id' => $shared->id]);
    BudgetIncome::factory()->create(['budget_period_id' => $period->id, 'name' => 'Shared income', 'expected_date' => '2026-10-10']);
    BudgetRecurringExpense::factory()->create(['budget_id' => $shared->id, 'name' => 'Shared rent', 'start_date' => '2026-10-12']);
    Subscription::factory()->create(['user_id' => $other->id, 'name' => 'Private subscription', 'next_billing_date' => '2026-10-13']);
    Debt::factory()->create(['user_id' => $other->id, 'name' => 'Private debt', 'due_anchor' => '2026-10-14']);
    $this->get(route('payment-calendar.index'))->assertOk()->assertSee('Shared rent')->assertSee('Shared income')->assertDontSee('Private subscription')->assertDontSee('Private debt');
    $shared->members()->detach($this->owner);
    $this->get(route('payment-calendar.index'))->assertOk()->assertDontSee('Shared rent')->assertDontSee('Shared income');
});

test('shared subscription forecasts count once across visible overlapping budgets', function () {
    $other = User::factory()->create();
    Subscription::factory()->create(['user_id' => $other->id, 'name' => 'Shared streaming', 'next_billing_date' => '2026-10-10', 'amount_cents' => 10000]);
    foreach ([1, 2] as $index) {
        $budget = Budget::factory()->household()->create(['user_id' => $other->id, 'include_subscriptions' => true]);
        $budget->members()->attach($this->owner, ['role' => 'viewer']);
        BudgetPeriod::factory()->create(['budget_id' => $budget->id]);
    }
    $response = $this->get(route('payment-calendar.index'))->assertOk();
    expect($response->viewData('events'))->toHaveCount(1)->and($response->viewData('outgoing'))->toBe(10000);
});

test('shared debt schedules expose only the budget estimate and do not expose private payment adjustments', function () {
    $other = User::factory()->create();
    $shared = Budget::factory()->household()->create(['user_id' => $other->id]);
    $shared->members()->attach($this->owner, ['role' => 'viewer']);
    BudgetPeriod::factory()->create(['budget_id' => $shared->id]);
    $debt = Debt::factory()->create(['user_id' => $other->id, 'name' => 'Private debt title', 'minimum_payment_cents' => 20000, 'due_anchor' => '2026-10-15']);
    DebtPayment::factory()->create(['debt_id' => $debt->id, 'amount_cents' => 5000, 'interest_cents' => 0, 'date' => '2026-10-03']);
    BudgetRecurringExpense::factory()->create(['budget_id' => $shared->id, 'debt_id' => $debt->id, 'name' => 'Shared loan payment', 'amount_cents' => 20000, 'start_date' => '2026-10-15']);
    $response = $this->get(route('payment-calendar.index'))->assertOk()->assertSee('Shared loan payment')->assertDontSee('Private debt title');
    expect($response->viewData('events')->sole())->toMatchArray(['type' => 'debt', 'amount' => 20000]);
});

test('calendar warns about undated income and includes all subscriptions in the household currency', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_date' => null]);
    Subscription::factory()->create(['user_id' => $this->owner->id, 'currency' => 'USD', 'next_billing_date' => '2026-10-10', 'amount_cents' => 15999]);
    $response = $this->get(route('payment-calendar.index'))->assertOk()->assertSee('need an expected date')->assertDontSee('No exchange rate is assumed.')->assertSee('R 159.99');
    expect($response->viewData('events'))->toHaveCount(1)->and($response->viewData('undatedIncome'))->toBe(1)->and($response->viewData('outgoing'))->toBe(15999);
});

test('calendar navigation validates months and event types and renders leap year month end', function () {
    foreach (['2026-09', '2027-10', 'bad', '2026-13'] as $month) {
        $this->getJson(route('payment-calendar.index', ['month' => $month]))->assertUnprocessable()->assertJsonValidationErrors('month');
    }
    $this->getJson(route('payment-calendar.index', ['type' => 'invalid']))->assertUnprocessable();
    $this->travelTo(CarbonImmutable::parse('2028-02-01 12:00:00'));
    Subscription::factory()->create(['user_id' => $this->owner->id, 'next_billing_date' => '2028-01-31']);
    $response = $this->get(route('payment-calendar.index'))->assertOk();
    expect($response->viewData('events')->sole()['date'])->toBe('2028-02-29');
    expect($response->viewData('days')[0]['date']->dayOfWeekIso)->toBe(1);
});

test('application administrator roles do not grant calendar access to other users private records', function (UserRole $role) {
    $other = User::factory()->create();
    Subscription::factory()->create(['user_id' => $other->id, 'name' => 'Hidden subscription']);
    $this->owner->update(['role' => $role]);
    $this->get(route('payment-calendar.index'))->assertOk()->assertDontSee('Hidden subscription');
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('calendar requires authentication', function () {
    auth()->logout();
    $this->get(route('payment-calendar.index'))->assertRedirect(route('login'));
});
