<?php

use App\BudgetWorkspace;
use App\DebtPayoff;
use App\DebtWorkspace;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.debt_tracking' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->debt = Debt::factory()->create(['user_id' => $this->owner->id, 'name' => 'Private loan', 'opening_balance_cents' => 100000, 'balance_date' => '2026-10-01', 'due_anchor' => '2026-10-04', 'minimum_payment_cents' => 10000, 'annual_rate_basis_points' => 1200]);
});

function debtDetails(array $changes = []): array
{
    return [...['name' => 'Private loan', 'balance' => '1000', 'balance_date' => '2026-10-01', 'annual_rate' => '12', 'minimum_payment' => '100', 'due_anchor' => '2026-10-04'], ...$changes];
}

function debtPaymentData(array $changes = []): array
{
    return [...['amount' => '100', 'interest' => '10', 'date' => '2026-10-04', 'request_id' => (string) Str::uuid()], ...$changes];
}

function debtTestBudget(User $owner): BudgetPeriod
{
    $budget = Budget::factory()->create(['user_id' => $owner->id, 'include_subscriptions' => false]);
    $period = BudgetPeriod::factory()->create(['budget_id' => $budget->id]);
    $period->categories()->create(['name' => 'Other', 'kind' => 'other', 'allocated_cents' => 0]);

    return $period;
}

test('debts can be created edited and viewed from one page with safe monetary validation', function () {
    $this->post(route('debts.store'), debtDetails(['name' => 'Car loan', 'balance' => '1200.05', 'annual_rate' => '18.25']))->assertRedirect(route('debts.index'));
    $debt = Debt::query()->where('name', 'Car loan')->sole();
    expect($debt->opening_balance_cents)->toBe(120005)->and($debt->annual_rate_basis_points)->toBe(1825)->and($debt->user_id)->toBe($this->owner->id);
    $this->put(route('debts.update', $debt), debtDetails(['name' => 'Renamed loan']))->assertRedirect();
    $this->get(route('debts.index'))->assertOk()->assertSee('Renamed loan')->assertSee('Payoff plan')->assertSee('data-confirm-title="Delete debt?"', false);
    $this->postJson(route('debts.store'), debtDetails(['balance' => '1.001']))->assertUnprocessable();
    $this->postJson(route('debts.store'), debtDetails(['annual_rate' => '-1']))->assertUnprocessable();
});

test('principal excludes interest and manual payment replay records only one payment', function () {
    $data = debtPaymentData();
    $this->post(route('debts.payments.store', $this->debt), $data)->assertRedirect();
    $this->post(route('debts.payments.store', $this->debt), $data)->assertRedirect();
    expect($this->debt->payments()->count())->toBe(1);
    $this->get(route('debts.index'))->assertOk()->assertViewHas('total', 91000)->assertViewHas('paid', 9000);
    $payment = $this->debt->payments()->sole();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['payment_id' => $payment->id, 'amount' => '200', 'interest' => '20']))->assertRedirect();
    expect(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(82000);
    $this->putJson(route('debts.update', $this->debt), debtDetails(['balance' => '2000']))->assertUnprocessable();
    $this->put(route('debts.update', $this->debt), debtDetails(['name' => 'Updated title']))->assertRedirect();
});

test('debt payment removal and undo restore the principal balance', function () {
    $payment = DebtPayment::factory()->create(['debt_id' => $this->debt->id, 'amount_cents' => 10000, 'interest_cents' => 1000]);
    $this->delete(route('debts.payments.destroy', [$this->debt, $payment]))->assertRedirect()->assertSessionHas('undo_payment');
    expect(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(100000);
    $this->post(route('debts.payments.restore', [$this->debt, $payment]))->assertRedirect();
    expect(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(91000);
    $this->get(route('debts.index', ['tab' => 'payments']))->assertOk()->assertSee('Remove payment?');
});

test('interest cannot exceed payment and payments cannot precede the starting balance or be future dated', function () {
    foreach ([['interest' => '101'], ['date' => '2026-09-30'], ['date' => '2026-10-05']] as $invalid) {
        $this->postJson(route('debts.payments.store', $this->debt), debtPaymentData($invalid))->assertUnprocessable();
    }
    expect($this->debt->payments()->count())->toBe(0);
});

test('debt data stays private for every app role and household membership', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $period = debtTestBudget($this->owner);
    $period->budget->update(['scope' => 'household']);
    $period->budget->members()->attach($actor, ['role' => 'editor']);
    $this->actingAs($actor)->get(route('debts.index'))->assertOk()->assertDontSee('Private loan');
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Private loan');
    $this->putJson(route('debts.update', $this->debt), debtDetails())->assertForbidden();
    $this->postJson(route('debts.payments.store', $this->debt), debtPaymentData())->assertForbidden();
    $this->delete(route('debts.destroy', $this->debt))->assertForbidden();
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('only owned budgets can be linked and linking creates a minimum based recurring forecast', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $schedule = $this->debt->schedules()->sole();
    expect($schedule->amount_cents)->toBe(10000);
    $charge = $period->recurringCharges()->sole();
    expect($charge->debt_id)->toBe($this->debt->id)->and($charge->amount_cents)->toBe(10000);
    $this->get(route('debts.index'))->assertOk()->assertSee('Debt: Private loan');
    $foreign = debtTestBudget(User::factory()->create());
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $foreign->budget_id]))->assertNotFound();
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'recurring-remove']), ['id' => $schedule->id, 'version' => $period->fresh()->version])->assertUnprocessable();
});

test('a debt payment entered from debts records the budget expense once and synchronizes edits removal and undo', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $data = debtPaymentData(['charge_id' => $charge->id]);
    $this->post(route('debts.payments.store', $this->debt), $data)->assertRedirect();
    $this->post(route('debts.payments.store', $this->debt), $data)->assertRedirect();
    expect($period->transactions()->count())->toBe(1)->and($this->debt->payments()->count())->toBe(1);
    $payment = $this->debt->payments()->sole();
    expect(app(BudgetWorkspace::class)->data($period)['totals'])->toMatchArray(['spent' => 10000, 'upcoming' => 0]);
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['payment_id' => $payment->id, 'amount' => '150', 'interest' => '15']))->assertRedirect();
    expect($period->transactions()->sole()->amount_cents)->toBe(15000)->and(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(86500);
    $this->delete(route('debts.payments.destroy', [$this->debt, $payment]))->assertRedirect();
    expect($period->transactions()->count())->toBe(0)->and(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(100000);
    $this->post(route('debts.payments.restore', [$this->debt, $payment]))->assertRedirect();
    expect($period->transactions()->count())->toBe(1)->and(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(86500);
});

test('budget entered payments synchronize debt history and budget undo never duplicates debt payments', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'recurring-pay']), ['id' => $charge->id, 'amount' => '120', 'date' => '2026-10-04', 'version' => $period->fresh()->version])->assertOk();
    $transaction = $period->transactions()->sole();
    expect($this->debt->payments()->sole()->amount_cents)->toBe(12000)->and(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(88099);
    expect($this->debt->payments()->sole()->interest_cents)->toBe(99)->and($this->debt->payments()->sole()->interest_is_estimated)->toBeTrue();
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-remove']), ['id' => $transaction->id, 'version' => $period->fresh()->version])->assertOk();
    expect($this->debt->payments()->count())->toBe(0);
    $this->postJson(route('budgets.action', ['period' => $period, 'action' => 'expense-restore']), ['id' => $transaction->id, 'version' => $period->fresh()->version])->assertOk();
    expect($this->debt->payments()->count())->toBe(1)->and(DebtPayment::withTrashed()->count())->toBe(1);
});

test('unlinking or deleting a budget retains recorded debt payment history', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['charge_id' => $charge->id]))->assertRedirect();
    $this->put(route('debts.update', $this->debt), debtDetails())->assertRedirect();
    expect($this->debt->payments()->count())->toBe(1)->and($this->debt->schedules()->count())->toBe(0);
    $period->budget->delete();
    expect($this->debt->payments()->sole()->budget_transaction_id)->toBeNull()->and(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(91000);
});

test('debt deletion retains budget expenses while removing unpaid forecasts and its ledger', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['charge_id' => $charge->id]))->assertRedirect();
    $this->delete(route('debts.destroy', $this->debt))->assertRedirect();
    expect($period->transactions()->count())->toBe(1)->and(DebtPayment::query()->count())->toBe(0)->and(Debt::query()->count())->toBe(0);
    $this->get(route('budgets.index'))->assertOk();
});

test('paid debts no longer generate future minimum forecasts and appear as paid off', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['amount' => '1000', 'interest' => '0']))->assertRedirect();
    expect(app(BudgetWorkspace::class)->data($period)['totals']['upcoming'])->toBe(0);
    $this->get(route('debts.index'))->assertOk()->assertSee('Paid off')->assertViewHas('minimum', 0);
    $this->get(route('dashboard'))->assertOk()->assertSee('Debt progress')->assertSee('Paid off');
});

test('payoff models use monthly interest and a constant rolled over payment budget', function () {
    $payoff = app(DebtPayoff::class);
    $debt = ['id' => 1, 'name' => 'Test', 'balance' => 100000, 'minimum' => 10000, 'rate' => 0];
    expect($payoff->simulate([$debt], 0, 'snowball'))->toMatchArray(['months' => 10, 'interest' => 0, 'remaining' => 0]);
    expect($payoff->simulate([$debt], 10000, 'avalanche')['months'])->toBe(5);
    expect($payoff->simulate([[...$debt, 'minimum' => 100000, 'rate' => 1200]], 0, 'avalanche'))->toMatchArray(['months' => 2, 'interest' => 1010]);
    expect($payoff->simulate([[...$debt, 'minimum' => 0, 'rate' => 10000]], 0, 'avalanche')['months'])->toBeNull();
    expect($payoff->simulate([], 0, 'avalanche')['months'])->toBe(0);
    $portfolio = [['id' => 1, 'name' => 'Small', 'balance' => 30000, 'minimum' => 1000, 'rate' => 0], ['id' => 2, 'name' => 'Expensive', 'balance' => 100000, 'minimum' => 2000, 'rate' => 2400]];
    $snowball = $payoff->simulate($portfolio, 10000, 'snowball');
    $avalanche = $payoff->simulate($portfolio, 10000, 'avalanche');
    expect($snowball['order'][0]['id'])->toBe(1)->and($avalanche['order'][0]['id'])->toBe(2)->and($avalanche['interest'])->toBeLessThan($snowball['interest']);
    $this->get(route('debts.index', ['tab' => 'plan', 'extra' => '100']))->assertOk()->assertSee('Snowball')->assertSee('Avalanche')->assertSee('Illustrative monthly estimates');
});

test('a portfolio can recover after early aggregate negative amortization when expensive debt is prioritized', function () {
    $portfolio = [['id' => 1, 'name' => 'Large', 'balance' => 30000, 'minimum' => 5000, 'rate' => 1000], ['id' => 2, 'name' => 'Small expensive', 'balance' => 1000, 'minimum' => 25000, 'rate' => 10000]];
    $plan = app(DebtPayoff::class)->simulate($portfolio, 0, 'avalanche');
    expect($plan['months'])->not->toBeNull();
    $portfolio = [['id' => 1, 'name' => 'Large', 'balance' => 3000000, 'minimum' => 5000, 'rate' => 1000], ['id' => 2, 'name' => 'Small expensive', 'balance' => 100000, 'minimum' => 25000, 'rate' => 10000]];
    $plan = app(DebtPayoff::class)->simulate($portfolio, 0, 'avalanche');
    expect($plan['months'])->not->toBeNull()->and($plan['order'][0]['id'])->toBe(2);
});

test('debt payment IDs cannot be used across debts and linked charges cannot be stolen', function () {
    $other = Debt::factory()->create(['user_id' => $this->owner->id]);
    $payment = DebtPayment::factory()->create(['debt_id' => $other->id]);
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['payment_id' => $payment->id]))->assertNotFound();
    $this->delete(route('debts.payments.destroy', [$this->debt, $payment]))->assertNotFound();
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $this->post(route('debts.payments.store', $other), debtPaymentData(['charge_id' => $charge->id]))->assertNotFound();
});

test('a manual payment can be linked to a budget later without reducing the balance twice', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData())->assertRedirect();
    $payment = $this->debt->payments()->sole();
    $charge = $period->recurringCharges()->sole();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['payment_id' => $payment->id, 'charge_id' => $charge->id]))->assertRedirect();
    expect($this->debt->payments()->count())->toBe(1)->and($period->transactions()->count())->toBe(1)->and(app(DebtWorkspace::class)->build($this->owner)['total'])->toBe(91000);
});

test('month end due dates remain anchored and recording a payment today advances its next scheduled date', function () {
    $this->debt->due_anchor = '2026-01-31';
    $this->debt->balance_date = '2026-01-01';
    $this->debt->save();
    $this->travelTo(CarbonImmutable::parse('2026-02-28 12:00:00'));
    expect(app(DebtWorkspace::class)->build($this->owner)['rows']->sole()['due']->toDateString())->toBe('2026-02-28');
    DebtPayment::factory()->create(['debt_id' => $this->debt->id, 'date' => '2026-02-28']);
    expect(app(DebtWorkspace::class)->build($this->owner)['rows']->sole()['due']->toDateString())->toBe('2026-03-31');
});

test('partial payments do not advance a due date until the minimum has been recorded', function () {
    DebtPayment::factory()->create(['debt_id' => $this->debt->id, 'amount_cents' => 5000, 'interest_cents' => 0]);
    expect(app(DebtWorkspace::class)->build($this->owner)['rows']->sole()['due']->toDateString())->toBe('2026-10-04');
    DebtPayment::factory()->create(['debt_id' => $this->debt->id, 'amount_cents' => 5000, 'interest_cents' => 0]);
    expect(app(DebtWorkspace::class)->build($this->owner)['rows']->sole()['due']->toDateString())->toBe('2026-11-04');
});

test('linked budget edits retain interest and validate against its amount and debt balance date', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['charge_id' => $charge->id]))->assertRedirect();
    $transaction = $period->transactions()->sole();
    $url = route('budgets.action', ['period' => $period, 'action' => 'expense-save']);
    $payload = ['id' => $transaction->id, 'category_id' => $transaction->budget_category_id, 'date' => '2026-10-04', 'amount' => '5', 'version' => $period->fresh()->version];
    $this->postJson($url, $payload)->assertUnprocessable();
    $this->postJson($url, [...$payload, 'amount' => '110'])->assertOk();
    expect($this->debt->payments()->sole()->interest_cents)->toBe(1000)->and($this->debt->payments()->sole()->amount_cents)->toBe(11000);
    $this->postJson($url, [...$payload, 'amount' => '110', 'date' => '2026-10-05', 'version' => $period->fresh()->version])->assertUnprocessable();
    expect($this->debt->payments()->sole()->amount_cents)->toBe(11000);
});

test('automatic interest uses elapsed days and saved principal while keeping retries idempotent', function () {
    $this->debt->update(['opening_balance_cents' => 100000, 'annual_rate_basis_points' => 3650, 'balance_date' => '2026-09-01']);
    $data = debtPaymentData(['interest_mode' => 'automatic', 'interest' => null, 'date' => '2026-10-01']);
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-01']))->assertOk()->assertJson(['interest' => '30.00', 'principal' => '70.00']);
    $this->post(route('debts.payments.store', $this->debt), $data)->assertRedirect();
    $this->post(route('debts.payments.store', $this->debt), $data)->assertRedirect();
    $payment = $this->debt->payments()->sole();
    expect($payment->interest_cents)->toBe(3000)->and($payment->interest_is_estimated)->toBeTrue();
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-01', 'payment_id' => $payment->id]))->assertOk()->assertJson(['interest' => '30.00']);
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-04']))->assertOk()->assertJson(['interest' => '2.79', 'principal' => '97.21']);
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['payment_id' => $payment->id, 'interest_mode' => 'manual', 'interest' => '20', 'date' => '2026-10-01']))->assertRedirect();
    expect($payment->fresh()->interest_cents)->toBe(2000)->and($payment->fresh()->interest_is_estimated)->toBeFalse();
});

test('interest estimates handle small payments same day payments zero rates and deleted history', function () {
    $this->debt->update(['annual_rate_basis_points' => 3650, 'balance_date' => '2026-09-01']);
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['interest_mode' => 'automatic', 'interest' => null, 'amount' => '10', 'date' => '2026-10-01']))->assertRedirect();
    expect($this->debt->payments()->sole()->interest_cents)->toBe(1000);
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-01']))->assertOk()->assertJson(['interest' => '20.00', 'principal' => '80.00']);
    $this->debt->payments()->sole()->delete();
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-01']))->assertOk()->assertJson(['interest' => '30.00']);
    $this->debt->update(['annual_rate_basis_points' => 0]);
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-01']))->assertOk()->assertJson(['interest' => '0.00', 'principal' => '100.00']);
});

test('interest previews enforce debt ownership payment ownership and date validation', function () {
    $other = Debt::factory()->create();
    $otherPayment = DebtPayment::factory()->create(['debt_id' => $other->id]);
    $this->getJson(route('debts.interest', [$other, 'amount' => '100', 'date' => '2026-10-04']))->assertForbidden();
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-04', 'payment_id' => $otherPayment->id]))->assertNotFound();
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-10-05']))->assertUnprocessable();
    $this->getJson(route('debts.interest', [$this->debt, 'amount' => '100', 'date' => '2026-09-01']))->assertUnprocessable();
});

test('automatic budget linked splits stay consistent on debt and budget edits', function () {
    $period = debtTestBudget($this->owner);
    $this->put(route('debts.update', $this->debt), debtDetails(['budget_id' => $period->budget_id]))->assertRedirect();
    $charge = $period->recurringCharges()->sole();
    $this->post(route('debts.payments.store', $this->debt), debtPaymentData(['charge_id' => $charge->id, 'interest_mode' => 'automatic', 'interest' => null]))->assertRedirect();
    $payment = $this->debt->payments()->sole();
    $transaction = $period->transactions()->sole();
    expect($payment->interest_cents)->toBe(99)->and($transaction->interest_cents)->toBe(99)->and($payment->interest_is_estimated)->toBeTrue();
    $url = route('budgets.action', ['period' => $period, 'action' => 'expense-save']);
    $this->postJson($url, ['id' => $transaction->id, 'category_id' => $transaction->budget_category_id, 'date' => '2026-10-03', 'amount' => '150', 'version' => $period->fresh()->version])->assertOk();
    expect($payment->fresh()->interest_cents)->toBe(66)->and($period->transactions()->sole()->interest_cents)->toBe(66);
    $this->get(route('debts.index', ['tab' => 'payments']))->assertOk()->assertSee('Estimated')->assertSee('Estimate automatically');
});
