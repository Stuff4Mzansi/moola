<?php

use App\BudgetWorkspace;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetGroup;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->budget = Budget::factory()->create(['user_id' => $this->owner->id, 'name' => 'My October plan', 'include_subscriptions' => false]);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 100000, 'received_cents' => 80000]);
    $this->category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Home', 'allocated_cents' => 60000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'amount_cents' => 20000]);
    $this->actingAs($this->owner);
});

test('dashboard separates actual spending from recurring forecasts and uses workspace totals', function () {
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Home', 'name' => 'Electricity', 'start_date' => '2026-10-05', 'amount_cents' => 30000]);
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('Your budgets at a glance')->assertSee('My October plan')
        ->assertSee('Room after scheduled expenses')->assertSee('ZAR 500.00')->assertSee('Spending by category')
        ->assertSee('Allocate money')->assertSee('not your bank balance');
    $card = $response->viewData('budgetOverview')['cards']->sole();
    expect($card['totals'])->toBe(app(BudgetWorkspace::class)->data($this->period->fresh())['totals'])
        ->and($card['totals'])->toMatchArray(['expected' => 100000, 'received' => 80000, 'spent' => 20000, 'upcoming' => 30000, 'after_commitments' => 50000])
        ->and($card['dueSoonCents'])->toBe(30000)->and($card['daysLeft'])->toBe(29)
        ->and($card['dailyRoom'])->toBe(1724)->and($card['spentPercent'])->toBe(33.3);
    $response->assertSee(e(route('budgets.index', ['period' => $this->period->id, 'tab' => 'recurring'])), false)
        ->assertSee(e(route('budgets.index', ['period' => $this->period->id, 'tab' => 'plan'])), false);
});

test('next seven days includes both schedule types at the boundary and excludes recorded payments', function () {
    $this->budget->update(['include_subscriptions' => true]);
    BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Subscriptions', 'kind' => 'subscriptions', 'allocated_cents' => null]);
    Subscription::factory()->for($this->owner)->create(['name' => 'Due today', 'next_billing_date' => '2026-10-03', 'amount_cents' => 1000]);
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Home', 'name' => 'Boundary bill', 'start_date' => '2026-10-09', 'amount_cents' => 2000]);
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Home', 'name' => 'Later bill', 'start_date' => '2026-10-10', 'amount_cents' => 4000]);
    $paid = BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Home', 'start_date' => '2026-10-05', 'amount_cents' => 9000]);
    $charge = BudgetRecurringCharge::factory()->create(['budget_period_id' => $this->period->id, 'budget_recurring_expense_id' => $paid->id, 'budget_category_id' => $this->category->id, 'name' => 'Already recorded', 'scheduled_date' => '2026-10-05', 'amount_cents' => 9000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $this->category->id, 'budget_recurring_charge_id' => $charge->id, 'amount_cents' => 8500]);
    $card = $this->get(route('dashboard'))->assertOk()->viewData('budgetOverview')['cards']->sole();
    expect($card['dueSoonCents'])->toBe(3000)->and($card['dueSoonCount'])->toBe(2)
        ->and($card['totals']['upcoming'])->toBe(7000)->and($card['totals']['spent'])->toBe(28500)
        ->and($card['payments']->pluck('name')->all())->toBe(['Due today', 'Boundary bill']);
});

test('overdue forecasts are called unrecorded and are not included in next seven day totals', function () {
    BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Earlier payment', 'scheduled_date' => '2026-10-01', 'amount_cents' => 5000]);
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('1 scheduled payment not recorded')->assertSee('Confirm whether these were paid.');
    $card = $response->viewData('budgetOverview')['cards']->sole();
    expect($card['overdueCount'])->toBe(1)->and($card['dueSoonCents'])->toBe(0)->and($card['totals']['upcoming'])->toBe(5000)
        ->and($card['payments']->sole()['name'])->toBe('Earlier payment');
});

test('group limits use expected income and display over limit actions', function () {
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Needs', 'percentage_basis_points' => 1000]);
    $this->category->budget_group_id = $group->id;
    $this->category->save();
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('Spending by group')->assertSee('Needs is over its limit')->assertSee('Adjust limits')->assertDontSee('Spending by category');
    $card = $response->viewData('budgetOverview')['cards']->sole();
    expect($card['rows']->sole())->toMatchArray(['name' => 'Needs', 'limit' => 10000, 'spent' => 20000])
        ->and($card['insights'][0]['amount'])->toBe(10000)->and($card['risk'])->toBe(2);
});

test('forecast shortfalls are highlighted before budgets with room and never shown as negative daily allowances', function () {
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Home', 'start_date' => '2026-10-05', 'amount_cents' => 90000]);
    $other = Budget::factory()->create(['user_id' => $this->owner->id, 'name' => 'A separate budget', 'include_subscriptions' => false]);
    BudgetPeriod::factory()->create(['budget_id' => $other->id]);
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('Forecast shortfall')->assertSee('Spending and forecasts exceed income')->assertSee('Review forecast')->assertDontSee('Forecast room:');
    $cards = $response->viewData('budgetOverview')['cards'];
    expect($cards)->toHaveCount(2)->and($cards->first()['budget']->id)->toBe($this->budget->id)
        ->and($cards->first()['totals']['after_commitments'])->toBe(-10000)->and($cards->first()['dailyRoom'])->toBe(0);
});

test('app administrators cannot see someone elses budget insights', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $this->actingAs($actor)->get(route('dashboard'))->assertOk()->assertDontSee('My October plan')->assertSee('Give your money a plan')
        ->assertViewHas('budgetOverview', fn (array $overview): bool => $overview['totalBudgets'] === 0 && $overview['cards']->isEmpty());
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('household viewers and editors receive separate shared cards and appropriate actions', function (string $role) {
    $this->budget->update(['scope' => 'household']);
    $member = User::factory()->create();
    $this->budget->members()->attach($member, ['role' => $role]);
    $own = Budget::factory()->create(['user_id' => $member->id, 'name' => 'Member personal plan', 'include_subscriptions' => false]);
    $ownPeriod = BudgetPeriod::factory()->create(['budget_id' => $own->id]);
    BudgetIncome::factory()->create(['budget_period_id' => $ownPeriod->id, 'expected_cents' => 300000]);
    $response = $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertSee('My October plan')->assertSee('Member personal plan');
    $cards = $response->viewData('budgetOverview')['cards'];
    $shared = $cards->first(fn (array $card): bool => $card['budget']->id === $this->budget->id);
    expect($cards)->toHaveCount(2)->and($shared['canEdit'])->toBe($role === 'editor')
        ->and($shared['totals']['expected'])->toBe(100000)->and($shared['insights'][0]['action'])->toBe($role === 'viewer' ? 'Review allocations' : 'Allocate money')
        ->and($cards->first(fn (array $card): bool => $card['budget']->id === $own->id)['totals']['expected'])->toBe(300000);
    if ($role === 'viewer') {
        $response->assertSee('View only');
    }
})->with(['viewer', 'editor']);

test('malformed personal membership grants no dashboard access and owners are not duplicated', function () {
    $member = User::factory()->create();
    $this->budget->members()->attach($member, ['role' => 'editor']);
    $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertDontSee('My October plan');
    $this->budget->members()->attach($this->owner, ['role' => 'editor']);
    $overview = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->viewData('budgetOverview');
    expect($overview['cards'])->toHaveCount(1)->and($overview['totalBudgets'])->toBe(1);
});

test('inactive periods offer the nearest future plan without presenting its amounts as current', function () {
    $this->period->update(['start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-31']);
    $next = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('No budget period covers today')->assertSee('starting 01 Nov 2026')->assertSee('Review next period')->assertDontSee('Room after scheduled expenses');
    expect($response->viewData('budgetOverview')['nextPeriod']->id)->toBe($next->id);
    $next->delete();
    $this->period->budget->periods()->where('start_date', '>', '2026-10-03')->delete();
    $this->get(route('dashboard'))->assertOk()->assertSee('Your latest period ended 30 Sep 2026');
});

test('custom period boundaries are inclusive and a final day forecast divides by one', function () {
    $this->period->update(['start_date' => '2026-09-20', 'end_date' => '2026-10-03']);
    $card = $this->get(route('dashboard'))->assertOk()->assertSee('1 day left, including today')->viewData('budgetOverview')['cards']->sole();
    expect($card['daysLeft'])->toBe(1)->and($card['elapsedPercent'])->toBe(100)->and($card['dailyRoom'])->toBe(80000);
    $this->period->update(['start_date' => '2026-10-03', 'end_date' => '2026-10-16']);
    $card = $this->get(route('dashboard'))->assertOk()->viewData('budgetOverview')['cards']->sole();
    expect($card['daysLeft'])->toBe(14)->and($card['elapsedPercent'])->toBe(7);
});

test('a new account receives a budget setup action without empty financial charts', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('Create a budget')->assertSee('Give your money a plan')->assertDontSee('of plan spent');
});
