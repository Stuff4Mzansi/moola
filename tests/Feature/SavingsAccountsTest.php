<?php

use App\LiquidityAnalytics;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetReserve;
use App\Models\AssetValuation;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use App\NetWorthWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->account = Asset::factory()->create(['user_id' => $this->owner->id, 'kind' => 'bank', 'liquidity' => 'immediate']);
    AssetValuation::factory()->create(['asset_id' => $this->account->id, 'amount_cents' => 100000, 'date' => '2026-10-01']);
    $this->goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'opening_cents' => 20000, 'start_date' => '2026-10-01', 'kind' => 'emergency']);
});

function accountGoalData(array $changes = []): array
{
    return [...['name' => 'Emergency savings', 'kind' => 'emergency', 'target' => '5000', 'opening' => '200', 'start_date' => '2026-10-01', 'monthly' => '0'], ...$changes];
}

function accountContributionData(array $changes = []): array
{
    return [...['request_id' => (string) Str::uuid(), 'source' => 'goal', 'amount' => '100', 'date' => '2026-10-04', 'money_origin' => 'existing'], ...$changes];
}

function currentCash(User $user): int
{
    return app(NetWorthWorkspace::class)->balances($user, CarbonImmutable::today())['assetTotal'];
}

test('linking existing goal savings creates one automatic reserve without adding money', function () {
    SavingsContribution::factory()->create(['savings_goal_id' => $this->goal->id, 'amount_cents' => 10000, 'date' => '2026-10-02']);
    AssetReserve::factory()->create(['asset_id' => $this->account->id, 'savings_goal_id' => $this->goal->id, 'kind' => 'emergency', 'amount_cents' => 10000]);
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect()->assertSessionHasNoErrors();
    $reserve = AssetReserve::query()->sole();
    expect($reserve->is_automatic)->toBeTrue()->and($reserve->amount_cents)->toBe(30000)->and(currentCash($this->owner))->toBe(100000);
    expect(app(LiquidityAnalytics::class)->build($this->owner)['free'])->toBe(70000);
    $this->get(route('goals.index'))->assertOk()->assertSee('Saved money is protected automatically');
    $this->get(route('net-worth.index', ['tab' => 'liquidity']))->assertOk()->assertSee('Manage in Goals');
    $this->delete(route('liquidity.reserves.destroy', $reserve))->assertUnprocessable();
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => '']))->assertRedirect();
    expect(AssetReserve::query()->count())->toBe(0)->and(currentCash($this->owner))->toBe(100000);
});

test('existing money protects savings while new money increases balance once and survives edits undo and replay', function () {
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData())->assertRedirect()->assertSessionHasNoErrors();
    expect(currentCash($this->owner))->toBe(100000)->and(AssetReserve::query()->sole()->amount_cents)->toBe(30000);
    $data = accountContributionData(['money_origin' => 'new']);
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect()->assertSessionHasNoErrors();
    expect(currentCash($this->owner))->toBe(110000)->and(AssetMovement::query()->count())->toBe(1)->and(AssetReserve::query()->sole()->amount_cents)->toBe(40000);
    $entry = $this->goal->contributions()->where('money_origin', 'new')->sole();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['contribution_id' => $entry->id, 'money_origin' => 'new', 'amount' => '150']))->assertRedirect();
    expect(currentCash($this->owner))->toBe(115000)->and(AssetReserve::query()->sole()->amount_cents)->toBe(45000);
    $this->delete(route('goals.contributions.destroy', [$this->goal, $entry]))->assertRedirect();
    expect(currentCash($this->owner))->toBe(100000)->and(AssetReserve::query()->sole()->amount_cents)->toBe(30000);
    $this->post(route('goals.contributions.restore', [$this->goal, $entry]))->assertRedirect();
    expect(currentCash($this->owner))->toBe(115000)->and(AssetReserve::query()->sole()->amount_cents)->toBe(45000);
});

test('changing contribution origin and date reverses prior entries without inflating balances', function () {
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new']))->assertRedirect();
    $entry = $this->goal->contributions()->sole();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['contribution_id' => $entry->id, 'date' => '2026-10-03']))->assertRedirect();
    expect(currentCash($this->owner))->toBe(100000);
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['contribution_id' => $entry->id, 'money_origin' => 'new', 'date' => '2026-10-03']))->assertRedirect();
    expect(currentCash($this->owner))->toBe(110000)->and(app(NetWorthWorkspace::class)->balances($this->owner, CarbonImmutable::parse('2026-10-02'))['assetTotal'])->toBe(100000);
});

test('manual reconciliation absorbs prior deposits including same day deposits but later new money is still applied', function () {
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new']))->assertRedirect();
    expect(currentCash($this->owner))->toBe(110000);
    $this->post(route('net-worth.values.store', $this->account), ['amount' => '1100', 'date' => '2026-10-04'])->assertRedirect();
    expect(currentCash($this->owner))->toBe(110000);
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new', 'amount' => '50']))->assertRedirect();
    expect(currentCash($this->owner))->toBe(115000);
    $this->postJson(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new', 'date' => '2026-10-03']))->assertUnprocessable()->assertJsonValidationErrors('money_origin');
});

test('foreign accounts and unfunded allocations are rejected and unlinked new money requires an account', function () {
    $foreign = Asset::factory()->create();
    $this->putJson(route('goals.update', $this->goal), accountGoalData(['asset_id' => $foreign->id]))->assertNotFound();
    $this->postJson(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new']))->assertUnprocessable();
    AssetReserve::factory()->create(['asset_id' => $this->account->id, 'amount_cents' => 90000]);
    $this->putJson(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertUnprocessable();
    expect($this->goal->fresh()->asset_id)->toBeNull()->and(AssetReserve::query()->count())->toBe(1);
});

test('budget linked new money synchronizes account reserves and budget edits removals and restores', function () {
    $budget = Budget::factory()->create(['user_id' => $this->owner->id]);
    $period = BudgetPeriod::factory()->create(['budget_id' => $budget->id]);
    $category = $period->categories()->create(['name' => 'Savings', 'kind' => 'custom', 'allocated_cents' => 100000]);
    $period->categories()->create(['name' => 'Other', 'kind' => 'other', 'allocated_cents' => 0]);
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id, 'category_id' => $category->id]))->assertRedirect();
    $data = accountContributionData(['source' => 'budget', 'money_origin' => 'new']);
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    $entry = $this->goal->contributions()->sole();
    $transaction = $period->transactions()->sole();
    expect(currentCash($this->owner))->toBe(110000)->and($entry->money_origin)->toBe('new');
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-save']), ['id' => $transaction->id, 'category_id' => $category->id, 'amount' => '150', 'date' => '2026-10-04', 'version' => $period->fresh()->version])->assertOk();
    expect(currentCash($this->owner))->toBe(115000)->and(AssetReserve::query()->sole()->amount_cents)->toBe(35000);
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-remove']), ['id' => $transaction->id, 'version' => $period->fresh()->version])->assertOk();
    expect(currentCash($this->owner))->toBe(100000)->and(AssetReserve::query()->sole()->amount_cents)->toBe(20000);
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-restore']), ['id' => $transaction->id, 'version' => $period->fresh()->version])->assertOk();
    expect(currentCash($this->owner))->toBe(115000)->and($this->goal->contributions()->count())->toBe(1);
});

test('deleting a goal releases its reserve while keeping the actual deposited money', function () {
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new']))->assertRedirect();
    $this->delete(route('goals.destroy', $this->goal))->assertRedirect();
    expect(currentCash($this->owner))->toBe(110000)->and(AssetReserve::query()->count())->toBe(0);
});

test('correcting a historical baseline retains subsequent deposits while a fresh balance reconciles them', function () {
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), accountContributionData(['money_origin' => 'new', 'date' => '2026-10-01']))->assertRedirect();
    $value = $this->account->valuations()->sole();
    $this->post(route('net-worth.values.store', $this->account), ['valuation_id' => $value->id, 'date' => '2026-10-01', 'amount' => '1050'])->assertRedirect();
    expect(currentCash($this->owner))->toBe(115000);
    $this->post(route('net-worth.values.store', $this->account), ['date' => '2026-10-01', 'amount' => '1150'])->assertRedirect();
    expect(currentCash($this->owner))->toBe(115000);
});

test('restoring a removed account refreshes its automatic reserve after budget-side changes', function () {
    $this->put(route('goals.update', $this->goal), accountGoalData(['asset_id' => $this->account->id]))->assertRedirect();
    $this->delete(route('net-worth.assets.destroy', $this->account))->assertRedirect();
    SavingsContribution::factory()->create(['savings_goal_id' => $this->goal->id, 'amount_cents' => 10000, 'date' => '2026-10-04']);
    $this->post(route('net-worth.assets.restore', $this->account))->assertRedirect();
    expect(AssetReserve::query()->sole()->amount_cents)->toBe(30000)->and(app(LiquidityAnalytics::class)->build($this->owner)['free'])->toBe(70000);
});
