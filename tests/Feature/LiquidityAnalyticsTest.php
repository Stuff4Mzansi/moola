<?php

use App\LiquidityAnalytics;
use App\Models\Asset;
use App\Models\AssetReserve;
use App\Models\AssetValuation;
use App\Models\Budget;
use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\LiquidityPreference;
use App\Models\SavingsGoal;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->asset = Asset::factory()->create(['user_id' => $this->owner->id, 'liquidity' => 'immediate', 'withdrawal_cost_cents' => 1000]);
    AssetValuation::factory()->create(['asset_id' => $this->asset->id, 'amount_cents' => 100000, 'date' => '2026-10-04']);
    $this->budget = Budget::factory()->create(['user_id' => $this->owner->id]);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
});

function reserveInput(array $changes = []): array
{
    return [...['purpose' => 'emergency', 'amount' => '200', 'form_kind' => 'reserve'], ...$changes];
}

test('cash excludes uncertain sale notice and unclassified assets and includes dated unlocks after costs', function () {
    AssetReserve::factory()->create(['asset_id' => $this->asset->id, 'amount_cents' => 20000]);
    foreach (['unknown', 'sale', 'delayed', 'immediate', 'dated'] as $kind) {
        $asset = Asset::factory()->create(['user_id' => $this->owner->id, 'liquidity' => $kind, 'value_uncertain' => $kind === 'immediate', 'available_date' => $kind === 'dated' ? '2026-10-10' : null]);
        AssetValuation::factory()->create(['asset_id' => $asset->id, 'amount_cents' => 50000, 'date' => '2026-10-04']);
    }
    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['accessible'])->toBe(99000)->and($data['protected'])->toBe(20000)->and($data['free'])->toBe(79000)->and($data['unknown'])->toBe(1);
    expect($data['events']->where('source', 'unlock')->sole()['amount'])->toBe(50000)->and($data['daily']->last()['closing'])->toBe(129000);
    $this->get(route('net-worth.index', ['tab' => 'liquidity']))->assertOk()->assertSee('data-liquidity-chart', false)->assertSee('data-confirm-title="Release reserve?"', false);
    $this->get(route('dashboard'))->assertOk()->assertViewHas('liquidityOverview', fn (array $data): bool => $data['free'] === 79000);
});

test('reserve limits preserve asset values and goal progress and removal can be undone', function () {
    $goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'opening_cents' => 30000]);
    $this->post(route('liquidity.reserves.store'), reserveInput(['asset_id' => $this->asset->id]))->assertRedirect();
    $reserve = AssetReserve::query()->sole();
    $this->postJson(route('liquidity.reserves.store'), reserveInput(['asset_id' => $this->asset->id, 'purpose' => 'other', 'name' => 'Too much', 'amount' => '800']))->assertUnprocessable();
    $this->postJson(route('liquidity.reserves.store'), reserveInput(['asset_id' => $this->asset->id, 'purpose' => 'goal', 'goal_id' => $goal->id, 'amount' => '301']))->assertUnprocessable();
    $this->post(route('liquidity.reserves.store'), reserveInput(['asset_id' => $this->asset->id, 'purpose' => 'goal', 'goal_id' => $goal->id, 'amount' => '300']))->assertRedirect();
    expect($this->asset->valuations()->sole()->amount_cents)->toBe(100000)->and($goal->contributions()->count())->toBe(0);
    $this->delete(route('liquidity.reserves.destroy', $reserve))->assertRedirect()->assertSessionHas('undo_reserve');
    $this->post(route('liquidity.reserves.restore', $reserve))->assertRedirect();
    $this->asset->valuations()->sole()->update(['amount_cents' => 10000]);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['overallocated'])->toBe(1);
    $this->delete(route('liquidity.reserves.destroy', $reserve))->assertRedirect();
    $this->post(route('liquidity.reserves.restore', $reserve))->assertUnprocessable();
});

test('liquidity settings reserves and financial forecasts stay private', function () {
    $other = User::factory()->create();
    $foreign = Asset::factory()->create(['user_id' => $other->id]);
    $goal = SavingsGoal::factory()->create(['user_id' => $other->id]);
    $budget = Budget::factory()->create(['user_id' => $other->id]);
    $reserve = AssetReserve::factory()->create(['asset_id' => $foreign->id]);
    $this->postJson(route('liquidity.reserves.store'), reserveInput(['asset_id' => $foreign->id]))->assertNotFound();
    $this->postJson(route('liquidity.reserves.store'), reserveInput(['asset_id' => $this->asset->id, 'purpose' => 'goal', 'goal_id' => $goal->id]))->assertNotFound();
    $this->delete(route('liquidity.reserves.destroy', $reserve))->assertForbidden();
    $this->postJson(route('liquidity.settings'), ['budget_ids' => [$budget->id], 'horizon' => 30, 'buffer' => '0', 'essential' => '100', 'variable' => '0'])->assertNotFound();
    expect(app(LiquidityAnalytics::class)->build($this->owner)['reserves'])->toHaveCount(0);
});

test('forecast uses remaining dated income and warns about undated or past income', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000, 'received_cents' => 25000, 'expected_date' => '2026-10-10']);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 20000, 'expected_date' => null]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 30000, 'expected_date' => '2026-10-01']);
    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['events']->where('source', 'income')->sole()['amount'])->toBe(75000)->and($data['missingIncome'])->toBe(2)->and($data['missingIncomeAmount'])->toBe(50000)->and($data['daily']->last()['closing'])->toBe(174000);
    $delayed = app(LiquidityAnalytics::class)->build($this->owner, ['income_delay' => 40]);
    expect($delayed['events']->where('source', 'income'))->toHaveCount(0);
});

test('subscriptions recurring snapshots and debt minima are deduplicated including recorded payments', function () {
    $sub = Subscription::factory()->create(['user_id' => $this->owner->id, 'amount_cents' => 10000, 'next_billing_date' => '2026-10-10']);
    $commit = BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'subscription_id' => $sub->id, 'amount_cents' => 10000, 'scheduled_date' => '2026-10-10']);
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-10-12', 'amount_cents' => 20000]);
    BudgetRecurringCharge::factory()->create(['budget_period_id' => $this->period->id, 'budget_recurring_expense_id' => $expense->id, 'scheduled_date' => '2026-10-12', 'amount_cents' => 21000, 'is_current' => true]);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'due_anchor' => '2026-10-15', 'minimum_payment_cents' => 30000]);
    DebtPayment::factory()->create(['debt_id' => $debt->id, 'date' => '2026-10-03', 'amount_cents' => 10000, 'interest_cents' => 0]);
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'debt_id' => $debt->id, 'start_date' => '2026-10-15', 'amount_cents' => 30000]);
    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['events']->where('source', 'subscription'))->toHaveCount(1)->and($data['events']->where('source', 'recurring')->sole()['amount'])->toBe(21000)->and($data['events']->where('source', 'debt')->sole()['amount'])->toBe(20000);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_commitment_id' => $commit->id, 'date' => '2026-10-04']);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['events']->where('source', 'subscription'))->toHaveCount(0);
});

test('same day ordering identifies intraday gaps while scenario forecasts never write financial records', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000, 'expected_date' => '2026-10-04']);
    Subscription::factory()->create(['user_id' => $this->owner->id, 'amount_cents' => 150000, 'next_billing_date' => '2026-10-04']);
    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['daily']->first()['low'])->toBe(-51000)->and($data['daily']->first()['closing'])->toBe(49000)->and($data['cashGap'])->toBe(51000);
    LiquidityPreference::factory()->create(['user_id' => $this->owner->id, 'budget_ids' => [$this->budget->id], 'income_first' => true]);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['daily']->first()['low'])->toBe(49000);
    $this->get(route('net-worth.index', ['tab' => 'liquidity', 'extra_reserve' => '100', 'purchase' => '50', 'purchase_date' => '2026-10-05']))->assertOk();
    expect(AssetReserve::query()->count())->toBe(0)->and($this->asset->valuations()->count())->toBe(1);
    $this->getJson(route('net-worth.index', ['horizon' => 30, 'purchase' => '50', 'purchase_date' => '2026-11-20']))->assertUnprocessable()->assertJsonValidationErrors('purchase_date');
});

test('liquidity immediately excludes paused and cancelled subscriptions with unpaid budget snapshots', function (SubscriptionStatus $status) {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'next_billing_date' => '2026-10-10']);
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'subscription_id' => $subscription->id, 'scheduled_date' => '2026-10-10']);
    $subscription->update(['status' => $status]);

    $this->get(route('net-worth.index', ['tab' => 'liquidity']))->assertOk()
        ->assertViewHas('liquidity', fn (array $data): bool => $data['events']->where('source', 'subscription')->isEmpty());
})->with([SubscriptionStatus::Paused, SubscriptionStatus::Cancelled]);

test('liquidity uses the latest subscription amount and name without requiring a budget visit', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'name' => 'Old plan', 'amount_cents' => 10000, 'next_billing_date' => '2026-10-10']);
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'subscription_id' => $subscription->id, 'name' => 'Old plan', 'amount_cents' => 10000, 'scheduled_date' => '2026-10-10']);
    $subscription->update(['name' => 'New plan', 'amount_cents' => 15000]);

    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['events']->where('source', 'subscription')->sole())->toMatchArray(['name' => 'New plan', 'amount' => 15000, 'date' => '2026-10-10']);
    expect($data['daily']->last()['closing'])->toBe(84000);
});

test('rescheduling a subscription does not forecast both the old and new renewal', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->owner->id, 'amount_cents' => 10000, 'next_billing_date' => '2026-10-10']);
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'subscription_id' => $subscription->id, 'amount_cents' => 10000, 'scheduled_date' => '2026-10-10']);
    $subscription->update(['next_billing_date' => '2026-10-15']);

    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['events']->where('source', 'subscription')->sole())->toMatchArray(['date' => '2026-10-15', 'amount' => 10000]);
});

test('runway uses accessible emergency reserves and essentials plus debt minimums', function () {
    AssetReserve::factory()->create(['asset_id' => $this->asset->id, 'amount_cents' => 60000]);
    LiquidityPreference::factory()->create(['user_id' => $this->owner->id, 'essential_cents' => 20000]);
    Debt::factory()->create(['user_id' => $this->owner->id, 'minimum_payment_cents' => 10000]);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['runway'])->toBe(2.0);
    $this->asset->update(['value_uncertain' => true]);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['runway'])->toBe(0.0);
});

test('variable estimates round to the configured monthly total over actual calendar days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00'));
    LiquidityPreference::factory()->create(['user_id' => $this->owner->id, 'variable_cents' => 100001]);
    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['events']->where('source', 'variable')->sum('amount'))->toBe(100001)->and($data['daily'])->toHaveCount(30)->and($data['daily']->last()['closing'])->toBe(-1001);
});

test('settings save money exactly and selecting no budgets excludes their income', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_date' => '2026-10-10']);
    $this->post(route('liquidity.settings'), ['budget_ids' => null, 'horizon' => 60, 'buffer' => '200.05', 'essential' => '1000', 'variable' => '500', 'income_first' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $preference = LiquidityPreference::query()->sole();
    expect($preference->budget_ids)->toBe([])->and($preference->buffer_cents)->toBe(20005)->and($preference->income_first)->toBeTrue();
    $data = app(LiquidityAnalytics::class)->build($this->owner);
    expect($data['daily'])->toHaveCount(60)->and($data['events']->where('source', 'income'))->toHaveCount(0);
});

test('goal readiness respects confirmed access dates and reduced recorded savings', function () {
    $goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'opening_cents' => 40000, 'target_date' => '2026-10-20']);
    $this->asset->update(['liquidity' => 'dated', 'available_date' => '2026-10-21']);
    AssetReserve::factory()->create(['asset_id' => $this->asset->id, 'savings_goal_id' => $goal->id, 'kind' => 'goal', 'amount_cents' => 40000]);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['goalReadiness']->sole()['ready'])->toBe(0);
    $this->asset->update(['available_date' => '2026-10-20']);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['goalReadiness']->sole()['ready'])->toBe(40000);
    $goal->update(['opening_cents' => 10000]);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['goalReadiness']->sole()['ready'])->toBe(10000);
    $this->get(route('net-worth.index', ['tab' => 'liquidity']))->assertOk()->assertSee($goal->name);
});

test('asset accessibility validates conditional fields and preserves unchecked uncertainty', function () {
    $payload = ['name' => 'Notice account', 'kind' => 'bank', 'amount' => '500', 'date' => '2026-10-04', 'liquidity' => 'delayed', 'access_days' => 30, 'withdrawal_cost' => '1.25'];
    $this->post(route('net-worth.assets.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $asset = Asset::query()->where('name', 'Notice account')->sole();
    expect($asset->access_days)->toBe(30)->and($asset->withdrawal_cost_cents)->toBe(125)->and($asset->value_uncertain)->toBeFalse();
    $this->postJson(route('net-worth.assets.store'), [...$payload, 'liquidity' => 'dated'])->assertUnprocessable()->assertJsonValidationErrors('available_date');
    $this->put(route('net-worth.assets.update', $asset), [...$payload, 'liquidity' => 'immediate', 'value_uncertain' => 1])->assertRedirect();
    expect($asset->fresh()->value_uncertain)->toBeTrue()->and($asset->fresh()->access_days)->toBeNull();
});

test('budget expected income dates persist and must fall inside the budget period', function () {
    $url = route('budgets.action', ['period' => $this->period, 'action' => 'income-save']);
    $payload = ['version' => $this->period->fresh()->version, 'name' => 'Payday', 'expected_amount' => '1500', 'received_amount' => '0', 'expected_date' => '2026-10-25'];
    $this->postJson($url, $payload)->assertOk();
    expect($this->period->incomes()->sole()->expected_date->toDateString())->toBe('2026-10-25');
    $this->postJson($url, [...$payload, 'version' => $this->period->fresh()->version, 'expected_date' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('expected_date');
});
