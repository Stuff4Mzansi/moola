<div class="space-y-4" data-budget-live="review">
    @php
        $reviewStatus = [
            'ended' => ['label' => 'Period ended', 'icon' => 'check', 'style' => 'border-success/40 bg-success/5 text-success'],
            'current' => ['label' => 'In progress', 'icon' => 'activity', 'style' => 'border-primary/40 bg-primary/5 text-primary'],
            'upcoming' => ['label' => 'Upcoming period', 'icon' => 'calendar', 'style' => 'border-info/40 bg-info/5 text-info'],
        ][$periodReview['status']];
    @endphp
    <section class="space-y-4 rounded-sm border border-base-300 bg-base-100 p-3 sm:p-4" aria-labelledby="period-review-title">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-base-300/70 bg-base-200/30 p-3 sm:p-4">
            <div class="flex min-w-0 items-start gap-3">
                <span @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-sm',
                    'bg-success/10 text-success' => $periodReview['status'] === 'ended',
                    'bg-primary/10 text-primary' => $periodReview['status'] === 'current',
                    'bg-info/10 text-info' => $periodReview['status'] === 'upcoming',
                ])>
                    @if($periodReview['status'] === 'ended')
                    <x-lucide-clipboard-check class="size-5" aria-hidden="true" />
                    @elseif($periodReview['status'] === 'current')
                    <x-lucide-chart-no-axes-combined class="size-5" aria-hidden="true" />
                    @else
                    <x-lucide-calendar-clock class="size-5" aria-hidden="true" />
                    @endif
                </span>
                <div class="min-w-0">
                    <h3 id="period-review-title" class="text-base font-semibold sm:text-lg">{{ $periodReview['status'] === 'ended' ? 'End-of-period financial review' : 'Period review preview' }}</h3>
                    <p class="mt-1 text-xs tabular-nums opacity-65">{{ $period->start_date->format('d M Y') }} - {{ $period->end_date->format('d M Y') }} &middot; Both dates included</p>
                </div>
            </div>
            <span @class(['inline-flex shrink-0 items-center gap-1.5 rounded-sm border px-2.5 py-1 text-xs font-medium', $reviewStatus['style']])>
                @if($periodReview['status'] === 'ended')
                <x-lucide-circle-check class="size-3.5" aria-hidden="true" />
                @elseif($periodReview['status'] === 'current')
                <x-lucide-circle-dot class="size-3.5" aria-hidden="true" />
                @else
                <x-lucide-calendar-days class="size-3.5" aria-hidden="true" />
                @endif
                {{ $reviewStatus['label'] }}
            </span>
        </div>
        <p class="text-xs leading-relaxed opacity-70">{{ $periodReview['status'] === 'ended' ? 'Review your recorded results and confirm unfinished items before starting your next plan.' : 'These are the records entered so far. Results can change until the period has ended and your records are complete.' }}</p>
        @include('budgets.review-spending-trend')
        @include('budgets.review-charts')
        <div class="flex items-start gap-2 rounded-sm border border-base-300/70 px-3 py-2.5 text-xs leading-relaxed opacity-70">
            <x-lucide-info class="mt-0.5 size-4 shrink-0 text-info" aria-hidden="true" />
            <p>Spending is grouped by the category assigned to each budget-period transaction. Debt or savings entries recorded only on the Debts or Goals pages are not included here.</p>
        </div>
        <div @class([
            'flex flex-wrap items-center justify-between gap-3 rounded-sm border-l-4 px-4 py-3',
            'border-base-300 bg-base-200/40' => $periodReview['recordedBalance'] >= 0,
            'border-error bg-error/5' => $periodReview['recordedBalance'] < 0,
        ])>
            <div class="flex min-w-0 items-start gap-3">
                <span @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-sm',
                    'bg-base-300/60 text-base-content/70' => $periodReview['recordedBalance'] >= 0,
                    'bg-error/10 text-error' => $periodReview['recordedBalance'] < 0,
                ])>
                    <x-lucide-scale class="size-4" aria-hidden="true" />
                </span>
                <div>
                    <p class="text-xs font-semibold">Received income minus recorded spending</p>
                    <p class="mt-1 text-[11px] leading-relaxed opacity-65">A difference between budget records, not your bank balance or money available to spend. Unrecorded payments can change the result.</p>
                </div>
            </div>
            <p @class(['shrink-0 text-lg font-bold tabular-nums sm:text-xl', 'text-error' => $periodReview['recordedBalance'] < 0])>{{ $money($periodReview['recordedBalance']) }}</p>
        </div>
    </section>

    <section class="rounded-sm border border-base-300 bg-base-100 p-3 sm:p-4" aria-labelledby="period-review-checklist-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 id="period-review-checklist-title" class="flex items-center gap-2 font-semibold"><x-lucide-list-checks class="size-4 text-primary" aria-hidden="true" />Review before your next period</h3>
                <p class="mt-1 text-xs opacity-65">Check scheduled payments, expected income, and category limits.</p>
            </div>
            @if($canEdit && $periodReview['status'] === 'ended')
            <button class="btn btn-primary btn-sm" type="button" data-open-dialog="budget-copy">Copy plan to next period <x-lucide-arrow-right class="size-4" aria-hidden="true" /></button>
            @endif
        </div>

        <div class="mt-4 grid items-stretch gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article class="flex min-w-0 flex-col rounded-sm border border-warning/25 bg-warning/5 p-3" aria-labelledby="review-pending-title">
                <div class="flex items-start gap-2.5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-sm bg-warning/10 text-warning"><x-lucide-clock-3 class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <h4 id="review-pending-title" class="text-sm font-semibold">Scheduled payments</h4>
                            <span class="text-xs tabular-nums opacity-65">{{ count($periodReview['pending']) }} to check</span>
                        </div>
                        @if($periodReview['pending'])
                        <p class="mt-1 text-xs leading-relaxed opacity-70">{{ $money($periodReview['pendingAmount']) }} estimated and not recorded. A passed date does not confirm whether a payment was missed.</p>
                        @else
                        <p class="mt-1 text-xs leading-relaxed opacity-70">No unpaid scheduled payments in this period's records.</p>
                        @endif
                    </div>
                </div>
                @if($periodReview['pending'])
                <ul class="mt-3 max-h-52 divide-y divide-base-300/70 overflow-y-auto rounded-sm border border-base-300/70 bg-base-100/70 px-2" tabindex="0" role="region" aria-label="Scheduled payments to review">
                    @foreach($periodReview['pending'] as $payment)
                    <li class="flex min-w-0 flex-wrap items-center justify-between gap-x-2 gap-y-1 py-2">
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-xs font-medium">{{ $payment['name'] }}</p>
                            <p class="mt-0.5 text-[10px] tabular-nums opacity-65">{{ \Carbon\CarbonImmutable::parse($payment['date'])->format('d M Y') }} &middot; {{ $money($payment['amount']) }} estimated</p>
                        </div>
                        <a class="link link-primary shrink-0 text-[11px] font-medium" aria-label="Review {{ $payment['name'] }} payment" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => $payment['tab']]) }}">Review <x-lucide-arrow-up-right class="inline size-3" aria-hidden="true" /></a>
                    </li>
                    @endforeach
                </ul>
                @else
                <div class="mt-3 flex items-center gap-2 rounded-sm bg-success/5 px-2.5 py-2 text-xs text-success"><x-lucide-circle-check class="size-4 shrink-0" aria-hidden="true" />All scheduled payments are accounted for.</div>
                @endif
            </article>

            <article @class([
                'flex min-w-0 flex-col rounded-sm border p-3',
                'border-info/25 bg-info/5' => $periodReview['missingIncome'],
                'border-success/25 bg-success/5' => !$periodReview['missingIncome'],
            ]) aria-labelledby="review-income-title">
                <div class="flex items-start gap-2.5">
                    <span @class([
                        'flex size-8 shrink-0 items-center justify-center rounded-sm',
                        'bg-info/10 text-info' => $periodReview['missingIncome'],
                        'bg-success/10 text-success' => !$periodReview['missingIncome'],
                    ])><x-lucide-wallet class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <h4 id="review-income-title" class="text-sm font-semibold">Income to confirm</h4>
                            <span class="text-xs tabular-nums opacity-65">{{ count($periodReview['missingIncome']) }} remaining</span>
                        </div>
                        @if($periodReview['missingIncome'])
                        <p class="mt-1 text-xs leading-relaxed opacity-70">Expected income that is not yet fully recorded as received.</p>
                        @else
                        <p class="mt-1 text-xs leading-relaxed opacity-70">No outstanding expected income in this period's records.</p>
                        @endif
                    </div>
                </div>
                @if($periodReview['missingIncome'])
                <ul class="mt-3 max-h-52 space-y-1 overflow-y-auto rounded-sm border border-base-300/70 bg-base-100/70 p-2" tabindex="0" role="region" aria-label="Expected income to confirm">
                    @foreach($periodReview['missingIncome'] as $income)
                    <li class="flex min-w-0 items-start justify-between gap-2 rounded-sm px-1 py-1.5 text-xs">
                        <span class="min-w-0 break-words font-medium">{{ $income['name'] }}</span>
                        <span class="shrink-0 text-right tabular-nums">{{ $money($income['amount']) }}<span class="ml-1 text-[10px] opacity-65">expected</span></span>
                    </li>
                    @endforeach
                </ul>
                <a class="link link-primary mt-3 inline-flex items-center gap-1 text-xs font-medium" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'plan']) }}">Review income <x-lucide-arrow-right class="size-3.5" aria-hidden="true" /></a>
                @else
                <div class="mt-3 flex items-center gap-2 rounded-sm bg-success/5 px-2.5 py-2 text-xs text-success"><x-lucide-circle-check class="size-4 shrink-0" aria-hidden="true" />Expected income is up to date.</div>
                @endif
            </article>

            <article @class([
                'flex min-w-0 flex-col rounded-sm border p-3',
                'border-error/25 bg-error/5' => $periodReview['overLimits'],
                'border-success/25 bg-success/5' => !$periodReview['overLimits'],
            ]) aria-labelledby="review-limits-title">
                <div class="flex items-start gap-2.5">
                    <span @class([
                        'flex size-8 shrink-0 items-center justify-center rounded-sm',
                        'bg-error/10 text-error' => $periodReview['overLimits'],
                        'bg-success/10 text-success' => !$periodReview['overLimits'],
                    ])><x-lucide-chart-no-axes-column-increasing class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <h4 id="review-limits-title" class="text-sm font-semibold">Limits to revisit</h4>
                            <span class="text-xs tabular-nums opacity-65">{{ count($periodReview['overLimits']) }} over limit</span>
                        </div>
                        @if($periodReview['overLimits'])
                        <p class="mt-1 text-xs leading-relaxed opacity-70">Recorded spending has exceeded these category limits.</p>
                        @else
                        <p class="mt-1 text-xs leading-relaxed opacity-70">No configured limits exceeded by recorded spending.</p>
                        @endif
                    </div>
                </div>
                @if($periodReview['overLimits'])
                <ul class="mt-3 max-h-52 space-y-1 overflow-y-auto rounded-sm border border-base-300/70 bg-base-100/70 p-2" tabindex="0" role="region" aria-label="Budget category limits exceeded">
                    @foreach($periodReview['overLimits'] as $limit)
                    <li class="flex min-w-0 items-start justify-between gap-2 rounded-sm px-1 py-1.5 text-xs">
                        <span class="min-w-0 break-words font-medium">{{ $limit['name'] }}</span>
                        <span class="shrink-0 text-right font-semibold tabular-nums text-error">{{ $money($limit['amount']) }} over</span>
                    </li>
                    @endforeach
                </ul>
                <a class="link link-primary mt-3 inline-flex items-center gap-1 text-xs font-medium" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'plan']) }}">Review allocations <x-lucide-arrow-right class="size-3.5" aria-hidden="true" /></a>
                @else
                <div class="mt-3 flex items-center gap-2 rounded-sm bg-success/5 px-2.5 py-2 text-xs text-success"><x-lucide-circle-check class="size-4 shrink-0" aria-hidden="true" />All configured limits are within plan.</div>
                @endif
            </article>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-sm border border-base-300/70 bg-base-200/30 px-3 py-2.5">
            <p class="flex items-start gap-2 text-xs leading-relaxed opacity-75"><x-lucide-notebook-pen class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />Check that everyday expenses are recorded before treating underspending as a final result.</p>
            <a class="link link-primary inline-flex shrink-0 items-center gap-1 text-xs font-medium" href="{{ route('budgets.index', ['period' => $period->id, 'tab' => 'expenses']) }}">Review expenses <x-lucide-arrow-right class="size-3.5" aria-hidden="true" /></a>
        </div>
        @if($canEdit && $periodReview['status'] === 'ended')
        <p class="mt-2 text-[11px] opacity-65">Review the dates and allocations before copying to a new period. Recorded payments are not copied.</p>
        @endif
    </section>
</div>
