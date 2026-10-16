<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-labelledby="dashboard-worth-title">
    <div class="flex flex-wrap items-center justify-between gap-2 px-3 pt-3">
        <div class="min-w-0">
            <h2 id="dashboard-worth-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-chart-no-axes-combined class="size-4 text-primary" aria-hidden="true" />Net worth trend</h2>
            <p class="mt-0.5 text-[10px] opacity-60">Current balances and saved history, in one view.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($netWorthOverview['snapshots']->isNotEmpty())
                <div class="join" role="group" aria-label="Chart view">
                    <button class="btn btn-xs join-item btn-primary" type="button" data-worth-chart-mode="line" data-worth-chart-for="dashboard-net-worth" aria-pressed="true"><x-lucide-chart-line class="size-3.5" aria-hidden="true" /> Line</button>
                    <button class="btn btn-xs join-item btn-ghost" type="button" data-worth-chart-mode="bar" data-worth-chart-for="dashboard-net-worth" aria-pressed="false"><x-lucide-chart-column class="size-3.5" aria-hidden="true" /> Bar</button>
                </div>
            @endif
            <a class="btn btn-xs btn-outline" href="{{ route('net-worth.index') }}">View history</a>
        </div>
    </div>
    @if($netWorthOverview['assets']->isEmpty() && $netWorthOverview['debts']->isEmpty() && $netWorthOverview['liabilities']->isEmpty())
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-3">
            <p class="text-xs opacity-65">Add assets{{ config('features.debt_tracking') ? ' or track debts' : '' }} to see your overall financial position. Savings goals will not be counted twice.</p>
            <a class="link link-primary text-xs" href="{{ route('net-worth.index') }}">Get started</a>
        </div>
    @else
        <dl class="mt-2 grid grid-cols-3 gap-1.5 border-y border-base-300 bg-base-200/30 px-2 py-1.5">
            <div class="min-w-0 rounded-sm border border-primary/20 bg-primary/5 px-2 py-1.5">
                <dt class="flex items-center gap-1 text-[9px] font-medium leading-tight text-primary"><x-lucide-chart-no-axes-combined class="size-3 shrink-0" aria-hidden="true" /><span class="truncate">Net worth</span></dt>
                <dd @class(['mt-0.5 break-words text-[11px] font-semibold leading-tight tabular-nums sm:text-xs', 'text-error' => $netWorthOverview['netWorth'] < 0, 'text-primary' => $netWorthOverview['netWorth'] >= 0])>{{ $currencyPrefix }}{{ number_format($netWorthOverview['netWorth'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 rounded-sm border border-success/20 bg-success/5 px-2 py-1.5">
                <dt class="flex items-center gap-1 text-[9px] font-medium leading-tight text-success"><x-lucide-wallet class="size-3 shrink-0" aria-hidden="true" /><span class="truncate">Assets</span></dt>
                <dd class="mt-0.5 break-words text-[11px] font-semibold leading-tight tabular-nums text-success sm:text-xs">{{ $currencyPrefix }}{{ number_format($netWorthOverview['assetTotal'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 rounded-sm border border-error/20 bg-error/5 px-2 py-1.5">
                <dt class="flex items-center gap-1 text-[9px] font-medium leading-tight text-error"><x-lucide-credit-card class="size-3 shrink-0" aria-hidden="true" /><span class="truncate">{{ config('features.debt_tracking') ? 'Debts + liabilities' : 'Liabilities' }}</span></dt>
                <dd class="mt-0.5 break-words text-[11px] font-semibold leading-tight tabular-nums text-error sm:text-xs">{{ $currencyPrefix }}{{ number_format(($netWorthOverview['debtTotal'] + $netWorthOverview['liabilityTotal']) / 100, 2) }}</dd>
            </div>
        </dl>
        @if($netWorthOverview['snapshots']->isNotEmpty())
            <div class="px-2 pb-2 pt-1 sm:px-3">
                @include('net-worth.chart', ['chartSnapshots' => $netWorthOverview['snapshots']->take(-6), 'chartId' => 'dashboard-net-worth', 'compact' => true])
            </div>
        @else
            <p class="px-3 py-3 text-[11px] opacity-65"><a class="link" href="{{ route('net-worth.index') }}">Save your first snapshot</a> to begin tracking trends over time.</p>
        @endif
        @if($netWorthOverview['stale'] > 0)
            <p class="border-t border-base-300 px-3 py-1.5 text-[10px] text-warning">{{ $netWorthOverview['stale'] }} assets have older valuations. <a class="link" href="{{ route('net-worth.index', ['tab' => 'assets']) }}">Refresh their values</a>.</p>
        @endif
    @endif
</section>
