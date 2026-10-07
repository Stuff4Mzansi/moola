<section class="rounded-sm border border-base-300 bg-base-100 p-3" aria-labelledby="dashboard-spending-mix-title" data-dashboard-spending-mix>
    <div class="flex flex-wrap items-baseline justify-between gap-2"><div><h2 id="dashboard-spending-mix-title" class="text-sm font-semibold">Current-period spending mix</h2><p class="text-[10px] opacity-65">Recorded expenses by category; uncategorised spending is included.</p></div><label class="block"><span class="sr-only">Budget for spending mix</span><select class="select select-xs max-w-52" data-mix-budget>@foreach($budgetOverview['cards'] as $card)<option value="{{ $card['budget']->id }}">{{ $card['budget']->name }}</option>@endforeach</select></label></div>
    @if($budgetOverview['cards']->isEmpty())
        <p class="mt-3 text-xs opacity-65">A category chart appears when a budget period is in progress. <a class="link link-primary" href="{{ route('budgets.index') }}">View budgets</a>.</p>
    @else
        <div class="mt-2 grid items-stretch gap-3 sm:grid-cols-[auto_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col items-center justify-center"><div class="size-36" data-mix-chart role="img" aria-label="Recorded spending by category"></div><p class="text-[10px] tabular-nums opacity-65" data-mix-total></p></div>
            <ul class="max-h-40 min-w-0 divide-y divide-base-300/60 overflow-y-auto pr-1 text-[10px]" tabindex="0" aria-label="Current-period spending by category" data-mix-legend></ul>
        </div>
        <p class="mt-2 text-[10px] leading-snug opacity-60" data-mix-empty hidden>No recorded spending for this period yet.</p>
        <script type="application/json" data-mix-data>{!! json_encode($budgetOverview['cards']->map(fn (array $card): array => ['id' => $card['budget']->id, 'name' => $card['budget']->name, 'start' => $card['period']->start_date->toDateString(), 'end' => $card['period']->end_date->toDateString(), 'spent' => $card['totals']['spent'], 'categories' => $card['spendingMix']])->values()->all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    @endif
</section>
