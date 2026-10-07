<?php

use App\BudgetWorkspace;
use App\Models\BudgetCategory;
use App\Models\BudgetGroup;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->postJson(route('budgets.store'), ['name' => 'My budget', 'scope' => 'personal', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'expected_income' => '10000', 'starter_categories' => 1])->assertOk();
    $this->period = BudgetPeriod::query()->sole();
});

function groupAction(BudgetPeriod $period, string $action): string
{
    return route('budgets.action', ['period' => $period, 'action' => $action]);
}

function groupPayload(BudgetPeriod $period, array $data): array
{
    return ['version' => $period->fresh()->version, ...$data];
}

test('new budgets have no groups and users can opt into a 50 30 20 plan', function () {
    expect($this->period->groups()->count())->toBe(0);
    $this->get(route('budgets.index'))->assertOk()->assertSee('No groups yet.');
    foreach (['Needs' => '50', 'Wants' => '30', 'Savings' => '20'] as $name => $percentage) {
        $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, compact('name', 'percentage')))->assertOk();
    }
    $data = app(BudgetWorkspace::class)->data($this->period->fresh());
    expect($data['groupPercentageTotal'])->toBe(10000)->and($data['groupRows']->pluck('limit')->all())->toBe([500000, 300000, 200000]);
    expect($data['totals']['planned'])->toBe(0);
    $this->get(route('budgets.index'))->assertSee('Your group balance');
});

test('group balances use compact rows within a bounded scroll region for any group count', function (int $count) {
    BudgetGroup::factory()->count($count)->create(['budget_period_id' => $this->period->id, 'percentage_basis_points' => null]);
    $response = $this->get(route('budgets.index'))->assertOk()->assertSee('max-h-64 overflow-auto', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-group-balance-table]//tr[@data-group-balance-row]')->length)->toBe($count)
        ->and($xpath->query('//*[@data-group-balance-table]//thead[contains(@class,"sticky")]')->length)->toBe(1);
})->with([1, 4, 12]);

test('group percentage limits validate exact decimals total coverage and unique names', function () {
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['name' => 'Needs', 'percentage' => '50.25']))->assertOk();
    $group = $this->period->groups()->sole();
    expect($group->percentage_basis_points)->toBe(5025);
    foreach (['-1', '100.01', '1.001', '1e1'] as $percentage) {
        $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['name' => 'Invalid', 'percentage' => $percentage]))->assertUnprocessable()->assertJsonValidationErrors('percentage');
    }
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['name' => 'Wants', 'percentage' => '50']))->assertUnprocessable();
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['name' => 'Needs', 'percentage' => '10']))->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['id' => $group->id, 'name' => 'Essentials', 'percentage' => '100']))->assertOk();
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['id' => $group->id, 'name' => 'Essentials', 'percentage' => null]))->assertOk();
    expect($group->fresh()->percentage_basis_points)->toBeNull();
});

test('group analytics aggregate categories and follow changes in income and spending', function () {
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Needs', 'percentage_basis_points' => 5000]);
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->update(['allocated_cents' => 600000]);
    $this->postJson(groupAction($this->period, 'category-group'), groupPayload($this->period, ['id' => $food->id, 'group_id' => $group->id]))->assertOk();
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 550000, 'date' => '2026-10-03']);
    $data = app(BudgetWorkspace::class)->data($this->period->fresh());
    expect($data['groupRows']->sole())->toMatchArray(['planned' => 600000, 'spent' => 550000, 'limit' => 500000, 'remaining' => -50000]);
    $this->get(route('budgets.index'))->assertSee('Over limit by R 500.00')->assertSee('Planned categories exceed the group limit.');
    $this->period->incomes()->sole()->update(['expected_cents' => 2000000]);
    expect(app(BudgetWorkspace::class)->data($this->period->fresh())['groupRows']->sole()['limit'])->toBe(1000000);
    $expense->delete();
    expect(app(BudgetWorkspace::class)->data($this->period->fresh())['groupRows']->sole()['spent'])->toBe(0);
    $expense->restore();
    $this->postJson(groupAction($this->period, 'category-group'), groupPayload($this->period, ['id' => $food->id, 'group_id' => null]))->assertOk();
    expect(app(BudgetWorkspace::class)->data($this->period->fresh())['groupRows']->sole()['spent'])->toBe(0)->and($expense->fresh()->amount_cents)->toBe(550000);
});

test('removing a group preserves categories allocations and recorded expenses', function () {
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id]);
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->budget_group_id = $group->id;
    $food->allocated_cents = 12345;
    $food->save();
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 10000]);
    $this->postJson(groupAction($this->period, 'group-remove'), groupPayload($this->period, ['id' => $group->id]))->assertOk();
    expect($food->fresh()->budget_group_id)->toBeNull()->and($food->fresh()->allocated_cents)->toBe(12345)->and($expense->fresh()->amount_cents)->toBe(10000);
});

test('copying a plan remaps groups to the new period without copying spending', function () {
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Needs', 'percentage_basis_points' => 5000]);
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->budget_group_id = $group->id;
    $food->save();
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id]);
    $this->postJson(route('budgets.periods.store', $this->period->budget), ['name' => 'Next', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'expected_income' => '0', 'copy_from' => $this->period->id])->assertOk();
    $copy = BudgetPeriod::query()->latest('id')->first();
    $newGroup = $copy->groups()->sole();
    expect($newGroup->id)->not->toBe($group->id)->and($newGroup->percentage_basis_points)->toBe(5000)->and($copy->categories()->where('name', 'Food')->sole()->budget_group_id)->toBe($newGroup->id)->and($copy->transactions()->count())->toBe(0);
});

test('group actions enforce period boundaries and household editing permissions', function () {
    $foreignGroup = BudgetGroup::factory()->create();
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $this->postJson(groupAction($this->period, 'category-group'), groupPayload($this->period, ['id' => $food->id, 'group_id' => $foreignGroup->id]))->assertNotFound();
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['id' => $foreignGroup->id, 'name' => 'Forbidden', 'percentage' => '10']))->assertNotFound();
    $this->postJson(groupAction($this->period, 'group-remove'), groupPayload($this->period, ['id' => $foreignGroup->id]))->assertNotFound();
    $foreignCategory = BudgetCategory::factory()->create();
    $this->postJson(groupAction($this->period, 'category-group'), groupPayload($this->period, ['id' => $foreignCategory->id, 'group_id' => null]))->assertNotFound();
    $member = User::factory()->create();
    $this->period->budget->update(['scope' => 'household']);
    $this->period->budget->members()->attach($member, ['role' => 'viewer']);
    $this->actingAs($member)->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['name' => 'Needs', 'percentage' => '50']))->assertForbidden();
    $this->period->budget->members()->updateExistingPivot($member, ['role' => 'editor']);
    $this->postJson(groupAction($this->period, 'group-save'), groupPayload($this->period, ['name' => 'Needs', 'percentage' => '50']))->assertOk();
});

test('subscription payments contribute once to their assigned group', function () {
    Subscription::factory()->for($this->owner)->create(['amount_cents' => 10000, 'next_billing_date' => '2026-10-10']);
    $this->get(route('budgets.index'))->assertOk();
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'percentage_basis_points' => 1000]);
    $category = $this->period->categories()->where('kind', 'subscriptions')->sole();
    $category->budget_group_id = $group->id;
    $category->save();
    $charge = $this->period->commitments()->sole();
    $data = app(BudgetWorkspace::class)->data($this->period->fresh());
    expect($data['groupRows']->sole())->toMatchArray(['spent' => 0, 'upcoming' => 10000, 'available' => 90000]);
    $this->postJson(groupAction($this->period, 'commitment-pay'), groupPayload($this->period, ['id' => $charge->id, 'amount' => '99', 'date' => '2026-10-10']))->assertOk();
    expect(app(BudgetWorkspace::class)->data($this->period->fresh())['groupRows']->sole())->toMatchArray(['spent' => 9900, 'upcoming' => 0, 'available' => 90100]);
});

test('overview charts aggregate groups and retain ungrouped spending with category drill-down', function () {
    $needs = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Needs', 'percentage_basis_points' => 5000]);
    $wants = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Wants']);
    BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Savings', 'percentage_basis_points' => 2000]);
    foreach (['Food' => [$needs->id, 20000, 10000], 'Transport' => [$needs->id, 20000, 20000], 'Health' => [$wants->id, 10000, 5000], 'Home' => [null, 10000, 7000]] as $name => [$groupId, $planned, $spent]) {
        $category = $this->period->categories()->where('name', $name)->sole();
        $category->budget_group_id = $groupId;
        $category->allocated_cents = $planned;
        $category->save();
        BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $category->id, 'amount_cents' => $spent, 'date' => '2026-10-03']);
    }
    $response = $this->get(route('budgets.index'))->assertOk()->assertSee('Spending by group')->assertSee('Largest spending group:')->assertDontSee('Spending by category');
    $rows = $response->viewData('analyticsRows')->keyBy('name');
    expect($rows['Needs'])->toMatchArray(['spent' => 30000, 'planned' => 40000])->and($rows['Ungrouped']['spent'])->toBe(7000)->and($rows['Savings']['spent'])->toBe(0)->and($rows->sum('spent'))->toBe($response->viewData('totals')['spent']);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@aria-labelledby="budget-comparison-title"]//*[@role="img"]')->length)->toBe(4);
    expect($xpath->query('//*[@aria-labelledby="budget-comparison-title"]//*[@role="img" and @aria-label="Needs: R 300.00 spent, R 400.00 planned"]')->length)->toBe(1);
    expect($xpath->query('//*[@aria-labelledby="budget-comparison-title"]//*[@role="img" and contains(@aria-label,"Food")]')->length)->toBe(0);
    expect($xpath->query('//*[@aria-labelledby="budget-comparison-title"]//details//li[contains(.,"Food")]')->length)->toBe(1);
});

test('grouped overview warnings use group percentage limits instead of category allocations', function () {
    $needs = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Needs', 'percentage_basis_points' => 100]);
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->budget_group_id = $needs->id;
    $food->allocated_cents = 50000;
    $food->save();
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 15000, 'date' => '2026-10-03']);
    $this->get(route('budgets.index'))->assertOk()->assertSee('1 group over limit')->assertDontSee('1 category over limit')->assertSee('R 50.00 over limit');
    $needs->update(['percentage_basis_points' => null]);
    $this->get(route('budgets.index'))->assertOk()->assertSee('No group limits configured')->assertDontSee('1 group over limit');
});

test('analytics switches back to category rows after the last group is removed', function () {
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Needs']);
    $this->period->categories()->update(['budget_group_id' => $group->id]);
    $response = $this->get(route('budgets.index'))->assertOk()->assertSee('Spending by group');
    expect($response->viewData('analyticsRows')->pluck('name')->all())->toBe(['Needs']);
    $response = $this->postJson(groupAction($this->period, 'group-remove'), groupPayload($this->period, ['id' => $group->id]))->assertOk();
    expect($response->json('html'))->toContain('Spending by category')->not->toContain('Spending by group');
    $this->get(route('budgets.index'))->assertOk()->assertViewHas('analyticsRows', fn (Collection $rows): bool => $rows->count() === 7);
});
