<?php

namespace App;

use App\Models\Budget;
use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetRecurringExpense;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PaymentCalendar
{
    /**
     * @return array{events: Collection<int, array{date: string, name: string, amount: int, type: string, scope: string, url: string}>, undatedIncome: int}
     */
    public function build(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $today = CarbonImmutable::today();
        $from = $from->max($today);
        $budgets = Budget::visibleTo($user)->with(['periods' => fn (HasMany $periods): HasMany => $periods->orderByDesc('start_date')])->orderBy('name')->get()->keyBy('id');
        $subscriptionIds = Subscription::query()->whereIn('user_id', $budgets->pluck('user_id')->push($user->id)->unique())->pluck('id')->flip();
        $periods = BudgetPeriod::query()->whereIn('budget_id', $budgets->keys())->whereDate('start_date', '<=', $until)->whereDate('end_date', '>=', $from)->get();
        $commitments = BudgetCommitment::query()->whereIn('budget_period_id', $periods->pluck('id'))->whereBetween('scheduled_date', [$from->toDateString(), $until->toDateString()])->with('transaction')->get();
        $paidSubscriptions = $commitments->filter(fn (BudgetCommitment $charge): bool => $charge->transaction !== null)->keyBy(fn (BudgetCommitment $charge): string => $charge->subscription_id.'|'.$charge->scheduled_date->toDateString());
        $charges = BudgetRecurringCharge::query()->whereIn('budget_period_id', $periods->pluck('id'))->whereBetween('scheduled_date', [$from->toDateString(), $until->toDateString()])->with('transaction')->get();
        $paidRecurring = $charges->filter(fn (BudgetRecurringCharge $charge): bool => $charge->transaction !== null)->keyBy(fn (BudgetRecurringCharge $charge): string => $charge->budget_recurring_expense_id.'|'.$charge->scheduled_date->toDateString());
        $events = [];
        $subscriptions = Subscription::query()->where('user_id', $user->id)->where('status', SubscriptionStatus::Active->value)->get();
        foreach ($subscriptions as $subscription) {
            foreach ($subscription->renewalsBetween($from, $until) as $date) {
                $key = $subscription->id.'|'.$date->toDateString();
                if (! $paidSubscriptions->has($key)) {
                    $events['subscription:'.$key] = $this->event($date, $subscription->name, $subscription->amount_cents, 'subscription', 'Your subscriptions', route('subscriptions.show', $subscription));
                }
            }
        }
        foreach ($periods as $period) {
            $period->setRelation('budget', $budgets->get($period->budget_id));
            foreach (app(BudgetWorkspace::class)->expectedCommitments($period) as $charge) {
                $date = CarbonImmutable::parse($charge['scheduled_date']);
                $key = $charge['subscription_id'].'|'.$charge['scheduled_date'];
                if ($date->betweenIncluded($from, $until) && ! $paidSubscriptions->has($key) && $subscriptionIds->has($charge['subscription_id'])) {
                    $events['subscription:'.$key] = $this->event($date, $charge['name'], $charge['amount_cents'], 'subscription', $period->budget->name, route('budgets.index', ['period' => $period->id, 'tab' => 'subscriptions']));
                }
            }
        }
        $expenses = BudgetRecurringExpense::query()->whereIn('budget_id', $budgets->keys())->where('is_active', true)->get();
        $debts = Debt::query()->where(fn (Builder $query): Builder => $query->where('user_id', $user->id)->orWhereIn('id', $expenses->pluck('debt_id')->filter()))->with(['payments' => fn (HasMany $payments): HasMany => $payments->whereDate('date', '<=', $today)])->get()->keyBy('id');
        foreach ($expenses as $expense) {
            $debt = $expense->debt_id === null ? null : $debts->get($expense->debt_id);
            if ($expense->debt_id !== null && ($debt === null || $debt->user_id === $user->id || $this->balance($debt) === 0)) {
                continue;
            }
            $budget = $budgets->get($expense->budget_id);
            $end = $expense->end_date === null ? $until : $until->min($expense->end_date);
            $estimate = app(BudgetRecurringExpenses::class)->estimate($expense, $from)['amount_cents'];
            foreach ((new RecurringSchedule($expense->billing_frequency, $expense->start_date))->between($from, $end) as $date) {
                $key = $expense->id.'|'.$date->toDateString();
                if ($paidRecurring->has($key)) {
                    continue;
                }
                $period = $budget->periods->first(fn (BudgetPeriod $candidate): bool => $date->betweenIncluded($candidate->start_date, $candidate->end_date)) ?? $budget->periods->first();
                $amount = $estimate;
                if ($debt !== null) {
                    $key = 'debt:'.$debt->id.'|'.$date->toDateString();
                } else {
                    $key = 'recurring:'.$key;
                }
                if ($amount > 0) {
                    $events[$key] = $this->event($date, $expense->name, $amount, $debt === null ? 'recurring' : 'debt', $budget->name, route('budgets.index', ['period' => $period?->id, 'tab' => 'recurring']));
                }
            }
        }
        foreach ($debts->where('user_id', $user->id) as $debt) {
            if ($debt->balance_date->gt($today) || $this->balance($debt) === 0) {
                continue;
            }
            foreach ((new RecurringSchedule(BillingFrequency::Monthly, $debt->due_anchor))->between($from, $until) as $date) {
                $amount = max(0, $debt->minimum_payment_cents - $this->paidInMonth($debt, $date));
                if ($amount > 0) {
                    $events['debt:'.$debt->id.'|'.$date->toDateString()] = $this->event($date, $debt->name.' minimum payment', $amount, 'debt', 'Your debts', route('debts.index', ['tab' => 'payments', 'debt' => $debt->id]));
                }
            }
        }
        $incomes = BudgetIncome::query()->whereIn('budget_period_id', $periods->pluck('id'))->get();
        foreach ($incomes as $income) {
            $amount = max(0, $income->expected_cents - $income->received_cents);
            if ($amount > 0 && $income->expected_date?->betweenIncluded($from, $until)) {
                $period = $periods->firstWhere('id', $income->budget_period_id);
                $events['income:'.$income->id] = $this->event($income->expected_date, $income->name, $amount, 'income', $budgets->get($period->budget_id)->name, route('budgets.index', ['period' => $period->id, 'tab' => 'plan']));
            }
        }

        return [
            'events' => collect($events)->sortBy(fn (array $event): string => $event['date'].'|'.$event['type'].'|'.$event['name'])->values(),
            'undatedIncome' => $incomes->filter(fn (BudgetIncome $income): bool => $income->expected_date === null && $income->expected_cents > $income->received_cents)->count(),
        ];
    }

    private function balance(Debt $debt): int
    {
        return max(0, $debt->opening_balance_cents - $debt->payments->sum(fn (DebtPayment $payment): int => $payment->amount_cents - $payment->interest_cents));
    }

    private function paidInMonth(Debt $debt, CarbonImmutable $date): int
    {
        return $debt->payments->filter(fn (DebtPayment $payment): bool => $payment->date->format('Y-m') === $date->format('Y-m'))->sum('amount_cents');
    }

    /** @return array{date: string, name: string, amount: int, type: string, scope: string, url: string} */
    private function event(CarbonImmutable $date, string $name, int $amount, string $type, string $scope, string $url): array
    {
        return ['date' => $date->toDateString(), 'name' => $name, 'amount' => $amount, 'type' => $type, 'scope' => $scope, 'url' => $url];
    }
}
