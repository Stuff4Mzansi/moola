    @php($actualMoneyLeft = $totals['received'] - $totals['spent'])
    <section data-budget-live="summary" class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-label="Budget summary">
        <dl class="grid grid-cols-2 lg:grid-cols-4">
            <div class="min-w-0 border-b border-r border-base-300 p-3 lg:border-b-0">
                <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-banknote class="size-3.5 shrink-0 text-primary" aria-hidden="true" />Expected income</dt>
                <dd class="mt-1 break-words text-base font-semibold tracking-tight tabular-nums sm:text-lg">{{ $money($totals['expected']) }}</dd>
                <dd class="mt-1 break-words text-[11px] leading-snug opacity-60">Received: {{ $money($totals['received']) }}</dd>
            </div>
            <div class="min-w-0 border-b border-base-300 p-3 lg:border-b-0 lg:border-r">
                <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-clipboard-list class="size-3.5 shrink-0 text-primary" aria-hidden="true" />Planned spending</dt>
                <dd class="mt-1 break-words text-base font-semibold tracking-tight tabular-nums sm:text-lg">{{ $money($totals['planned']) }}</dd>
                <dd @class(['mt-1 break-words text-[11px] leading-snug', 'text-error' => $totals['unallocated'] < 0, 'opacity-60' => $totals['unallocated'] >= 0])>{{ $totals['unallocated'] < 0 ? 'Overplanned by ' : 'Unallocated: ' }}{{ $money(abs($totals['unallocated'])) }}</dd>
            </div>
            <div class="min-w-0 border-r border-base-300 p-3">
                <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-trending-up class="size-3.5 shrink-0 text-orange-600" aria-hidden="true" />Spent so far</dt>
                <dd class="mt-1 break-words text-base font-semibold tracking-tight tabular-nums text-orange-600 sm:text-lg">{{ $money($totals['spent']) }}</dd>
                <dd class="mt-1 break-words text-[11px] leading-snug opacity-60">Unpaid scheduled expenses: {{ $money($totals['upcoming']) }}</dd>
            </div>
            <div class="min-w-0 bg-primary/5 p-3">
                <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-wallet class="size-3.5 shrink-0 text-primary" aria-hidden="true" />Expected money left</dt>
                <dd @class(['mt-1 break-words text-base font-semibold tracking-tight tabular-nums sm:text-lg', 'text-error' => $totals['remaining'] < 0, 'text-primary' => $totals['remaining'] >= 0])>{{ $money($totals['remaining']) }}</dd>
                <dd @class(['mt-1 break-words text-[11px] leading-snug', 'text-error' => $totals['after_commitments'] < 0, 'opacity-60' => $totals['after_commitments'] >= 0])>After unpaid charges: {{ $money($totals['after_commitments']) }}</dd>
                <dd @class(['mt-1 break-words text-[11px] font-medium leading-snug tabular-nums', 'text-error' => $actualMoneyLeft < 0, 'text-success' => $actualMoneyLeft >= 0])>Actual money left: {{ $money($actualMoneyLeft) }}</dd>
            </div>
        </dl>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 border-t border-base-300 bg-base-200/40 px-3 py-2 text-[11px] leading-snug">
            <p class="min-w-0 flex-1 basis-64 opacity-60">Actual money left is received income minus recorded spending; it is not your bank balance.</p>
            <div class="flex flex-wrap items-center gap-3"><button type="button" class="link link-hover font-medium" data-scroll-to="budget-transactions">Review expenses</button><button type="button" class="link link-hover font-medium" data-scroll-to="budget-incomes">Review income</button></div>
        </div>
    </section>
    @if($subscriptionChanges)<div class="alert alert-info text-sm"><div><p class="font-semibold">{{ collect($subscriptionChanges)->contains(fn (string $change): bool => str_contains($change, 'recurring forecast')) ? 'Scheduled expense forecast updated' : 'Subscription forecast updated' }}</p><ul>@foreach(array_slice($subscriptionChanges, 0, 5) as $change)<li>{{ $change }}</li>@endforeach</ul><p>Recorded payments are preserved. Unpaid forecasts reflect your current schedules.</p></div></div>@endif
