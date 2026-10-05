<?php

namespace App;

use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\DebtPayment;
use App\Models\SavingsContribution;
use Carbon\CarbonImmutable;

class BudgetPeriodReview
{
    /**
     * @param  array<string, mixed>  $workspace
     * @return array{status: string, variance: int, recordedBalance: int, principal: int, debtInterest: int, savings: int, pendingAmount: int, pending: list<array{name: string, date: string, amount: int, tab: string}>, missingIncome: list<array{name: string, amount: int}>, overLimits: list<array{name: string, amount: int}>}
     */
    public function build(BudgetPeriod $period, array $workspace): array
    {
        $today = CarbonImmutable::today();
        $transactionIds = $workspace['transactions']->pluck('id');
        $payments = DebtPayment::query()->whereIn('budget_transaction_id', $transactionIds)->get();
        $savings = SavingsContribution::query()->whereIn('budget_transaction_id', $transactionIds)->sum('amount_cents');
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
            'principal' => $payments->sum(fn (DebtPayment $payment): int => $payment->amount_cents - $payment->interest_cents),
            'debtInterest' => (int) $payments->sum('interest_cents'),
            'savings' => (int) $savings,
            'pendingAmount' => $pending->sum('amount'),
            'pending' => $pending->all(),
            'missingIncome' => $missingIncome->all(),
            'overLimits' => $overLimits->all(),
        ];
    }
}
