<?php

namespace App;

use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringExpense;
use App\Models\BudgetTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BudgetRecurringExpenses
{
    /** @return array{amount_cents: int, history_months: int, history_payments: int} */
    public function estimate(BudgetRecurringExpense $expense, CarbonImmutable $date): array
    {
        $until = $date->startOfMonth()->min(CarbonImmutable::today()->startOfMonth());
        $payments = BudgetTransaction::query()->whereHas('recurringCharge', fn (Builder $query): Builder => $query->where('budget_recurring_expense_id', $expense->id))->whereDate('date', '>=', $until->subMonths(3)->toDateString())->whereDate('date', '<', $until->toDateString())->get(['amount_cents', 'date']);

        return ['amount_cents' => $payments->isEmpty() ? $expense->amount_cents : (int) round($payments->sum('amount_cents') / $payments->count()), 'history_months' => $payments->map(fn (BudgetTransaction $payment): string => $payment->date->format('Y-m'))->unique()->count(), 'history_payments' => $payments->count()];
    }

    /** @return list<array<string, int|string>> */
    public function expected(BudgetPeriod $period): array
    {
        $categories = $period->categories()->get()->keyBy('name');
        $expected = [];
        $estimates = [];
        foreach ($period->budget->recurringExpenses()->where('is_active', true)->get() as $expense) {
            $category = $categories->get($expense->category_name) ?? $categories->get('Other');
            if ($category === null) {
                continue;
            }
            $until = $expense->end_date === null ? $period->end_date : $period->end_date->min($expense->end_date);
            foreach ((new RecurringSchedule($expense->billing_frequency, $expense->start_date))->between($period->start_date, $until) as $date) {
                $estimateKey = $expense->id.'|'.$date->startOfMonth()->min(CarbonImmutable::today()->startOfMonth())->toDateString();
                $estimates[$estimateKey] ??= $this->estimate($expense, $date);
                $expected[] = ['budget_recurring_expense_id' => $expense->id, 'budget_category_id' => $category->id, 'name' => $expense->name, 'scheduled_date' => $date->toDateString(), ...$estimates[$estimateKey]];
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
        $expected = collect($this->expected($period))->keyBy(fn (array $charge): string => $charge['budget_recurring_expense_id'].'|'.$charge['scheduled_date']);
        $changes = [];
        foreach ($period->recurringCharges()->with('transaction')->get() as $charge) {
            $key = $charge->budget_recurring_expense_id.'|'.$charge->scheduled_date->toDateString();
            if ($charge->transaction !== null) {
                $expected->forget($key);

                continue;
            }
            $data = $expected->get($key);
            $charge->fill($data === null ? ['is_current' => false] : [...$data, 'is_current' => true]);
            if ($charge->isDirty()) {
                $charge->save();
                $changes[] = 'Updated recurring forecast: '.$charge->name;
            }
            $expected->forget($key);
        }
        foreach ($expected as $data) {
            $period->recurringCharges()->create($data);
            $changes[] = 'Added recurring forecast: '.$data['name'];
        }
        if ($changes !== []) {
            $period->increment('version');
        }

        return $changes;
    }
}
