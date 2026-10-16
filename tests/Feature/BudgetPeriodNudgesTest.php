<?php

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->budget = Budget::factory()->household()->create(['user_id' => $this->owner->id, 'name' => 'Household budget']);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'name' => 'October', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
    $this->actingAs($this->owner);
});

test('overview nudges respond to unallocated income, spending limits, and due payments', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Groceries', 'allocated_cents' => 40000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $category->id, 'amount_cents' => 33000]);
    $this->budget->update(['include_subscriptions' => true]);
    Subscription::factory()->for($this->owner)->create(['name' => 'Internet', 'next_billing_date' => '2026-10-16', 'status' => 'active']);

    $response = $this->get(route('budgets.index', ['period' => $this->period->id]));
    $response
        ->assertOk()
        ->assertSee('Nudges for this period')
        ->assertSee('Give unallocated income a plan')
        ->assertSee('Spending is approaching a limit')
        ->assertSee('Subscription payments need attention')
        ->assertSee('data-budget-go="plan"', false)
        ->assertSee('data-budget-go="subscriptions"', false)
        ->assertSee('aria-label="Budget period nudge cards"', false)
        ->assertSee('aria-label="Next nudge"', false)
        ->assertDontSee('scroll horizontally to view more');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@aria-labelledby="budget-attention-title"]//*[@aria-labelledby="budget-nudges-title"]')->length)->toBe(1)
        ->and($xpath->query('//*[not(ancestor::*[@aria-labelledby="budget-attention-title"])][@aria-labelledby="budget-nudges-title"]')->length)->toBe(0);
});

test('overview nudges surface upcoming recurring expenses and an ending period', function () {
    $this->period->update(['end_date' => '2026-10-19']);
    BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Home']);
    BudgetRecurringExpense::factory()->create([
        'budget_id' => $this->budget->id,
        'category_name' => 'Home',
        'name' => 'Internet',
        'start_date' => '2026-10-16',
        'billing_frequency' => 'monthly',
        'amount_cents' => 5000,
    ]);

    $this->get(route('budgets.index', ['period' => $this->period->id]))
        ->assertOk()
        ->assertSee('Recurring expenses need attention')
        ->assertSee('This budget period is nearly over')
        ->assertSee('data-budget-go="review"', false);
});

test('overview predicts when recorded spending pace will exhaust expected income', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Groceries']);
    BudgetTransaction::factory()->create([
        'budget_period_id' => $this->period->id,
        'budget_category_id' => $category->id,
        'amount_cents' => 80000,
        'date' => '2026-10-15',
    ]);

    $this->get(route('budgets.index', ['period' => $this->period->id]))
        ->assertOk()
        ->assertSee('Expected income may run out before period ends')
        ->assertSee('R 50.00 per day')
        ->assertSee('expected income of R 1,000.00')
        ->assertSee('around 20 Oct')
        ->assertSee('This is an estimate using your current spending pace.')
        ->assertSee('data-budget-go="expenses"', false);
});

test('income runout nudge is omitted when current spending pace stays within expected income', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Groceries']);
    BudgetTransaction::factory()->create([
        'budget_period_id' => $this->period->id,
        'budget_category_id' => $category->id,
        'amount_cents' => 10000,
        'date' => '2026-10-15',
    ]);

    $this->get(route('budgets.index', ['period' => $this->period->id]))
        ->assertOk()
        ->assertDontSee('Expected income may run out before period ends');
});

test('dismissal is per user and period while snoozing lasts seven days', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $nextPeriod = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
    BudgetIncome::factory()->create(['budget_period_id' => $nextPeriod->id, 'expected_cents' => 100000]);
    $viewer = User::factory()->create();
    $this->budget->members()->attach($viewer, ['role' => 'viewer']);

    $this->post(route('budgets.nudges.update', ['period' => $this->period, 'nudge' => 'unallocated-income', 'action' => 'dismiss']))
        ->assertRedirect(route('budgets.index', ['period' => $this->period->id]));
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertDontSee('Give unallocated income a plan');
    $this->get(route('budgets.index', ['period' => $nextPeriod->id]))->assertSee('Give unallocated income a plan');

    $this->actingAs($viewer)->get(route('budgets.index', ['period' => $this->period->id]))->assertSee('Give unallocated income a plan');
    $this->post(route('budgets.nudges.update', ['period' => $this->period, 'nudge' => 'unallocated-income', 'action' => 'snooze']))
        ->assertRedirect(route('budgets.index', ['period' => $this->period->id]));
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertDontSee('Give unallocated income a plan');
    $this->travel(8)->days();
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertSee('Give unallocated income a plan');
});

test('only budget members can update a period nudge and unknown nudge keys are rejected', function () {
    $outsider = User::factory()->create();
    $url = route('budgets.nudges.update', ['period' => $this->period, 'nudge' => 'unallocated-income', 'action' => 'dismiss']);
    $this->actingAs($outsider)->post($url)->assertForbidden();

    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $this->actingAs($this->owner)->post(route('budgets.nudges.update', ['period' => $this->period, 'nudge' => 'not-a-real-nudge', 'action' => 'dismiss']))->assertNotFound();
});
