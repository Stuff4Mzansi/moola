<?php

namespace App\Http\Controllers;

use App\BillingFrequency;
use App\BudgetMoney;
use App\BudgetWorkspace;
use App\DebtInterest;
use App\DebtPayoff;
use App\DebtWorkspace;
use App\Http\Requests\DebtPaymentRequest;
use App\Http\Requests\DebtRequest;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DebtController extends Controller
{
    public function index(Request $request, DebtWorkspace $workspace, DebtPayoff $payoff): View
    {
        $request->validate(['extra' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/']]);
        $data = $workspace->build($request->user());
        $extra = BudgetMoney::cents($request->input('extra') ?? '0');
        $debts = $data['rows']->map(fn (array $row): array => ['id' => $row['debt']->id, 'name' => $row['debt']->name, 'balance' => $row['balance'], 'rate' => $row['debt']->annual_rate_basis_points, 'minimum' => $row['debt']->minimum_payment_cents])->all();
        $charges = BudgetRecurringCharge::query()->whereIn('debt_id', $data['rows']->pluck('debt.id'))->where('is_current', true)->whereHas('period', fn (Builder $periods): Builder => $periods->whereDate('start_date', '<=', CarbonImmutable::today())->whereDate('end_date', '>=', CarbonImmutable::today()))->whereDoesntHave('transaction')->orderBy('scheduled_date')->get();

        return view('debts.index', [...$data, 'budgets' => Budget::query()->where('user_id', $request->user()->id)->orderBy('name')->get(), 'charges' => $charges, 'extra' => $extra, 'plans' => ['snowball' => $payoff->simulate($debts, $extra, 'snowball'), 'avalanche' => $payoff->simulate($debts, $extra, 'avalanche')]]);
    }

    public function interest(Request $request, Debt $debt, DebtInterest $interest): JsonResponse
    {
        Gate::authorize('update', $debt);
        $request->validate(['amount' => BudgetMoney::rules(true), 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$debt->balance_date->toDateString(), 'before_or_equal:today'], 'payment_id' => ['nullable', 'integer']]);
        $payment = $request->filled('payment_id') ? $debt->payments()->findOrFail($request->integer('payment_id')) : null;
        $amount = BudgetMoney::cents($request->input('amount'));
        $estimate = $interest->estimate($debt, $amount, CarbonImmutable::parse($request->input('date')), $payment?->id);

        return response()->json(['interest' => number_format($estimate / 100, 2, '.', ''), 'principal' => number_format(($amount - $estimate) / 100, 2, '.', '')]);
    }

    public function store(DebtRequest $request): RedirectResponse
    {
        return $this->persist($request, null);
    }

    public function update(DebtRequest $request, Debt $debt): RedirectResponse
    {
        return $this->persist($request, $debt);
    }

    private function persist(DebtRequest $request, ?Debt $debt): RedirectResponse
    {
        $budget = $request->filled('budget_id') ? Budget::query()->where('user_id', $request->user()->id)->findOrFail($request->integer('budget_id')) : null;
        $ids = $debt?->schedules()->pluck('budget_id')->all() ?? [];
        if ($budget !== null) {
            $ids[] = $budget->id;
        }
        $this->locked($ids, function () use ($debt, $budget, $request): void {
            $debt?->refresh();
            $balance = BudgetMoney::cents($request->input('balance'));
            if ($debt?->payments()->withTrashed()->exists() && ($balance !== $debt->opening_balance_cents || $request->input('balance_date') !== $debt->balance_date->toDateString())) {
                throw ValidationException::withMessages(['balance' => 'Starting balance and date are fixed once payment history exists. Correct payments to update the tracked balance.']);
            }
            $debt ??= new Debt;
            $debt->user_id = $request->user()->id;
            $debt->fill(['name' => $request->input('name'), 'creditor' => $request->input('creditor'), 'opening_balance_cents' => $balance, 'balance_date' => $request->input('balance_date'), 'annual_rate_basis_points' => BudgetMoney::cents($request->input('annual_rate')), 'minimum_payment_cents' => BudgetMoney::cents($request->input('minimum_payment')), 'due_anchor' => $request->input('due_anchor'), 'notes' => $request->input('notes')])->save();
            $schedule = $debt->schedules()->first();
            $oldBudget = $schedule === null ? null : Budget::query()->find($schedule->budget_id);
            if ($schedule !== null && ($budget === null || $budget->id !== $schedule->budget_id)) {
                $schedule->delete();
                $schedule = null;
            }
            if ($budget !== null) {
                $schedule ??= $budget->recurringExpenses()->make();
                $schedule->debt_id = $debt->id;
                $schedule->fill(['name' => 'Debt: '.Str::limit($debt->name, 94, ''), 'category_name' => $schedule->category_name ?? 'Debt payments', 'amount_cents' => $debt->minimum_payment_cents, 'billing_frequency' => BillingFrequency::Monthly, 'start_date' => $debt->due_anchor, 'end_date' => null, 'is_active' => $debt->minimum_payment_cents > 0])->save();
            }
            foreach (collect([$oldBudget, $budget])->filter()->unique('id') as $affected) {
                $this->syncBudget($affected);
            }
        });

        return to_route('debts.index')->with('status', 'Debt saved.');
    }

    public function payment(DebtPaymentRequest $request, Debt $debt): RedirectResponse
    {
        $existing = $request->filled('payment_id') ? $debt->payments()->findOrFail($request->integer('payment_id')) : null;
        $charge = $request->filled('charge_id') ? BudgetRecurringCharge::query()->where('debt_id', $debt->id)->findOrFail($request->integer('charge_id')) : null;
        $transaction = $existing?->budgetTransaction;
        $period = $transaction?->period ?? ($charge === null ? null : BudgetPeriod::query()->findOrFail($charge->budget_period_id));
        if ($period !== null) {
            Gate::authorize('update', $period->budget);
        }
        $this->locked($period === null ? [] : [$period->budget_id], function () use ($request, $debt, $existing, $charge, $period, $transaction): void {
            $amount = BudgetMoney::cents($request->input('amount'));
            $estimated = $request->input('interest_mode') === 'automatic';
            $replay = $existing === null ? $debt->payments()->where('request_id', $request->input('request_id'))->first() : null;
            $interest = $estimated ? app(DebtInterest::class)->estimate($debt, $amount, CarbonImmutable::parse($request->input('date')), $existing?->id ?? $replay?->id) : BudgetMoney::cents($request->input('interest'));
            if ($existing === null && DebtPayment::withTrashed()->where('request_id', $request->input('request_id'))->exists()) {
                $replay = $debt->payments()->where('request_id', $request->input('request_id'))->first();
                abort_unless($replay !== null && $replay->amount_cents === $amount && $replay->interest_cents === $interest && $replay->date->toDateString() === $request->input('date'), 422, 'This payment request was already used. Refresh and retry.');

                return;
            }
            if ($period !== null) {
                abort_unless($request->input('date') >= $period->start_date->toDateString() && $request->input('date') <= $period->end_date->toDateString(), 422, 'The payment date must be inside its linked budget period.');
                if ($transaction === null) {
                    abort_unless($charge->is_current, 422, 'This scheduled payment was removed.');
                    abort_if($charge->transaction()->exists(), 422, 'This scheduled payment was already recorded.');
                    $transaction = $period->transactions()->withTrashed()->firstOrNew(['budget_recurring_charge_id' => $charge->id]);
                    $transaction->budget_category_id = $charge->budget_category_id;
                }
                $transaction->interestIsEstimated = $estimated;
                $transaction->fill(['amount_cents' => $amount, 'interest_cents' => $interest, 'date' => $request->input('date'), 'description' => $request->input('notes') ?: 'Debt: '.$debt->name]);
                $transaction->deleted_at = null;
                $transaction->save();
                if ($existing !== null && $existing->budget_transaction_id === null) {
                    $existing->delete();
                }
                if ($existing === null) {
                    DebtPayment::query()->where('budget_transaction_id', $transaction->id)->update(['request_id' => $request->input('request_id')]);
                }
                $period->increment('version');
            } else {
                $payment = $existing ?? $debt->payments()->make(['request_id' => $request->input('request_id')]);
                $payment->fill(['amount_cents' => $amount, 'interest_is_estimated' => $estimated, 'interest_cents' => $interest, 'date' => $request->input('date'), 'notes' => $request->input('notes')])->save();
            }
        });

        return to_route('debts.index', ['tab' => 'payments'])->with('status', 'Payment recorded. Interest does not reduce the tracked principal balance.');
    }

    public function removePayment(Request $request, Debt $debt, DebtPayment $payment): RedirectResponse
    {
        Gate::authorize('update', $debt);
        abort_unless($payment->debt_id === $debt->id, 404);
        $transaction = $payment->budgetTransaction;
        if ($transaction !== null) {
            Gate::authorize('update', $transaction->period->budget);
        }
        $this->locked($transaction === null ? [] : [$transaction->period->budget_id], function () use ($transaction, $payment): void {
            if ($transaction !== null) {
                $transaction->delete();
                $transaction->period->increment('version');
            } else {
                $payment->delete();
            }
        });

        return to_route('debts.index', ['tab' => 'payments'])->with('status', 'Payment removed.')->with('undo_payment', ['debt' => $debt->id, 'payment' => $payment->id]);
    }

    public function restorePayment(Request $request, Debt $debt, DebtPayment $payment): RedirectResponse
    {
        Gate::authorize('update', $debt);
        abort_unless($payment->debt_id === $debt->id && $payment->trashed(), 404);
        $transaction = $payment->budget_transaction_id === null ? null : BudgetTransaction::withTrashed()->find($payment->budget_transaction_id);
        if ($transaction !== null) {
            Gate::authorize('update', $transaction->period->budget);
        }
        $this->locked($transaction === null ? [] : [$transaction->period->budget_id], function () use ($transaction, $payment): void {
            if ($transaction !== null) {
                abort_unless($transaction->date->betweenIncluded($transaction->period->start_date, $transaction->period->end_date), 422, 'The expense no longer fits its budget dates.');
                $transaction->restore();
                $transaction->period->increment('version');
            } else {
                $payment->restore();
            }
        });

        return to_route('debts.index', ['tab' => 'payments'])->with('status', 'Payment restored.');
    }

    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        Gate::authorize('delete', $debt);
        $ids = $debt->schedules()->pluck('budget_id')->all();
        $this->locked($ids, function () use ($debt, $ids): void {
            $debt->schedules()->delete();
            $debt->delete();
            foreach (Budget::query()->whereIn('id', $ids)->get() as $budget) {
                $this->syncBudget($budget);
            }
        });

        return to_route('debts.index')->with('status', 'Debt deleted. Recorded budget expenses were kept.');
    }

    private function syncBudget(Budget $budget): void
    {
        foreach ($budget->periods()->whereDate('end_date', '>=', CarbonImmutable::today())->get() as $period) {
            if ($budget->recurringExpenses()->whereNotNull('debt_id')->exists() && ! $period->categories()->where('name', 'Debt payments')->exists()) {
                $period->categories()->create(['name' => 'Debt payments', 'kind' => 'custom', 'allocated_cents' => 0]);
            }
            app(BudgetWorkspace::class)->sync($period, true);
            $period->increment('version');
        }
    }

    /** @param list<int> $budgetIds */
    private function locked(array $budgetIds, Closure $action): mixed
    {
        return Cache::store('database')->lock('debts:'.auth()->id(), 30)->block(5, fn (): mixed => $this->budgetLocks($budgetIds, $action));
    }

    /** @param list<int> $budgetIds */
    private function budgetLocks(array $budgetIds, Closure $action): mixed
    {
        sort($budgetIds);
        $ids = array_values(array_unique($budgetIds));
        if ($ids === []) {
            return DB::transaction($action);
        }
        $first = array_shift($ids);

        return Cache::store('database')->lock('budget:'.$first, 30)->block(5, fn (): mixed => $this->budgetLocks($ids, $action));
    }
}
