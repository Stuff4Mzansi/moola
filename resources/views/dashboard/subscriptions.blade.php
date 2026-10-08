<section class="space-y-3" aria-labelledby="dashboard-subscriptions-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 id="dashboard-subscriptions-title" class="flex items-center gap-1.5 text-sm font-semibold">
                <x-lucide-repeat class="size-3.5 text-primary" aria-hidden="true" />
                Subscription overview
            </h2>
            <p class="mt-0.5 text-[11px] opacity-60">Recurring costs and renewals coming up.</p>
        </div>
        <a class="btn btn-xs btn-outline" href="{{ route('subscriptions.index') }}">
            View subscriptions
            <x-lucide-arrow-up-right class="size-3.5" aria-hidden="true" />
        </a>
    </div>

    <section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-label="Subscription summary">
        <dl class="grid grid-cols-2 md:grid-cols-4">
            <div class="min-w-0 border-b border-r border-base-300 p-2.5 md:border-b-0">
                <dt class="flex items-center gap-1.5 text-[10px] font-medium opacity-65"><x-lucide-repeat class="size-3 text-primary" aria-hidden="true" />Active</dt>
                <dd class="mt-0.5 text-base font-semibold tabular-nums">{{ $activeCount }}</dd>
            </div>
            <div class="min-w-0 border-b border-base-300 p-2.5 md:border-b-0 md:border-r">
                <dt class="flex items-center gap-1.5 text-[10px] font-medium opacity-65"><x-lucide-calendar-days class="size-3 text-orange-600" aria-hidden="true" />Monthly equivalent</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tracking-tight tabular-nums">{{ $currencyPrefix }}{{ number_format($monthlyCostCents / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 border-r border-base-300 p-2.5">
                <dt class="flex items-center gap-1.5 text-[10px] font-medium opacity-65"><x-lucide-chart-no-axes-combined class="size-3 text-primary" aria-hidden="true" />Annual equivalent</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tracking-tight tabular-nums">{{ $currencyPrefix }}{{ number_format($analytics['annualCostCents'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 bg-primary/5 p-2.5">
                <dt class="flex items-center gap-1.5 text-[10px] font-medium opacity-65"><x-lucide-clock-3 class="size-3 text-primary" aria-hidden="true" />Expected in next 7 days</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tracking-tight tabular-nums text-primary">{{ $currencyPrefix }}{{ number_format($analytics['next7DaysCostCents'] / 100, 2) }}</dd>
                <dd class="text-[10px] opacity-60">{{ $analytics['next7DaysPaymentCount'] }} expected {{ $analytics['next7DaysPaymentCount'] === 1 ? 'payment' : 'payments' }}</dd>
            </div>
        </dl>
        <p class="border-t border-base-300 bg-base-200/40 px-2.5 py-1.5 text-[10px] leading-snug opacity-60">Active subscriptions only. Cost equivalents are estimates; upcoming renewals are forecasts, not confirmed payments.</p>
    </section>

    @if($activeCount > 0)
        <div class="grid items-stretch gap-3 xl:grid-cols-3">
            @include('subscriptions.category-chart', ['chartId' => 'dashboard-category-chart', 'compact' => true])
            <section class="flex min-w-0 flex-col overflow-hidden rounded-sm border border-base-300 bg-base-100 xl:col-span-2" aria-labelledby="dashboard-renewals-title">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-3 py-2">
                    <div>
                        <h3 id="dashboard-renewals-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-clock-3 class="size-3.5 text-primary" aria-hidden="true" />Upcoming renewals</h3>
                        <p class="mt-0.5 text-[10px] opacity-60">Next renewal for up to five subscriptions due in 30 days.</p>
                    </div>
                    <span class="badge badge-ghost badge-xs">Next 30 days</span>
                </div>
                <div class="max-h-64 flex-1 divide-y divide-base-300 overflow-auto" tabindex="0" role="region" aria-label="Upcoming subscription renewals">
                    @forelse($renewals as $renewal)
                        <a class="flex items-center gap-2.5 px-3 py-2 hover:bg-base-200/50" href="{{ route('subscriptions.show', $renewal['subscription']) }}">
                            <time class="flex w-9 shrink-0 flex-col items-center rounded-sm bg-primary/5 py-1 text-primary" datetime="{{ $renewal['date']->toDateString() }}">
                                <span class="text-[9px] uppercase opacity-65">{{ $renewal['date']->format('M') }}</span>
                                <span class="text-sm font-semibold leading-tight tabular-nums">{{ $renewal['date']->format('d') }}</span>
                            </time>
                            <span class="min-w-0 flex-1">
                                <span class="block break-words text-xs font-semibold">{{ $renewal['subscription']->name }}</span>
                                <span class="mt-0.5 block text-[10px] opacity-55">{{ $renewal['date']->format('d M Y') }}</span>
                            </span>
                            <span class="whitespace-nowrap text-xs font-semibold tabular-nums text-orange-600">{{ $currencyPrefix }}{{ number_format($renewal['subscription']->amount_cents / 100, 2) }}</span>
                        </a>
                    @empty
                        <p class="px-3 py-4 text-xs opacity-65">No active subscriptions are due in the next 30 days.</p>
                    @endforelse
                </div>
                @if($analytics['largestSubscriptions'])
                    @php $largestCost = $analytics['largestSubscriptions'][0]; @endphp
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-t border-base-300 bg-base-200/40 px-3 py-2">
                        <p class="min-w-0 text-[11px]">
                            <span class="opacity-60">Largest recurring cost</span>
                            <a class="link link-hover font-semibold" href="{{ route('subscriptions.show', $largestCost['id']) }}">{{ $largestCost['name'] }}</a>
                        </p>
                        <span class="shrink-0 text-xs font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($largestCost['monthly_cost_cents'] / 100, 2) }}/mo</span>
                    </div>
                @endif
                <div class="border-t border-base-300 px-3 py-2">
                    <a class="link link-primary text-[11px]" href="{{ route('subscriptions.index') }}">Explore forecasts and potential savings</a>
                </div>
            </section>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-dashed border-base-300 bg-base-100 px-3 py-3">
            <div class="flex min-w-0 items-start gap-2">
                <x-lucide-repeat class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                <div>
                    <h3 class="text-sm font-semibold">{{ $hasSubscriptions ? 'No active subscriptions' : 'Track your first subscription' }}</h3>
                    <p class="mt-0.5 text-xs opacity-65">{{ $hasSubscriptions ? 'Reactivate a subscription to see your category chart and upcoming renewals.' : 'Add a recurring expense to see spending insights here.' }}</p>
                </div>
            </div>
            <a class="btn btn-primary btn-xs shrink-0" href="{{ $hasSubscriptions ? route('subscriptions.index') : route('subscriptions.create') }}">{{ $hasSubscriptions ? 'View subscriptions' : 'Add subscription' }}</a>
        </div>
    @endif
</section>
