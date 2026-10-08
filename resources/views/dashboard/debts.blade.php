<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-labelledby="dashboard-debts-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-3 py-2">
        <div>
            <h2 id="dashboard-debts-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-credit-card class="size-3.5 text-primary" aria-hidden="true" />Debt progress</h2>
            <p class="mt-0.5 text-[10px] opacity-60">Tracked balances, monthly minimums, and payoff estimates.</p>
        </div>
        <a class="btn btn-xs btn-outline" href="{{ route('debts.index') }}">View debts and payoff plans</a>
    </div>
    @if($debtOverview['rows']->isEmpty())
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-3">
            <p class="text-xs opacity-65">Track balances and minimum payments, then compare ways to pay them off.</p>
            <a class="link link-primary text-xs" href="{{ route('debts.index') }}">Get started</a>
        </div>
    @else
        <dl class="grid grid-cols-1 divide-y divide-base-300 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="min-w-0 px-3 py-2 sm:border-r sm:border-base-300">
                <dt class="text-[10px] opacity-60">Tracked balance</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($debtOverview['total'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 px-3 py-2 sm:border-r sm:border-base-300">
                <dt class="text-[10px] opacity-60">Minimum payments / month</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($debtOverview['minimum'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 px-3 py-2">
                <dt class="text-[10px] opacity-60">Estimated payoff · minimums, avalanche</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $debtOverview['total'] === 0 ? 'Paid off' : ($debtPlan['date'] ?? 'Increase payments') }}</dd>
            </div>
        </dl>
        <div class="border-t border-base-300">
            @if($debtOverview['dueSoon']->isNotEmpty())
                <div class="max-h-40 divide-y divide-base-300 overflow-y-auto" tabindex="0" role="region" aria-label="Upcoming debt minimum payments">
                    @foreach($debtOverview['dueSoon']->take(4) as $row)
                        <a class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-base-200/50" href="{{ route('debts.index', ['tab' => 'payments', 'debt' => $row['debt']->id]) }}">
                            <span class="min-w-0 break-words text-xs font-medium">{{ $row['debt']->name }} <span class="font-normal opacity-60">· due {{ $row['due']->format('d M') }}</span></span>
                            <span class="shrink-0 text-xs font-semibold tabular-nums text-orange-600">{{ $currencyPrefix }}{{ number_format($row['debt']->minimum_payment_cents / 100, 2) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        @if($debtOverview['rows']->where('unrecorded', true)->isNotEmpty())
            <p class="border-t border-base-300 px-3 py-2 text-[11px] text-warning">{{ $debtOverview['rows']->where('unrecorded', true)->count() }} debts have an earlier scheduled date with no payment recorded since. <a class="link" href="{{ route('debts.index', ['tab' => 'payments']) }}">Review payments</a>.</p>
        @endif
        <p class="border-t border-base-300 bg-base-200/40 px-3 py-1.5 text-[10px] leading-snug opacity-60">Your debts only. Balance reflects recorded principal payments; interest forecasts are estimates.</p>
    @endif
</section>
