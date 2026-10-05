<?php

namespace App;

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

class BudgetTrends
{
    /** @return list<array{id: int, name: string, scope: string, periods: list<array{id: int, label: string, start: string, end: string, days: int, received: int, complete: bool, planned: int, spent: int, categories: list<array{key: string, name: string, planned: int, spent: int}>, url: string}>}> */
    public function build(User $user): array
    {
        $today = CarbonImmutable::today();
        $budgets = Budget::visibleTo($user)->orderBy('name')->with(['periods' => fn (HasMany $periods): HasMany => $periods
            ->whereDate('start_date', '<=', $today)->orderBy('start_date')->orderBy('id')
            ->withSum('transactions', 'amount_cents')->withSum('incomes', 'received_cents')
            ->with(['categories' => fn (HasMany $categories): HasMany => $categories->withSum('transactions', 'amount_cents')->orderBy('name')])
            ->with(['commitments' => fn (HasMany $charges): HasMany => $charges->where(fn (Builder $current): Builder => $current->where('is_current', true)->orWhereHas('transaction'))])])->get();

        return $budgets->filter(fn (Budget $budget): bool => Gate::forUser($user)->allows('view', $budget))->map(function (Budget $budget) use ($today): array {
            $periods = $budget->periods->map(function (BudgetPeriod $period) use ($today): array {
                $scheduled = $period->commitments->filter(fn (BudgetCommitment $charge): bool => $charge->scheduled_date->betweenIncluded($period->start_date, $period->end_date))->sum('amount_cents');
                $planned = $period->categories->sum(fn (BudgetCategory $category): int => $category->allocated_cents ?? ($category->kind === 'subscriptions' ? $scheduled : 0));
                $categories = $period->categories->map(fn (BudgetCategory $category): array => [
                    'key' => $category->kind === 'custom' ? 'custom:'.$category->name : $category->kind,
                    'name' => $category->name,
                    'planned' => $category->allocated_cents ?? ($category->kind === 'subscriptions' ? $scheduled : 0),
                    'spent' => (int) ($category->transactions_sum_amount_cents ?? 0),
                ])->all();

                return ['id' => $period->id, 'label' => $period->name, 'start' => $period->start_date->toDateString(), 'end' => $period->end_date->toDateString(), 'days' => (int) $period->start_date->diffInDays($period->end_date) + 1, 'received' => (int) ($period->incomes_sum_received_cents ?? 0), 'complete' => $period->end_date->lt($today), 'planned' => $planned, 'spent' => (int) ($period->transactions_sum_amount_cents ?? 0), 'categories' => $categories, 'url' => route('budgets.index', ['period' => $period->id, 'tab' => 'overview'])];
            });

            return ['id' => $budget->id, 'name' => $budget->name, 'scope' => $budget->scope, 'periods' => $periods->all()];
        })->values()->all();
    }
}
