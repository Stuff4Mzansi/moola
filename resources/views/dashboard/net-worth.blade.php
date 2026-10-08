<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-labelledby="dashboard-worth-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-3 py-2">
        <div>
            <h2 id="dashboard-worth-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-chart-no-axes-combined class="size-3.5 text-primary" aria-hidden="true" />Net worth</h2>
            <p class="mt-0.5 text-[10px] opacity-60">Assets, debts, and available cash at a glance.</p>
        </div>
        <a class="btn btn-xs btn-outline" href="{{ route('net-worth.index') }}">View assets and history</a>
    </div>
    @if($netWorthOverview['assets']->isEmpty() && $netWorthOverview['debts']->isEmpty() && $netWorthOverview['liabilities']->isEmpty())
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-3">
            <p class="text-xs opacity-65">Add assets{{ config('features.debt_tracking') ? ' or track debts' : '' }} to see your overall financial position. Savings goals will not be counted twice.</p>
            <a class="link link-primary text-xs" href="{{ route('net-worth.index') }}">Get started</a>
        </div>
    @else
        <dl class="grid grid-cols-1 divide-y divide-base-300 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            @foreach(['Net worth' => $netWorthOverview['netWorth'], 'Assets' => $netWorthOverview['assetTotal'], config('features.debt_tracking') ? 'Debt + liabilities' : 'Liabilities' => $netWorthOverview['debtTotal'] + $netWorthOverview['liabilityTotal']] as $label => $value)
                <div class="min-w-0 px-3 py-2 sm:border-r sm:border-base-300 last:sm:border-r-0">
                    <dt class="text-[10px] opacity-60">{{ $label }}</dt>
                    <dd @class(['mt-0.5 break-words text-sm font-semibold tabular-nums', 'text-error' => $label === 'Net worth' && $value < 0])>{{ $currencyPrefix }}{{ number_format($value / 100, 2) }}</dd>
                </div>
            @endforeach
        </dl>
        @if($netWorthOverview['change'] !== null)
            <p class="border-t border-base-300 px-3 py-1.5 text-[10px] opacity-65">{{ $currencyPrefix }}{{ number_format(abs($netWorthOverview['change']) / 100, 2) }} {{ $netWorthOverview['change'] >= 0 ? 'higher' : 'lower' }} than {{ $netWorthOverview['latest']->date->format('d M Y') }} snapshot.</p>
        @endif
        @if($netWorthOverview['snapshots']->isNotEmpty())
            <div class="border-t border-base-300 p-2.5">
                @include('net-worth.chart', ['chartSnapshots' => $netWorthOverview['snapshots']->take(-6), 'chartId' => 'dashboard-net-worth', 'compact' => true])
            </div>
        @else
            <p class="border-t border-base-300 px-3 py-2 text-[11px] opacity-65"><a class="link" href="{{ route('net-worth.index') }}">Save your first snapshot</a> to begin tracking net worth over time.</p>
        @endif
        @if($netWorthOverview['stale'] > 0)
            <p class="border-t border-base-300 px-3 py-2 text-[11px] text-warning">{{ $netWorthOverview['stale'] }} assets have older valuations. <a class="link" href="{{ route('net-worth.index', ['tab' => 'assets']) }}">Refresh their values</a>.</p>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 bg-base-200/40 px-3 py-2">
            <div class="min-w-0 text-[11px]">
                <p><span class="opacity-60">Unreserved cash:</span> <span class="font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($liquidityOverview['free'] / 100, 2) }}</span> <span class="mx-1 opacity-40">·</span> <span class="opacity-60">Emergency runway:</span> <span class="font-semibold">{{ $liquidityOverview['runway'] === null ? 'Set up reserves and essentials' : $liquidityOverview['runway'].' months' }}</span></p>
                @if($liquidityOverview['unknown'] > 0)
                    <p class="mt-1 text-[10px] text-warning">Classify access for {{ $liquidityOverview['unknown'] }} assets to complete the cash picture.</p>
                @elseif($liquidityOverview['cashGap'] > 0)
                    <p class="mt-1 text-[10px] text-warning">Forecast cash gap: {{ $currencyPrefix }}{{ number_format($liquidityOverview['cashGap'] / 100, 2) }} on {{ \Carbon\CarbonImmutable::parse($liquidityOverview['lowest']['date'])->format('d M') }}. Check assumptions and upcoming payments.</p>
                @endif
            </div>
            <a class="btn btn-xs btn-outline shrink-0" href="{{ route('net-worth.index', ['tab' => 'liquidity']) }}">Plan cash flow</a>
        </div>
        <p class="border-t border-base-300 px-3 py-1.5 text-[10px] leading-snug opacity-60">Latest recorded asset values minus {{ config('features.debt_tracking') ? 'tracked debt and liabilities' : 'manual liabilities' }}. Your records only.</p>
    @endif
</section>
