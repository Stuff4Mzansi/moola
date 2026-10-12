@if($dashboardBudget)
    @php
        $categoryPalette = [
            '#6366f1', // Indigo
            '#0284c7', // Sky
            '#059669', // Emerald
            '#d97706', // Amber
            '#e11d48', // Rose
            '#8b5cf6', // Violet
            '#ea580c', // Orange
            '#0d9488', // Teal
            '#2563eb', // Blue
            '#c026d3', // Fuchsia
            '#0891b2', // Cyan
            '#db2777', // Pink
        ];

        $monthlyBudgetChoices = $budgetOverview['cards']->map(function (array $card) use ($money, $categoryPalette): array {
            $totals = $card['totals'];
            $budgetLeft = $totals['planned'] - $totals['spent'];
            $expectedVariance = $totals['planned'] - $card['forecastSpent'];
            $isCalendarMonth = $card['period']->start_date->isSameDay(now()->startOfMonth())
                && $card['period']->end_date->isSameDay(now()->endOfMonth());

            $topSpentMax = (int) $card['topSpentRows']->max('amount');
            $usedColors = [];
            $getCategoryColor = function (string $name) use ($categoryPalette, &$usedColors): string {
                $paletteCount = count($categoryPalette);
                $start = abs(crc32($name)) % $paletteCount;
                for ($i = 0; $i < $paletteCount; $i++) {
                    $candidate = $categoryPalette[($start + $i) % $paletteCount];
                    if (! in_array($candidate, $usedColors, true)) {
                        $usedColors[] = $candidate;

                        return $candidate;
                    }
                }

                return $categoryPalette[$start];
            };

            return [
                'id' => (string) $card['period']->id,
                'title' => $isCalendarMonth ? 'Budget for this month' : 'Current budget period',
                'periodLabel' => $card['budget']->name.' · '.$card['period']->start_date->format('j M').'–'.$card['period']->end_date->format('j M Y'),
                'spentLabel' => $money($totals['spent']),
                'hasPlan' => $totals['planned'] > 0,
                'expectedLabel' => $expectedVariance >= 0 ? 'Underspend '.$money($expectedVariance) : 'Overspend '.$money(abs($expectedVariance)),
                'underPlan' => $budgetLeft >= 0,
                'plannedLabel' => 'Planned spending '.$money($totals['planned']),
                'leftLabel' => $budgetLeft >= 0 ? $money($budgetLeft).' left' : $money(abs($budgetLeft)).' over plan',
                'progress' => $totals['planned'] > 0 ? min(100, $totals['spent'] / $totals['planned'] * 100) : 0,
                'topSpentRows' => $card['topSpentRows']->map(fn (array $row): array => [
                    'name' => $row['name'],
                    'amount' => $money($row['amount']),
                    'percent' => $topSpentMax > 0 ? round($row['amount'] / $topSpentMax * 100, 1) : 0,
                    'color' => $getCategoryColor($row['name']),
                ])->all(),
                'url' => $card['url'],
            ];
        })->values();
        $budgetTotals = $dashboardBudget['totals'];
        $budgetLeft = $budgetTotals['planned'] - $budgetTotals['spent'];
        $expectedVariance = $budgetTotals['planned'] - $dashboardBudget['forecastSpent'];
        $progress = $budgetTotals['planned'] > 0 ? min(100, $budgetTotals['spent'] / $budgetTotals['planned'] * 100) : 0;
        $isCalendarMonth = $dashboardBudget['period']->start_date->isSameDay(now()->startOfMonth())
            && $dashboardBudget['period']->end_date->isSameDay(now()->endOfMonth());
    @endphp
    <section class="overflow-hidden rounded-sm border border-base-300 bg-base-100 p-3 sm:p-4" aria-labelledby="dashboard-month-budget-title" x-data="dashboardMonthlyBudget">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 id="dashboard-month-budget-title" class="flex items-center gap-2 text-base font-semibold"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><x-lucide-wallet class="size-4" aria-hidden="true" /></span><span x-text="selectedBudget.title">{{ $isCalendarMonth ? 'Budget for this month' : 'Current budget period' }}</span></h2>
                <p class="mt-0.5 break-words text-[11px] opacity-60" x-text="selectedBudget.periodLabel">{{ $dashboardBudget['budget']->name }} · {{ $dashboardBudget['period']->start_date->format('j M') }}–{{ $dashboardBudget['period']->end_date->format('j M Y') }}</p>
            </div>
            @if($budgetOverview['cards']->count() > 1)
                <form class="flex max-w-full items-center gap-1.5" method="GET" action="{{ route('dashboard') }}">
                    <label class="sr-only" for="dashboard-budget-period">Choose budget period</label>
                    <select class="select select-xs max-w-48" id="dashboard-budget-period" name="budget" x-model="budgetId" x-on:change="selectBudget()">
                        @foreach($budgetOverview['cards'] as $card)
                            <option value="{{ $card['period']->id }}" @selected($card['period']->id === $dashboardBudget['period']->id)>{{ $card['budget']->name }} · {{ $card['budget']->scope === 'household' ? 'Shared' : 'Personal' }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-ghost btn-xs" type="submit">Show</button></noscript>
                </form>
            @endif
        </header>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
            <p class="min-w-0 text-xs"><strong class="text-lg font-semibold tabular-nums" x-text="selectedBudget.spentLabel">{{ $money($budgetTotals['spent']) }}</strong> <span class="opacity-65">spent</span></p>
            <p class="text-right text-xs" x-bind:hidden="!selectedBudget.hasPlan"><span class="block text-[10px] opacity-60" x-text="'Expected ' + (selectedBudget.title === 'Budget for this month' ? 'EoM' : 'period end')">Expected {{ $isCalendarMonth ? 'EoM' : 'period end' }}</span><strong class="text-sm font-semibold tabular-nums" x-bind:class="selectedBudget.underPlan ? 'text-success' : 'text-error'" x-text="selectedBudget.expectedLabel">{{ $expectedVariance >= 0 ? 'Underspend ' : 'Overspend ' }}{{ $money(abs($expectedVariance)) }}</strong></p>
        </div>
        <div class="mt-2 flex items-baseline justify-between gap-3 text-xs">
            <span class="text-[10px] opacity-60" x-text="selectedBudget.plannedLabel">Planned spending {{ $money($budgetTotals['planned']) }}</span>
            <span class="shrink-0 text-right tabular-nums" x-text="selectedBudget.leftLabel">{{ $budgetLeft >= 0 ? $money($budgetLeft).' left' : $money(abs($budgetLeft)).' over plan' }}</span>
        </div>
        <progress class="progress mt-2 h-2 w-full" x-bind:class="selectedBudget.underPlan ? 'progress-success' : 'progress-error'" x-bind:value="selectedBudget.progress" max="100" aria-label="Recorded spending as a share of planned spending" value="{{ $progress }}"></progress>
        <p class="mt-1 text-[10px] opacity-55" x-text="selectedBudget.hasPlan ? 'End-of-period estimate follows recorded spending pace and excludes unpaid scheduled expenses.' : 'Add a spending plan to see what may be left at period end.'">@if($budgetTotals['planned'] === 0)Add a spending plan to see what may be left at period end.@else End-of-period estimate follows recorded spending pace and excludes unpaid scheduled expenses.@endif</p>
        <h3 class="mt-3 flex items-center gap-1.5 text-xs font-medium opacity-80"><x-lucide-chart-column class="size-3.5 text-primary" aria-hidden="true" />Biggest spending categories</h3>
        <template x-if="selectedBudget.topSpentRows.length">
            <ul class="mt-2 flex items-end justify-between gap-1.5 rounded-sm bg-base-200/70 px-2 pt-2 pb-1.5" aria-label="Biggest spending categories, largest to smallest">
                <template x-for="row in selectedBudget.topSpentRows" :key="row.name">
                    <li class="flex min-w-0 flex-1 flex-col items-center gap-1 text-center" x-bind:title="row.name + ': ' + row.amount">
                        <span class="text-[9px] font-semibold tabular-nums opacity-70" x-text="row.amount"></span>
                        <div class="flex h-24 w-full items-end justify-center">
                            <div class="w-full max-w-6 rounded-t-sm transition-all duration-300" x-bind:style="'height: ' + Math.max(row.percent, 4) + '%; background-color: ' + row.color"></div>
                        </div>
                        <span class="w-full truncate text-[9px] opacity-60" x-text="row.name"></span>
                    </li>
                </template>
            </ul>
        </template>
        <p class="mt-1.5 rounded-sm bg-base-200/70 px-3 py-2 text-[11px] opacity-65" x-bind:hidden="selectedBudget.topSpentRows.length > 0">No recorded spending in this period yet.</p>
        <a class="btn btn-primary mt-2 min-h-10 w-full gap-1.5 text-sm font-semibold shadow-sm" x-bind:href="selectedBudget.url" href="{{ $dashboardBudget['url'] }}">View budget <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
        <script type="application/json" data-budget-choices>{!! json_encode($monthlyBudgetChoices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    </section>
@else
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-base-300 bg-base-100 p-3" aria-labelledby="dashboard-month-budget-title">
        <div><h2 id="dashboard-month-budget-title" class="text-sm font-semibold">Budget for this month</h2><p class="mt-0.5 text-[11px] opacity-65">Start a budget to track spending and see an end-of-period estimate.</p></div>
        <a class="btn btn-primary btn-sm" href="{{ route('budgets.index') }}">Create a budget</a>
    </section>
@endif
