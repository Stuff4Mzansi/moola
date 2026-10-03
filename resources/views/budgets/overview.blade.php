<div class="space-y-6">
    @include('budgets.summary')
    @include('budgets.group-analytics')
    <div data-budget-live="analytics" class="space-y-6">
        @php
            $breakdownLabel = $groups->isEmpty() ? 'category' : 'group';
            $overspent = $analyticsRows->filter(fn (array $row): bool => $row['limit'] !== null && $row['spent'] > $row['limit']);
            $largest = $analyticsRows->sortByDesc('spent')->first();
            $chartMaximum = max(1, $analyticsRows->max('planned') ?? 0, $analyticsRows->max('spent') ?? 0);
            $periodDays = max(1, (int) $period->start_date->diffInDays($period->end_date));
            $elapsedDays = max(0, min($periodDays + 1, (int) $period->start_date->diffInDays(now()->startOfDay(), false) + 1));
            $elapsedPercent = min(100, (int) round($elapsedDays / ($periodDays + 1) * 100));
            $spendingPercent = $totals['planned'] > 0 ? round($totals['spent'] / $totals['planned'] * 100) : null;
            $dailySpending = $transactions->groupBy(fn (\App\Models\BudgetTransaction $transaction): string => $transaction->date->toDateString())->sortKeys();
            $trendMaximum = max(1, $totals['spent'], $totals['planned']);
            $runningSpent = 0;
            $trendPath = 'M 40 180';
            foreach ($dailySpending as $date => $dailyTransactions) {
                $x = 40 + (int) $period->start_date->diffInDays(\Carbon\CarbonImmutable::parse($date)) / $periodDays * 620;
                $oldY = 180 - $runningSpent / $trendMaximum * 140;
                $runningSpent += $dailyTransactions->sum('amount_cents');
                $newY = 180 - $runningSpent / $trendMaximum * 140;
                $trendPath .= ' L '.round($x, 2).' '.round($oldY, 2).' L '.round($x, 2).' '.round($newY, 2);
            }
            $lastDate = $transactions->max('date');
            $trendEnd = max($period->start_date->toDateString(), min($period->end_date->toDateString(), max(now()->toDateString(), $lastDate?->toDateString() ?? '')));
            $endX = 40 + (int) $period->start_date->diffInDays(\Carbon\CarbonImmutable::parse($trendEnd)) / $periodDays * 620;
            $trendPath .= ' L '.round($endX, 2).' '.round(180 - $runningSpent / $trendMaximum * 140, 2);
            $planY = 180 - $totals['planned'] / $trendMaximum * 140;
        @endphp
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="rounded-sm border border-base-300 bg-base-100 p-5 lg:col-span-2" aria-labelledby="budget-trend-title">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 id="budget-trend-title" class="font-semibold">Spending over time</h3><p class="mt-1 text-xs opacity-60">Cumulative recorded expenses across this period</p></div><span class="badge badge-outline">{{ $elapsedPercent }}% of period elapsed</span></div>
                @if($transactions->isNotEmpty())
                <svg viewBox="0 0 700 225" class="mt-5 w-full text-primary" role="img" aria-labelledby="budget-trend-title budget-trend-description">
                    <title id="budget-trend-description">Recorded spending totals {{ $money($totals['spent']) }} against a planned limit of {{ $money($totals['planned']) }}. Steps show spending on each transaction date.</title>
                    <line x1="40" y1="180" x2="660" y2="180" stroke="currentColor" opacity="0.15" />
                    <line x1="40" y1="110" x2="660" y2="110" stroke="currentColor" opacity="0.08" />
                    @if($totals['planned'] > 0)<line x1="40" y1="{{ $planY }}" x2="660" y2="{{ $planY }}" stroke="currentColor" stroke-dasharray="5 5" opacity="0.4" /><text x="660" y="{{ $planY - 8 }}" text-anchor="end" fill="currentColor" font-size="11">Planned limit</text>@endif
                    <path d="{{ $trendPath }} L {{ round($endX, 2) }} 180 Z" fill="currentColor" opacity="0.08" />
                    <path d="{{ $trendPath }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" />
                    <text x="40" y="210" fill="currentColor" font-size="11">{{ $period->start_date->format('d M') }}</text><text x="660" y="210" text-anchor="end" fill="currentColor" font-size="11">{{ $period->end_date->format('d M') }}</text>
                </svg>
                <p class="text-xs opacity-60">{{ $money($totals['spent']) }} spent @if($spendingPercent !== null)&middot; {{ number_format($spendingPercent, 1) }}% of your planned spending @endif</p>
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
