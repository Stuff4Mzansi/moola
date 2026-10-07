<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100 shadow-sm" aria-labelledby="budget-trends-title" data-budget-trends>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 bg-gradient-to-r from-primary/5 to-base-100 p-3 sm:px-4">
        <div><h2 id="budget-trends-title" class="flex items-center gap-1.5 text-base font-semibold"><x-lucide-chart-no-axes-combined class="size-4 text-primary" /> Budget trends</h2><p class="mt-0.5 text-xs opacity-65">Compare plans and recorded amounts over time.</p></div>
        @if(count($budgetTrends) > 0)
            <div class="flex flex-wrap items-center gap-2" data-trend-controls hidden>
                <label class="block"><span class="sr-only">Budget</span><select class="select select-xs max-w-52" data-trend-budget>@foreach($budgetTrends as $trendBudget)<option value="{{ $trendBudget['id'] }}">{{ $trendBudget['name'] }} ({{ ucfirst($trendBudget['scope']) }})</option>@endforeach</select></label>
                <label class="block"><span class="sr-only">History</span><select class="select select-xs" data-trend-range><option value="3">Last 3 periods</option><option value="6" selected>Last 6 periods</option><option value="12">Last 12 periods</option><option value="all">All periods</option></select></label>
                <div class="join" role="group" aria-label="Amounts to compare"><button class="btn btn-xs join-item btn-primary" type="button" data-trend-comparison="budget" aria-pressed="true">Budget</button><button class="btn btn-xs join-item btn-ghost" type="button" data-trend-comparison="income" aria-pressed="false">Income</button></div>
                <div class="join" role="group" aria-label="Chart view"><button class="btn btn-xs join-item btn-primary" type="button" data-trend-mode="line" aria-pressed="true"><x-lucide-chart-line class="size-3.5" /> Line</button><button class="btn btn-xs join-item btn-ghost" type="button" data-trend-mode="bar" aria-pressed="false"><x-lucide-chart-column class="size-3.5" /> Bar</button></div>
            </div>
        @endif
    </div>
    <div class="p-3 sm:px-4">
        <div data-trend-content hidden>
            <div class="mb-2 grid grid-cols-3 divide-x divide-base-300 border-b border-base-300 pb-2" aria-live="polite">
                <div class="min-w-0 px-2 first:pl-0"><p class="text-[11px] opacity-60" data-trend-reference-label>Budgeted</p><p class="mt-0.5 break-words text-sm font-semibold sm:text-base" data-trend-planned></p></div>
                <div class="min-w-0 px-2 first:pl-0"><p class="text-[11px] opacity-60">Recorded spending</p><p class="mt-0.5 break-words text-sm font-semibold sm:text-base" data-trend-spent></p></div>
                <div class="min-w-0 pl-2"><p class="text-[11px] opacity-60" data-trend-variance-label></p><p class="mt-0.5 break-words text-sm font-semibold sm:text-base" data-trend-variance></p><p class="mt-0.5 text-[10px] leading-tight opacity-60" data-trend-variance-detail></p></div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]"><p class="opacity-65" data-trend-window></p><div class="flex flex-wrap gap-3"><span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 bg-primary"></span><span data-trend-reference-legend>Budgeted</span></span><span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 bg-info"></span>Spent</span><span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-sm bg-error"></span><span data-trend-over-legend>Over budget</span></span></div></div>
            <div class="mt-2 overflow-x-auto rounded-sm bg-base-200/30" data-trend-chart></div>
            <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p class="text-[11px] opacity-60">Recorded expenses only. In-progress periods are not final underspending.</p>
                <details class="w-full"><summary class="cursor-pointer text-[11px] font-medium opacity-70">Insights and period details</summary>
                    <p class="mt-2 text-xs opacity-60">Each point represents one budget period with its original dates and category allocations. Unpaid forecasts are excluded.</p>
                    <div class="mt-2 rounded-sm border border-base-300 p-2 text-xs" data-trend-insight aria-live="polite"></div>
                    <div class="mt-2 overflow-x-auto"><table class="table table-xs"><caption class="sr-only" data-trend-caption>Budgeted and recorded spending by budget period</caption><thead><tr><th>Period</th><th>Dates</th><th data-trend-table-reference>Budgeted</th><th>Spent</th><th data-trend-difference-heading>Difference</th></tr></thead><tbody data-trend-table></tbody></table></div></details>
            </div>
        </div>
        <div data-trend-empty class="rounded-sm border border-dashed border-base-300 p-3 text-center"><x-lucide-chart-line class="mx-auto size-5 text-primary/60" /><h3 class="mt-2 text-sm font-semibold">{{ count($budgetTrends) === 0 ? 'Your budget story starts here' : 'No started periods to compare' }}</h3><p class="mt-1 text-xs opacity-65">{{ count($budgetTrends) === 0 ? 'Create a budget to compare your plan with recorded spending over time.' : 'Your chart will appear when a budget period begins.' }}</p><a class="btn btn-xs btn-outline mt-2" href="{{ route('budgets.index') }}">Open budgets</a></div>
        <noscript><p class="mt-3 text-sm opacity-65">Enable JavaScript to explore the trend chart. You can view each period in <a class="link link-primary" href="{{ route('budgets.index') }}">Budgets</a>.</p></noscript>
    </div>
    <script type="application/json" data-trend-data>{!! json_encode($budgetTrends, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</section>
