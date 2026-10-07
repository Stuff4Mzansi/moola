<?php

use App\BudgetWorkspace;
use App\Models\BudgetCategory;
use App\Models\BudgetIncome;
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
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->creation = ['name' => 'My plan', 'scope' => 'personal', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'expected_income' => '1000.00', 'starter_categories' => 1, 'repeat_cycle' => 'monthly'];
    $this->postJson(route('budgets.store'), $this->creation)->assertOk();
    $this->period = BudgetPeriod::query()->sole();
});

function budgetActionPayload(BudgetPeriod $period, array $data = []): array
{
    return ['version' => $period->fresh()->version, ...$data];
}

function budgetActionUrl(BudgetPeriod $period, string $action): string
{
    return route('budgets.action', ['period' => $period, 'action' => $action]);
}

test('budget workspace contains same-page entry controls and the expected totals', function () {
    $this->get(route('budgets.index'))->assertOk()->assertSee('Your category plan')->assertSee('Copy to next period')->assertSee('Change dates')->assertSee('Add expense')
        ->assertViewHas('totals', fn (array $totals): bool => $totals['expected'] === 100000 && $totals['received'] === 0 && $totals['spent'] === 0);
    expect($this->period->categories)->toHaveCount(7);
});

test('category allocations autosave exact cents and manual limits follow the submitted value', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $response = $this->postJson(budgetActionUrl($this->period, 'category-save'), budgetActionPayload($this->period, ['id' => $food->id, 'name' => 'Groceries', 'amount' => '100.05']));
    $response->assertOk()->assertJsonStructure(['html', 'message']);
    expect($food->fresh()->allocated_cents)->toBe(10005)->and($food->fresh()->name)->toBe('Groceries');
    $this->postJson(budgetActionUrl($this->period, 'category-save'), budgetActionPayload($this->period, ['id' => $food->id, 'name' => 'Groceries', 'amount' => '1.001']))->assertUnprocessable();
});

test('income sources distinguish expected and received funds', function () {
    $this->postJson(budgetActionUrl($this->period, 'income-save'), budgetActionPayload($this->period, ['name' => 'Freelance', 'expected_amount' => '500', 'received_amount' => '250', 'received_date' => '2026-10-03']))->assertOk();
    $this->get(route('budgets.index'))->assertOk()->assertViewHas('totals', fn (array $totals): bool => $totals['expected'] === 150000 && $totals['received'] === 25000);
    $this->postJson(budgetActionUrl($this->period, 'income-save'), budgetActionPayload($this->period, ['name' => 'Missing date', 'expected_amount' => '100', 'received_amount' => '20']))->assertUnprocessable()->assertJsonValidationErrors('received_date');
});

test('overview chart compares the plan with received income even before expenses exist', function () {
    $this->period->categories()->where('name', 'Food')->update(['allocated_cents' => 80000]);
    $this->period->incomes()->sole()->update(['received_cents' => 40000, 'received_date' => '2026-10-02']);
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()
        ->assertSee('data-budget-income-line x1="40" y1="110" x2="660" y2="110" class="stroke-green-600"', false)
        ->assertSee('Plan needs funding')->assertSee('R 400.00')->assertSee('above income received so far')
        ->assertSee('Income received: R 400.00')->assertDontSee('Your spending story starts here');
});

test('overview income line scales above the plan and excludes unreceived expected income', function () {
    $this->period->categories()->where('name', 'Food')->update(['allocated_cents' => 50000]);
    $this->period->incomes()->sole()->update(['received_cents' => 150000, 'received_date' => '2026-10-02']);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'expected_cents' => 900000, 'received_cents' => 0]);
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()
        ->assertSee('data-budget-income-line x1="40" y1="40" x2="660" y2="40"', false)
        ->assertSee('Income received: R 1,500.00')->assertDontSee('data-budget-received-gap', false);
});

test('income autosaves refresh the overview funding line and show zero when nothing is received', function () {
    $this->period->categories()->where('name', 'Food')->update(['allocated_cents' => 80000]);
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()
        ->assertSee('data-budget-income-line x1="40" y1="180" x2="660" y2="180"', false)
        ->assertSee('Plan needs funding')->assertSee('R 800.00')->assertSee('above income received so far');
    $response = $this->postJson(budgetActionUrl($this->period, 'income-save'), budgetActionPayload($this->period, ['name' => 'Received payment', 'expected_amount' => '800', 'received_amount' => '800', 'received_date' => '2026-10-03']))->assertOk();
    expect($response->json('html'))->toContain('Income received: R 800.00')
        ->toContain('data-budget-income-line x1="40" y1="40" x2="660" y2="40"')
        ->not->toContain('data-budget-received-gap');
});

test('overview spending curve retains exact daily totals with bounded smooth segments', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->update(['allocated_cents' => 100000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 10000, 'date' => '2026-10-02']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 5000, 'date' => '2026-10-02']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 15000, 'date' => '2026-10-03']);
    $response = $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()
        ->assertSee('02 Oct: cumulative spending R 150.00')->assertSee('03 Oct: cumulative spending R 300.00')
        ->assertSee('stroke-width="1.5" stroke-dasharray="5 5"', false);
    preg_match('/data-budget-spending-line d="([^"]+)"/', $response->getContent(), $matches);
    preg_match_all('/C ([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+)/', $matches[1], $segments, PREG_SET_ORDER);
    expect($segments)->toHaveCount(2);
    foreach ($segments as $segment) {
        expect((float) $segment[2])->toBeGreaterThanOrEqual((float) $segment[4])
            ->and((float) $segment[4])->toBe((float) $segment[6]);
    }
    expect((float) $segments[1][6])->toBe(138.0);
});

test('smooth spending charts handle a single day and expenses on the first day', function () {
    $this->period->update(['end_date' => '2026-10-01']);
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->update(['allocated_cents' => 10000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 5000, 'date' => '2026-10-01']);
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()
        ->assertSee('data-budget-spending-line d="M 40 110 L 40 110"', false)
        ->assertSee('01 Oct: cumulative spending R 50.00');
});

test('period clock displays the countdown and elapsed progress for the selected dates', function (string $start, string $end, string $label, int $elapsed, ?int $days) {
    $this->period->update(['start_date' => $start, 'end_date' => $end]);
    $response = $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()
        ->assertSee('data-budget-period-clock', false)->assertSee($label)
        ->assertSee('value="'.$elapsed.'" max="100" aria-label="Period elapsed"', false);
    if ($days !== null) {
        $response->assertSee('style="--value:'.$days.';" aria-label="'.$days.'"', false);
    }
})->with([
    'in progress' => ['2026-10-01', '2026-10-31', 'days left', 10, 28],
    'upcoming' => ['2026-10-10', '2026-10-31', 'days to start', 0, 7],
    'ended' => ['2026-09-01', '2026-09-30', 'Period ended', 100, null],
    'last day' => ['2026-10-01', '2026-10-03', 'Ends today', 100, null],
    'single day' => ['2026-10-03', '2026-10-03', 'Ends today', 100, null],
]);

test('expense entry edit delete and undo update category and total spending', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $this->postJson(budgetActionUrl($this->period, 'expense-save'), budgetActionPayload($this->period, ['category_id' => $food->id, 'amount' => '12.34', 'date' => '2026-10-03']))->assertOk();
    $expense = $this->period->transactions()->sole();
    expect($expense->amount_cents)->toBe(1234);
    $this->postJson(budgetActionUrl($this->period, 'expense-save'), budgetActionPayload($this->period, ['id' => $expense->id, 'category_id' => $food->id, 'amount' => '20', 'date' => '2026-10-04', 'description' => 'Lunch']))->assertOk();
    $this->get(route('budgets.index'))->assertViewHas('totals', fn (array $totals): bool => $totals['spent'] === 2000 && $totals['remaining'] === 98000);
    $this->postJson(budgetActionUrl($this->period, 'expense-remove'), budgetActionPayload($this->period, ['id' => $expense->id]))->assertOk()->assertJsonPath('undo_id', $expense->id);
    $this->assertSoftDeleted($expense);
    $this->postJson(budgetActionUrl($this->period, 'expense-restore'), budgetActionPayload($this->period, ['id' => $expense->id]))->assertOk();
    expect($expense->fresh()->deleted_at)->toBeNull()->and($expense->fresh()->amount_cents)->toBe(2000);
});

test('money transfers preserve the total allocation and reject excessive amounts', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $home = $this->period->categories()->where('name', 'Home')->sole();
    $food->update(['allocated_cents' => 10000]);
    $this->postJson(budgetActionUrl($this->period, 'transfer'), budgetActionPayload($this->period, ['from_id' => $food->id, 'to_id' => $home->id, 'amount' => '25']))->assertOk();
    expect($food->fresh()->allocated_cents)->toBe(7500)->and($home->fresh()->allocated_cents)->toBe(2500);
    $this->postJson(budgetActionUrl($this->period, 'transfer'), budgetActionPayload($this->period, ['from_id' => $food->id, 'to_id' => $home->id, 'amount' => '100']))->assertUnprocessable();
});

test('removing a category preserves its expenses in Other', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id]);
    $this->postJson(budgetActionUrl($this->period, 'category-remove'), budgetActionPayload($this->period, ['id' => $food->id]))->assertOk();
    expect($expense->fresh()->budget_category_id)->toBe($this->period->categories()->where('kind', 'other')->sole()->id);
    $this->assertModelMissing($food);
});

test('subscription schedules include both custom boundaries and weekly repetitions', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Start boundary', 'amount_cents' => 1000, 'next_billing_date' => '2026-10-25']);
    Subscription::factory()->for($this->user)->create(['name' => 'End boundary', 'amount_cents' => 2000, 'next_billing_date' => '2026-11-24']);
    Subscription::factory()->for($this->user)->create(['name' => 'Outside', 'amount_cents' => 9999, 'next_billing_date' => '2026-11-25']);
    Subscription::factory()->for($this->user)->create(['name' => 'Weekly', 'amount_cents' => 500, 'billing_frequency' => 'weekly', 'next_billing_date' => '2026-10-25']);
    $this->postJson(route('budgets.store'), [...$this->creation, 'name' => 'Pay cycle', 'start_date' => '2026-10-25', 'end_date' => '2026-11-24'])->assertOk();
    $period = BudgetPeriod::query()->latest('id')->first();
    expect($period->commitments()->count())->toBe(7)->and($period->commitments()->sum('amount_cents'))->toBe(5500);
});

test('recording subscription payments counts each charge once and supports undo', function () {
    Subscription::factory()->for($this->user)->create(['amount_cents' => 15999, 'next_billing_date' => '2026-10-10']);
    $this->get(route('budgets.index'))->assertOk()->assertViewHas('totals', fn (array $totals): bool => $totals['upcoming'] === 15999 && $totals['planned'] === 15999);
    $charge = $this->period->commitments()->sole();
    $data = ['id' => $charge->id, 'amount' => '159.50', 'date' => '2026-10-10'];
    $this->postJson(budgetActionUrl($this->period, 'commitment-pay'), budgetActionPayload($this->period, $data))->assertOk();
    $this->get(route('budgets.index'))->assertViewHas('totals', fn (array $totals): bool => $totals['spent'] === 15950 && $totals['upcoming'] === 0 && $totals['planned'] === 15999);
    $this->postJson(budgetActionUrl($this->period, 'commitment-pay'), budgetActionPayload($this->period, $data))->assertUnprocessable();
    $expense = $this->period->transactions()->sole();
    $this->postJson(budgetActionUrl($this->period, 'expense-remove'), budgetActionPayload($this->period, ['id' => $expense->id]))->assertOk();
    $this->get(route('budgets.index'))->assertViewHas('totals', fn (array $totals): bool => $totals['spent'] === 0 && $totals['upcoming'] === 15999);
    $this->postJson(budgetActionUrl($this->period, 'expense-restore'), budgetActionPayload($this->period, ['id' => $expense->id]))->assertOk();
    $this->assertDatabaseCount('budget_transactions', 1);
});

test('future forecast updates explain subscription changes while historic periods are preserved', function () {
    $subscription = Subscription::factory()->for($this->user)->create(['amount_cents' => 10000, 'next_billing_date' => '2026-09-10']);
    $this->postJson(route('budgets.store'), [...$this->creation, 'name' => 'Historic', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30'])->assertOk();
    $historic = BudgetPeriod::query()->latest('id')->first();
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk();
    $subscription->update(['amount_cents' => 20000]);
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()->assertSee('Subscription forecast updated');
    expect($this->period->commitments()->where('is_current', true)->sum('amount_cents'))->toBe(20000);
    $this->get(route('budgets.index', ['period' => $historic->id]))->assertOk();
    expect($historic->commitments()->sum('amount_cents'))->toBe(10000);
});

test('period copying preserves plans but resets recorded income and transactions', function () {
    $income = $this->period->incomes()->sole();
    $income->update(['received_cents' => 100000, 'received_date' => '2026-10-03']);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id]);
    $this->postJson(route('budgets.periods.store', $this->period->budget), ['name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'expected_income' => '0', 'copy_from' => $this->period->id])->assertOk();
    $copy = $this->period->budget->periods()->latest('id')->first();
    expect($copy->categories()->count())->toBe(7)->and($copy->incomes()->sum('expected_cents'))->toBe(100000)->and($copy->incomes()->sum('received_cents'))->toBe(0)->and($copy->transactions()->count())->toBe(0);
});

test('overlapping periods including shared boundary dates are rejected', function () {
    $this->postJson(route('budgets.periods.store', $this->period->budget), ['name' => 'Overlap', 'start_date' => '2026-10-31', 'end_date' => '2026-11-30', 'expected_income' => '0'])->assertUnprocessable()->assertJsonValidationErrors('start_date');
    $this->postJson(route('budgets.store'), [...$this->creation, 'end_date' => '2026-10-01'])->assertUnprocessable();
});

test('date changes require a matching impact preview and preserve all recorded activity', function () {
    $data = ['name' => 'Custom dates', 'start_date' => '2026-10-02', 'end_date' => '2026-11-02'];
    $this->postJson(budgetActionUrl($this->period, 'period-save'), budgetActionPayload($this->period, $data))->assertUnprocessable()->assertJsonValidationErrors('preview_token');
    $preview = $this->postJson(budgetActionUrl($this->period, 'period-preview'), $data)->assertOk();
    $this->postJson(budgetActionUrl($this->period, 'period-save'), budgetActionPayload($this->period, [...$data, 'preview_token' => $preview->json('preview_token')]))->assertOk();
    expect($this->period->fresh()->end_date->toDateString())->toBe('2026-11-02');
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'date' => '2026-10-02']);
    $this->postJson(budgetActionUrl($this->period, 'period-preview'), [...$data, 'start_date' => '2026-10-03'])->assertUnprocessable();
});

test('subscription changes invalidate an earlier date preview', function () {
    $data = ['name' => 'Custom', 'start_date' => '2026-10-02', 'end_date' => '2026-10-31'];
    $preview = $this->postJson(budgetActionUrl($this->period, 'period-preview'), $data)->assertOk();
    Subscription::factory()->for($this->user)->create();
    $this->postJson(budgetActionUrl($this->period, 'period-save'), budgetActionPayload($this->period, [...$data, 'preview_token' => $preview->json('preview_token')]))->assertUnprocessable();
});

test('stale edits return a recoverable conflict rather than overwriting another change', function () {
    $version = $this->period->version;
    $data = ['name' => 'Custom category', 'amount' => '100', 'version' => $version];
    $this->postJson(budgetActionUrl($this->period, 'category-save'), $data)->assertOk();
    $this->postJson(budgetActionUrl($this->period, 'category-save'), [...$data, 'name' => 'Stale category'])->assertConflict();
    expect($this->period->categories()->where('name', 'Stale category')->exists())->toBeFalse();
});

test('monthly payday cycles and fixed length periods are suggested correctly', function () {
    $this->postJson(route('budgets.store'), [...$this->creation, 'name' => 'Payday', 'start_date' => '2026-10-25', 'end_date' => '2026-11-24'])->assertOk();
    $payday = BudgetPeriod::query()->latest('id')->first();
    $data = app(BudgetWorkspace::class)->data($payday);
    expect($data['nextStart'])->toBe('2026-11-25')->and($data['nextEnd'])->toBe('2026-12-24');
    $payday->budget->update(['repeat_cycle' => 'fixed_days']);
    $data = app(BudgetWorkspace::class)->data($payday->fresh());
    expect($data['nextStart'])->toBe('2026-11-25')->and($data['nextEnd'])->toBe('2026-12-25');
});

test('expenses outside the period and categories from another period cannot be used', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $this->postJson(budgetActionUrl($this->period, 'expense-save'), budgetActionPayload($this->period, ['category_id' => $food->id, 'amount' => '10', 'date' => '2026-11-01']))->assertUnprocessable();
    $otherCategory = BudgetCategory::factory()->create();
    $this->postJson(budgetActionUrl($this->period, 'expense-save'), budgetActionPayload($this->period, ['category_id' => $otherCategory->id, 'amount' => '10', 'date' => '2026-10-03']))->assertNotFound();
});

test('budget opens on a read-only analytics overview with setup in separate tabs', function () {
    $response = $this->get(route('budgets.index'))->assertOk()->assertSee('Spending over time')->assertSee('Spending by category')->assertSee('At a glance');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@role="tab" and @aria-selected="true"]')->item(0)->getAttribute('data-budget-tab'))->toBe('overview');
    expect($xpath->query('//*[@data-budget-panel="overview"]')->item(0)->hasAttribute('hidden'))->toBeFalse();
    expect($xpath->query('//*[@data-budget-panel="overview"]//form')->length)->toBe(0);
    foreach (['plan', 'expenses', 'subscriptions'] as $tab) {
        expect($xpath->query('//*[@data-budget-panel="'.$tab.'"]')->item(0)->hasAttribute('hidden'))->toBeTrue();
    }
    expect($xpath->query('//*[@data-budget-panel="plan"]//form[@data-budget-autosave]')->length)->toBeGreaterThan(0);
});

test('analytics compares recorded spending against category limits and flags overspending', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $food->update(['allocated_cents' => 10000]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 15000, 'date' => '2026-10-02']);
    $response = $this->get(route('budgets.index'))->assertOk()->assertSee('1 category over limit')->assertSee('R 50.00 over limit')->assertSee('150.0% of your planned spending')->assertSee('Largest spending category:');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-budget-panel="overview"]//*[local-name()="svg" and @role="img"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-budget-panel="overview"]//*[@role="img" and contains(@aria-label,"Food")]')->item(0)->getAttribute('aria-label'))->toBe('Food: R 150.00 spent, R 100.00 planned');
});

test('budget name can be edited separately from its period names and financial plan', function () {
    $next = BudgetPeriod::factory()->create(['budget_id' => $this->period->budget_id, 'name' => 'Next month', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
    $oldVersion = $next->fresh()->version;
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $expense = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'amount_cents' => 2500, 'date' => '2026-10-03']);
    $this->get(route('budgets.index'))->assertOk()->assertSee('Rename budget');
    $response = $this->postJson(budgetActionUrl($this->period, 'budget-rename'), budgetActionPayload($this->period, ['name' => 'Household living costs']))->assertOk()->assertJsonPath('message', 'Budget renamed.');
    expect($response->json('html'))->toContain('Household living costs');
    expect($this->period->budget->fresh()->name)->toBe('Household living costs')->and($this->period->fresh()->name)->toBe('My plan')->and($next->fresh()->name)->toBe('Next month')->and($next->fresh()->version)->toBe($oldVersion + 1)->and($expense->fresh()->amount_cents)->toBe(2500)->and($this->period->incomes()->sum('expected_cents'))->toBe(100000);
    $this->postJson(budgetActionUrl($next, 'budget-rename'), ['name' => 'Stale name', 'version' => $oldVersion])->assertConflict();
});

test('budget renaming validates names and ignores fields unrelated to the name', function () {
    foreach (['', '   ', str_repeat('a', 101)] as $name) {
        $this->postJson(budgetActionUrl($this->period, 'budget-rename'), budgetActionPayload($this->period, ['name' => $name]))->assertUnprocessable()->assertJsonValidationErrors('name');
    }
    $this->postJson(budgetActionUrl($this->period, 'budget-rename'), budgetActionPayload($this->period, ['name' => 'New name', 'scope' => 'household', 'user_id' => 999999, 'start_date' => '2020-01-01']))->assertOk();
    expect($this->period->budget->fresh()->scope)->toBe('personal')->and($this->period->budget->fresh()->user_id)->toBe($this->user->id)->and($this->period->fresh()->start_date->toDateString())->toBe('2026-10-01');
});

test('recorded expenses default to descending dates and reorder after a date edit', function () {
    $food = $this->period->categories()->where('name', 'Food')->sole();
    $middle = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'date' => '2026-10-10', 'description' => 'Middle dated purchase']);
    $newest = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'date' => '2026-10-20', 'description' => 'Newest dated purchase']);
    $sameDay = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'date' => '2026-10-20', 'description' => 'Later same day entry']);
    $oldest = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $food->id, 'date' => '2026-10-01', 'description' => 'Oldest dated purchase']);

    $this->get(route('budgets.index', ['period' => $this->period->id, 'tab' => 'expenses']))->assertOk()
        ->assertSee('aria-sort="descending"', false)
        ->assertSeeInOrder(['Later same day entry', 'Newest dated purchase', 'Middle dated purchase', 'Oldest dated purchase'])
        ->assertViewHas('transactions', fn (Collection $transactions): bool => $transactions->modelKeys() === [$sameDay->id, $newest->id, $middle->id, $oldest->id]);

    $response = $this->postJson(budgetActionUrl($this->period, 'expense-save'), budgetActionPayload($this->period, ['id' => $oldest->id, 'category_id' => $food->id, 'amount' => '150', 'date' => '2026-10-25', 'description' => 'Moved dated purchase']))->assertOk();
    $html = $response->json('html');
    expect(strpos($html, 'Moved dated purchase'))->toBeLessThan(strpos($html, 'Later same day entry'));
    $this->get(route('budgets.index'))->assertOk()
        ->assertViewHas('transactions', fn (Collection $transactions): bool => $transactions->modelKeys() === [$oldest->id, $sameDay->id, $newest->id, $middle->id]);
});
