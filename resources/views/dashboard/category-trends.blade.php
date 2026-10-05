<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100 shadow-sm" aria-labelledby="category-trends-title" data-category-trends>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 bg-gradient-to-r from-primary/5 to-base-100 p-4">
        <div><h2 id="category-trends-title" class="text-base font-semibold">Category spending trends</h2><p class="mt-1 text-xs opacity-65">Spot changing costs and allocations that need another look.</p></div>
        @if(count($budgetTrends) > 0)
            <div class="flex flex-wrap gap-2" data-category-controls hidden>
                <label><span class="sr-only">Category trends budget</span><select class="select select-xs max-w-52" data-category-budget>@foreach($budgetTrends as $trendBudget)<option value="{{ $trendBudget['id'] }}">{{ $trendBudget['name'] }} ({{ ucfirst($trendBudget['scope']) }})</option>@endforeach</select></label>
                <label><span class="sr-only">Category history</span><select class="select select-xs" data-category-range><option value="3">Last 3 completed periods</option><option value="6" selected>Last 6 completed periods</option><option value="12">Last 12 completed periods</option><option value="all">All completed periods</option></select></label>
            </div>
        @endif
    </div>
    <div class="space-y-4 p-4">
        <div data-category-empty class="rounded-sm border border-dashed border-base-300 p-5 text-center"><p class="text-sm font-medium">Your category history starts with a completed period</p><p class="mt-1 text-xs opacity-65">Record expenses and finish a budget period to explore its categories here.</p></div>
        <div data-category-content hidden>
            <p class="text-xs opacity-65" data-category-window></p>
            <div class="mt-4 grid gap-3 md:grid-cols-3" data-category-insights aria-live="polite"></div>
            <div class="mt-4 rounded-sm border border-base-300 bg-base-200/30 p-3">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-sm font-semibold">Spending per day</h3><p class="mt-1 text-xs opacity-65">Daily averages make periods of different lengths comparable.</p></div><label><span class="sr-only">Chart category</span><select class="select select-sm max-w-64" data-category-select></select></label></div>
                <div class="mt-3 overflow-x-auto" data-category-chart></div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs"><span class="flex items-center gap-2"><span class="h-0.5 w-5 bg-primary"></span>Recorded / day</span><span class="flex items-center gap-2"><span class="h-0.5 w-5 bg-base-content/40"></span>Allocated / day</span><span class="flex items-center gap-2"><span class="size-2 rounded-sm bg-error"></span>Over allocation</span></div>
            </div>
            <div class="mt-4 overflow-x-auto"><table class="table table-sm"><caption class="mb-2 text-left text-xs opacity-65">Latest completed period compared with the previous completed period</caption><thead><tr><th>Category</th><th>Recorded</th><th>Change / day</th><th>Share of received income</th><th>Periods over allocation</th></tr></thead><tbody data-category-table></tbody></table></div>
            <p class="mt-3 text-xs opacity-65">Recorded expenses only; current and future periods are excluded. Categories match by exact name, so renamed or missing categories are shown as gaps. An ended period can still have incomplete records. Income shares need received income greater than zero. Over-allocation counts use periods with a positive allocation.</p>
        </div>
        <noscript><p class="text-sm opacity-65">Enable JavaScript to explore category trends. Period totals remain available in <a class="link link-primary" href="{{ route('budgets.index') }}">Budgets</a>.</p></noscript>
    </div>
    <script type="application/json" data-category-data>{!! json_encode($budgetTrends, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</section>
