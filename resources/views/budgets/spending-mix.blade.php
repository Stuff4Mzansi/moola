<div data-budget-live="spending-mix">
    @php
        $spendingMix = $categoryRows->filter(fn (array $row): bool => $row['spent'] > 0)
            ->map(fn (array $row): array => ['name' => $row['category']->name, 'amount' => $row['spent']])->values();
        $uncategorised = $transactions->whereNull('budget_category_id')->sum('amount_cents');
        if ($uncategorised > 0) {
            $spendingMix->push(['name' => 'Uncategorised', 'amount' => $uncategorised]);
        }
    @endphp
    <section class="rounded-sm border border-base-300 bg-base-100 p-3" aria-labelledby="budget-spending-mix-title" data-budget-spending-mix x-data="spendingMixChart">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div><h3 id="budget-spending-mix-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-chart-pie class="size-3.5 text-primary" aria-hidden="true" />Spending mix</h3><p class="text-[10px] opacity-60">Recorded expenses by category, including uncategorised.</p></div>
            <p class="text-[10px] opacity-60">{{ $period->start_date->format('j M') }}–{{ $period->end_date->format('j M Y') }}</p>
        </div>
        <div class="mt-2 grid items-stretch gap-3 sm:grid-cols-[auto_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col items-center justify-center"><div class="size-36" data-mix-chart role="img" aria-label="Recorded spending by category"></div></div>
            <ul class="max-h-40 min-w-0 divide-y divide-base-300/60 overflow-y-auto pr-1 text-[10px]" tabindex="0" aria-label="Budget-period spending by category" data-mix-legend></ul>
        </div>
        <p class="mt-2 text-[10px] leading-snug opacity-60" data-mix-empty hidden>No recorded spending for this period yet.</p>
        <script type="application/json" data-mix-data>{!! json_encode([['id' => $budget->id, 'name' => $budget->name, 'start' => $period->start_date->toDateString(), 'end' => $period->end_date->toDateString(), 'spent' => $totals['spent'], 'categories' => $spendingMix->all()]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    </section>
</div>
