<?php

namespace App\Http\Controllers;

use App\BudgetMoney;
use App\Http\Requests\SavingsContributionRequest;
use App\Http\Requests\SavingsGoalRequest;
use App\Models\Asset;
use App\Models\AssetReserve;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\SavingsAccounts;
use App\SavingsWorkspace;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SavingsGoalController extends Controller
{
    public function index(Request $request, SavingsWorkspace $workspace): View
    {
        $request->validate(['helper_budget' => ['nullable', 'integer'], 'essential' => ['nullable', 'array', 'max:100'], 'essential.*' => ['string', 'max:100'], 'essential_monthly' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'coverage' => ['nullable', 'integer', 'min:1', 'max:24']]);
        $budgets = Budget::query()->where('user_id', $request->user()->id)->with('periods.categories')->orderBy('name')->get();
        $helperBudget = $request->filled('helper_budget') ? $budgets->firstWhere('id', $request->integer('helper_budget')) : null;
        abort_if($request->filled('helper_budget') && $helperBudget === null, 404);
        $categories = $budgets->flatMap(fn (Budget $budget): Collection => $budget->periods->sortByDesc('start_date')->flatMap(fn (BudgetPeriod $period): Collection => $period->categories->where('kind', 'custom')->map(fn (BudgetCategory $category): array => ['id' => $category->id, 'budget_id' => $budget->id, 'name' => $category->name, 'label' => $budget->name.' / '.$category->name]))->unique('name'))->values();
        $transactions = BudgetTransaction::query()->whereHas('period.budget', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))->whereNull('savings_goal_id')->whereNull('budget_recurring_charge_id')->whereNull('budget_commitment_id')->whereDate('date', '<=', CarbonImmutable::today())->with('period.categories')->orderByDesc('date')->orderByDesc('id')->get();
        $essential = $request->input('essential', []);
        $monthly = null;
        $basis = null;
        if ($request->filled('essential_monthly')) {
            $monthly = BudgetMoney::cents($request->input('essential_monthly'));
            $basis = 'Your monthly essential spending estimate';
        } elseif ($helperBudget !== null && $essential !== []) {
            $validNames = $helperBudget->periods->flatMap(fn (BudgetPeriod $period): Collection => $period->categories->pluck('name'))->unique();
            abort_unless(collect($essential)->diff($validNames)->isEmpty(), 422, 'Select categories from the chosen budget.');
            $end = CarbonImmutable::today()->startOfMonth();
            $total = BudgetTransaction::query()->join('budget_periods', 'budget_periods.id', '=', 'budget_transactions.budget_period_id')->join('budget_categories', 'budget_categories.id', '=', 'budget_transactions.budget_category_id')->where('budget_periods.budget_id', $helperBudget->id)->whereIn('budget_categories.name', $essential)->whereDate('budget_transactions.date', '>=', $end->subMonths(3))->whereDate('budget_transactions.date', '<', $end)->sum('budget_transactions.amount_cents');
            $monthly = (int) ceil($total / 3);
            $basis = 'Average recorded spending across the last three completed months (including months with no records)';
        }
        $coverage = $request->integer('coverage', 3);

        return view('goals.index', [...$workspace->build($request->user()), 'budgets' => $budgets, 'categories' => $categories, 'transactions' => $transactions, 'helperBudget' => $helperBudget, 'accounts' => Asset::query()->where('user_id', $request->user()->id)->where('kind', 'bank')->orderBy('name')->get(), 'emergency' => ['monthly' => $monthly, 'target' => $monthly === null ? null : $monthly * $coverage, 'basis' => $basis, 'coverage' => $coverage]]);
    }

    public function store(SavingsGoalRequest $request): RedirectResponse
    {
        return $this->persist($request, null);
    }

    public function update(SavingsGoalRequest $request, SavingsGoal $goal): RedirectResponse
    {
        return $this->persist($request, $goal);
    }

    private function persist(SavingsGoalRequest $request, ?SavingsGoal $goal): RedirectResponse
    {
        $account = $request->filled('asset_id') ? Asset::query()->where('user_id', $request->user()->id)->where('kind', 'bank')->findOrFail($request->integer('asset_id')) : null;
        $category = $request->filled('category_id') ? BudgetCategory::query()->findOrFail($request->integer('category_id')) : null;
        $period = $category === null ? null : BudgetPeriod::query()->whereHas('budget', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))->findOrFail($category->budget_period_id);
        abort_if($category !== null && $category->kind !== 'custom', 422, 'Choose a custom savings category.');
        $this->locked($period === null ? [] : [$period->budget_id], function () use ($request, $goal, $category, $period, $account): void {
            $goal?->refresh();
            $opening = BudgetMoney::cents($request->input('opening'));
            if ($goal?->contributions()->withTrashed()->exists() && ($goal->opening_cents !== $opening || $goal->start_date->toDateString() !== $request->input('start_date'))) {
                throw ValidationException::withMessages(['opening' => 'Starting savings and date are fixed once contribution history exists. Edit contributions to correct progress.']);
            }
            $goal ??= new SavingsGoal;
            $goal->user_id = $request->user()->id;
            if ($request->has('asset_id')) {
                $goal->asset_id = $account?->id;
                $goal->unsetRelation('account');
            }
            $goal->fill(['name' => $request->input('name'), 'kind' => $request->input('kind'), 'target_cents' => BudgetMoney::cents($request->input('target')), 'opening_cents' => $opening, 'start_date' => $request->input('start_date'), 'target_date' => $request->input('target_date'), 'monthly_cents' => BudgetMoney::cents($request->input('monthly')), 'budget_id' => $period?->budget_id, 'category_name' => $category?->name, 'notes' => $request->input('notes')])->save();
            app(SavingsAccounts::class)->sync($goal);
            app(SavingsAccounts::class)->assertFunded($goal);
        });

        return to_route('goals.index')->with('status', 'Savings goal saved.');
    }

    public function contribute(SavingsContributionRequest $request, SavingsGoal $goal): RedirectResponse
    {
        $existing = $request->filled('contribution_id') ? $goal->contributions()->findOrFail($request->integer('contribution_id')) : null;
        $transaction = $existing?->budgetTransaction;
        $period = $transaction?->period;
        if ($transaction === null && $request->input('source') !== 'goal') {
            abort_if($goal->budget_id === null, 422, 'Link this goal to a budget category first.');
            $period = BudgetPeriod::query()->where('budget_id', $goal->budget_id)->whereDate('start_date', '<=', $request->input('date'))->whereDate('end_date', '>=', $request->input('date'))->first();
            if ($period === null) {
                throw ValidationException::withMessages(['date' => 'No linked budget period covers this date. Create a period or record in the goal only.']);
            }
            if ($request->input('source') === 'existing') {
                $transaction = $period->transactions()->where(fn (Builder $query): Builder => $query->whereNull('savings_goal_id')->orWhere('savings_goal_id', $goal->id))->whereNull('budget_commitment_id')->whereNull('budget_recurring_charge_id')->whereHas('period.budget', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))->findOrFail($request->integer('transaction_id'));
                $category = $period->categories()->findOrFail($transaction->budget_category_id);
                abort_unless($category->name === $goal->category_name, 422, 'Choose an expense in the linked savings category.');
                abort_unless($transaction->amount_cents === BudgetMoney::cents($request->input('amount')) && $transaction->date->toDateString() === $request->input('date'), 422, 'Use the existing expense amount and date.');
            }
        }
        if ($period !== null) {
            Gate::authorize('update', $period->budget);
        }
        $this->locked($period === null ? [] : [$period->budget_id], function () use ($request, $goal, $existing, $period, $transaction): void {
            $goal->refresh();
            $existing?->refresh();
            abort_if($existing?->trashed(), 422, 'This contribution was removed. Refresh and retry.');
            if ($period !== null && $existing === null) {
                abort_unless($goal->budget_id === $period->budget_id, 422, 'The budget link changed. Refresh and retry.');
            }
            $amount = BudgetMoney::cents($request->input('amount'));
            $origin = $request->input('money_origin') ?? $existing?->money_origin ?? 'existing';
            $accountId = $existing?->asset_id ?? $goal->asset_id;
            if ($origin === 'new') {
                $account = Asset::query()->where('user_id', $goal->user_id)->where('kind', 'bank')->find($accountId);
                if ($account === null) {
                    throw ValidationException::withMessages(['money_origin' => 'Link this goal to an active bank or cash account first.']);
                }
                $latest = $account->valuations()->whereDate('date', '<=', CarbonImmutable::today())->orderByDesc('date')->first();
                if ($latest === null || ($existing?->money_origin !== 'new' && $request->input('date') < $latest->date->toDateString())) {
                    throw ValidationException::withMessages(['money_origin' => 'Record an account balance on or before this date first. If this money is already included in a newer balance, choose money already in the account.']);
                }
            }
            if ($existing === null && SavingsContribution::withTrashed()->where('request_id', $request->input('request_id'))->exists()) {
                $replay = $goal->contributions()->where('request_id', $request->input('request_id'))->first();
                abort_unless($replay !== null && $replay->amount_cents === $amount && $replay->date->toDateString() === $request->input('date') && $replay->money_origin === $origin, 422, 'This request was already used. Refresh and retry.');

                return;
            }
            if ($period !== null) {
                abort_unless($request->input('date') >= $period->start_date->toDateString() && $request->input('date') <= $period->end_date->toDateString(), 422, 'The contribution date must be inside its linked budget period.');
                if ($transaction === null) {
                    $category = $period->categories()->where('name', $goal->category_name)->where('kind', 'custom')->first();
                    if ($category === null) {
                        throw ValidationException::withMessages(['source' => 'Add the linked savings category to this budget period first, or record in the goal only.']);
                    }
                    $transaction = $period->transactions()->make(['budget_category_id' => $category->id]);
                } else {
                    $transaction->refresh();
                    if ($existing === null && $request->input('source') === 'existing') {
                        abort_unless($transaction->amount_cents === $amount && $transaction->date->toDateString() === $request->input('date'), 422, 'This expense changed. Refresh and retry.');
                    }
                    abort_if($transaction->trashed() || ($transaction->savings_goal_id !== null && $transaction->savings_goal_id !== $goal->id), 422, 'This expense is no longer available.');
                    abort_if($existing === null && SavingsContribution::withTrashed()->where('budget_transaction_id', $transaction->id)->exists(), 422, 'This expense already has a contribution.');
                }
                $transaction->savingsMoneyOrigin = $origin;
                $transaction->savingsAccountId = $accountId;
                $transaction->savings_goal_id = $goal->id;
                $transaction->fill(['amount_cents' => $amount, 'date' => $request->input('date'), 'description' => $request->input('notes') ?: ($transaction->description ?: 'Savings contribution')])->save();
                if ($existing !== null && $existing->budget_transaction_id === null) {
                    $existing->delete();
                }
                if ($existing === null) {
                    SavingsContribution::query()->where('budget_transaction_id', $transaction->id)->update(['request_id' => $request->input('request_id')]);
                }
                $period->increment('version');
            } else {
                $entry = $existing ?? $goal->contributions()->make(['request_id' => $request->input('request_id')]);
                $entry->asset_id = $accountId;
                $entry->fill(['money_origin' => $origin, 'amount_cents' => $amount, 'date' => $request->input('date'), 'notes' => $request->input('notes')])->save();
            }
            app(SavingsAccounts::class)->assertFunded($goal);
        });

        return to_route('goals.index', ['tab' => 'contributions'])->with('status', 'Contribution recorded.');
    }

    public function remove(SavingsGoal $goal, SavingsContribution $contribution): RedirectResponse
    {
        Gate::authorize('update', $goal);
        abort_unless($contribution->savings_goal_id === $goal->id, 404);
        $transaction = $contribution->budgetTransaction;
        if ($transaction !== null) {
            Gate::authorize('update', $transaction->period->budget);
        }
        $this->locked($transaction === null ? [] : [$transaction->period->budget_id], function () use ($transaction, $contribution): void {
            if ($transaction !== null) {
                $transaction->delete();
                $transaction->period->increment('version');
            } else {
                $contribution->delete();
            }
        });

        return to_route('goals.index', ['tab' => 'contributions'])->with('status', 'Contribution removed.')->with('undo_contribution', ['goal' => $goal->id, 'contribution' => $contribution->id]);
    }

    public function restore(SavingsGoal $goal, SavingsContribution $contribution): RedirectResponse
    {
        Gate::authorize('update', $goal);
        abort_unless($contribution->savings_goal_id === $goal->id && $contribution->trashed(), 404);
        $transaction = $contribution->budget_transaction_id === null ? null : BudgetTransaction::withTrashed()->find($contribution->budget_transaction_id);
        if ($transaction !== null) {
            Gate::authorize('update', $transaction->period->budget);
        }
        $this->locked($transaction === null ? [] : [$transaction->period->budget_id], function () use ($transaction, $contribution, $goal): void {
            if ($transaction !== null) {
                abort_unless($transaction->date->betweenIncluded($transaction->period->start_date, $transaction->period->end_date), 422, 'The contribution no longer fits its budget dates.');
                $transaction->restore();
                $transaction->period->increment('version');
            } else {
                $contribution->restore();
            }
            app(SavingsAccounts::class)->assertFunded($goal);
        });

        return to_route('goals.index', ['tab' => 'contributions'])->with('status', 'Contribution restored.');
    }

    public function destroy(SavingsGoal $goal): RedirectResponse
    {
        Gate::authorize('delete', $goal);
        $this->locked($goal->budget_id === null ? [] : [$goal->budget_id], function () use ($goal): void {
            AssetReserve::query()->where('savings_goal_id', $goal->id)->where('is_automatic', true)->delete();
            $goal->delete();
        });

        return to_route('goals.index')->with('status', 'Goal deleted. Recorded budget expenses were kept.');
    }

    /** @param list<int> $budgetIds */
    private function locked(array $budgetIds, Closure $action): mixed
    {
        return Cache::store('database')->lock('savings:'.auth()->id(), 30)->block(5, fn (): mixed => $this->budgetLocks($budgetIds, $action));
    }

    /** @param list<int> $budgetIds */
    private function budgetLocks(array $budgetIds, Closure $action): mixed
    {
        sort($budgetIds);
        $ids = array_values(array_unique($budgetIds));
        if ($ids === []) {
            return app(SavingsAccounts::class)->locked((int) auth()->id(), $action);
        }
        $first = array_shift($ids);

        return Cache::store('database')->lock('budget:'.$first, 30)->block(5, fn (): mixed => $this->budgetLocks($ids, $action));
    }
}
