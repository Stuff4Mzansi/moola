<?php

namespace App;

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetGroup;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BudgetWorkspace
{
    /** @return list<array{subscription_id: int, name: string, scheduled_date: string, amount_cents: int}> */
    public function expectedCommitments(BudgetPeriod $period): array
    {
        if (! $period->budget->include_subscriptions) {
            return [];
        }
        $expected = [];
        foreach ($period->budget->owner->subscriptions()->where('status', SubscriptionStatus::Active->value)->get() as $subscription) {
            foreach ($subscription->renewalsBetween($period->start_date, $period->end_date) as $date) {
                $expected[] = ['subscription_id' => $subscription->id, 'name' => $subscription->name, 'scheduled_date' => $date->toDateString(), 'amount_cents' => $subscription->amount_cents];
            }
        }

        return $expected;
    }

    /** @return list<string> */
    public function sync(BudgetPeriod $period, bool $force = false): array
    {
        if (! $force && $period->end_date->lt(CarbonImmutable::today())) {
            return [];
        }
        $changes = app(BudgetRecurringExpenses::class)->sync($period, $force);
        $expected = collect($this->expectedCommitments($period))->keyBy(fn (array $charge): string => $charge['subscription_id'].'|'.$charge['scheduled_date']);
        $existing = $period->commitments()->with('transaction')->get();
        foreach ($existing as $commitment) {
            $key = $commitment->subscription_id.'|'.$commitment->scheduled_date->toDateString();
            if ($commitment->transaction !== null) {
                $expected->forget($key);

                continue;
            }
            if (! $force && $commitment->scheduled_date->lt(CarbonImmutable::today())) {
                $expected->forget($key);

                continue;
            }
            $charge = $expected->get($key);
            $commitment->fill($charge === null ? ['is_current' => false] : [...$charge, 'is_current' => true]);
            if ($commitment->isDirty()) {
                $changes[] = ($charge === null ? 'Removed expected renewal: ' : 'Updated expected renewal: ').$commitment->name;
                $commitment->save();
            }
            $expected->forget($key);
        }
        foreach ($expected as $charge) {
            if ($force || $charge['scheduled_date'] >= CarbonImmutable::today()->toDateString()) {
                $period->commitments()->create($charge);
                $changes[] = 'Added expected renewal: '.$charge['name'];
            }
        }
        if ($changes !== []) {
            $period->increment('version');
        }

        return $changes;
    }

    /** @return array{period: BudgetPeriod, budget: Budget, categories: Collection, incomes: Collection, transactions: Collection, commitments: Collection, recurringCharges: Collection, recurringExpenses: Collection, totals: array{expected: int, received: int, planned: int, spent: int, upcoming: int, unallocated: int, remaining: int, after_commitments: int}, categoryRows: Collection, groups: Collection, groupRows: Collection, analyticsRows: Collection, groupPercentageTotal: int, subscriptionChanges: list<string>, nextStart: string, nextEnd: string} */
    public function data(BudgetPeriod $period): array
    {
        $changes = $this->sync($period);
        $categories = $period->categories()->orderBy('id')->get();
        $transactions = $period->transactions()->orderByDesc('date')->orderByDesc('id')->get();
        $incomes = $period->incomes()->get();
        $commitments = $period->commitments()->with('transaction')->whereDate('scheduled_date', '>=', $period->start_date->toDateString())->whereDate('scheduled_date', '<=', $period->end_date->toDateString())->get()
            ->filter(fn (BudgetCommitment $commitment): bool => $commitment->is_current || $commitment->transaction !== null)
            ->sortBy(fn (BudgetCommitment $commitment): string => $commitment->scheduled_date->toDateString())->values();
        $recurringCharges = $period->recurringCharges()->with('transaction')->whereDate('scheduled_date', '>=', $period->start_date->toDateString())->whereDate('scheduled_date', '<=', $period->end_date->toDateString())->get()->filter(fn (BudgetRecurringCharge $charge): bool => $charge->is_current || $charge->transaction !== null)->sortBy('scheduled_date')->values();
        $scheduledCents = $commitments->sum('amount_cents');
        $categoryRows = $categories->map(function (BudgetCategory $category) use ($transactions, $commitments, $scheduledCents, $recurringCharges): array {
            $planned = $category->allocated_cents ?? ($category->kind === 'subscriptions' ? $scheduledCents : 0);
            $spent = $transactions->where('budget_category_id', $category->id)->sum('amount_cents');
            $upcoming = $category->kind === 'subscriptions' ? $commitments->filter(fn (BudgetCommitment $charge): bool => $charge->transaction === null && $charge->is_current)->sum('amount_cents') : 0;

            $upcoming += $recurringCharges->where('budget_category_id', $category->id)->filter(fn (BudgetRecurringCharge $charge): bool => $charge->is_current && $charge->transaction === null)->sum('amount_cents');

            return ['category' => $category, 'planned' => $planned, 'spent' => $spent, 'upcoming' => $upcoming, 'remaining' => $planned - $spent, 'available' => $planned - $spent - $upcoming];
        });
        $expected = $incomes->sum('expected_cents');
        $received = $incomes->sum('received_cents');
        $planned = $categoryRows->sum('planned');
        $spent = $transactions->sum('amount_cents');
        $upcoming = $commitments->filter(fn (BudgetCommitment $charge): bool => $charge->transaction === null && $charge->is_current)->sum('amount_cents');
        $upcoming += $recurringCharges->filter(fn (BudgetRecurringCharge $charge): bool => $charge->is_current && $charge->transaction === null)->sum('amount_cents');
        $groups = $period->groups()->orderBy('id')->get();
        $groupRows = $groups->map(function (BudgetGroup $group) use ($categoryRows, $expected): array {
            $rows = $categoryRows->filter(fn (array $row): bool => $row['category']->budget_group_id === $group->id);
            $limit = $group->percentage_basis_points === null ? null : intdiv($expected * $group->percentage_basis_points + 5000, 10000);
            $spent = $rows->sum('spent');
            $upcoming = $rows->sum('upcoming');

            return ['group' => $group, 'limit' => $limit, 'planned' => $rows->sum('planned'), 'spent' => $spent, 'upcoming' => $upcoming, 'remaining' => $limit === null ? null : $limit - $spent, 'available' => $limit === null ? null : $limit - $spent - $upcoming, 'category_count' => $rows->count()];
        });
        $analyticsRows = $groups->isEmpty()
            ? $categoryRows->map(fn (array $row): array => ['name' => $row['category']->name, 'planned' => $row['planned'], 'spent' => $row['spent'], 'limit' => $row['planned'], 'categories' => collect()])
            : $groupRows->map(fn (array $row): array => ['name' => $row['group']->name, 'planned' => $row['planned'], 'spent' => $row['spent'], 'limit' => $row['limit'], 'categories' => $categoryRows->filter(fn (array $categoryRow): bool => $categoryRow['category']->budget_group_id === $row['group']->id)->values()]);
        if ($groups->isNotEmpty()) {
            $ungrouped = $categoryRows->filter(fn (array $row): bool => $row['category']->budget_group_id === null)->values();
            if ($ungrouped->isNotEmpty()) {
                $analyticsRows->push(['name' => 'Ungrouped', 'planned' => $ungrouped->sum('planned'), 'spent' => $ungrouped->sum('spent'), 'limit' => null, 'categories' => $ungrouped]);
            }
        }
        $nextStart = $period->end_date->addDay();
        if ($period->budget->repeat_cycle === 'monthly') {
            $boundary = $nextStart->startOfMonth()->addDays(min($period->budget->anchor_date->day, $nextStart->daysInMonth) - 1);
            if ($boundary->lte($nextStart)) {
                $month = $nextStart->startOfMonth()->addMonth();
                $boundary = $month->addDays(min($period->budget->anchor_date->day, $month->daysInMonth) - 1);
            }
            $nextEnd = $boundary->subDay();
        } else {
            $nextEnd = $nextStart->addDays((int) $period->start_date->diffInDays($period->end_date));
        }

        return ['period' => $period->fresh(), 'budget' => $period->budget, 'categories' => $categories, 'incomes' => $incomes, 'transactions' => $transactions, 'commitments' => $commitments, 'recurringCharges' => $recurringCharges, 'recurringExpenses' => $period->budget->recurringExpenses()->orderBy('name')->get(),
            'totals' => ['expected' => $expected, 'received' => $received, 'planned' => $planned, 'spent' => $spent, 'upcoming' => $upcoming, 'unallocated' => $expected - $planned, 'remaining' => $expected - $spent, 'after_commitments' => $expected - $spent - $upcoming],
            'groups' => $groups, 'groupRows' => $groupRows, 'analyticsRows' => $analyticsRows, 'groupPercentageTotal' => $groups->sum('percentage_basis_points'), 'categoryRows' => $categoryRows, 'subscriptionChanges' => $changes, 'nextStart' => $nextStart->toDateString(), 'nextEnd' => $nextEnd->toDateString()];
    }

    public function previewToken(BudgetPeriod $period, string $start, string $end): string
    {
        $subscriptions = $period->budget->owner->subscriptions()->orderBy('id')->get()->map(fn (Subscription $subscription): array => $subscription->getAttributes())->all();

        $candidate = clone $period;
        $candidate->start_date = $start;
        $candidate->end_date = $end;
        $recurring = app(BudgetRecurringExpenses::class)->expected($candidate);

        return hash_hmac('sha256', json_encode([$period->id, $period->version, $start, $end, $subscriptions, $recurring], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
