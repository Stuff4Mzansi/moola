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
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('Budget at a glance')->assertSee('My October plan')
        ->assertSee('A smarter budget, one tip at a time')->assertSee('Adjust dates when your pay cycle changes')
        ->assertSee('Room after scheduled expenses')->assertSee('R 500.00')->assertSee('Spending by category')
        ->assertSee('Allocate money')->assertSee('not your bank balance');
    $card = $response->viewData('budgetOverview')['cards']->sole();
    expect($card['totals'])->toBe(app(BudgetWorkspace::class)->data($this->period->fresh())['totals'])
        ->and($card['totals'])->toMatchArray(['expected' => 100000, 'received' => 80000, 'spent' => 20000, 'upcoming' => 30000, 'after_commitments' => 50000])
        ->and($card['dueSoonCents'])->toBe(30000)->and($card['daysLeft'])->toBe(29)
        ->and($card['dailyRoom'])->toBe(1724)->and($card['spentPercent'])->toBe(33.3);
    $response->assertSee(e(route('budgets.index', ['period' => $this->period->id, 'tab' => 'recurring'])), false)
        ->assertSee(e(route('budgets.index', ['period' => $this->period->id, 'tab' => 'plan'])), false)
        ->assertSee('href="'.e(route('budgets.index', ['period' => $this->period->id])).'"', false);
});

test('dashboard chart data includes current spending pace and uncategorised spending', function () {
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => null, 'amount_cents' => 5000, 'date' => '2026-10-02']);
    $response = $this->get(route('dashboard'))->assertOk()
        ->assertSee('Selected-period spending pace')
        ->assertSee('data-pace-chart', false)
        ->assertDontSee('data-layout-widget="budget-pace"', false)
        ->assertSee('Selected-period spending mix')
        ->assertSee('Income received')
        ->assertSee('Uncategorised');
    expect(strpos($response->getContent(), 'aria-label="Budget period elapsed"'))
        ->toBeLessThan(strpos($response->getContent(), 'aria-label="Selected-period spending pace chart"'));
    $card = $response->viewData('budgetOverview')['cards']->sole();

    expect($card['dailyPace'])->toHaveCount(31)
        ->and($card['dailyPace'][1])->toMatchArray(['date' => '2026-10-02', 'spent' => 5000])
        ->and($card['dailyPace'][2]['spent'])->toBe(25000)
        ->and($card['dailyPace'][3]['spent'])->toBeNull()
        ->and($card['spendingMix'])->toContain(['name' => 'Uncategorised', 'amount' => 5000]);
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
    foreach ([
        ['name' => 'Second upcoming bill', 'date' => '2026-10-04', 'amount' => 1000],
        ['name' => 'Third upcoming bill', 'date' => '2026-10-06', 'amount' => 3000],
        ['name' => 'Fourth upcoming bill', 'date' => '2026-10-08', 'amount' => 4000],
    ] as $upcoming) {
        BudgetRecurringExpense::factory()->create([
            'budget_id' => $this->budget->id,
            'category_name' => $this->category->name,
            'name' => $upcoming['name'],
            'start_date' => $upcoming['date'],
            'amount_cents' => $upcoming['amount'],
        ]);
    }
    BudgetIncome::factory()->create([
        'budget_period_id' => $this->period->id,
        'name' => 'Upcoming salary',
        'expected_cents' => 12000,
        'received_cents' => 2000,
        'expected_date' => '2026-10-05',
    ]);
    BudgetIncome::factory()->create([
        'budget_period_id' => $this->period->id,
        'name' => 'Outside seven days',
        'expected_cents' => 50000,
        'expected_date' => '2026-10-10',
    ]);
    BudgetIncome::factory()->create([
        'budget_period_id' => $this->period->id,
        'name' => 'Already received',
        'expected_cents' => 8000,
        'received_cents' => 8000,
        'expected_date' => '2026-10-06',
    ]);
    $card = $this->get(route('dashboard'))->assertOk()->viewData('budgetOverview')['cards']->sole();
    expect($card['dueSoonCents'])->toBe(11000)->and($card['dueSoonCount'])->toBe(5)
        ->and($card['totals']['upcoming'])->toBe(15000)->and($card['totals']['spent'])->toBe(28500)
        ->and($card['payments']->pluck('name')->all())->toBe(['Due today', 'Second upcoming bill', 'Third upcoming bill'])
        ->and($card['sevenDayEvents']->count())->toBe(6)
        ->and($card['sevenDayEvents']->where('kind', 'expense')->count())->toBe(5)
        ->and($card['sevenDayEvents']->where('kind', 'income')->pluck('name')->all())->toBe(['Upcoming salary'])
        ->and($card['sevenDayIncomeCents'])->toBe(10000)
        ->and($card)->not->toHaveKey('sevenDayNetCents');
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('aria-label="Income and scheduled payments for the next seven days"', false)
        ->assertSee('max-h-40 divide-y divide-base-300/70 overflow-y-auto overscroll-contain', false)
        ->assertSee('Upcoming salary')
        ->assertSee('Second upcoming bill')->assertSee('Third upcoming bill')->assertSee('Fourth upcoming bill')
        ->assertSee('text-success', false)->assertSee('text-warning', false)
        ->assertDontSee(' net</span>', false)
        ->assertSee('Income +R 100.00')->assertSee('Payments −R 110.00');
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
        $this->post(route('dashboard.budget-period.update'), ['period_id' => $this->period->id])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('View only')
            ->assertViewHas('dashboardBudget', fn (array $card): bool => $card['budget']->id === $this->budget->id);
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
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('Starts 1 Nov 2026')->assertSee('Selected-period spending pace')
        ->assertDontSee('Upcoming budget period')->assertDontSee('Cash-flow outlook');
    expect($response->viewData('budgetOverview')['nextPeriod']->id)->toBe($next->id);
    expect($response->viewData('dashboardPeriod')->id)->toBe($next->id);
    $next->delete();
    $this->period->budget->periods()->where('start_date', '>', '2026-10-03')->delete();
    $this->get(route('dashboard'))->assertOk()->assertSee('Period ended')->assertDontSee('Completed budget period');
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
