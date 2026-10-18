@php
    $actualMoneyLeft = $totals['received'] - $totals['spent'];
    $incomeReceivedPercent = $totals['expected'] > 0 ? min(100, (int) round($totals['received'] / $totals['expected'] * 100)) : 0;
    $plannedIncomePercent = $totals['expected'] > 0 ? (int) round($totals['planned'] / $totals['expected'] * 100) : null;
    $spendingPlanPercent = $totals['planned'] > 0 ? min(100, (int) round($totals['spent'] / $totals['planned'] * 100)) : 0;
@endphp
<section data-budget-live="summary" class="space-y-2" aria-label="Budget summary">
    <dl class="grid grid-cols-2 items-stretch gap-2 lg:grid-cols-4">
        <div class="flex min-w-0 flex-col rounded-sm border border-base-300 border-t-2 border-t-success bg-base-100 p-3" data-budget-summary-tile="expected-income">
            <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><span class="grid size-6 shrink-0 place-items-center rounded-sm bg-success/10 text-success"><x-lucide-banknote class="size-3.5" aria-hidden="true" /></span>Expected income</dt>
            <dd class="mt-2 break-words text-lg font-semibold tracking-tight tabular-nums">{{ $money($totals['expected']) }}</dd>
            <div class="mt-auto pt-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5 text-[10px] leading-snug"><span class="opacity-60">Received</span><span class="font-medium tabular-nums">{{ $money($totals['received']) }} <span class="font-normal opacity-60">({{ $incomeReceivedPercent }}%)</span></span></div>
                <progress class="progress progress-success mt-1.5 block h-1 w-full" value="{{ min($totals['received'], max(1, $totals['expected'])) }}" max="{{ max(1, $totals['expected']) }}" aria-label="Income received against expected income" aria-valuetext="{{ $money($totals['received']) }} received of {{ $money($totals['expected']) }} expected"></progress>
            </div>
        </div>
        <div class="flex min-w-0 flex-col rounded-sm border border-base-300 border-t-2 border-t-primary bg-base-100 p-3" data-budget-summary-tile="planned-spending">
            <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><span class="grid size-6 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><x-lucide-clipboard-list class="size-3.5" aria-hidden="true" /></span>Planned spending</dt>
            <dd class="mt-2 break-words text-lg font-semibold tracking-tight tabular-nums">{{ $money($totals['planned']) }}</dd>
            <div class="mt-auto pt-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5 text-[10px] leading-snug">
                    <span class="opacity-60">{{ $plannedIncomePercent === null ? 'Income not set' : 'Share of income' }}</span>
                    <span @class(['font-medium tabular-nums', 'text-error' => $totals['unallocated'] < 0])>{{ $totals['unallocated'] < 0 ? 'Over by ' : ($plannedIncomePercent === null ? 'Unallocated ' : $plannedIncomePercent.'% · Left ') }}{{ $money(abs($totals['unallocated'])) }}</span>
                </div>
                @if($totals['expected'] > 0)
                    <progress @class(['progress mt-1.5 block h-1 w-full', 'progress-error' => $plannedIncomePercent > 100, 'progress-primary' => $plannedIncomePercent <= 100]) value="{{ min($totals['planned'], max(1, $totals['expected'])) }}" max="{{ max(1, $totals['expected']) }}" aria-label="Planned spending against expected income" aria-valuetext="{{ $money($totals['planned']) }} planned of {{ $money($totals['expected']) }} expected"></progress>
                @else
                    <p class="mt-1.5 text-[10px] leading-snug opacity-55">Add income to compare your plan.</p>
                @endif
            </div>
        </div>
        <div class="flex min-w-0 flex-col rounded-sm border border-base-300 border-t-2 border-t-orange-600 bg-base-100 p-3" data-budget-summary-tile="spent">
            <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><span class="grid size-6 shrink-0 place-items-center rounded-sm bg-orange-600/10 text-orange-600"><x-lucide-trending-up class="size-3.5" aria-hidden="true" /></span>Spent so far</dt>
            <dd @class(['mt-2 break-words text-lg font-semibold tracking-tight tabular-nums', 'text-error' => $totals['spent'] > $totals['planned'], 'text-orange-600' => $totals['spent'] <= $totals['planned']])>{{ $money($totals['spent']) }}</dd>
            <div class="mt-auto pt-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5 text-[10px] leading-snug"><span class="opacity-60">Unpaid scheduled</span><span class="font-medium tabular-nums">{{ $money($totals['upcoming']) }}</span></div>
                @if($totals['planned'] > 0)
                    <progress @class(['progress mt-1.5 block h-1 w-full', 'progress-error' => $totals['spent'] > $totals['planned'], 'text-orange-600' => $totals['spent'] <= $totals['planned']]) value="{{ min($totals['spent'], max(1, $totals['planned'])) }}" max="{{ max(1, $totals['planned']) }}" aria-label="Recorded spending against planned spending" aria-valuetext="{{ $money($totals['spent']) }} spent of {{ $money($totals['planned']) }} planned"></progress>
                @else
                    <p class="mt-1.5 text-[10px] leading-snug opacity-55">No spending plan set yet.</p>
                @endif
            </div>
        </div>
        <div class="flex min-w-0 flex-col rounded-sm border border-primary/20 border-t-2 border-t-primary bg-primary/5 p-3" data-budget-summary-tile="money-left">
            <dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><span class="grid size-6 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><x-lucide-wallet class="size-3.5" aria-hidden="true" /></span>Expected money left</dt>
            <dd @class(['mt-2 break-words text-lg font-semibold tracking-tight tabular-nums', 'text-error' => $totals['remaining'] < 0, 'text-primary' => $totals['remaining'] >= 0])>{{ $money($totals['remaining']) }}</dd>
            <div class="mt-auto space-y-1.5 border-t border-primary/10 pt-3 text-[10px] leading-snug">
                <p class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5"><span class="opacity-60">After unpaid charges</span><span @class(['font-medium tabular-nums', 'text-error' => $totals['after_commitments'] < 0])>{{ $money($totals['after_commitments']) }}</span></p>
                <p class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5"><span class="opacity-60">Actual money left:</span><span @class(['font-medium tabular-nums', 'text-error' => $actualMoneyLeft < 0, 'text-success' => $actualMoneyLeft >= 0])>{{ $money($actualMoneyLeft) }}</span></p>
            </div>
        </div>
    </dl>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 rounded-sm border border-base-300 bg-base-200/40 px-3 py-2 text-[10px] leading-snug">
        <p class="min-w-0 flex-1 basis-64 opacity-60">Expected income minus recorded spending; after unpaid charges also subtracts unpaid scheduled expenses. Actual money left is received income minus recorded spending, not your bank balance.</p>
        <div class="flex flex-wrap items-center gap-3"><button type="button" class="link link-hover font-medium" data-scroll-to="budget-transactions">Review expenses</button><button type="button" class="link link-hover font-medium" data-scroll-to="budget-incomes">Review income</button></div>
    </div>
</section>
@if($subscriptionChanges)<div class="alert alert-info text-sm"><div><p class="font-semibold">{{ collect($subscriptionChanges)->contains(fn (string $change): bool => str_contains($change, 'recurring forecast')) ? 'Scheduled expense forecast updated' : 'Subscription forecast updated' }}</p><ul>@foreach(array_slice($subscriptionChanges, 0, 5) as $change)<li>{{ $change }}</li>@endforeach</ul><p>Recorded payments are preserved. Unpaid forecasts reflect your current schedules.</p></div></div>@endif
