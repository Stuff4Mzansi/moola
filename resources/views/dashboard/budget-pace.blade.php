<section class="mt-3 border-t border-base-300/70 pt-2" aria-labelledby="dashboard-budget-pace-title" data-dashboard-budget-pace x-data="dashboardPaceChart">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h4 id="dashboard-budget-pace-title" class="flex items-center gap-1.5 text-xs font-semibold"><x-lucide-chart-line class="size-3.5 text-primary" aria-hidden="true" />Spending pace</h4>
        <a class="link link-primary text-[10px]" data-pace-link>Review period</a>
    </div>
    <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[9px] opacity-75">
        <span class="inline-flex items-center gap-1"><span class="h-0.5 w-4 bg-orange-600" aria-hidden="true"></span>Recorded spending</span>
        <span class="inline-flex items-center gap-1"><span class="h-0.5 w-4 border-t border-dashed border-primary" aria-hidden="true"></span>Budget pace</span>
        <span class="inline-flex items-center gap-1"><span class="h-0.5 w-4 border-t border-dotted border-success" aria-hidden="true"></span>Income received</span>
    </div>
    <div class="mt-1 w-full" role="region" aria-label="Selected-period spending pace chart" tabindex="0" data-pace-chart></div>
    <p class="text-[9px] tabular-nums opacity-60" data-pace-summary></p>
    <script type="application/json" data-pace-data>{!! json_encode($dashboardBudgetOverview['cards']->map(fn (array $card): array => ['id' => $card['budget']->id, 'name' => $card['budget']->name, 'start' => $card['period']->start_date->toDateString(), 'end' => $card['period']->end_date->toDateString(), 'planned' => $card['totals']['planned'], 'received' => $card['totals']['received'], 'spent' => $card['totals']['spent'], 'points' => $card['dailyPace'], 'url' => $card['url']])->values()->all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</section>
