<?php

namespace App;

use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetTransaction;
use Carbon\CarbonImmutable;

class BudgetPeriodReview
{
    /**
     * @param  array<string, mixed>  $workspace
     * @return array{status: string, variance: int, recordedBalance: int, dailySpending: list<array{date: string, amount: int, cumulative: int}>, plannedReachedDate: ?string, receivedReachedDate: ?string, spendingCategories: list<array{name: string, amount: int}>, pendingAmount: int, pending: list<array{name: string, date: string, amount: int, tab: string}>, missingIncome: list<array{name: string, amount: int}>, overLimits: list<array{name: string, amount: int}>}
     */
    public function build(BudgetPeriod $period, array $workspace): array
    {
        $today = CarbonImmutable::today();
        $transactionsByDate = $workspace['transactions']->groupBy(fn (BudgetTransaction $transaction): string => $transaction->date->toDateString());
        $dailySpending = [];
        $cumulativeSpending = 0;
        $plannedReachedDate = null;
        $receivedReachedDate = null;
        $date = $period->start_date;
        while ($date->lte($period->end_date)) {
            $dateString = $date->toDateString();
            $dailyAmount = (int) $transactionsByDate->get($dateString, collect())->sum('amount_cents');
            $cumulativeSpending += $dailyAmount;
            $dailySpending[] = ['date' => $dateString, 'amount' => $dailyAmount, 'cumulative' => $cumulativeSpending];

            if ($plannedReachedDate === null && $workspace['totals']['planned'] > 0 && $cumulativeSpending >= $workspace['totals']['planned']) {
                $plannedReachedDate = $dateString;
            }
            if ($receivedReachedDate === null && $workspace['totals']['received'] > 0 && $cumulativeSpending >= $workspace['totals']['received']) {
                $receivedReachedDate = $dateString;
            }
            $date = $date->addDay();
        }
        $spendingCategories = $workspace['categoryRows']
            ->filter(fn (array $row): bool => $row['spent'] > 0)
            ->map(fn (array $row): array => ['name' => $row['category']->name, 'amount' => $row['spent']]);
        $uncategorised = $workspace['transactions']
            ->whereNull('budget_category_id')
            ->sum('amount_cents');
        if ($uncategorised > 0) {
            $spendingCategories->push(['name' => 'Uncategorised', 'amount' => $uncategorised]);
        }
        $spendingCategories = $spendingCategories->sortByDesc('amount')->values()->all();
        $pending = $workspace['commitments']
            ->filter(fn (BudgetCommitment $charge): bool => $charge->is_current && $charge->transaction === null)
            ->map(fn (BudgetCommitment $charge): array => ['name' => $charge->name, 'date' => $charge->scheduled_date->toDateString(), 'amount' => $charge->amount_cents, 'tab' => 'subscriptions'])
            ->concat($workspace['recurringCharges']
                ->filter(fn (BudgetRecurringCharge $charge): bool => $charge->is_current && $charge->transaction === null)
                ->map(fn (BudgetRecurringCharge $charge): array => ['name' => $charge->name, 'date' => $charge->scheduled_date->toDateString(), 'amount' => $charge->amount_cents, 'tab' => 'recurring']))
            ->sortBy('date')->values();
        $missingIncome = $workspace['incomes']
            ->filter(fn (BudgetIncome $income): bool => $income->received_cents < $income->expected_cents)
            ->map(fn (BudgetIncome $income): array => ['name' => $income->name, 'amount' => $income->expected_cents - $income->received_cents])->values();
        $overLimits = $workspace['analyticsRows']
            ->filter(fn (array $row): bool => $row['limit'] !== null && $row['spent'] > $row['limit'])
            ->map(fn (array $row): array => ['name' => $row['name'], 'amount' => $row['spent'] - $row['limit']])->values();

        return [
            'status' => $period->end_date->lt($today) ? 'ended' : ($period->start_date->gt($today) ? 'upcoming' : 'current'),
            'variance' => $workspace['totals']['planned'] - $workspace['totals']['spent'],
            'recordedBalance' => $workspace['totals']['received'] - $workspace['totals']['spent'],
            'dailySpending' => $dailySpending,
            'plannedReachedDate' => $plannedReachedDate,
            'receivedReachedDate' => $receivedReachedDate,
            'spendingCategories' => $spendingCategories,
            'pendingAmount' => $pending->sum('amount'),
            'pending' => $pending->all(),
            'missingIncome' => $missingIncome->all(),
            'overLimits' => $overLimits->all(),
        ];
    }
}
