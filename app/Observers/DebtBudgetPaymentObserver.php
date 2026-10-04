<?php

namespace App\Observers;

use App\DebtInterest;
use App\Models\BudgetRecurringCharge;
use App\Models\BudgetTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class DebtBudgetPaymentObserver
{
    public function saving(BudgetTransaction $transaction): void
    {
        $charge = $transaction->budget_recurring_charge_id === null ? null : BudgetRecurringCharge::query()->find($transaction->budget_recurring_charge_id);
        if ($charge?->debt_id === null) {
            return;
        }
        $debt = Debt::query()->findOrFail($charge->debt_id);
        $payment = $transaction->exists ? DebtPayment::withTrashed()->where('budget_transaction_id', $transaction->id)->first() : null;
        if ($transaction->interestIsEstimated === null && ((! $transaction->exists && ! array_key_exists('interest_cents', $transaction->getAttributes())) || ($payment?->interest_is_estimated && ($transaction->isDirty('amount_cents') || $transaction->isDirty('date'))))) {
            $transaction->interest_cents = app(DebtInterest::class)->estimate($debt, $transaction->amount_cents, $transaction->date, $payment?->id);
            $transaction->interestIsEstimated = true;
        }
        if ($transaction->interest_cents > $transaction->amount_cents) {
            throw ValidationException::withMessages(['amount' => 'The payment cannot be less than its recorded interest. Edit the interest on the Debts page first.']);
        }
        if ($transaction->date->lt($debt->balance_date) || $transaction->date->gt(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['date' => 'Debt payments must be on or after the starting balance date and cannot be in the future.']);
        }
    }

    public function saved(BudgetTransaction $transaction): void
    {
        $payment = DebtPayment::withTrashed()->where('budget_transaction_id', $transaction->id)->first();
        $charge = $transaction->budget_recurring_charge_id === null ? null : BudgetRecurringCharge::query()->find($transaction->budget_recurring_charge_id);
        if ($charge?->debt_id === null || $transaction->trashed()) {
            $payment?->delete();

            return;
        }
        $payment ??= new DebtPayment(['budget_transaction_id' => $transaction->id]);
        $payment->debt_id = $charge->debt_id;
        $payment->interest_is_estimated = $transaction->interestIsEstimated ?? $payment->interest_is_estimated ?? false;
        $payment->fill(['amount_cents' => $transaction->amount_cents, 'interest_cents' => $transaction->interest_cents ?? 0, 'date' => $transaction->date, 'notes' => $transaction->description, 'budget_period_name' => $transaction->period->name]);
        $payment->deleted_at = null;
        $payment->save();
    }

    public function deleted(BudgetTransaction $transaction): void
    {
        if (! $transaction->isForceDeleting()) {
            DebtPayment::query()->where('budget_transaction_id', $transaction->id)->first()?->delete();
        }
    }

    public function restored(BudgetTransaction $transaction): void
    {
        $this->saved($transaction);
    }
}
