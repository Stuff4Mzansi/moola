<?php

namespace App;

use App\Models\Debt;
use Carbon\CarbonImmutable;

class DebtInterest
{
    public function estimate(Debt $debt, int $amount, CarbonImmutable $date, ?int $paymentId = null): int
    {
        $balance = $debt->opening_balance_cents;
        $previousDate = $debt->balance_date;
        $accrued = 0;
        $denominator = 365 * 10000;
        $payments = $debt->payments()->whereDate('date', '<=', $date)->orderBy('date')->orderBy('id')->get();
        foreach ($payments as $payment) {
            if ($payment->id === $paymentId || ($paymentId !== null && $payment->date->equalTo($date) && $payment->id > $paymentId)) {
                continue;
            }
            $days = max(0, (int) $previousDate->diffInDays($payment->date));
            $accrued += $balance * $debt->annual_rate_basis_points * $days;
            $accrued = max(0, $accrued - $payment->interest_cents * $denominator);
            $balance = max(0, $balance - ($payment->amount_cents - $payment->interest_cents));
            $previousDate = $payment->date;
        }
        $days = max(0, (int) $previousDate->diffInDays($date));
        $accrued += $balance * $debt->annual_rate_basis_points * $days;

        return min($amount, intdiv($accrued + intdiv($denominator, 2), $denominator));
    }
}
