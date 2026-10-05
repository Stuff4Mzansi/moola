<?php

namespace App;

use App\Models\Asset;
use App\Models\AssetReserve;
use App\Models\Budget;
use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use App\Models\DebtPayment;
use App\Models\LiquidityPreference;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LiquidityAnalytics
{
    /** @param array<string, mixed> $scenario
     * @param  array<string, mixed>|null  $balances
     * @return array<string, mixed>
     */
    public function build(User $user, array $scenario = [], ?array $balances = null): array
    {
        $today = CarbonImmutable::today();
        $balances ??= app(NetWorthWorkspace::class)->balances($user, $today);
        $preference = LiquidityPreference::query()->where('user_id', $user->id)->first() ?? new LiquidityPreference(['horizon' => 30, 'buffer_cents' => 0, 'essential_cents' => 0, 'variable_cents' => 0, 'income_first' => false]);
        $budgets = Budget::query()->where('user_id', $user->id)->orderBy('name')->get();
        $budgetIds = $preference->budget_ids ?? $budgets->where('scope', 'personal')->pluck('id')->all();
        $budgetIds = $budgets->whereIn('id', $budgetIds)->pluck('id')->all();
        $horizon = (int) ($scenario['horizon'] ?? $preference->horizon);
        $until = $today->addDays($horizon - 1);
        $reserves = AssetReserve::query()->whereHas('asset', fn (Builder $query): Builder => $query->where('user_id', $user->id))->with(['asset', 'goal'])->orderBy('name')->get();
        $assetRows = $balances['assets']->map(function (array $row) use ($reserves, $today): array {
            $asset = $row['asset'];
            $allocated = $reserves->where('asset_id', $asset->id)->sum('amount_cents');
            $net = max(0, $row['amount'] - $asset->withdrawal_cost_cents);
            $accessible = $this->availableBy($asset, $today);

            return [...$row, 'net' => $net, 'allocated' => $allocated, 'free' => max(0, $net - $allocated), 'accessible' => $accessible, 'overallocated' => $allocated > $net, 'emergency' => $accessible ? min($net, $reserves->where('asset_id', $asset->id)->where('kind', 'emergency')->sum('amount_cents')) : 0];
        });
        $accessible = $assetRows->where('accessible', true)->sum('net');
        $protected = $assetRows->where('accessible', true)->sum(fn (array $row): int => min($row['net'], $row['allocated']));
        $free = $assetRows->where('accessible', true)->sum('free');
        $emergency = $assetRows->sum('emergency');
        $overallocated = $assetRows->where('overallocated', true)->count();
        $minimums = $balances['debts']->where('amount', '>', 0)->sum(fn (array $row): int => $row['debt']->minimum_payment_cents);
        $essential = $preference->essential_cents + $minimums;
        $runway = $preference->essential_cents > 0 && $overallocated === 0 ? round($emergency / max(1, $essential), 1) : null;
        $groups = $assetRows->groupBy(fn (array $row): string => $row['asset']->liquidity)->map(fn (Collection $items, string $kind): array => ['kind' => $kind, 'amount' => $items->sum('amount')])->values();
        $events = [];
        $missingIncome = 0;
        $missingIncomeAmount = 0;
        $overdue = 0;
        $periodIds = BudgetPeriod::query()->whereIn('budget_id', $budgetIds)->whereDate('end_date', '>=', $today)->whereDate('start_date', '<=', $until)->pluck('id');
        foreach (BudgetIncome::query()->whereIn('budget_period_id', $periodIds)->get() as $income) {
            $remaining = max(0, $income->expected_cents - $income->received_cents);
            if ($remaining === 0) {
                continue;
            }
            if ($income->expected_date === null || $income->expected_date->lt($today)) {
                $missingIncome++;
                $missingIncomeAmount += $remaining;

                continue;
            }
            $date = $income->expected_date->addDays((int) ($scenario['income_delay'] ?? 0));
            if ($date->lte($until)) {
                $events['income:'.$income->id] = $this->event($date, $income->name, $remaining, 'income', 'income', route('budgets.index', ['period' => $income->budget_period_id, 'tab' => 'plan']));
            }
        }
        $zarSubscriptions = Subscription::query()->where('user_id', $user->id)->where('currency', 'ZAR')->pluck('id');
        $foreignSubscriptions = Subscription::query()->where('user_id', $user->id)->where('status', SubscriptionStatus::Active->value)->where('currency', '!=', 'ZAR')->count();
        $commitments = BudgetCommitment::query()->whereIn('subscription_id', $zarSubscriptions)->whereIn('budget_period_id', BudgetPeriod::query()->whereIn('budget_id', $budgets->pluck('id'))->select('id'))->whereDate('scheduled_date', '>=', $today->subDays(90))->whereDate('scheduled_date', '<=', $until)->with('transaction')->orderBy('id')->get();
        $paidSubscriptions = $commitments->filter(fn (BudgetCommitment $charge): bool => $charge->transaction !== null)->keyBy(fn (BudgetCommitment $charge): string => $charge->subscription_id.'|'.$charge->scheduled_date->toDateString());
        $selectedPeriods = BudgetPeriod::query()->whereIn('budget_id', $budgetIds)->pluck('id');
        $selectedCommitments = $commitments->whereIn('budget_period_id', $selectedPeriods)->where('is_current', true)->keyBy(fn (BudgetCommitment $charge): string => $charge->subscription_id.'|'.$charge->scheduled_date->toDateString());
        foreach ($selectedCommitments as $key => $charge) {
            if ($paidSubscriptions->has($key)) {
                continue;
            }
            if ($charge->scheduled_date->lt($today)) {
                $overdue++;
            }
        }
        foreach (Subscription::query()->where('user_id', $user->id)->where('status', SubscriptionStatus::Active->value)->where('currency', 'ZAR')->get() as $subscription) {
            foreach ($subscription->renewalsBetween($today, $until) as $date) {
                $key = $subscription->id.'|'.$date->toDateString();
                if (! $paidSubscriptions->has($key)) {
                    $charge = $selectedCommitments->get($key);
                    $url = $charge === null ? route('subscriptions.edit', $subscription) : route('budgets.index', ['period' => $charge->budget_period_id, 'tab' => 'subscriptions']);
                    $events['subscription:'.$key] = $this->event($date, $subscription->name, $subscription->amount_cents, 'expense', 'subscription', $url);
                }
            }
        }
        $charges = BudgetRecurringCharge::query()->whereIn('budget_period_id', $selectedPeriods)->whereDate('scheduled_date', '>=', $today->subDays(90))->whereDate('scheduled_date', '<=', $until)->with('transaction')->orderBy('id')->get();
        $paidRecurring = $charges->filter(fn (BudgetRecurringCharge $charge): bool => $charge->transaction !== null)->keyBy(fn (BudgetRecurringCharge $charge): string => $charge->budget_recurring_expense_id.'|'.$charge->scheduled_date->toDateString());
        foreach ($charges->where('is_current', true)->whereNull('debt_id') as $charge) {
            $key = $charge->budget_recurring_expense_id.'|'.$charge->scheduled_date->toDateString();
            if ($paidRecurring->has($key)) {
                continue;
            }
            if ($charge->scheduled_date->lt($today)) {
                $overdue++;

                continue;
            }
            $events['recurring:'.$key] = $this->event($charge->scheduled_date, $charge->name, $charge->amount_cents, 'expense', 'recurring', route('budgets.index', ['period' => $charge->budget_period_id, 'tab' => 'recurring']));
        }
        foreach (BudgetRecurringExpense::query()->whereIn('budget_id', $budgetIds)->whereNull('debt_id')->where('is_active', true)->get() as $expense) {
            $end = $expense->end_date === null ? $until : $until->min($expense->end_date);
            $estimate = app(BudgetRecurringExpenses::class)->estimate($expense, $today)['amount_cents'];
            foreach ((new RecurringSchedule($expense->billing_frequency, $expense->start_date))->between($today, $end) as $date) {
                $key = $expense->id.'|'.$date->toDateString();
                if (! $paidRecurring->has($key)) {
                    $events['recurring:'.$key] ??= $this->event($date, $expense->name, $estimate, 'expense', 'recurring', route('budgets.index'));
                }
            }
        }
        foreach ($balances['debts']->where('amount', '>', 0) as $row) {
            $debt = $row['debt'];
            foreach ((new RecurringSchedule(BillingFrequency::Monthly, $debt->due_anchor))->between($today, $until) as $date) {
                $paid = $debt->payments->filter(fn (DebtPayment $payment): bool => $payment->date->format('Y-m') === $date->format('Y-m'))->sum('amount_cents');
                $amount = max(0, $debt->minimum_payment_cents - $paid);
                if ($amount > 0) {
                    $events['debt:'.$debt->id.'|'.$date->toDateString()] = $this->event($date, $debt->name.' minimum payment', $amount, 'expense', 'debt', route('debts.index'));
                }
            }
        }
        foreach ($assetRows->where('accessible', false) as $row) {
            if ($row['asset']->liquidity === 'dated' && ! $row['asset']->value_uncertain && $row['asset']->available_date?->betweenIncluded($today, $until) && $row['free'] > 0) {
                $events['unlock:'.$row['asset']->id] = $this->event($row['asset']->available_date, $row['asset']->name.' scheduled unlock', $row['free'], 'income', 'unlock', route('net-worth.index', ['tab' => 'assets']));
            }
        }
        for ($day = $today; $day->lte($until); $day = $day->addDay()) {
            $variable = intdiv($preference->variable_cents, $day->daysInMonth) + ($day->day <= $preference->variable_cents % $day->daysInMonth ? 1 : 0);
            if ($variable > 0) {
                $events['variable:'.$day->toDateString()] = $this->event($day, 'Everyday spending estimate', $variable, 'expense', 'variable');
            }
        }
        foreach (['extra_debt' => ['Extra debt payment', 'expense'], 'extra_reserve' => ['Extra protected savings', 'reserve']] as $field => [$label, $kind]) {
            $amount = BudgetMoney::cents($scenario[$field] ?? '0');
            if ($amount > 0) {
                foreach ((new RecurringSchedule(BillingFrequency::Monthly, $today))->between($today, $until) as $date) {
                    $events['scenario:'.$field.'|'.$date->toDateString()] = $this->event($date, $label, $amount, $kind, 'scenario');
                }
            }
        }
        $purchase = BudgetMoney::cents($scenario['purchase'] ?? '0');
        if ($purchase > 0) {
            $date = CarbonImmutable::parse($scenario['purchase_date'] ?? $today->toDateString());
            if ($date->betweenIncluded($today, $until)) {
                $events['scenario:purchase'] = $this->event($date, 'Planned one-off purchase', $purchase, 'expense', 'scenario');
            }
        }
        $events = collect($events)->sortBy(fn (array $event): string => $event['date'].'|'.($event['kind'] === 'income' ? ($preference->income_first ? '0' : '2') : '1').'|'.$event['name'])->values();
        $daily = $this->project($events, $free, $today, $until, $preference->income_first);
        $lowest = $daily->sortBy('low')->first();
        $nextIncome = $events->first(fn (array $event): bool => $event['source'] === 'income');
        $beforeIncome = $events->filter(fn (array $event): bool => $event['kind'] !== 'income' && ($nextIncome === null || ($preference->income_first ? $event['date'] < $nextIncome['date'] : $event['date'] <= $nextIncome['date'])))->sum('amount');
        $availableBeforeIncome = $free + $events->filter(fn (array $event): bool => $event['source'] === 'unlock' && ($nextIncome === null || ($preference->income_first ? $event['date'] <= $nextIncome['date'] : $event['date'] < $nextIncome['date'])))->sum('amount');
        $goalReadiness = $reserves->whereNotNull('savings_goal_id')->groupBy('savings_goal_id')->map(function (Collection $items) use ($assetRows, $today): array {
            $goal = $items->first()->goal;
            $ready = $items->sum(function (AssetReserve $reserve) use ($assetRows, $goal, $today): int {
                $asset = $assetRows->firstWhere('asset.id', $reserve->asset_id);

                return $asset !== null && ! $asset['overallocated'] && $this->availableBy($asset['asset'], $goal?->target_date ?? $today) ? $reserve->amount_cents : 0;
            });

            $saved = $goal->opening_cents + $goal->contributions()->whereDate('date', '<=', $today)->sum('amount_cents');

            return ['goal' => $goal, 'allocated' => $items->sum('amount_cents'), 'ready' => min($ready, $saved)];
        })->values();

        return ['preference' => $preference, 'budgets' => $budgets, 'budgetIds' => $budgetIds, 'assets' => $assetRows, 'groups' => $groups, 'reserves' => $reserves, 'accessible' => $accessible, 'protected' => $protected, 'free' => $free, 'emergency' => $emergency, 'runway' => $runway, 'essential' => $essential, 'minimums' => $minimums, 'overallocated' => $overallocated, 'unknown' => $assetRows->filter(fn (array $row): bool => $row['asset']->liquidity === 'unknown')->count(), 'stale' => $assetRows->where('accessible', true)->where('stale', true)->count(), 'missingIncome' => $missingIncome, 'missingIncomeAmount' => $missingIncomeAmount, 'overdue' => $overdue, 'horizon' => $horizon, 'until' => $until, 'events' => $events, 'daily' => $daily, 'lowest' => $lowest, 'nextIncome' => $nextIncome, 'beforeIncome' => $beforeIncome, 'availableBeforeIncome' => $availableBeforeIncome, 'shortfall' => max(0, $beforeIncome - $availableBeforeIncome), 'cashGap' => max(0, -$lowest['low']), 'foreignSubscriptions' => $foreignSubscriptions, 'bufferGap' => max(0, $preference->buffer_cents - $lowest['low']), 'scenario' => $scenario, 'goalReadiness' => $goalReadiness];
    }

    public function availableBy(Asset $asset, CarbonImmutable $date): bool
    {
        return ! $asset->value_uncertain && ($asset->liquidity === 'immediate' || ($asset->liquidity === 'dated' && $asset->available_date?->lte($date)));
    }

    /** @return array{date: string, name: string, amount: int, kind: string, source: string, url: ?string} */
    private function event(CarbonImmutable $date, string $name, int $amount, string $kind, string $source, ?string $url = null): array
    {
        return ['date' => $date->toDateString(), 'name' => $name, 'amount' => $amount, 'kind' => $kind, 'source' => $source, 'url' => $url];
    }

    /** @param Collection<int, array{date: string, name: string, amount: int, kind: string, source: string, url: ?string}> $events
     * @return Collection<int, array{date: string, closing: int, low: int, income: int, outgoing: int}>
     */
    public function project(Collection $events, int $starting, CarbonImmutable $today, CarbonImmutable $until, bool $incomeFirst): Collection
    {
        $byDay = $events->groupBy('date');
        $balance = $starting;
        $points = collect();
        for ($day = $today; $day->lte($until); $day = $day->addDay()) {
            $dayEvents = $byDay->get($day->toDateString(), collect());
            $income = $dayEvents->where('kind', 'income')->sum('amount');
            $outgoing = $dayEvents->where('kind', '!=', 'income')->sum('amount');
            $low = $incomeFirst ? min($balance, $balance + $income - $outgoing) : $balance - $outgoing;
            $balance += $income - $outgoing;
            $points->push(['date' => $day->toDateString(), 'closing' => $balance, 'low' => $low, 'income' => $income, 'outgoing' => $outgoing]);
        }

        return $points;
    }
}
