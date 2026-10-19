<div class="space-y-6">
    <header class="flex items-center gap-2 rounded-sm border border-base-300 bg-base-100 px-3 py-2.5">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-primary"><x-lucide-chart-no-axes-combined class="size-4" aria-hidden="true" /></span>
        <div><h3 class="text-sm font-semibold">Budget overview</h3><p class="text-xs opacity-65">Income, spending, and how your plan is tracking this period.</p></div>
    </header>
    @include('budgets.summary')
    @include('budgets.group-analytics')
    <div data-budget-live="analytics" class="space-y-6">
        @php
            $breakdownLabel = $groups->isEmpty() ? 'category' : 'group';
            $overspent = $analyticsRows->filter(fn (array $row): bool => $row['limit'] !== null && $row['spent'] > $row['limit']);
            $totalPeriodDays = (int) $period->start_date->diffInDays($period->end_date) + 1;
            $periodDays = max(1, $totalPeriodDays - 1);
            $today = \Carbon\CarbonImmutable::today();
            $elapsedDays = max(0, min($totalPeriodDays, (int) $period->start_date->diffInDays($today, false) + 1));
            $elapsedPercent = min(100, (int) round($elapsedDays / $totalPeriodDays * 100));
            $periodUpcoming = $today->lt($period->start_date);
            $periodEnded = $today->gt($period->end_date);
            $countdownDays = max(0, (int) $today->diffInDays($periodUpcoming ? $period->start_date : $period->end_date, false));
            $countdownLabel = $periodUpcoming ? ($countdownDays === 1 ? 'day to start' : 'days to start') : ($countdownDays === 1 ? 'day left' : 'days left');
            $spendingPercent = $totals['planned'] > 0 ? round($totals['spent'] / $totals['planned'] * 100) : null;
            $trendMaximum = max(1, $totals['spent'], $totals['planned'], $totals['received']);
            $trendPlotLeft = 64;
            $trendPlotRight = 696;
            $trendPlotTop = 40;
            $trendPlotBottom = 180;
            $trendPlotWidth = $trendPlotRight - $trendPlotLeft;
            $trendPlotHeight = $trendPlotBottom - $trendPlotTop;
            $runningSpent = 0;
            $trendPath = 'M '.$trendPlotLeft.' '.$trendPlotBottom;
            $previousX = $trendPlotLeft;
            $previousY = $trendPlotBottom;
            $trendPoints = [];
            $outOfFundsPoint = null;
            $trendDateTicks = [];
            $tickCount = min(5, $totalPeriodDays);
            for ($tickIndex = 0; $tickIndex < $tickCount; $tickIndex++) {
                $dayOffset = $tickCount === 1 ? 0 : (int) round(($totalPeriodDays - 1) * $tickIndex / ($tickCount - 1));
                $trendDateTicks[] = [
                    'date' => $period->start_date->addDays($dayOffset),
                    'x' => $trendPlotLeft + $dayOffset / $periodDays * $trendPlotWidth,
                    'anchor' => $tickIndex === 0 ? 'start' : ($tickIndex === $tickCount - 1 ? 'end' : 'middle'),
                ];
            }
            $trendAmountTicks = [];
            for ($tickIndex = 0; $tickIndex <= 4; $tickIndex++) {
                $amount = (int) round($trendMaximum * $tickIndex / 4);
                $trendAmountTicks[] = [
                    'amount' => $amount,
                    'y' => $trendPlotBottom - $tickIndex / 4 * $trendPlotHeight,
                ];
            }
            foreach ($dailySpending as $date => $dailyTransactions) {
                $x = $trendPlotLeft + (int) $period->start_date->diffInDays(\Carbon\CarbonImmutable::parse($date)) / $periodDays * $trendPlotWidth;
                $runningSpent += $dailyTransactions;
                $newY = $trendPlotBottom - $runningSpent / $trendMaximum * $trendPlotHeight;
                if (\Carbon\CarbonImmutable::parse($date)->toDateString() === $period->start_date->toDateString()) {
                    $trendPath = 'M '.$trendPlotLeft.' '.round($newY, 2);
                } else {
                    $controlOffset = ($x - $previousX) / 3;
                    $trendPath .= ' C '.round($previousX + $controlOffset, 2).' '.round($previousY, 2).' '.round($x - $controlOffset, 2).' '.round($newY, 2).' '.round($x, 2).' '.round($newY, 2);
                }
                $trendPoints[] = ['date' => $date, 'x' => $x, 'y' => $newY, 'spent' => $runningSpent];
                if ($outOfFundsPoint === null && $runningSpent >= $totals['received']) {
                    $outOfFundsPoint = [
                        'date' => \Carbon\CarbonImmutable::parse($date),
                        'x' => $x,
                    ];
                }
                $previousX = $x;
                $previousY = $newY;
            }
            $lastDate = $dailySpending->keys()->last();
            $trendEnd = max($period->start_date->toDateString(), min($period->end_date->toDateString(), max(now()->toDateString(), $lastDate ?? '')));
            $endX = $trendPlotLeft + (int) $period->start_date->diffInDays(\Carbon\CarbonImmutable::parse($trendEnd)) / $periodDays * $trendPlotWidth;
            $trendPath .= ' L '.round($endX, 2).' '.round($trendPlotBottom - $runningSpent / $trendMaximum * $trendPlotHeight, 2);
            $planY = $trendPlotBottom - $totals['planned'] / $trendMaximum * $trendPlotHeight;
            $incomeY = $trendPlotBottom - $totals['received'] / $trendMaximum * $trendPlotHeight;
            $trendDescription = 'Recorded spending totals '.$money($totals['spent']).' against a planned limit of '.$money($totals['planned']).' and income received of '.$money($totals['received']).'. A smooth curve connects cumulative recorded totals on expense dates. '.($totals['planned'] > 0 ? 'The budget pace line shows planned spending distributed evenly from the start to the end of the period. ' : '').'The green dashed line shows total income recorded as received for this period.';
            $receivedFundingGap = max(0, $totals['planned'] - $totals['received']);
        @endphp
            <div class="grid min-w-0 gap-4 lg:grid-cols-3">
            <section class="min-w-0 rounded-sm border border-base-300 bg-base-100 p-5 lg:col-span-2" aria-labelledby="budget-trend-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 id="budget-trend-title" class="font-semibold">Spending over time</h3><p class="mt-1 text-xs opacity-60">Cumulative expenses compared with your plan and income received</p></div>
                    <div class="w-full rounded-sm border border-primary/15 bg-primary/5 px-3 py-2 sm:w-auto sm:min-w-44" data-budget-period-clock>
                        <div class="flex items-center gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-primary"><x-lucide-clock-3 class="size-3.5" aria-hidden="true" /></span>
                            @if($periodEnded)<span class="text-xs font-semibold">Period ended</span>
                            @elseif(! $periodUpcoming && $countdownDays === 0)<span class="text-xs font-semibold text-primary">Ends today</span>
                            @else<span class="inline-flex items-baseline gap-1.5"><span @class(['font-mono text-lg font-semibold leading-none text-primary', 'countdown' => $countdownDays <= 999])><span style="--value:{{ $countdownDays }};" aria-label="{{ $countdownDays }}">{{ $countdownDays }}</span></span><span class="text-[11px] opacity-65">{{ $countdownLabel }}</span></span>@endif
                        </div>
                        <progress class="progress progress-primary mt-1.5 block h-1 w-full" value="{{ $elapsedPercent }}" max="100" aria-label="Period elapsed" aria-valuetext="{{ $elapsedPercent }}% of period elapsed"></progress>
                        <div class="mt-1 flex items-center justify-between gap-3 text-[10px] opacity-60"><span>{{ $elapsedPercent }}% elapsed</span><time datetime="{{ ($periodUpcoming ? $period->start_date : $period->end_date)->toDateString() }}">{{ $periodUpcoming ? 'Starts' : ($periodEnded ? 'Ended' : 'Ends') }} {{ ($periodUpcoming ? $period->start_date : $period->end_date)->format('d M') }}</time></div>
                    </div>
                </div>
                @if($dailySpending->isNotEmpty() || $totals['planned'] > 0 || $totals['received'] > 0)
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs"><span class="inline-flex items-center gap-2"><span class="h-0.5 w-5 bg-orange-600"></span>Recorded spending</span>@if($totals['planned'] > 0)<span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-dotted border-sky-500"></span>Budget pace</span>@endif<span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-dashed border-primary/40"></span>Planned limit</span><span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-dashed border-green-600"></span>Income received</span></div>
                <div class="mt-5 overflow-x-auto" tabindex="0" role="region" aria-label="Spending chart with amounts and dates; scroll horizontally to view all labels">
                <svg viewBox="0 0 700 225" class="block w-full min-w-[40rem] text-primary sm:min-w-0" role="img" aria-labelledby="budget-trend-title budget-trend-description">
                    <title id="budget-trend-description">{{ $trendDescription }}</title>
                    <line x1="{{ $trendPlotLeft }}" y1="{{ $trendPlotTop }}" x2="{{ $trendPlotLeft }}" y2="{{ $trendPlotBottom }}" stroke="currentColor" opacity="0.15" />
                    @foreach($trendAmountTicks as $tick)
                        <line x1="{{ $trendPlotLeft }}" y1="{{ $tick['y'] }}" x2="{{ $trendPlotRight }}" y2="{{ $tick['y'] }}" stroke="currentColor" opacity="{{ $loop->first ? '0.15' : '0.08' }}" />
                        <text data-budget-trend-amount x="{{ $trendPlotLeft - 8 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end" fill="currentColor" font-size="9" opacity="0.7">{{ $money($tick['amount']) }}</text>
                    @endforeach
                    @if($totals['planned'] > 0)<line x1="{{ $trendPlotLeft }}" y1="{{ $planY }}" x2="{{ $trendPlotRight }}" y2="{{ $planY }}" stroke="currentColor" stroke-dasharray="5 5" opacity="0.4" />@endif
                    <path d="{{ $trendPath }} L {{ round($endX, 2) }} {{ $trendPlotBottom }} L {{ $trendPlotLeft }} {{ $trendPlotBottom }} Z" class="fill-orange-600" opacity="0.08" />
                    <path data-budget-spending-line d="{{ $trendPath }}" fill="none" class="stroke-orange-600" stroke-width="1" stroke-linejoin="round" stroke-linecap="round" />
                    @if($totals['planned'] > 0)
                        <line data-budget-pace-line x1="{{ $trendPlotLeft }}" y1="{{ $trendPlotBottom }}" x2="{{ $trendPlotRight }}" y2="{{ round($planY, 2) }}" class="stroke-sky-500" stroke-width="1.5" stroke-dasharray="1 4" stroke-linecap="round">
                            <title>Budget pace: planned spending rises evenly from {{ $money(0) }} at period start to {{ $money($totals['planned']) }} by {{ $period->end_date->format('d M') }}.</title>
                        </line>
                    @endif
                    <line data-budget-income-line x1="{{ $trendPlotLeft }}" y1="{{ $incomeY }}" x2="{{ $trendPlotRight }}" y2="{{ $incomeY }}" class="stroke-green-600" stroke-width="1.5" stroke-dasharray="5 5"><title>Income received: {{ $money($totals['received']) }}</title></line>
                    @if($outOfFundsPoint !== null)
                        @php($outOfFundsLabelX = max($trendPlotLeft, min($outOfFundsPoint['x'] - 43, $trendPlotRight - 86)))
                        <g data-budget-out-of-funds>
                            <title>Out of funds: cumulative expenses first equaled or exceeded income received on {{ $outOfFundsPoint['date']->format('d M') }}</title>
                            <line x1="{{ round($outOfFundsPoint['x'], 2) }}" y1="{{ $trendPlotTop }}" x2="{{ round($outOfFundsPoint['x'], 2) }}" y2="{{ $trendPlotBottom }}" class="stroke-error" stroke-width="1.5" stroke-dasharray="4 3" />
                            <path d="M {{ round($outOfFundsPoint['x'] - 4, 2) }} 28 L {{ round($outOfFundsPoint['x'] + 4, 2) }} 28 L {{ round($outOfFundsPoint['x'], 2) }} 34 Z" class="fill-error" />
                            <rect x="{{ round($outOfFundsLabelX, 2) }}" y="7" width="86" height="21" rx="10.5" class="fill-error" />
                            <text x="{{ round($outOfFundsLabelX + 43, 2) }}" y="21" text-anchor="middle" class="fill-error-content" font-size="10" font-weight="600">Out of funds</text>
                        </g>
                    @endif
                    @foreach($trendPoints as $point)<circle data-budget-spending-point cx="{{ round($point['x'], 2) }}" cy="{{ round($point['y'], 2) }}" r="3" class="fill-orange-600" tabindex="0" aria-label="{{ \Carbon\CarbonImmutable::parse($point['date'])->format('d M') }}: cumulative spending {{ $money($point['spent']) }}"><title>{{ \Carbon\CarbonImmutable::parse($point['date'])->format('d M') }}: cumulative spending {{ $money($point['spent']) }}</title></circle>@endforeach
                    <line x1="{{ $trendPlotLeft }}" y1="{{ $trendPlotBottom }}" x2="{{ $trendPlotRight }}" y2="{{ $trendPlotBottom }}" stroke="currentColor" opacity="0.15" />
                    @foreach($trendDateTicks as $tick)
                        <line x1="{{ round($tick['x'], 2) }}" y1="{{ $trendPlotBottom }}" x2="{{ round($tick['x'], 2) }}" y2="{{ $trendPlotBottom + 4 }}" stroke="currentColor" opacity="0.3" />
                        <text data-budget-trend-date x="{{ round($tick['x'], 2) }}" y="210" text-anchor="{{ $tick['anchor'] }}" fill="currentColor" font-size="10" opacity="0.7">{{ $tick['date']->format('d M') }}</text>
                    @endforeach
                </svg>
                </div>
                <div class="grid gap-2 sm:grid-cols-2" aria-label="Chart insights">
                    <div class="min-w-0 rounded-sm border border-orange-600/15 bg-orange-600/5 p-3">
                        <div class="flex items-center gap-1.5"><span class="flex size-5 shrink-0 items-center justify-center rounded-sm bg-orange-600/10 text-orange-600"><x-lucide-trending-up class="size-3" aria-hidden="true" /></span><p class="text-[11px] font-medium opacity-70">Recorded spending</p></div>
                        <p class="mt-1.5 break-words text-base font-semibold tracking-tight tabular-nums">{{ $money($totals['spent']) }}</p>
                        @if($spendingPercent !== null)
                            <div class="mt-2 flex flex-wrap items-baseline justify-between gap-1 text-[11px]"><span class="opacity-60">of planned spending</span><span @class(['font-semibold tabular-nums', 'text-error' => $spendingPercent > 100, 'text-orange-600' => $spendingPercent <= 100])>{{ number_format($spendingPercent, 1) }}%</span></div>
                            <div class="mt-1 h-1 overflow-hidden rounded-full bg-orange-600/10" role="progressbar" aria-label="Planned spending used" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $spendingPercent) }}" aria-valuetext="{{ number_format($spendingPercent, 1) }}% of your planned spending"><div @class(['h-full rounded-full', 'bg-error' => $spendingPercent > 100, 'bg-orange-600' => $spendingPercent <= 100]) style="width: {{ min(100, $spendingPercent) }}%"></div></div>
                        @else<p class="mt-2 text-[11px] opacity-60">Add category allocations to compare spending with your plan.</p>@endif
                    </div>
                    <div class="min-w-0 rounded-sm border border-green-600/15 bg-green-600/5 p-3">
                        <div class="flex items-center gap-1.5"><span class="flex size-5 shrink-0 items-center justify-center rounded-sm bg-green-600/10 text-green-600"><x-lucide-wallet class="size-3" aria-hidden="true" /></span><p class="text-[11px] font-medium opacity-70">Income received</p></div>
                        <p class="mt-1.5 break-words text-base font-semibold tracking-tight tabular-nums">{{ $money($totals['received']) }}</p>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-1.5 text-[11px]"><span class="inline-flex items-center gap-1.5 opacity-60"><span class="w-4 border-t-2 border-dashed border-green-600" aria-hidden="true"></span>Recorded for this period</span><button class="link link-hover font-medium text-green-600" type="button" data-budget-go="plan">Review income</button></div>
                    </div>
                </div>
                @if($receivedFundingGap > 0)
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-sm border border-warning/25 bg-warning/10 px-3 py-2" data-budget-received-gap>
                        <div class="flex min-w-0 items-start gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-sm bg-warning/15 text-warning"><x-lucide-triangle-alert class="size-3.5" aria-hidden="true" /></span><div class="min-w-0"><p class="text-[11px] font-medium opacity-70">Plan needs funding</p><p class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $money($receivedFundingGap) }} <span class="font-normal text-[11px] opacity-65">above income received so far</span></p></div></div>
                        <button class="btn btn-outline btn-xs" type="button" data-budget-go="plan">Review plan <x-lucide-arrow-up-right class="size-3" aria-hidden="true" /></button>
                    </div>
                @endif
                <p class="mt-2 flex items-start gap-1.5 text-[11px] leading-snug opacity-55"><x-lucide-info class="mt-0.5 size-3 shrink-0" aria-hidden="true" /><span>Income reflects amounts marked as received. Expected or unpaid income is excluded.</span></p>
                @else
                <div class="flex min-h-52 flex-col items-center justify-center gap-2 rounded-sm bg-base-200/50 p-6 mt-5 text-center"><span class="text-3xl text-primary" aria-hidden="true">&nearr;</span><p class="font-medium">Your spending story starts here</p><p class="text-sm opacity-60">Record an expense to see your trend.</p>@if($canEdit)<button class="btn btn-sm btn-primary mt-2" data-open-dialog="budget-expense" data-new-expense type="button">Add your first expense</button>@endif</div>
                @endif
            </section>
            <section class="rounded-sm border border-base-300 bg-base-100 p-5" aria-labelledby="budget-attention-title">
                <div class="flex items-center gap-2">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><x-lucide-sparkles class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0">
                        <h3 id="budget-attention-title" class="text-sm font-semibold">At a glance</h3>
                        <p class="mt-0.5 text-[11px] opacity-60">Your plan, limits and upcoming commitments</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex min-w-0 items-start gap-2">
                                <span class="grid size-7 shrink-0 place-items-center rounded-md bg-primary/10 text-primary"><x-lucide-clipboard-list class="size-3.5" aria-hidden="true" /></span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-medium opacity-65">Plan balance</p>
                                    @if($totals['unallocated'] < 0)
                                        <p class="mt-1 break-words text-sm font-semibold leading-snug text-error tabular-nums">{{ $money(abs($totals['unallocated'])) }} overplanned</p>
                                    @elseif($totals['unallocated'] > 0)
                                        <p class="mt-1 break-words text-sm font-semibold leading-snug text-primary tabular-nums">{{ $money($totals['unallocated']) }} still to allocate</p>
                                    @else
                                        <p class="mt-1 text-sm font-semibold leading-snug text-success">Every amount has a place</p>
                                    @endif
                                </div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-xs shrink-0" data-budget-go="plan">Review plan <x-lucide-arrow-up-right class="size-3" aria-hidden="true" /></button>
                        </div>
                    </div>
                    <div class="rounded-lg border border-base-300/80 bg-base-200/30 p-3">
                        <div class="flex items-start gap-2">
                            <span @class(['grid size-7 shrink-0 place-items-center rounded-md', 'bg-error/10 text-error' => $overspent->isNotEmpty(), 'bg-success/10 text-success' => $overspent->isEmpty() && !($groups->isNotEmpty() && $groupRows->whereNotNull('limit')->isEmpty()), 'bg-base-300/50 text-base-content/60' => $groups->isNotEmpty() && $groupRows->whereNotNull('limit')->isEmpty()])>
                                @if($overspent->isNotEmpty())
                                    <x-lucide-triangle-alert class="size-3.5" aria-hidden="true" />
                                @else
                                    <x-lucide-layers class="size-3.5" aria-hidden="true" />
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-medium opacity-65">{{ ucfirst($breakdownLabel) }} limits</p>
                                @if($overspent->isNotEmpty())
                                    <p class="mt-1 text-sm font-semibold leading-snug text-error">{{ $overspent->count() }} {{ str($breakdownLabel)->plural($overspent->count()) }} over limit</p>
                                    <p class="mt-1 break-words text-[11px] leading-snug opacity-65">{{ $overspent->pluck('name')->join(', ') }}</p>
                                @elseif($groups->isNotEmpty() && $groupRows->whereNotNull('limit')->isEmpty())
                                    <p class="mt-1 text-sm font-semibold leading-snug">No group limits configured</p>
                                @else
                                    <p class="mt-1 text-sm font-semibold leading-snug text-success">Spending is within {{ $breakdownLabel }} limits</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-warning/20 bg-warning/5 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex min-w-0 items-start gap-2">
                                <span class="grid size-7 shrink-0 place-items-center rounded-md bg-warning/10 text-warning"><x-lucide-calendar-days class="size-3.5" aria-hidden="true" /></span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-medium opacity-65">After unpaid scheduled expenses</p>
                                    <p @class(['mt-1 text-base font-semibold leading-snug tabular-nums', 'text-error' => $totals['after_commitments'] < 0, 'text-warning' => $totals['after_commitments'] >= 0])>{{ $money($totals['after_commitments']) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1 border-t border-warning/15 pt-2">
                            <button type="button" class="btn btn-ghost btn-xs" data-budget-go="subscriptions">Review subscriptions <x-lucide-arrow-up-right class="size-3" aria-hidden="true" /></button>
                            <button type="button" class="btn btn-ghost btn-xs" data-budget-go="recurring">Review recurring expenses <x-lucide-arrow-up-right class="size-3" aria-hidden="true" /></button>
                        </div>
                    </div>
                </div>
                @include('budgets.nudges')
            </section>
        </div>
    </div>
</div>
