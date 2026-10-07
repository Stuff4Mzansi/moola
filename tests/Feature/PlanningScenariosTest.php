<?php

use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\LiquidityPreference;
use App\Models\PlanningScenario;
use App\Models\Subscription;
use App\Models\User;
use App\PlanningScenarios;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.debt_tracking' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->user = User::factory()->create();
});

test('each planning tool has a working empty state and preview', function (string $kind) {
    $this->actingAs($this->user)->get(route('planning-scenarios.index', ['kind' => $kind]))->assertOk()
        ->assertSee('Explore a new plan')->assertSee('Your first plan starts here')->assertSee('Current preview');
})->with(['debt', 'subscriptions', 'liquidity']);

test('scenarios can be saved reopened renamed duplicated and deleted without changing debts', function () {
    $debt = Debt::factory()->for($this->user, 'owner')->create();
    $payload = ['name' => 'Pay off faster', 'kind' => 'debt', 'notes' => 'Use a bonus', 'inputs' => ['extra' => '250.00', 'strategy' => 'snowball']];
    $this->actingAs($this->user)->post(route('planning-scenarios.store'), $payload)->assertSessionHasNoErrors();
    $scenario = PlanningScenario::query()->sole();
    expect($scenario->user_id)->toBe($this->user->id)->and($scenario->inputs)->toBe($payload['inputs']);
    $this->get(route('planning-scenarios.index', ['scenario' => $scenario->id]))->assertOk()->assertSee('Edit Pay off faster')
        ->assertViewHas('inputs', $payload['inputs']);
    $payload['name'] = 'Larger extra payment';
    $payload['inputs']['extra'] = '500.00';
    $this->put(route('planning-scenarios.update', $scenario), $payload)->assertSessionHasNoErrors();
    expect($scenario->fresh()->name)->toBe('Larger extra payment')->and($scenario->fresh()->inputs['extra'])->toBe('500.00');
    $this->post(route('planning-scenarios.duplicate', $scenario))->assertRedirect();
    $copy = PlanningScenario::query()->where('id', '!=', $scenario->id)->sole();
    expect($copy->name)->toBe('Larger extra payment (copy)')->and($copy->inputs)->toBe($scenario->fresh()->inputs);
    $this->delete(route('planning-scenarios.destroy', $scenario))->assertRedirect();
    expect(PlanningScenario::query()->count())->toBe(1)->and($debt->fresh()->opening_balance_cents)->toBe(100000);
    $this->assertDatabaseCount('debt_payments', 0);
});

test('debt comparisons use current payments and a baseline and handle uncleared plans', function () {
    $debt = Debt::factory()->for($this->user, 'owner')->create(['opening_balance_cents' => 100000, 'annual_rate_basis_points' => 0]);
    $slow = PlanningScenario::factory()->for($this->user, 'owner')->create(['name' => 'Minimums', 'inputs' => ['extra' => '0.00', 'strategy' => 'snowball']]);
    $fast = PlanningScenario::factory()->for($this->user, 'owner')->create(['name' => 'Fast plan', 'inputs' => ['extra' => '100.00', 'strategy' => 'avalanche']]);
    $url = route('planning-scenarios.index', ['kind' => 'debt', 'compare' => [$slow->id, $fast->id]]);
    $response = $this->actingAs($this->user)->get($url)->assertOk()->assertSee('Compare your options');
    expect(array_column(array_column($response->viewData('comparison'), 'result'), 'score'))->toBe([10, 10, 5]);
    DebtPayment::factory()->for($debt)->create(['amount_cents' => 20000, 'interest_cents' => 0, 'date' => today()]);
    $response = $this->get($url)->assertOk();
    expect(array_column(array_column($response->viewData('comparison'), 'result'), 'score'))->toBe([8, 8, 4]);
    $debt->update(['minimum_payment_cents' => 0]);
    $this->get(route('planning-scenarios.index', ['compare' => [$slow->id]]))->assertOk()->assertSee('Not cleared');
});

test('subscription plans save selections and targets and recalculate prices without cancelling services', function () {
    $subscription = Subscription::factory()->for($this->user)->create(['amount_cents' => 10000]);
    Subscription::factory()->for($this->user)->create(['amount_cents' => 20000]);
    $payload = ['kind' => 'subscriptions', 'name' => 'Trim recurring costs', 'inputs' => ['subscription_ids' => [$subscription->id], 'target' => '250.00']];
    $this->actingAs($this->user)->post(route('planning-scenarios.store'), $payload)->assertSessionHasNoErrors();
    $scenario = PlanningScenario::query()->sole();
    $response = $this->get(route('planning-scenarios.index', ['scenario' => $scenario->id]))->assertOk();
    expect($response->viewData('result')['metrics']['Monthly savings'])->toBe(10000)
        ->and($subscription->fresh()->status->value)->toBe('active');
    $subscription->update(['amount_cents' => 15000]);
    $response = $this->get(route('planning-scenarios.index', ['kind' => 'subscriptions', 'compare' => [$scenario->id]]))->assertOk();
    expect($response->viewData('comparison')[1]['result']['metrics']['Monthly savings'])->toBe(15000);
    $subscription->delete();
    $this->get(route('planning-scenarios.index', ['scenario' => $scenario->id]))->assertOk()->assertSee('paused, cancelled, or removed');
});

test('cash flow scenarios reuse relative purchase dates and comparisons share a forecast window', function () {
    $asset = Asset::factory()->for($this->user, 'owner')->create(['liquidity' => 'immediate']);
    AssetValuation::factory()->for($asset)->create(['amount_cents' => 100000, 'date' => today()]);
    $preferences = LiquidityPreference::factory()->create(['user_id' => $this->user->id, 'variable_cents' => 0]);
    $inputs = [...app(PlanningScenarios::class)->defaults('liquidity'), 'horizon' => 90, 'purchase' => '200.00', 'purchase_after_days' => 45];
    $this->actingAs($this->user)->post(route('planning-scenarios.store'), ['name' => 'Future purchase', 'kind' => 'liquidity', 'inputs' => $inputs])->assertSessionHasNoErrors();
    $scenario = PlanningScenario::query()->sole();
    $response = $this->get(route('planning-scenarios.index', ['scenario' => $scenario->id]))->assertOk();
    expect($response->viewData('result')['metrics']['Closing available cash'])->toBe(80000);
    $url = route('planning-scenarios.index', ['kind' => 'liquidity', 'compare' => [$scenario->id], 'comparison_window' => 30]);
    $response = $this->get($url)->assertOk()->assertSee('purchase falls after this comparison window');
    expect($response->viewData('comparison')[1]['result']['daily'])->toHaveCount(30)
        ->and($response->viewData('comparison')[1]['result']['metrics']['Closing available cash'])->toBe(100000);
    $this->travel(10)->days();
    $response = $this->get(route('planning-scenarios.index', ['scenario' => $scenario->id]))->assertOk();
    expect($response->viewData('result')['daily'][45]['closing'])->toBe(80000)
        ->and($preferences->fresh()->variable_cents)->toBe(0);
    $this->assertDatabaseCount('asset_valuations', 1);
    $this->assertDatabaseCount('asset_reserves', 0);
});

test('saved plans and every mutation remain private even to administrators', function (UserRole $role) {
    $scenario = PlanningScenario::factory()->for($this->user, 'owner')->create(['name' => 'Private planning name']);
    $actor = User::factory()->create(['role' => $role]);
    $this->actingAs($actor)->get(route('planning-scenarios.index'))->assertOk()->assertDontSee('Private planning name');
    $this->get(route('planning-scenarios.index', ['scenario' => $scenario->id]))->assertNotFound();
    $this->get(route('planning-scenarios.index', ['compare' => [$scenario->id]]))->assertNotFound();
    $this->putJson(route('planning-scenarios.update', $scenario), ['name' => 'Hijacked', 'kind' => 'debt', 'inputs' => $scenario->inputs])->assertNotFound();
    $this->post(route('planning-scenarios.duplicate', $scenario))->assertNotFound();
    $this->delete(route('planning-scenarios.destroy', $scenario))->assertNotFound();
    expect($scenario->fresh()->name)->toBe('Private planning name');
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('invalid assumptions are rejected without creating scenarios', function (string $invalid) {
    $payload = ['name' => 'Invalid', 'kind' => 'debt', 'inputs' => ['extra' => '100.00', 'strategy' => 'avalanche']];
    match ($invalid) {
        'negative' => $payload['inputs']['extra'] = '-1',
        'precision' => $payload['inputs']['extra'] = '1.001',
        'strategy' => $payload['inputs']['strategy'] = 'custom',
        'kind' => $payload['kind'] = 'unknown',
        'injection' => $payload['inputs']['user_id'] = 1,
        'empty_name' => $payload['name'] = '',
        'purchase_day' => $payload = ['name' => 'Invalid', 'kind' => 'liquidity', 'inputs' => [...app(PlanningScenarios::class)->defaults('liquidity'), 'purchase' => '100', 'purchase_after_days' => 30]],
    };
    $this->actingAs($this->user)->postJson(route('planning-scenarios.store'), $payload)->assertUnprocessable();
    $this->assertDatabaseCount('planning_scenarios', 0);
})->with(['negative', 'precision', 'strategy', 'kind', 'injection', 'empty_name', 'purchase_day']);

test('subscription references cannot cross account boundaries or repeat', function () {
    $subscription = Subscription::factory()->create();
    $payload = ['name' => 'Foreign selections', 'kind' => 'subscriptions', 'inputs' => ['subscription_ids' => [$subscription->id]]];
    $this->actingAs($this->user)->postJson(route('planning-scenarios.store'), $payload)->assertUnprocessable();
    $subscription->user_id = $this->user->id;
    $subscription->save();
    $payload['inputs']['subscription_ids'][] = $subscription->id;
    $this->postJson(route('planning-scenarios.store'), $payload)->assertUnprocessable();
});

test('comparisons reject mixed tools duplicates and more than three scenarios', function () {
    $debt = PlanningScenario::factory()->for($this->user, 'owner')->create();
    $cash = PlanningScenario::factory()->liquidity()->for($this->user, 'owner')->create();
    $this->actingAs($this->user)->getJson(route('planning-scenarios.index', ['compare' => [$debt->id, $cash->id]]))->assertUnprocessable();
    $this->getJson(route('planning-scenarios.index', ['compare' => [$debt->id, $debt->id]]))->assertUnprocessable();
    $this->getJson(route('planning-scenarios.index', ['compare' => [1, 2, 3, 4]]))->assertUnprocessable();
});

test('guests cannot access or save planning scenarios', function () {
    $this->get(route('planning-scenarios.index'))->assertRedirect(route('login'));
    $this->postJson(route('planning-scenarios.store'), [])->assertUnauthorized();
});

test('preview refreshes a draft without saving or overwriting the selected scenario', function () {
    Debt::factory()->for($this->user, 'owner')->create(['annual_rate_basis_points' => 0]);
    $scenario = PlanningScenario::factory()->for($this->user, 'owner')->create();
    $draft = ['scenario' => $scenario->id, 'kind' => 'debt', 'name' => 'Unsaved name', 'inputs' => ['extra' => '100.00', 'strategy' => 'snowball']];
    $this->actingAs($this->user)->put(route('planning-scenarios.preview'), $draft)->assertOk()
        ->assertViewHas('inputs', $draft['inputs'])->assertViewHas('result', fn (array $result): bool => $result['score'] === 5);
    expect($scenario->fresh()->inputs['extra'])->toBe('0.00');
    $this->post(route('planning-scenarios.preview'), ['kind' => 'debt', 'inputs' => $draft['inputs']])->assertOk();
    $this->assertDatabaseCount('planning_scenarios', 1);
});

test('calculator results never include another accounts financial records', function () {
    Subscription::factory()->create(['amount_cents' => 999999]);
    Debt::factory()->create(['name' => 'Private lender debt', 'opening_balance_cents' => 999999]);
    $this->actingAs($this->user)->get(route('planning-scenarios.index', ['kind' => 'debt']))->assertOk()
        ->assertDontSee('Private lender debt')->assertViewHas('result', fn (array $result): bool => $result['score'] === 0);
    $this->get(route('planning-scenarios.index', ['kind' => 'subscriptions']))->assertOk()
        ->assertViewHas('result', fn (array $result): bool => $result['metrics']['Remaining monthly equivalent'] === 0);
});

test('cash shortfalls render signed comparison bars and cannot change liquidity settings', function () {
    $inputs = [...app(PlanningScenarios::class)->defaults('liquidity'), 'purchase' => '100.00'];
    $scenario = PlanningScenario::factory()->liquidity()->for($this->user, 'owner')->create(['inputs' => $inputs]);
    $this->actingAs($this->user)->get(route('planning-scenarios.index', ['kind' => 'liquidity', 'compare' => [$scenario->id]]))->assertOk()
        ->assertSee('Cash shortfall: R 100.00')->assertSee('R -100.00');
    $this->assertDatabaseCount('liquidity_preferences', 0);
});

test('existing planners offer scenario handoffs with their current assumptions', function () {
    Subscription::factory()->for($this->user)->create();
    $this->actingAs($this->user)->get(route('debts.index', ['tab' => 'plan', 'extra' => '125.00']))->assertOk()
        ->assertSee(route('planning-scenarios.index', ['kind' => 'debt', 'inputs' => ['extra' => '125.00', 'strategy' => 'avalanche']]));
    $this->get(route('net-worth.index', ['tab' => 'liquidity', 'purchase' => '50.00', 'purchase_date' => '2026-10-10']))->assertOk()
        ->assertSee('Keep current assumptions');
    $this->get(route('subscriptions.index'))->assertOk()->assertSee('Keep this plan in Planning scenarios')
        ->assertSee('name="inputs[subscription_ids][]"', false);
});
