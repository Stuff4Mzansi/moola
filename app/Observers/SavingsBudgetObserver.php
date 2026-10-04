<?php

namespace App\Observers;

use App\Models\BudgetTransaction;
use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SavingsBudgetObserver
{
    public function saving(BudgetTransaction $transaction): void
    {
        if ($transaction->savings_goal_id === null) {
            return;
        }
        $goal = SavingsGoal::query()->findOrFail($transaction->savings_goal_id);
        if ($transaction->budget_recurring_charge_id !== null || $transaction->budget_commitment_id !== null) {
            throw ValidationException::withMessages(['amount' => 'Savings contributions cannot also be subscription or recurring expense payments.']);
        }
        if ($transaction->date->lt($goal->start_date) || $transaction->date->gt(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['date' => 'Savings contributions must be on or after the starting savings date and cannot be in the future.']);
        }
    }

    public function saved(BudgetTransaction $transaction): void
    {
        $entry = SavingsContribution::withTrashed()->where('budget_transaction_id', $transaction->id)->first();
        if ($transaction->savings_goal_id === null || $transaction->trashed()) {
            $entry?->delete();

            return;
        }
        $entry ??= new SavingsContribution(['budget_transaction_id' => $transaction->id]);
        $entry->savings_goal_id = $transaction->savings_goal_id;
        $entry->fill(['amount_cents' => $transaction->amount_cents, 'date' => $transaction->date, 'notes' => $transaction->description, 'budget_period_name' => $transaction->period->name]);
        $entry->deleted_at = null;
        $entry->save();
    }

    public function deleted(BudgetTransaction $transaction): void
    {
        if (! $transaction->isForceDeleting()) {
            SavingsContribution::query()->where('budget_transaction_id', $transaction->id)->first()?->delete();
        }
    }

    public function restored(BudgetTransaction $transaction): void
    {
        $this->saved($transaction);
    }
}
