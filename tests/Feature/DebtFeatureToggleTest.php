<?php

use App\Models\Budget;
use App\Models\BudgetRecurringExpense;
use App\Models\Debt;
use App\Models\Liability;
use App\Models\LiabilityValuation;
use App\Models\User;
use App\NetWorthWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.debt_tracking' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
});

test('disabled debt tracking hides its routes widgets balances and planning tool', function () {
    Debt::factory()->create(['user_id' => $this->owner->id, 'name' => 'Hidden debt', 'opening_balance_cents' => 100000]);
    $liability = Liability::factory()->create(['user_id' => $this->owner->id, 'name' => 'Manual liability']);
    LiabilityValuation::factory()->create(['liability_id' => $liability->id, 'amount_cents' => 25000, 'date' => '2026-10-07']);

    $data = app(NetWorthWorkspace::class)->build($this->owner);
    expect($data['debtTotal'])->toBe(0)
        ->and($data['debts'])->toBeEmpty()
        ->and($data['liabilityTotal'])->toBe(25000)
        ->and($data['netWorth'])->toBe(-25000);

    $this->get(route('debts.index'))->assertNotFound();
    $this->get(route('dashboard'))->assertOk()
        ->assertDontSee('Debt progress')
        ->assertDontSee('Debts')
        ->assertDontSee('Hidden debt');
    $this->get(route('net-worth.index'))->assertOk()
        ->assertDontSee('Hidden debt')
        ->assertSee('Manual liability')
        ->assertSee('Tracked liabilities');
    $this->get(route('planning-scenarios.index'))->assertOk()
        ->assertDontSee('Debt payoff');
    $this->getJson(route('payment-calendar.index', ['type' => 'debt']))->assertUnprocessable();
});

test('disabled debt tracking continues forecasting linked budget schedules as ordinary expenses', function () {
    $budget = Budget::factory()->create(['user_id' => $this->owner->id]);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'name' => 'Hidden scheduled debt']);
    BudgetRecurringExpense::factory()->create([
        'budget_id' => $budget->id,
        'debt_id' => $debt->id,
        'name' => 'Monthly loan bill',
        'start_date' => '2026-10-10',
        'amount_cents' => 15000,
    ]);

    $this->get(route('payment-calendar.index'))->assertOk()->assertSee('Monthly loan bill');
});

test('enabling debt tracking restores access without removing saved records', function () {
    config(['features.debt_tracking' => true]);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'name' => 'Existing loan', 'opening_balance_cents' => 100000]);

    $this->get(route('debts.index'))->assertOk()->assertSee('Existing loan');
    expect(app(NetWorthWorkspace::class)->build($this->owner)['debtTotal'])->toBe(100000);
});
