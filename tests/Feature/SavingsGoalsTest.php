<?php

use App\BudgetWorkspace;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use App\SavingsWorkspace;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->goal = SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'name' => 'My private holiday', 'target_cents' => 100000, 'opening_cents' => 10000, 'start_date' => '2026-10-01', 'target_date' => '2026-12-31']);
});

function savingsDetails(array $changes = []): array
{
    return [...['name' => 'My private holiday', 'kind' => 'holiday', 'target' => '1000', 'opening' => '100', 'start_date' => '2026-10-01', 'target_date' => '2026-12-31', 'monthly' => '0'], ...$changes];
}

function savingsContributionData(array $changes = []): array
{
    return [...['amount' => '100', 'date' => '2026-10-04', 'source' => 'goal', 'request_id' => (string) Str::uuid()], ...$changes];
}

function savingsBudget(User $owner): BudgetPeriod
{
    $budget = Budget::factory()->create(['user_id' => $owner->id, 'include_subscriptions' => false]);
    $period = BudgetPeriod::factory()->create(['budget_id' => $budget->id]);
    $period->categories()->create(['name' => 'Savings', 'kind' => 'custom', 'allocated_cents' => 30000]);
    $period->categories()->create(['name' => 'Other', 'kind' => 'other', 'allocated_cents' => 0]);

    return $period;
}

test('goals can be managed on a private single page with monetary and date validation', function () {
    $this->post(route('goals.store'), savingsDetails(['name' => 'Emergency fund', 'kind' => 'emergency', 'target' => '5000.25']))->assertRedirect();
    $goal = SavingsGoal::query()->where('name', 'Emergency fund')->sole();
    expect($goal->target_cents)->toBe(500025)->and($goal->user_id)->toBe($this->owner->id);
    $this->put(route('goals.update', $goal), savingsDetails(['name' => 'Updated goal', 'target_date' => null]))->assertRedirect();
    $this->get(route('goals.index'))->assertOk()
        ->assertSee('Updated goal')
        ->assertSee('Saved towards goals')
        ->assertSee('Combined targets')
        ->assertSee('Monthly saving plan')
        ->assertSee('Saved towards combined goal targets')
        ->assertSee('Contributions over time')
        ->assertSee('data-confirm-title="Delete savings goal?"', false);
    foreach ([['target' => '0'], ['opening' => '1.001'], ['monthly' => '-1'], ['target_date' => '2026-09-01'], ['start_date' => '2026-10-05']] as $invalid) {
        $this->postJson(route('goals.store'), savingsDetails($invalid))->assertUnprocessable();
    }
});

test('monthly planning progress and contribution history reflect recorded savings', function () {
    $row = app(SavingsWorkspace::class)->build($this->owner)['rows']->sole();
    expect($row['monthly'])->toBe(30000)->and($row['needed'])->toBe(30000)->and($row['progress'])->toBe(10)->and($row['status'])->toBe('Needs a boost');
    $data = savingsContributionData();
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    expect($this->goal->contributions()->count())->toBe(1);
    $row = app(SavingsWorkspace::class)->build($this->owner)['rows']->sole();
    expect($row['saved'])->toBe(20000)->and($row['needed'])->toBe(26667)->and($row['thisMonth'])->toBe(10000)->and($row['monthRemaining'])->toBe(20000)->and($row['status'])->toBe('On track');
    $entry = $this->goal->contributions()->sole();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['contribution_id' => $entry->id, 'amount' => '200']))->assertRedirect();
    expect(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(30000);
    $this->putJson(route('goals.update', $this->goal), savingsDetails(['opening' => '200']))->assertUnprocessable();
    $this->get(route('goals.index', ['tab' => 'contributions']))->assertOk()->assertSee('R 200.00');
});

test('contribution removal and undo update progress without duplicates', function () {
    $entry = SavingsContribution::factory()->create(['savings_goal_id' => $this->goal->id]);
    $this->delete(route('goals.contributions.destroy', [$this->goal, $entry]))->assertRedirect()->assertSessionHas('undo_contribution');
    expect(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(10000);
    $this->post(route('goals.contributions.restore', [$this->goal, $entry]))->assertRedirect();
    expect(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(20000)->and(SavingsContribution::withTrashed()->count())->toBe(1);
});

test('goals stay private for household members and all administrator roles', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $period = savingsBudget($this->owner);
    $period->budget->update(['scope' => 'household']);
    $period->budget->members()->attach($actor, ['role' => 'editor']);
    $this->actingAs($actor)->get(route('goals.index'))->assertOk()->assertDontSee('My private holiday');
    $this->get(route('dashboard'))->assertOk()->assertDontSee('My private holiday');
    $this->putJson(route('goals.update', $this->goal), savingsDetails())->assertForbidden();
    $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData())->assertForbidden();
    $this->delete(route('goals.destroy', $this->goal))->assertForbidden();
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('budget linked contributions create one expense and synchronize edits removals and restores', function () {
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $data = savingsContributionData(['source' => 'budget']);
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    $entry = $this->goal->contributions()->sole();
    $transaction = $period->transactions()->sole();
    expect($transaction->savings_goal_id)->toBe($this->goal->id)->and($entry->budget_transaction_id)->toBe($transaction->id)->and(app(BudgetWorkspace::class)->data($period)['totals']['spent'])->toBe(10000);
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-save']), ['id' => $transaction->id, 'category_id' => $category->id, 'date' => '2026-10-04', 'amount' => '150', 'version' => $period->fresh()->version])->assertOk();
    expect($entry->fresh()->amount_cents)->toBe(15000)->and(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(25000);
    $this->delete(route('goals.contributions.destroy', [$this->goal, $entry]))->assertRedirect();
    expect($period->transactions()->count())->toBe(0)->and($this->goal->contributions()->count())->toBe(0);
    $this->post(route('goals.contributions.restore', [$this->goal, $entry]))->assertRedirect();
    expect($period->transactions()->count())->toBe(1)->and($this->goal->contributions()->count())->toBe(1);
});

test('existing savings expenses can be attached without double counting or repeat submissions', function () {
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $transaction = $period->transactions()->create(['budget_category_id' => $category->id, 'amount_cents' => 10000, 'date' => '2026-10-04', 'description' => 'Savings transfer']);
    $data = savingsContributionData(['source' => 'existing', 'transaction_id' => $transaction->id]);
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), $data)->assertRedirect();
    expect($period->transactions()->count())->toBe(1)->and($this->goal->contributions()->count())->toBe(1)->and(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(20000);
    $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'existing', 'transaction_id' => $transaction->id]))->assertUnprocessable();
});

test('foreign budget categories expenses and contributions cannot be used', function () {
    $otherPeriod = savingsBudget(User::factory()->create());
    $category = $otherPeriod->categories()->where('name', 'Savings')->sole();
    $this->putJson(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertNotFound();
    $foreign = SavingsContribution::factory()->create();
    $this->delete(route('goals.contributions.destroy', [$this->goal, $foreign]))->assertNotFound();
    $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData(['contribution_id' => $foreign->id]))->assertNotFound();
    $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'budget']))->assertUnprocessable();
    foreach (['2026-09-30', '2026-10-05'] as $date) {
        $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData(['date' => $date]))->assertUnprocessable();
    }
});

test('deleting a budget retains savings history and deleting a goal retains budget expenses', function () {
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'budget']))->assertRedirect();
    $period->budget->delete();
    expect($this->goal->fresh()->budget_id)->toBeNull()->and($this->goal->contributions()->sole()->budget_transaction_id)->toBeNull()->and(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(20000);
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'budget']))->assertRedirect();
    $this->delete(route('goals.destroy', $this->goal))->assertRedirect();
    expect($period->transactions()->count())->toBe(1)->and($period->transactions()->sole()->savings_goal_id)->toBeNull()->and(SavingsContribution::query()->count())->toBe(0);
});

test('emergency fund helper averages selected essentials over three complete calendar months', function () {
    $period = savingsBudget($this->owner);
    $period->update(['start_date' => '2026-07-01', 'end_date' => '2026-10-31']);
    $food = $period->categories()->create(['name' => 'Food', 'kind' => 'custom', 'allocated_cents' => 0]);
    $period->transactions()->create(['budget_category_id' => $food->id, 'amount_cents' => 30000, 'date' => '2026-07-15']);
    $period->transactions()->create(['budget_category_id' => $food->id, 'amount_cents' => 60000, 'date' => '2026-09-15']);
    $period->transactions()->create(['budget_category_id' => $food->id, 'amount_cents' => 999999, 'date' => '2026-10-02']);
    $this->get(route('goals.index', ['tab' => 'planning', 'helper_budget' => $period->budget_id, 'essential' => ['Food'], 'coverage' => 3]))->assertOk()->assertViewHas('emergency', fn (array $data): bool => $data['monthly'] === 30000 && $data['target'] === 90000)->assertSee('Use this target');
    $this->get(route('goals.index', ['tab' => 'planning', 'essential_monthly' => '500', 'coverage' => 6]))->assertOk()->assertViewHas('emergency', fn (array $data): bool => $data['target'] === 300000);
    $this->get(route('goals.index', ['tab' => 'planning']))->assertOk()->assertViewHas('emergency', fn (array $data): bool => $data['target'] === null);
});

test('completed overdue and no deadline goals give appropriate dashboard actions', function () {
    $this->goal->update(['target_date' => '2026-10-02']);
    expect(app(SavingsWorkspace::class)->build($this->owner)['rows']->sole()['status'])->toBe('Past target date');
    $this->get(route('dashboard'))->assertOk()->assertSee('My private holiday')->assertSee('Set a new target date');
    $this->goal->update(['target_date' => null, 'monthly_cents' => 10000]);
    expect(app(SavingsWorkspace::class)->build($this->owner)['rows']->sole()['status'])->toBe('Building savings');
    SavingsContribution::factory()->create(['savings_goal_id' => $this->goal->id, 'amount_cents' => 90000]);
    $data = app(SavingsWorkspace::class)->build($this->owner);
    expect($data['completed'])->toBe(1)->and($data['monthly'])->toBe(0)->and($data['rows']->sole()['progress'])->toBe(100);
    $this->get(route('goals.index'))->assertOk()->assertSee('Goal reached');
});

test('manual contributions can later link to a budget without counting savings twice', function () {
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData())->assertRedirect();
    $entry = $this->goal->contributions()->sole();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['contribution_id' => $entry->id, 'source' => 'budget']))->assertRedirect();
    expect($this->goal->contributions()->count())->toBe(1)->and($period->transactions()->count())->toBe(1)->and(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(20000);
});

test('budget category links work across periods and follow category renames', function () {
    $period = savingsBudget($this->owner);
    $previous = BudgetPeriod::factory()->create(['budget_id' => $period->budget_id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $previous->categories()->create(['name' => 'Savings', 'kind' => 'custom', 'allocated_cents' => 30000]);
    $this->goal->update(['start_date' => '2026-09-01']);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id, 'start_date' => '2026-09-01']))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'budget', 'date' => '2026-09-10']))->assertRedirect();
    expect($previous->transactions()->count())->toBe(1)->and($period->transactions()->count())->toBe(0);
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'category-save']), ['id' => $category->id, 'name' => 'Savings pot', 'amount' => '300', 'version' => $period->fresh()->version])->assertOk();
    expect($this->goal->fresh()->category_name)->toBe('Savings pot');
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'budget']))->assertRedirect();
    expect($period->transactions()->count())->toBe(1);
});

test('budget deletion and undo from its expenses page synchronize goal history', function () {
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $this->post(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'budget']))->assertRedirect();
    $transaction = $period->transactions()->sole();
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-remove']), ['id' => $transaction->id, 'version' => $period->fresh()->version])->assertOk();
    expect(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(10000);
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-restore']), ['id' => $transaction->id, 'version' => $period->fresh()->version])->assertOk();
    expect($this->goal->contributions()->count())->toBe(1)->and(app(SavingsWorkspace::class)->build($this->owner)['saved'])->toBe(20000);
});

test('existing expenses from another budget and a request token used by another goal are rejected', function () {
    $period = savingsBudget($this->owner);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $this->put(route('goals.update', $this->goal), savingsDetails(['category_id' => $category->id]))->assertRedirect();
    $foreignPeriod = savingsBudget(User::factory()->create());
    $foreign = $foreignPeriod->transactions()->create(['budget_category_id' => $foreignPeriod->categories()->where('name', 'Savings')->sole()->id, 'amount_cents' => 10000, 'date' => '2026-10-04']);
    $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData(['source' => 'existing', 'transaction_id' => $foreign->id]))->assertNotFound();
    $token = (string) Str::uuid();
    $otherGoal = SavingsGoal::factory()->create(['user_id' => $this->owner->id]);
    SavingsContribution::factory()->create(['savings_goal_id' => $otherGoal->id, 'request_id' => $token]);
    $this->postJson(route('goals.contributions.store', $this->goal), savingsContributionData(['request_id' => $token]))->assertUnprocessable();
    expect($this->goal->contributions()->count())->toBe(0)->and($foreign->fresh()->savings_goal_id)->toBeNull();
});

test('emergency estimates exclude removed expenses and enforce budget ownership', function () {
    $period = savingsBudget($this->owner);
    $period->update(['start_date' => '2026-07-01']);
    $category = $period->categories()->where('name', 'Savings')->sole();
    $transaction = $period->transactions()->create(['budget_category_id' => $category->id, 'amount_cents' => 90000, 'date' => '2026-09-15']);
    $transaction->delete();
    $this->get(route('goals.index', ['tab' => 'planning', 'helper_budget' => $period->budget_id, 'essential' => ['Savings']]))->assertOk()->assertViewHas('emergency', fn (array $data): bool => $data['target'] === 0);
    $other = Budget::factory()->create();
    $this->get(route('goals.index', ['tab' => 'planning', 'helper_budget' => $other->id]))->assertNotFound();
});

test('validation errors retain goal dialog input for correction', function () {
    $this->from(route('goals.index'))->post(route('goals.store'), savingsDetails(['name' => 'Keep my input', 'target' => '0']))->assertRedirect(route('goals.index'))->assertSessionHasErrors('target')->assertSessionHasInput('name', 'Keep my input');

});
