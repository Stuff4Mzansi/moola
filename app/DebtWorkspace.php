<?php

namespace App;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class DebtWorkspace
{
    /** @return array{rows: Collection, total: int, minimum: int, paid: int, starting: int, dueSoon: Collection, payments: Collection} */
    public function build(User $user): array
    {
        $today = CarbonImmutable::today();
        $debts = Debt::query()->where('user_id', $user->id)->with(['payments' => fn (HasMany $payments): HasMany => $payments->whereDate('date', '<=', $today)->orderByDesc('date')->orderByDesc('id')->with('budgetTransaction.recurringCharge'), 'schedules'])->orderBy('name')->get();
        $rows = $debts->map(function (Debt $debt) use ($today): array {
            $principal = $debt->payments->sum(fn (DebtPayment $payment): int => $payment->amount_cents - $payment->interest_cents);
            $balance = max(0, $debt->opening_balance_cents - $principal);
            $due = (new RecurringSchedule(BillingFrequency::Monthly, $debt->due_anchor))->next($today);
            if ($debt->minimum_payment_cents > 0 && $debt->payments->filter(fn (DebtPayment $payment): bool => $payment->date->gte($due) || $payment->budgetTransaction?->recurringCharge?->scheduled_date?->equalTo($due) === true)->sum('amount_cents') >= $debt->minimum_payment_cents) {
                $due = (new RecurringSchedule(BillingFrequency::Monthly, $debt->due_anchor))->next($due->addDay());
            }
            $latestScheduled = collect((new RecurringSchedule(BillingFrequency::Monthly, $debt->due_anchor))->between($today->startOfMonth()->subMonth(), $today))->last();
            $unrecorded = $balance > 0 && $latestScheduled !== null && $latestScheduled->gte($debt->balance_date) && ! $debt->payments->contains(fn (DebtPayment $payment): bool => $payment->date->gte($latestScheduled));

            return ['debt' => $debt, 'balance' => $balance, 'principal' => $principal, 'credit' => max(0, $principal - $debt->opening_balance_cents), 'due' => $due, 'unrecorded' => $unrecorded, 'schedule' => $debt->schedules->first(), 'progress' => $debt->opening_balance_cents > 0 ? min(100, (int) round($principal / $debt->opening_balance_cents * 100)) : 100];
        });
        $payments = $debts->flatMap(fn (Debt $debt): Collection => $debt->payments->map(fn (DebtPayment $payment): array => ['payment' => $payment, 'debt' => $debt]))->sortByDesc(fn (array $row): string => $row['payment']->date->toDateString().'|'.str_pad((string) $row['payment']->id, 20, '0', STR_PAD_LEFT))->values();

        return ['rows' => $rows, 'total' => $rows->sum('balance'), 'minimum' => $rows->filter(fn (array $row): bool => $row['balance'] > 0)->sum(fn (array $row): int => $row['debt']->minimum_payment_cents), 'paid' => $rows->sum('principal'), 'starting' => $debts->sum('opening_balance_cents'), 'dueSoon' => $rows->filter(fn (array $row): bool => $row['balance'] > 0 && $row['due']->lte($today->addDays(6)))->sortBy('due')->values(), 'payments' => $payments];
    }
}
