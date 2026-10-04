<?php

namespace App;

use Carbon\CarbonImmutable;

class DebtPayoff
{
    /**
     * @param  list<array{id: int, name: string, balance: int, rate: int, minimum: int}>  $debts
     * @return array{months: ?int, interest: int, remaining: int, order: list<array{id: int, name: string, month: int}>, budget: int, date: ?string}
     */
    public function simulate(array $debts, int $extra, string $strategy): array
    {
        $debts = array_values(array_filter($debts, fn (array $debt): bool => $debt['balance'] > 0));
        $budget = array_sum(array_column($debts, 'minimum')) + $extra;
        $interest = 0;
        $order = [];
        for ($month = 1; $month <= 600 && $debts !== []; $month++) {
            $startingBalance = array_sum(array_column($debts, 'balance'));
            $singleDebt = count($debts) === 1;
            $available = $budget;
            foreach ($debts as &$debt) {
                $charge = intdiv($debt['balance'] * $debt['rate'] + 60000, 120000);
                $interest += $charge;
                $debt['balance'] += $charge;
                $payment = min($debt['balance'], $debt['minimum'], $available);
                $debt['balance'] -= $payment;
                $available -= $payment;
            }
            unset($debt);
            usort($debts, fn (array $left, array $right): int => $strategy === 'avalanche' ? ($right['rate'] <=> $left['rate'] ?: $left['balance'] <=> $right['balance'] ?: $left['id'] <=> $right['id']) : ($left['balance'] <=> $right['balance'] ?: $right['rate'] <=> $left['rate'] ?: $left['id'] <=> $right['id']));
            foreach ($debts as &$debt) {
                $payment = min($debt['balance'], $available);
                $debt['balance'] -= $payment;
                $available -= $payment;
                if ($debt['balance'] === 0) {
                    $order[] = ['id' => $debt['id'], 'name' => $debt['name'], 'month' => $month];
                }
            }
            unset($debt);
            $debts = array_values(array_filter($debts, fn (array $debt): bool => $debt['balance'] > 0));
            if ($debts !== [] && (($singleDebt && array_sum(array_column($debts, 'balance')) >= $startingBalance) || max(array_column($debts, 'balance')) > 100000000000000)) {
                break;
            }
        }
        $months = $debts === [] ? max(0, $month - 1) : null;

        return ['months' => $months, 'interest' => $interest, 'remaining' => array_sum(array_column($debts, 'balance')), 'order' => $order, 'budget' => $budget, 'date' => $months === null ? null : CarbonImmutable::today()->addMonthsNoOverflow($months)->format('M Y')];
    }
}
