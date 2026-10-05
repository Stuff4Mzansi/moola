@php
    $comparisonMaximum = max(1, $totals['expected'], $totals['received'], $totals['planned'], $totals['spent']);
    $spendingParts = [
        ['label' => 'Other spending', 'amount' => max(0, $totals['spent'] - $periodReview['principal'] - $periodReview['debtInterest'] - $periodReview['savings']), 'colour' => 'text-primary', 'background' => 'bg-primary'],
        ['label' => 'Debt principal reduced', 'amount' => $periodReview['principal'], 'colour' => 'text-info', 'background' => 'bg-info'],
        ['label' => 'Debt interest paid', 'amount' => $periodReview['debtInterest'], 'colour' => 'text-warning', 'background' => 'bg-warning'],
        ['label' => 'Savings contributed', 'amount' => $periodReview['savings'], 'colour' => 'text-success', 'background' => 'bg-success'],
    ];
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
        <h4 id="review-spending-title" class="font-semibold">Where recorded spending went</h4>
        <p class="mt-1 text-xs opacity-65">Every rand appears once across these four parts.</p>
        <div class="mt-4 flex flex-col items-center gap-5 sm:flex-row">
            <div class="relative size-44 shrink-0">
                <svg viewBox="0 0 120 120" class="size-full" role="img" aria-labelledby="review-spending-title review-spending-description">
                    <circle cx="60" cy="60" r="44" fill="none" stroke="currentColor" stroke-width="13" class="text-base-300/60" />
                    @foreach($spendingParts as $part)
                        @if($totals['spent'] > 0 && $part['amount'] > 0)
                            @php $share = $part['amount'] / $totals['spent'] * 100; @endphp
                            <circle cx="60" cy="60" r="44" fill="none" stroke="currentColor" stroke-width="13" pathLength="100" stroke-dasharray="{{ $share }} {{ 100 - $share }}" stroke-dashoffset="{{ -$spendingOffset }}" transform="rotate(-90 60 60)" class="{{ $part['colour'] }}"><title>{{ $part['label'] }}: {{ $money($part['amount']) }} ({{ number_format($share, 1) }}%)</title></circle>
                            @php $spendingOffset += $share; @endphp
                        @endif
                    @endforeach
                </svg>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-8 text-center" aria-hidden="true"><p class="text-xs opacity-65">Recorded</p><p class="mt-1 text-base font-bold tabular-nums">{{ number_format($totals['spent'] / 100, 2) }}</p><p class="text-xs opacity-65">ZAR</p></div>
            </div>
            <ul id="review-spending-description" class="w-full min-w-0 space-y-3">
                @foreach($spendingParts as $part)
                    <li class="flex items-start justify-between gap-3 text-sm"><span class="flex min-w-0 items-start gap-2"><span class="mt-1 size-2.5 shrink-0 rounded-sm {{ $part['background'] }}" aria-hidden="true"></span><span>{{ $part['label'] }}</span></span><span class="shrink-0 text-right"><span class="font-semibold tabular-nums">{{ $money($part['amount']) }}</span><span class="block text-xs opacity-65">{{ $totals['spent'] > 0 ? number_format($part['amount'] / $totals['spent'] * 100, 1).'%' : 'No spending yet' }}</span></span></li>
                @endforeach
            </ul>
        </div>
        @if($totals['spent'] === 0)<p class="mt-4 text-sm opacity-65">Record expenses to see your spending breakdown.</p>@else<p class="mt-4 text-xs opacity-65">Debt interest is shown separately from principal. Other spending includes the remaining recorded expenses.</p>@endif
    </section>
</div>
