<?php

use App\BillingFrequency;
use App\BudgetRecurringExpenses;
use App\BudgetWorkspace;
use App\Models\BudgetCategory;
use App\Models\BudgetGroup;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use App\Models\User;
use App\RecurringSchedule;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->postJson(route('budgets.store'), ['name' => 'Home budget', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'expected_income' => '10000', 'starter_categories' => 1])->assertOk();
    $this->period = BudgetPeriod::query()->sole();
    $this->creation = ['name' => 'Electricity', 'category_id' => $this->period->categories()->where('name', 'Home')->sole()->id, 'amount' => '1000', 'billing_frequency' => 'monthly', 'start_date' => '2026-07-10', 'is_active' => 1];
});

function recurringUrl(BudgetPeriod $period, string $action): string
{
    return route('budgets.action', ['period' => $period, 'action' => $action]);
}

function recurringData(BudgetPeriod $period, array $data = []): array
{
    return ['version' => $period->fresh()->version, ...$data];
}

function recurringHistory(BudgetRecurringExpense $expense, string $date, int $amount): BudgetTransaction
{
    $day = CarbonImmutable::parse($date);
    $period = BudgetPeriod::query()->where('budget_id', $expense->budget_id)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first();
    if ($period === null) {
        $period = BudgetPeriod::factory()->create(['budget_id' => $expense->budget_id, 'start_date' => $day->startOfMonth()->toDateString(), 'end_date' => $day->endOfMonth()->toDateString()]);
    }
    $charge = BudgetRecurringCharge::factory()->create(['budget_period_id' => $period->id, 'budget_recurring_expense_id' => $expense->id, 'scheduled_date' => $date, 'amount_cents' => $amount]);

    return BudgetTransaction::factory()->create(['budget_period_id' => $period->id, 'budget_recurring_charge_id' => $charge->id, 'amount_cents' => $amount, 'date' => $date]);
}

test('recurring expenses have their own tab and use the starting estimate without history', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $expense = $this->period->budget->recurringExpenses()->sole();
    expect($expense->category_name)->toBe('Home');
    $charge = $this->period->recurringCharges()->sole();
    expect($charge->amount_cents)->toBe(100000)->and($charge->history_months)->toBe(0);
    $this->get(route('budgets.index'))->assertOk()->assertSee('Recurring expenses')->assertSee('Starting estimate.')->assertViewHas('totals', fn (array $totals): bool => $totals['upcoming'] === 100000 && $totals['spent'] === 0);
});

test('suggestions average only actual payments in the prior three completed months for that schedule', function () {
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->period->budget_id, 'amount_cents' => 99900]);
    recurringHistory($expense, '2026-06-10', 900000);
    recurringHistory($expense, '2026-07-10', 10000);
    recurringHistory($expense, '2026-08-10', 20000);
    recurringHistory($expense, '2026-09-10', 30000);
    recurringHistory($expense, '2026-10-02', 800000);
    $deleted = recurringHistory($expense, '2026-09-20', 700000);
    $deleted->delete();
    $other = BudgetRecurringExpense::factory()->create(['budget_id' => $this->period->budget_id, 'name' => $expense->name]);
    recurringHistory($other, '2026-09-10', 600000);
    $estimate = app(BudgetRecurringExpenses::class)->estimate($expense, CarbonImmutable::parse('2026-10-10'));
    expect($estimate)->toBe(['amount_cents' => 20000, 'history_months' => 3, 'history_payments' => 3]);
    expect(app(BudgetRecurringExpenses::class)->estimate($expense, CarbonImmutable::parse('2027-01-10')))->toBe($estimate);
});

test('missing months are excluded and weekly suggestions average individual payments', function () {
    $expense = BudgetRecurringExpense::factory()->create(['budget_id' => $this->period->budget_id, 'billing_frequency' => BillingFrequency::Weekly]);
    recurringHistory($expense, '2026-07-01', 10001);
    recurringHistory($expense, '2026-09-01', 20000);
    recurringHistory($expense, '2026-09-08', 30000);
    expect(app(BudgetRecurringExpenses::class)->estimate($expense, CarbonImmutable::parse('2026-10-10')))->toBe(['amount_cents' => 20000, 'history_months' => 2, 'history_payments' => 3]);
});

test('recording a recurring payment replaces its reservation exactly once and supports undo', function () {
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id, 'percentage_basis_points' => 5000]);
    $category = $this->period->categories()->where('name', 'Home')->sole();
    $category->budget_group_id = $group->id;
    $category->allocated_cents = 100000;
    $category->save();
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $charge = $this->period->recurringCharges()->sole();
    $data = ['id' => $charge->id, 'amount' => '975.50', 'date' => '2026-10-10'];
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, $data))->assertOk();
    $workspace = app(BudgetWorkspace::class)->data($this->period->fresh());
    expect($workspace['totals'])->toMatchArray(['spent' => 97550, 'upcoming' => 0])->and($workspace['groupRows']->sole())->toMatchArray(['spent' => 97550, 'upcoming' => 0]);
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, $data))->assertUnprocessable();
    $transaction = $this->period->transactions()->sole();
    $this->postJson(recurringUrl($this->period, 'expense-remove'), recurringData($this->period, ['id' => $transaction->id]))->assertOk();
    expect(app(BudgetWorkspace::class)->data($this->period->fresh())['totals']['upcoming'])->toBe(100000);
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, $data))->assertOk();
    expect($this->period->transactions()->withTrashed()->count())->toBe(1);
    $this->postJson(recurringUrl($this->period, 'expense-remove'), recurringData($this->period, ['id' => $transaction->id]))->assertOk();
    $this->postJson(recurringUrl($this->period, 'expense-restore'), recurringData($this->period, ['id' => $transaction->id]))->assertOk();
});

test('schedules can be edited paused and removed while keeping recorded payments', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $expense = $this->period->budget->recurringExpenses()->sole();
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, 'id' => $expense->id, 'name' => 'Utilities', 'amount' => '1200']))->assertOk();
    expect($this->period->recurringCharges()->sole()->amount_cents)->toBe(120000);
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, 'id' => $expense->id, 'is_active' => 0]))->assertOk();
    expect(app(BudgetWorkspace::class)->data($this->period->fresh())['totals']['upcoming'])->toBe(0);
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, 'id' => $expense->id]))->assertOk();
    $charge = $this->period->recurringCharges()->sole();
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, ['id' => $charge->id, 'amount' => '950', 'date' => '2026-10-10']))->assertOk();
    $this->postJson(recurringUrl($this->period, 'recurring-remove'), recurringData($this->period, ['id' => $expense->id]))->assertOk();
    expect($this->period->transactions()->sole()->amount_cents)->toBe(95000)->and($this->period->recurringCharges()->sole()->budget_recurring_expense_id)->toBeNull();
});

test('copied periods reuse schedules and forecast from history without copying actual payments', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $expense = $this->period->budget->recurringExpenses()->sole();
    recurringHistory($expense, '2026-09-10', 80000);
    $this->postJson(route('budgets.periods.store', $this->period->budget), ['name' => 'Next', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'copy_from' => $this->period->id, 'expected_income' => '0'])->assertOk();
    $next = BudgetPeriod::query()->latest('id')->first();
    expect($next->recurringCharges()->sole()->amount_cents)->toBe(80000)->and($next->transactions()->count())->toBe(0)->and($this->period->budget->recurringExpenses()->count())->toBe(1);
});

test('past payments can be linked to recurring occurrences without recording expenses twice', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $past = BudgetPeriod::factory()->create(['budget_id' => $this->period->budget_id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $past->id, 'name' => 'Other', 'kind' => 'other']);
    $transaction = BudgetTransaction::factory()->create(['budget_period_id' => $past->id, 'budget_category_id' => $category->id, 'amount_cents' => 87500, 'date' => '2026-09-10']);
    $this->postJson(recurringUrl($past, 'recurring-load'), recurringData($past))->assertOk();
    $charge = $past->recurringCharges()->sole();
    $this->postJson(recurringUrl($past, 'expense-save'), recurringData($past, ['id' => $transaction->id, 'category_id' => $category->id, 'recurring_charge_id' => $charge->id, 'amount' => '875', 'date' => '2026-09-10']))->assertOk();
    expect($past->transactions()->count())->toBe(1);
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertOk();
    expect($this->period->recurringCharges()->sole()->amount_cents)->toBe(87500);
});

test('recurring actions enforce financial membership and period boundaries', function () {
    $this->period->budget->update(['scope' => 'household']);
    $viewer = User::factory()->create();
    $editor = User::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->period->budget->members()->attach($viewer, ['role' => 'viewer']);
    $this->period->budget->members()->attach($editor, ['role' => 'editor']);
    foreach ([$viewer, $admin] as $actor) {
        $this->actingAs($actor)->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertForbidden();
    }
    $this->actingAs($editor)->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $foreign = BudgetRecurringExpense::factory()->create();
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, 'id' => $foreign->id]))->assertNotFound();
    $this->postJson(recurringUrl($this->period, 'recurring-remove'), recurringData($this->period, ['id' => $foreign->id]))->assertNotFound();
    $charge = BudgetRecurringCharge::factory()->create();
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, ['id' => $charge->id, 'amount' => '10', 'date' => '2026-10-10']))->assertNotFound();
    $category = BudgetCategory::factory()->create();
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, 'category_id' => $category->id]))->assertNotFound();
});

test('date previews include recurring forecasts and invalidate when payment history changes', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $data = ['name' => 'Extended', 'start_date' => '2026-10-01', 'end_date' => '2026-11-30'];
    $preview = $this->postJson(recurringUrl($this->period, 'period-preview'), $data)->assertOk()->assertJsonPath('new_recurring_count', 2)->assertJsonPath('new_recurring_cents', 200000);
    $expense = $this->period->budget->recurringExpenses()->sole();
    recurringHistory($expense, '2026-09-10', 80000);
    $this->postJson(recurringUrl($this->period, 'period-save'), recurringData($this->period, [...$data, 'preview_token' => $preview->json('preview_token')]))->assertUnprocessable();
});

test('recurring schedules preserve month-end anchors and inclusive end dates', function () {
    $schedule = new RecurringSchedule(BillingFrequency::Monthly, CarbonImmutable::parse('2026-01-31'));
    expect(array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $schedule->between(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-03-31'))))->toBe(['2026-02-28', '2026-03-31']);
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, 'billing_frequency' => 'weekly', 'start_date' => '2026-10-01', 'end_date' => '2026-10-15']))->assertOk();
    expect($this->period->recurringCharges()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn (CarbonImmutable $date): string => $date->toDateString())->all())->toBe(['2026-10-01', '2026-10-08', '2026-10-15']);
});

test('invalid recurrence inputs are rejected and removals require a confirmation modal', function () {
    foreach ([['amount' => '0'], ['billing_frequency' => 'daily'], ['end_date' => '2026-01-01']] as $invalid) {
        $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, [...$this->creation, ...$invalid]))->assertUnprocessable();
    }
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($this->get(route('budgets.index'))->assertOk()->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//form[contains(@action,"recurring-remove") and @data-confirm]')->length)->toBe(1);
});

test('existing expenses cannot steal another recorded recurring payment or link foreign occurrences', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $charge = $this->period->recurringCharges()->sole();
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, ['id' => $charge->id, 'amount' => '1000', 'date' => '2026-10-10']))->assertOk();
    $data = ['category_id' => $this->creation['category_id'], 'amount' => '1000', 'date' => '2026-10-10', 'recurring_charge_id' => $charge->id];
    $this->postJson(recurringUrl($this->period, 'expense-save'), recurringData($this->period, $data))->assertUnprocessable();
    $foreignCharge = BudgetRecurringCharge::factory()->create();
    $this->postJson(recurringUrl($this->period, 'expense-save'), recurringData($this->period, [...$data, 'recurring_charge_id' => $foreignCharge->id]))->assertNotFound();
    expect($this->period->transactions()->count())->toBe(1);
});

test('deleting a budget cleans up recurring schedules occurrences and payments', function () {
    $this->postJson(recurringUrl($this->period, 'recurring-save'), recurringData($this->period, $this->creation))->assertOk();
    $expense = $this->period->budget->recurringExpenses()->sole();
    $charge = $this->period->recurringCharges()->sole();
    $this->postJson(recurringUrl($this->period, 'recurring-pay'), recurringData($this->period, ['id' => $charge->id, 'amount' => '900', 'date' => '2026-10-10']))->assertOk();
    $transaction = $this->period->transactions()->sole();
    $admin = User::query()->where('role', UserRole::SuperAdmin)->firstOrFail();
    $this->actingAs($admin)->delete(route('admin.budgets.destroy', $this->period->budget))->assertRedirect(route('admin.budgets.index'));
    $this->assertModelMissing($expense);
    $this->assertModelMissing($charge);
    $this->assertDatabaseMissing('budget_transactions', ['id' => $transaction->id]);
});
