<div class="space-y-6">
    @include('budgets.summary')
    @include('budgets.group-analytics')
    <div data-budget-live="analytics" class="space-y-6">
        @php
            $breakdownLabel = $groups->isEmpty() ? 'category' : 'group';
            $overspent = $analyticsRows->filter(fn (array $row): bool => $row['limit'] !== null && $row['spent'] > $row['limit']);
            $largest = $analyticsRows->sortByDesc('spent')->first();
            $chartMaximum = max(1, $analyticsRows->max('planned') ?? 0, $analyticsRows->max('spent') ?? 0);
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
            $dailySpending = $transactions->groupBy(fn (\App\Models\BudgetTransaction $transaction): string => $transaction->date->toDateString())->sortKeys();
            $trendMaximum = max(1, $totals['spent'], $totals['planned'], $totals['received']);
            $runningSpent = 0;
            $trendPath = 'M 40 180';
            $previousX = 40;
            $previousY = 180;
            $trendPoints = [];
            foreach ($dailySpending as $date => $dailyTransactions) {
                $x = 40 + (int) $period->start_date->diffInDays(\Carbon\CarbonImmutable::parse($date)) / $periodDays * 620;
                $runningSpent += $dailyTransactions->sum('amount_cents');
                $newY = 180 - $runningSpent / $trendMaximum * 140;
                if ($date === $period->start_date->toDateString()) {
                    $trendPath = 'M 40 '.round($newY, 2);
                } else {
                    $controlOffset = ($x - $previousX) / 3;
                    $trendPath .= ' C '.round($previousX + $controlOffset, 2).' '.round($previousY, 2).' '.round($x - $controlOffset, 2).' '.round($newY, 2).' '.round($x, 2).' '.round($newY, 2);
                }
                $trendPoints[] = ['date' => $date, 'x' => $x, 'y' => $newY, 'spent' => $runningSpent];
                $previousX = $x;
                $previousY = $newY;
            }
            $lastDate = $transactions->max('date');
            $trendEnd = max($period->start_date->toDateString(), min($period->end_date->toDateString(), max(now()->toDateString(), $lastDate?->toDateString() ?? '')));
            $endX = 40 + (int) $period->start_date->diffInDays(\Carbon\CarbonImmutable::parse($trendEnd)) / $periodDays * 620;
            $trendPath .= ' L '.round($endX, 2).' '.round(180 - $runningSpent / $trendMaximum * 140, 2);
            $planY = 180 - $totals['planned'] / $trendMaximum * 140;
            $incomeY = 180 - $totals['received'] / $trendMaximum * 140;
            $receivedFundingGap = max(0, $totals['planned'] - $totals['received']);
        @endphp
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="rounded-sm border border-base-300 bg-base-100 p-5 lg:col-span-2" aria-labelledby="budget-trend-title">
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
                @if($transactions->isNotEmpty() || $totals['planned'] > 0 || $totals['received'] > 0)
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs"><span class="inline-flex items-center gap-2"><span class="h-0.5 w-5 bg-orange-600"></span>Recorded spending</span><span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-dashed border-primary/40"></span>Planned limit</span><span class="inline-flex items-center gap-2"><span class="w-5 border-t-2 border-dashed border-green-600"></span>Income received</span></div>
                <svg viewBox="0 0 700 225" class="mt-5 w-full text-primary" role="img" aria-labelledby="budget-trend-title budget-trend-description">
                    <title id="budget-trend-description">Recorded spending totals {{ $money($totals['spent']) }} against a planned limit of {{ $money($totals['planned']) }} and income received of {{ $money($totals['received']) }}. A smooth curve connects cumulative recorded totals on expense dates. The green dashed line shows total income recorded as received for this period.</title>
                    <line x1="40" y1="180" x2="660" y2="180" stroke="currentColor" opacity="0.15" />
                    <line x1="40" y1="110" x2="660" y2="110" stroke="currentColor" opacity="0.08" />
                    @if($totals['planned'] > 0)<line x1="40" y1="{{ $planY }}" x2="660" y2="{{ $planY }}" stroke="currentColor" stroke-dasharray="5 5" opacity="0.4" /><text x="660" y="{{ $planY - 8 }}" text-anchor="end" fill="currentColor" font-size="11">Planned limit</text>@endif
                    <path d="{{ $trendPath }} L {{ round($endX, 2) }} 180 L 40 180 Z" class="fill-orange-600" opacity="0.08" />
                    <path data-budget-spending-line d="{{ $trendPath }}" fill="none" class="stroke-orange-600" stroke-width="1" stroke-linejoin="round" stroke-linecap="round" />
                    <line data-budget-income-line x1="40" y1="{{ $incomeY }}" x2="660" y2="{{ $incomeY }}" class="stroke-green-600" stroke-width="1.5" stroke-dasharray="5 5"><title>Income received: {{ $money($totals['received']) }}</title></line>
                    @foreach($trendPoints as $point)<circle data-budget-spending-point cx="{{ round($point['x'], 2) }}" cy="{{ round($point['y'], 2) }}" r="3" class="fill-orange-600" tabindex="0" aria-label="{{ \Carbon\CarbonImmutable::parse($point['date'])->format('d M') }}: cumulative spending {{ $money($point['spent']) }}"><title>{{ \Carbon\CarbonImmutable::parse($point['date'])->format('d M') }}: cumulative spending {{ $money($point['spent']) }}</title></circle>@endforeach
                    <text x="40" y="210" fill="currentColor" font-size="11">{{ $period->start_date->format('d M') }}</text><text x="660" y="210" text-anchor="end" fill="currentColor" font-size="11">{{ $period->end_date->format('d M') }}</text>
                </svg>
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
                <h3 id="budget-attention-title" class="font-semibold">At a glance</h3>
                <div class="mt-5 space-y-5">
                    <div><p class="text-xs opacity-60">Plan balance</p>@if($totals['unallocated'] < 0)<p class="mt-1 font-semibold text-error">{{ $money(abs($totals['unallocated'])) }} overplanned</p>@elseif($totals['unallocated'] > 0)<p class="mt-1 font-semibold">{{ $money($totals['unallocated']) }} still to allocate</p>@else<p class="mt-1 font-semibold text-success">Every rand has a place</p>@endif<button type="button" class="link text-xs mt-1" data-budget-go="plan">Review your plan</button></div>
                    <div><p class="text-xs opacity-60">{{ ucfirst($breakdownLabel) }} limits</p><p @class(['mt-1 font-semibold', 'text-error' => $overspent->isNotEmpty()])>{{ $overspent->isEmpty() ? ($groups->isNotEmpty() && $groupRows->whereNotNull('limit')->isEmpty() ? 'No group limits configured' : 'Spending is within '.$breakdownLabel.' limits') : $overspent->count().' '.str($breakdownLabel)->plural($overspent->count()).' over limit' }}</p>@if($overspent->isNotEmpty())<p class="mt-1 text-xs opacity-60">{{ $overspent->pluck('name')->join(', ') }}</p>@endif</div>
                    <div><p class="text-xs opacity-60">After unpaid scheduled expenses</p><p @class(['mt-1 text-xl font-semibold', 'text-error' => $totals['after_commitments'] < 0])>{{ $money($totals['after_commitments']) }}</p><button type="button" class="link text-xs mt-1" data-budget-go="subscriptions">Review subscriptions</button> &middot; <button type="button" class="link text-xs mt-1" data-budget-go="recurring">Review recurring expenses</button></div>
                </div>
            </section>
        </div>
        <section class="rounded-sm border border-base-300 bg-base-100 p-5 sm:p-6" aria-labelledby="budget-comparison-title">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 id="budget-comparison-title" class="font-semibold">Spending by {{ $breakdownLabel }}</h3><p class="mt-1 text-xs opacity-60">{{ $groups->isEmpty() ? 'Compare actual spending with your plan.' : 'Category spending combined by group. Expand a group to see its categories.' }}</p></div><div class="flex gap-4 text-xs"><span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-base-content/15"></span>Planned</span><span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-primary"></span>Spent</span></div></div>
            <div class="mt-6 grid gap-x-10 gap-y-5 md:grid-cols-2">
                @foreach($analyticsRows->sortByDesc('spent') as $row)
                <div><div class="mb-2 flex items-start justify-between gap-3 text-sm"><span class="font-medium break-words">{{ $row['name'] }}</span><span class="shrink-0 text-xs opacity-70">{{ $money($row['spent']) }} / {{ $money($row['planned']) }}</span></div><div class="space-y-1" role="img" aria-label="{{ $row['name'] }}: {{ $money($row['spent']) }} spent, {{ $money($row['planned']) }} planned"><div class="h-2 overflow-hidden rounded-sm bg-base-200"><div class="h-full rounded-sm bg-base-content/15" style="width: {{ $row['planned'] / $chartMaximum * 100 }}%"></div></div><div class="h-2 overflow-hidden rounded-sm bg-base-200"><div @class(['h-full rounded-sm', 'bg-error' => $row['limit'] !== null && $row['spent'] > $row['limit'], 'bg-primary' => $row['limit'] === null || $row['spent'] <= $row['limit']]) style="width: {{ $row['spent'] / $chartMaximum * 100 }}%"></div></div></div>@if($row['limit'] !== null && $row['spent'] > $row['limit'])<p class="mt-1 text-xs text-error">{{ $money($row['spent'] - $row['limit']) }} over limit</p>@endif
                    @if($groups->isNotEmpty())
                    <details class="mt-3 text-xs"><summary class="cursor-pointer opacity-60">{{ $row['categories']->count() }} categories &middot; View breakdown</summary><ul class="mt-3 space-y-2 border-l border-base-300 pl-3">@forelse($row['categories'] as $categoryRow)<li class="flex flex-wrap justify-between gap-2"><span>{{ $categoryRow['category']->name }}</span><span>{{ $money($categoryRow['spent']) }} spent / {{ $money($categoryRow['planned']) }} planned</span></li>@empty<li class="opacity-60">No categories assigned yet.</li>@endforelse</ul></details>
                    @endif
                </div>
                @endforeach
            </div>
            @if($largest && $largest['spent'] > 0)<p class="mt-5 text-xs opacity-60">Largest spending {{ $breakdownLabel }}: <span class="font-medium">{{ $largest['name'] }}</span> &middot; {{ $money($largest['spent']) }}</p>@endif
        </section>
    </div>
</div>
