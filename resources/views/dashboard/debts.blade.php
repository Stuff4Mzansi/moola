<section class="rounded-sm border border-base-300 bg-base-100 p-4" aria-labelledby="dashboard-debts-title">
    <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="dashboard-debts-title" class="text-lg font-semibold">Debt progress</h2><a class="btn btn-outline" href="{{ route('debts.index') }}">View debts and payoff plans</a></div>
    @if($debtOverview['rows']->isEmpty())
        <p class="mt-2 text-sm opacity-65">Track balances and minimum payments, then compare ways to pay them off.</p>
    @else
        <div class="mt-3 grid gap-3 sm:grid-cols-3"><div><p class="text-xs opacity-60">Tracked balance</p><p class="mt-1 text-xl font-semibold">ZAR {{ number_format($debtOverview['total'] / 100, 2) }}</p></div><div><p class="text-xs opacity-60">Minimum payments / month</p><p class="mt-1 text-xl font-semibold">ZAR {{ number_format($debtOverview['minimum'] / 100, 2) }}</p></div><div><p class="text-xs opacity-60">Estimated payoff &middot; minimums, avalanche</p><p class="mt-1 text-xl font-semibold">{{ $debtOverview['total'] === 0 ? 'Paid off' : ($debtPlan['date'] ?? 'Increase payments') }}</p></div></div>
        <div class="mt-3 grid gap-2 md:grid-cols-2">@foreach($debtOverview['dueSoon']->take(4) as $row)<a class="flex justify-between gap-3 rounded-sm bg-base-200/60 p-2 text-xs" href="{{ route('debts.index', ['tab' => 'payments', 'debt' => $row['debt']->id]) }}"><span>{{ $row['debt']->name }} &middot; due {{ $row['due']->format('d M') }}</span><span class="font-semibold">ZAR {{ number_format($row['debt']->minimum_payment_cents / 100, 2) }}</span></a>@endforeach</div>
        @if($debtOverview['rows']->where('unrecorded', true)->isNotEmpty())<p class="mt-2 text-xs text-warning">{{ $debtOverview['rows']->where('unrecorded', true)->count() }} debts have an earlier scheduled date with no payment recorded since. <a class="link" href="{{ route('debts.index', ['tab' => 'payments']) }}">Review payments</a>.</p>@endif
        <p class="mt-2 text-xs opacity-60">Your debts only. Balance reflects recorded principal payments; interest forecasts are estimates.</p>
    @endif
</section>
