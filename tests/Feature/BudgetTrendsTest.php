<?php

use App\BudgetTrends;
use App\BudgetWorkspace;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetGroup;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->budget = Budget::factory()->create(['user_id' => $this->owner->id, 'name' => 'My trend budget', 'include_subscriptions' => false]);
    $this->actingAs($this->owner);
});

test('trends show actual category allocations and recorded spending chronologically for custom periods', function () {
    $current = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'name' => 'Payday October', 'start_date' => '2026-10-03', 'end_date' => '2026-10-16']);
    $past = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'name' => 'Payday September', 'start_date' => '2026-09-12', 'end_date' => '2026-10-02']);
    $future = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'name' => 'Future period', 'start_date' => '2026-10-17', 'end_date' => '2026-10-30']);
    foreach ([$current, $past, $future] as $period) {
        $group = BudgetGroup::factory()->create(['budget_period_id' => $period->id, 'percentage_basis_points' => 5000]);
        $category = BudgetCategory::factory()->create(['budget_period_id' => $period->id, 'budget_group_id' => $group->id, 'name' => 'Home', 'allocated_cents' => 10000]);
        BudgetCategory::factory()->create(['budget_period_id' => $period->id, 'allocated_cents' => 20000]);
        BudgetTransaction::factory()->create(['budget_period_id' => $period->id, 'budget_category_id' => $category->id, 'amount_cents' => $period->id === $past->id ? 35000 : 5000]);
        $deleted = BudgetTransaction::factory()->create(['budget_period_id' => $period->id, 'amount_cents' => 999999]);
        $deleted->delete();
    }
    BudgetRecurringExpense::factory()->create(['budget_id' => $this->budget->id, 'category_name' => 'Home', 'amount_cents' => 40000, 'start_date' => '2026-10-05']);
    $response = $this->get(route('dashboard'))->assertOk()->assertSee('Budget trends')->assertSee('Last 6 periods')->assertSee('All periods')->assertSee('data-trend-mode="line"', false)->assertSee('data-trend-mode="bar"', false);
    $periods = $response->viewData('budgetTrends')[0]['periods'];
    expect(array_column($periods, 'id'))->toBe([$past->id, $current->id]);
    expect($periods[0])->toMatchArray(['start' => '2026-09-12', 'end' => '2026-10-02', 'planned' => 30000, 'spent' => 35000, 'complete' => true]);
    expect($periods[1])->toMatchArray(['planned' => 30000, 'spent' => 5000, 'complete' => false]);
    $workspace = app(BudgetWorkspace::class)->data($current);
    expect($workspace['totals']['upcoming'])->toBe(40000)->and($periods[1]['spent'])->toBe($workspace['totals']['spent'])->and($periods[1]['planned'])->toBe($workspace['totals']['planned']);
});

test('historical subscription allocations include paid snapshots but exclude removed and out of period forecasts', function () {
    $period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $period->id, 'kind' => 'subscriptions', 'allocated_cents' => null]);
    $paid = BudgetCommitment::factory()->create(['budget_period_id' => $period->id, 'scheduled_date' => '2026-09-04', 'amount_cents' => 2000, 'is_current' => false]);
    BudgetTransaction::factory()->create(['budget_period_id' => $period->id, 'budget_category_id' => $category->id, 'budget_commitment_id' => $paid->id, 'amount_cents' => 1500, 'date' => '2026-09-04']);
    BudgetCommitment::factory()->create(['budget_period_id' => $period->id, 'scheduled_date' => '2026-09-12', 'amount_cents' => 3000]);
    BudgetCommitment::factory()->create(['budget_period_id' => $period->id, 'scheduled_date' => '2026-09-15', 'amount_cents' => 50000, 'is_current' => false]);
    BudgetCommitment::factory()->create(['budget_period_id' => $period->id, 'scheduled_date' => '2026-10-01', 'amount_cents' => 90000]);
    $trend = app(BudgetTrends::class)->build($this->owner)[0]['periods'][0];
    expect($trend)->toMatchArray(['planned' => 5000, 'spent' => 1500]);
    expect($trend['planned'])->toBe(app(BudgetWorkspace::class)->data($period)['totals']['planned']);
    $category->update(['allocated_cents' => 7000]);
    expect(app(BudgetTrends::class)->build($this->owner)[0]['periods'][0]['planned'])->toBe(7000);
});

test('dashboard historical trends remain private for every app role', function (UserRole $role) {
    BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
    $actor = User::factory()->create(['role' => $role]);
    $this->actingAs($actor)->get(route('dashboard'))->assertOk()->assertDontSee('My trend budget')->assertViewHas('budgetTrends', []);
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('invited household budgets remain separate from personal history and revoked access removes trends', function () {
    $this->budget->update(['scope' => 'household']);
    $member = User::factory()->create();
    $this->budget->members()->attach($member, ['role' => 'viewer']);
    $shared = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    BudgetCategory::factory()->create(['budget_period_id' => $shared->id, 'allocated_cents' => 10000]);
    $personal = Budget::factory()->create(['user_id' => $member->id]);
    $own = BudgetPeriod::factory()->create(['budget_id' => $personal->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
    BudgetCategory::factory()->create(['budget_period_id' => $own->id, 'allocated_cents' => 30000]);
    $trends = $this->actingAs($member)->get(route('dashboard'))->assertOk()->viewData('budgetTrends');
    expect($trends)->toHaveCount(2);
    $byBudget = collect($trends)->keyBy('id');
    expect($byBudget[$this->budget->id]['periods'][0]['planned'])->toBe(10000)->and($byBudget[$personal->id]['periods'][0]['planned'])->toBe(30000);
    $this->budget->members()->detach($member);
    expect(app(BudgetTrends::class)->build($member))->toHaveCount(1);
    $this->budget->members()->attach($member, ['role' => 'editor']);
    $this->budget->update(['scope' => 'personal']);
    expect(app(BudgetTrends::class)->build($member))->toHaveCount(1);
});

test('single zero allocation periods and future only budgets have honest data without fabricated history', function () {
    $future = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
    expect(app(BudgetTrends::class)->build($this->owner)[0]['periods'])->toBe([]);
    $future->update(['start_date' => '2026-10-03', 'end_date' => '2026-10-03']);
    $period = app(BudgetTrends::class)->build($this->owner)[0]['periods'][0];
    expect($period)->toMatchArray(['planned' => 0, 'spent' => 0, 'complete' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    expect(app(BudgetTrends::class)->build($this->owner)[0]['periods'][0]['complete'])->toBeTrue();
});

test('budget names embedded in chart data cannot terminate its script element', function () {
    $this->budget->update(['name' => '</script><script>alert(1)</script>']);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('</script><script>alert(1)</script>', false)->assertSee('\\u003C', false);
});
