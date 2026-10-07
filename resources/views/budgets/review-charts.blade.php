@php
    $comparisonMaximum = max(1, $totals['expected'], $totals['received'], $totals['planned'], $totals['spent']);
    $spendingChartColours = ['#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9', '#8b5cf6', '#84cc16', '#f97316'];
    $spendingOffset = 0;
@endphp
<div class="mt-5 grid gap-4 xl:grid-cols-2">
    <section class="rounded-sm border border-base-300 bg-base-200/30 p-4 sm:p-5" aria-labelledby="review-comparison-title">
        <div class="flex flex-wrap items-center justify-between gap-2"><h4 id="review-comparison-title" class="font-semibold">Your plan and what happened</h4><span class="badge badge-ghost text-xs">Same scale for all bars</span></div>
        <div class="mt-5 space-y-6">
            <div>
                <div class="flex flex-wrap items-baseline justify-between gap-2"><p class="text-sm font-medium">Income received</p><p class="text-lg font-semibold">{{ $money($totals['received']) }}</p></div>
                <div class="mt-3 space-y-2" role="img" aria-label="Expected income {{ $money($totals['expected']) }}; received income {{ $money($totals['received']) }}. All comparison bars use the same scale.">
                    <div class="grid grid-cols-[4.5rem_1fr] items-center gap-2"><span class="text-xs opacity-65">Expected</span><div class="h-3 overflow-hidden rounded-sm bg-base-300/40"><div class="h-full rounded-sm bg-base-content/25" style="width: {{ $totals['expected'] / $comparisonMaximum * 100 }}%"></div></div></div>
                    <div class="grid grid-cols-[4.5rem_1fr] items-center gap-2"><span class="text-xs opacity-65">Received</span><div class="h-3 overflow-hidden rounded-sm bg-base-300/40"><div class="h-full rounded-sm bg-success" style="width: {{ $totals['received'] / $comparisonMaximum * 100 }}%"></div></div></div>
                </div>
                <p class="mt-2 text-xs opacity-65">{{ $money($totals['expected']) }} expected @if($totals['expected'] > 0)&middot; {{ number_format($totals['received'] / $totals['expected'] * 100, 1) }}% received @else &middot; No expected income entered @endif</p>
            </div>
            <div class="border-t border-base-300 pt-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2"><p class="text-sm font-medium">Recorded spending</p><p @class(['text-lg font-semibold', 'text-error' => $periodReview['variance'] < 0])>{{ $money($totals['spent']) }}</p></div>
                <div class="mt-3 space-y-2" role="img" aria-label="Allocated spending {{ $money($totals['planned']) }}; recorded spending {{ $money($totals['spent']) }}. All comparison bars use the same scale.">
                    <div class="grid grid-cols-[4.5rem_1fr] items-center gap-2"><span class="text-xs opacity-65">Allocated</span><div class="h-3 overflow-hidden rounded-sm bg-base-300/40"><div class="h-full rounded-sm bg-base-content/25" style="width: {{ $totals['planned'] / $comparisonMaximum * 100 }}%"></div></div></div>
                    <div class="grid grid-cols-[4.5rem_1fr] items-center gap-2"><span class="text-xs opacity-65">Spent</span><div class="h-3 overflow-hidden rounded-sm bg-base-300/40"><div @class(['h-full rounded-sm', 'bg-error' => $periodReview['variance'] < 0, 'bg-primary' => $periodReview['variance'] >= 0]) style="width: {{ $totals['spent'] / $comparisonMaximum * 100 }}%"></div></div></div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2"><span @class(['badge badge-soft', 'badge-error' => $periodReview['variance'] < 0, 'badge-ghost' => $periodReview['variance'] >= 0])>{{ $money(abs($periodReview['variance'])) }} {{ $periodReview['variance'] < 0 ? 'over plan' : ($periodReview['variance'] === 0 ? 'difference from plan' : 'under plan') }}</span><span class="text-xs opacity-65">{{ $money($totals['planned']) }} allocated</span></div>
                @if($totals['planned'] === 0)<p class="mt-2 text-xs opacity-65">No category allocations entered. Set a plan to compare your spending.</p>@endif
            </div>
        </div>
    </section>
    <section class="rounded-sm border border-base-300 bg-base-200/30 p-4 sm:p-5" aria-labelledby="review-spending-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2"><h4 id="review-spending-title" class="font-semibold">Recorded spending by category</h4><span class="text-xs tabular-nums opacity-65">{{ $money($totals['spent']) }} total</span></div>
        <p class="mt-1 text-xs opacity-65">Each slice and its matching label show a category's share of spending recorded in this budget period.</p>
        @if($periodReview['spendingCategories'])
            <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                <div class="relative size-40 shrink-0">
                    <svg viewBox="0 0 120 120" class="size-full" role="img" aria-labelledby="review-spending-title review-spending-description">
                        <circle cx="60" cy="60" r="44" fill="none" stroke="currentColor" stroke-width="16" class="text-base-300/60" />
                    @foreach($periodReview['spendingCategories'] as $index => $category)
                        @php
                            $share = $totals['spent'] > 0 ? $category['amount'] / $totals['spent'] * 100 : 0;
                            $colour = $spendingChartColours[$index % count($spendingChartColours)];
                        @endphp
                        <circle data-spending-category-slice data-spending-category-label="{{ $category['name'] }}" cx="60" cy="60" r="44" fill="none" stroke="{{ $colour }}" stroke-width="16" pathLength="100" stroke-dasharray="{{ $share }} {{ 100 - $share }}" stroke-dashoffset="{{ -$spendingOffset }}" transform="rotate(-90 60 60)" aria-label="{{ $category['name'] }}: {{ $money($category['amount']) }}, {{ number_format($share, 1) }}%"><title>{{ $category['name'] }}: {{ $money($category['amount']) }} ({{ number_format($share, 1) }}%)</title></circle>
                        @php $spendingOffset += $share; @endphp
                    @endforeach
                    </svg>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-7 text-center" aria-hidden="true"><p class="text-[11px] opacity-65">Recorded</p><p class="mt-1 break-words text-xs font-bold tabular-nums">{{ $money($totals['spent']) }}</p></div>
                </div>
                <p id="review-spending-description" class="sr-only">Pie chart of recorded spending by category. The adjacent legend labels every slice with its amount and percentage.</p>
                <ul class="max-h-64 w-full min-w-0 divide-y divide-base-300/60 overflow-y-auto pr-1" tabindex="0" aria-label="Recorded spending by budget category">
                    @foreach($periodReview['spendingCategories'] as $index => $category)
                        @php $share = $totals['spent'] > 0 ? $category['amount'] / $totals['spent'] * 100 : 0; @endphp
                        <li data-spending-category-legend="{{ $category['name'] }}" class="flex items-start justify-between gap-2 py-1 text-[11px]">
                            <span class="flex min-w-0 items-start gap-1.5"><span class="mt-0.5 size-2.5 shrink-0 rounded-sm" style="background-color: {{ $spendingChartColours[$index % count($spendingChartColours)] }}" aria-hidden="true"></span><span class="break-words">{{ $category['name'] }}</span></span>
                            <span class="shrink-0 text-right tabular-nums"><span class="font-semibold">{{ $money($category['amount']) }}</span><span class="ml-1.5 text-[10px] opacity-65">{{ number_format($share, 1) }}%</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="mt-4 text-sm opacity-65">Record expenses in this budget period to see spending by category.</p>
        @endif
    </section>
</div>
