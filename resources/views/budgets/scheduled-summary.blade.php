@php
    $paidScheduledCharges = $scheduledCharges->filter(fn (\App\Models\BudgetCommitment|\App\Models\BudgetRecurringCharge $charge): bool => $charge->transaction !== null);
    $unpaidScheduledCharges = $scheduledCharges->filter(fn (\App\Models\BudgetCommitment|\App\Models\BudgetRecurringCharge $charge): bool => $charge->transaction === null && $charge->is_current);
    $recordedScheduledCents = $paidScheduledCharges->sum(fn (\App\Models\BudgetCommitment|\App\Models\BudgetRecurringCharge $charge): int => $charge->transaction->amount_cents);
@endphp
<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-label="{{ $summaryLabel }}">
    <dl class="grid items-stretch sm:grid-cols-3">
        <div class="min-w-0 border-b border-base-300 p-3 sm:border-b-0 sm:border-r">
            <dt class="flex items-center gap-1.5 text-xs font-medium opacity-65"><x-lucide-calendar-days class="size-3.5 shrink-0 text-primary" aria-hidden="true" />Scheduled payments</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $scheduledCharges->count() }}</dd>
            <dd class="mt-1 text-xs opacity-65">{{ $paidScheduledCharges->count() }} paid &middot; {{ $unpaidScheduledCharges->count() }} unpaid</dd>
        </div>
        <div class="min-w-0 border-b border-base-300 p-3 sm:border-b-0 sm:border-r">
            <dt class="flex items-center gap-1.5 text-xs font-medium opacity-65"><x-lucide-banknote class="size-3.5 shrink-0 text-orange-600" aria-hidden="true" />Recorded spending</dt>
            <dd class="mt-1 break-words text-lg font-semibold tabular-nums text-orange-600">{{ $money($recordedScheduledCents) }}</dd>
            <dd class="mt-1 text-xs opacity-65">Actual payments linked to these charges</dd>
        </div>
        <div class="min-w-0 bg-primary/5 p-3">
            <dt class="flex items-center gap-1.5 text-xs font-medium opacity-65"><x-lucide-clock-3 class="size-3.5 shrink-0 text-primary" aria-hidden="true" />Unpaid forecast</dt>
            <dd class="mt-1 break-words text-lg font-semibold tabular-nums">{{ $money($unpaidScheduledCharges->sum('amount_cents')) }}</dd>
            <dd class="mt-1 text-xs opacity-65">Estimated payments still to record</dd>
        </div>
    </dl>
</section>
