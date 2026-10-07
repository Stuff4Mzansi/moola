<?php

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetGroup;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->budget = Budget::factory()->create(['user_id' => $this->owner->id, 'include_subscriptions' => false]);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-09-12', 'end_date' => '2026-10-02']);
    $this->category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Everyday', 'allocated_cents' => 40000]);
});

test('ended period reviews chart recorded spending by its assigned budget category', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 120000, 'received_cents' => 100000, 'received_date' => '2026-09-12']);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'balance_date' => '2026-09-01']);
    $charge = BudgetRecurringCharge::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'debt_id' => $debt->id, 'scheduled_date' => '2026-09-12']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_recurring_charge_id' => $charge->id, 'amount_cents' => 20000, 'interest_cents' => 3000, 'date' => '2026-09-12']);
    $goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'start_date' => '2026-09-01']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'savings_goal_id' => $goal->id, 'amount_cents' => 10000, 'date' => '2026-10-02']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 10000, 'date' => '2026-09-20']);
    $this->category->update(['allocated_cents' => 35000]);
    $transport = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Transport', 'allocated_cents' => 5000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $transport->id, 'amount_cents' => 5000, 'date' => '2026-09-20']);
    DebtPayment::factory()->create(['debt_id' => $debt->id, 'amount_cents' => 50000, 'date' => '2026-09-20']);
    SavingsContribution::factory()->create(['savings_goal_id' => $goal->id, 'amount_cents' => 50000, 'date' => '2026-09-20']);

    $response = $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk()
        ->assertSee('End-of-period financial review')->assertSee('Copy plan to next period')
        ->assertSee('data-budget-panel="review"', false)
        ->assertSee('aria-selected="true" tabindex="0" data-budget-tab="review"', false);
    expect($response->viewData('totals'))->toMatchArray(['received' => 100000, 'spent' => 45000]);
    $review = $response->viewData('periodReview');
    expect($review)->toMatchArray(['status' => 'ended', 'variance' => -5000, 'recordedBalance' => 55000, 'plannedReachedDate' => '2026-10-02', 'receivedReachedDate' => null, 'spendingCategories' => [['name' => 'Everyday', 'amount' => 40000], ['name' => 'Transport', 'amount' => 5000]], 'pendingAmount' => 0, 'missingIncome' => [['name' => $this->period->incomes()->sole()->name, 'amount' => 20000]], 'overLimits' => [['name' => 'Everyday', 'amount' => 5000]]]);
    expect(array_slice($review['dailySpending'], 0, 2))->toBe([
        ['date' => '2026-09-12', 'amount' => 20000, 'cumulative' => 20000],
        ['date' => '2026-09-13', 'amount' => 0, 'cumulative' => 20000],
    ]);
    $response->assertSee('Your plan and what happened')->assertSee('Recorded spending by category')
        ->assertSee('Daily spending across the period')
        ->assertSee('data-review-daily-spending-line', false)
        ->assertSee('data-review-threshold="planned" data-review-threshold-date="2026-10-02"', false)
        ->assertSee('stroke="var(--color-primary)" stroke-width="3"', false)
        ->assertDontSee('data-review-threshold="received"', false)
        ->assertSee('Planned amount reached (R 400.00 on 02 Oct)')
        ->assertSee('Income received reached')
        ->assertSee('data-spending-category-slice data-spending-category-label="Everyday"', false)
        ->assertSee('data-spending-category-slice data-spending-category-label="Transport"', false)
        ->assertSee('stroke-width="16" pathLength="100"', false)
        ->assertSee('data-spending-category-legend="Everyday"', false)
        ->assertSee('data-spending-category-legend="Transport"', false)
        ->assertSee('aria-hidden="true"><p class="text-[11px] opacity-65">Recorded</p>', false)
        ->assertSee('Everyday')->assertSee('R 400.00')->assertSee('88.9%')
        ->assertSee('Transport')->assertSee('R 50.00')->assertSee('11.1%')
        ->assertDontSee('Debt interest paid')->assertDontSee('Savings contributed')
        ->assertSee('Allocated spending R 400.00; recorded spending R 450.00.');
});

test('review checklist includes only current unpaid charges inside the selected period', function () {
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Subscription to confirm', 'scheduled_date' => '2026-09-12', 'amount_cents' => 12000]);
    BudgetRecurringCharge::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'name' => 'Bill to confirm', 'scheduled_date' => '2026-10-02', 'amount_cents' => 8000]);
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'scheduled_date' => '2026-10-03', 'amount_cents' => 90000]);
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'scheduled_date' => '2026-09-20', 'is_current' => false]);
    $paid = BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'scheduled_date' => '2026-09-21']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_commitment_id' => $paid->id, 'date' => '2026-09-21']);

    $response = $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk();
    $review = $response->viewData('periodReview');
    expect($review['pendingAmount'])->toBe(20000)->and(array_column($review['pending'], 'name'))->toBe(['Subscription to confirm', 'Bill to confirm']);
    $response->assertSee(route('budgets.index', ['period' => $this->period->id, 'tab' => 'recurring']));
});

test('review updates after deleting and restoring a linked savings expense', function () {
    $goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'start_date' => '2026-09-01']);
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'savings_goal_id' => $goal->id, 'amount_cents' => 10000, 'date' => '2026-09-20']);
    $expense->delete();
    $response = $this->getJson(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk();
    expect($response->json('html'))->not->toContain('R 100.00');
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertViewHas('periodReview', fn (array $review): bool => $review['spendingCategories'] === []);
    $expense->restore();
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertViewHas('periodReview', fn (array $review): bool => $review['spendingCategories'] === [['name' => 'Everyday', 'amount' => 10000]]);
});

test('review keeps uncategorised budget transactions visible and excludes standalone goal contributions', function () {
    $goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'start_date' => '2026-09-01']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => null, 'amount_cents' => 2500, 'date' => '2026-09-20']);
    SavingsContribution::factory()->create(['savings_goal_id' => $goal->id, 'amount_cents' => 90000, 'date' => '2026-09-20']);

    $response = $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk();

    expect($response->viewData('totals')['spent'])->toBe(2500)
        ->and($response->viewData('periodReview')['spendingCategories'])->toBe([['name' => 'Uncategorised', 'amount' => 2500]]);
    $response->assertSee('Uncategorised')->assertSee('R 25.00')->assertSee('100.0%')->assertDontSee('R 900.00');
});

test('daily spending timeline marks the dates planned spending and received income are reached', function () {
    $this->category->update(['allocated_cents' => 20000]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 30000, 'received_cents' => 30000, 'received_date' => '2026-09-12']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 20000, 'date' => '2026-09-12']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 15000, 'date' => '2026-09-20']);

    $response = $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk();

    expect($response->viewData('periodReview'))->toMatchArray([
        'plannedReachedDate' => '2026-09-12',
        'receivedReachedDate' => '2026-09-20',
    ]);
    $response->assertSee('data-review-threshold="planned"', false)
        ->assertSee('data-review-threshold="received"', false)
        ->assertSee('Planned amount reached (R 200.00 on 12 Sep)')
        ->assertSee('Income received reached (R 300.00 on 20 Sep)');
});

test('review distinguishes an inclusive final day from an ended period and upcoming periods', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 12:00:00'));
    $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk()
        ->assertSee('Period review preview')->assertDontSee('Copy plan to next period')
        ->assertViewHas('periodReview', fn (array $review): bool => $review['status'] === 'current');
    $this->travelTo(CarbonImmutable::parse('2026-09-11 12:00:00'));
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()->assertSee('Upcoming period')
        ->assertViewHas('periodReview', fn (array $review): bool => $review['status'] === 'upcoming');
});

test('household viewers can review shared entries without seeing private goals or debt records', function () {
    $this->budget->update(['scope' => 'household']);
    $viewer = User::factory()->create();
    $this->budget->members()->attach($viewer, ['role' => 'viewer']);
    $goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'name' => 'Private goal name', 'start_date' => '2026-09-01']);
    SavingsContribution::factory()->create(['savings_goal_id' => $goal->id, 'amount_cents' => 90000, 'date' => '2026-09-20']);
    $this->actingAs($viewer)->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk()
        ->assertDontSee('Private goal name')->assertDontSee('Copy plan to next period')
        ->assertViewHas('periodReview', fn (array $review): bool => $review['spendingCategories'] === []);
    $this->budget->members()->detach($viewer);
    $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertForbidden();
});

test('uninvited users cannot access a budget review even with application administrator roles', function (UserRole $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertForbidden();
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('group review uses configured group limits and does not invent a limit for ungrouped categories', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000]);
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Essentials', 'percentage_basis_points' => 2000]);
    $this->category->forceFill(['budget_group_id' => $group->id])->save();
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 25000, 'date' => '2026-09-20']);
    $ungrouped = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'allocated_cents' => 0]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $ungrouped->id, 'amount_cents' => 5000, 'date' => '2026-09-20']);
    $response = $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk();
    expect($response->viewData('periodReview')['overLimits'])->toBe([['name' => 'Essentials', 'amount' => 5000]]);
});

test('invalid review tab parameters fall back to the overview without an error', function () {
    foreach (['missing', ['review']] as $tab) {
        $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => $tab]))->assertOk()
            ->assertSee('aria-selected="true" tabindex="0" data-budget-tab="overview"', false);
    }
});

test('empty period charts show honest empty states and safely render zero baselines', function () {
    $this->category->update(['allocated_cents' => 0]);
    $response = $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'review']))->assertOk();
    $response->assertSee('No expected income entered')->assertSee('No category allocations entered.')
        ->assertSee('Record expenses in this budget period to see spending by category.');
    expect($response->viewData('periodReview')['spendingCategories'])->toBe([]);
});
