<section class="space-y-5" aria-labelledby="subscription-analytics-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="subscription-analytics-title" class="text-xl font-semibold">Subscription insights</h2>
            <p class="mt-1 text-sm opacity-70">Find your biggest costs, prepare for payment peaks, and explore where you could save.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <span class="badge badge-outline">{{ $analytics['statusCounts']['active'] }} active</span>
            <span class="badge badge-outline">{{ $analytics['statusCounts']['paused'] }} paused</span>
            <span class="badge badge-outline">{{ $analytics['statusCounts']['cancelled'] }} cancelled</span>
        </div>
    </div>
    @if($activeCount === 0)
        <div class="rounded-sm border border-base-300 bg-base-100 p-6 text-sm opacity-70">There are no active subscriptions to analyse. Add or reactivate a subscription to see your spending breakdown and payment forecast.</div>
    @else
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-sm border border-base-300 bg-base-100 p-5">
                <p class="text-sm opacity-70">Prepare for the next 7 days</p>
                <p class="mt-2 text-2xl font-bold">ZAR {{ number_format($analytics['next7DaysCostCents'] / 100, 2) }}</p>
                <p class="mt-2 text-sm opacity-70">{{ $analytics['next7DaysPaymentCount'] }} expected {{ $analytics['next7DaysPaymentCount'] === 1 ? 'payment' : 'payments' }}. Review these renewals before they are due.</p>
            </div>
            <div class="rounded-sm border border-base-300 bg-base-100 p-5">
                <p class="text-sm opacity-70">Highest forecast month</p>
                @if($analytics['peakMonth'])
                    <p class="mt-2 text-2xl font-bold">{{ $analytics['peakMonth']['label'] }}{{ $analytics['peakMonth']['is_partial'] ? ' (remaining)' : '' }}</p>
                    <p class="mt-2 text-sm opacity-70">ZAR {{ number_format($analytics['peakMonth']['amount_cents'] / 100, 2) }} in expected payments. Plan for this amount rather than the monthly average.</p>
                @else
                    <p class="mt-2 text-lg font-semibold">No payments in this forecast</p>
                    <p class="mt-2 text-sm opacity-70">Your next billing dates fall beyond the displayed period.</p>
                @endif
            </div>
            <div class="rounded-sm border border-base-300 bg-base-100 p-5">
                <p class="text-sm opacity-70">Top {{ min(3, $activeCount) }} costs account for</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($analytics['topThreeShare'], 1) }}%</p>
                <p class="mt-2 text-sm opacity-70">of your recurring subscription commitment. Start your review with the largest costs below.</p>
            </div>
        </div>
        <div class="grid gap-5 xl:grid-cols-3">
            @include('subscriptions.category-chart', ['chartId' => 'category-chart'])
            <section class="card border border-base-300 bg-base-100 xl:col-span-2" aria-labelledby="forecast-chart-title">
                <div class="card-body gap-5">
                    <div><h3 id="forecast-chart-title" class="card-title">Expected payments over 12 months</h3><p class="mt-1 text-sm opacity-70">Scheduled payments at your current prices and billing frequencies.</p></div>
                    @if($analytics['forecastTotalCents'] > 0)
                    @php $forecastMaximum = max(array_column($analytics['monthlyForecast'], 'amount_cents')); @endphp
                    <div class="overflow-x-auto">
                        <svg viewBox="0 0 720 250" class="w-full min-w-[640px]" role="img" aria-labelledby="forecast-chart-title" aria-describedby="forecast-chart-description">
                            @foreach([0, 0.5, 1] as $step)
                                <line x1="58" y1="{{ 190 - $step * 150 }}" x2="710" y2="{{ 190 - $step * 150 }}" stroke="currentColor" stroke-opacity="0.12" />
                                <text x="50" y="{{ 194 - $step * 150 }}" text-anchor="end" fill="currentColor" font-size="9">{{ number_format($forecastMaximum * $step / 100, $forecastMaximum < 10000 ? 2 : 0) }}</text>
                            @endforeach
                            <text x="8" y="20" fill="currentColor" font-size="9">ZAR</text>
                            @foreach($analytics['monthlyForecast'] as $index => $month)
                                @php $barHeight = $month['amount_cents'] / $forecastMaximum * 150; @endphp
                                <g>
                                    <title>{{ $month['label'] }}{{ $month['is_partial'] ? ' (remaining)' : '' }}: ZAR {{ number_format($month['amount_cents'] / 100, 2) }}, {{ $month['payment_count'] }} payments</title>
                                    <rect x="{{ 70 + $index * 54 }}" y="{{ 190 - $barHeight }}" width="30" height="{{ $barHeight }}" rx="4" fill="{{ $analytics['peakMonth'] && $month['month'] === $analytics['peakMonth']['month'] ? '#f59e0b' : '#6366f1' }}" />
                                    <text x="{{ 85 + $index * 54 }}" y="210" text-anchor="middle" fill="currentColor" font-size="10">{{ substr($month['label'], 0, 3) }}{{ $month['is_partial'] ? '*' : '' }}</text>
                                    <text x="{{ 85 + $index * 54 }}" y="225" text-anchor="middle" fill="currentColor" font-size="9" opacity="0.6">{{ substr($month['label'], -4) }}</text>
                                </g>
                            @endforeach
                        </svg>
                    </div>
                    <p id="forecast-chart-description" class="text-sm opacity-70">The current month includes only payments from today onwards{{ $analytics['monthlyForecast'][0]['is_partial'] ? ' (*)' : '' }}. Amber marks the highest forecast month. These are forecasts, not spending history.</p>
                    @else
                        <p class="rounded-sm bg-base-200 p-6 text-sm opacity-70">No renewal dates fall within the next 12 calendar months. Your active subscriptions still contribute to the recurring cost estimates.</p>
                    @endif
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-sm bg-base-200 p-4"><span class="text-sm opacity-70">Expected across this forecast period</span><span class="font-bold">ZAR {{ number_format($analytics['forecastTotalCents'] / 100, 2) }}</span></div>
                    <details>
                        <summary class="cursor-pointer text-sm font-medium">View monthly figures</summary>
                        <div class="mt-3 overflow-x-auto"><table class="table table-sm"><thead><tr><th>Month</th><th>Expected payments</th><th>Amount (ZAR)</th></tr></thead><tbody>@foreach($analytics['monthlyForecast'] as $month)<tr><td>{{ $month['label'] }}{{ $month['is_partial'] ? ' (remaining)' : '' }}</td><td>{{ $month['payment_count'] }}</td><td>{{ number_format($month['amount_cents'] / 100, 2) }}</td></tr>@endforeach</tbody></table></div>
                    </details>
                </div>
            </section>
        </div>
        <div class="grid gap-5 xl:grid-cols-2">
            <section class="card border border-base-300 bg-base-100" aria-labelledby="largest-costs-title">
                <div class="card-body gap-5">
                    <div><h3 id="largest-costs-title" class="card-title">Your largest subscription costs</h3><p class="mt-1 text-sm opacity-70">Compare up to five services on the same monthly basis.</p></div>
                    @foreach($analytics['largestSubscriptions'] as $subscriptionCost)
                        <div>
                            <div class="mb-2 flex items-start justify-between gap-4 text-sm"><a class="link link-hover font-semibold" href="{{ route('subscriptions.show', $subscriptionCost['id']) }}">{{ $subscriptionCost['name'] }}</a><span class="shrink-0 font-semibold">ZAR {{ number_format($subscriptionCost['monthly_cost_cents'] / 100, 2) }}/month</span></div>
                            <div class="h-3 overflow-hidden rounded-sm bg-base-200" aria-hidden="true"><div class="h-full rounded-sm bg-primary" style="width: {{ $subscriptionCost['annual_cost_cents'] / $analytics['largestSubscriptions'][0]['annual_cost_cents'] * 100 }}%"></div></div>
                            <p class="mt-1 text-xs opacity-60">{{ number_format($subscriptionCost['share'], 1) }}% of recurring costs · ZAR {{ number_format($subscriptionCost['annual_cost_cents'] / 100, 2) }}/year</p>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="card border border-primary/30 bg-base-100" aria-labelledby="savings-calculator-title" data-subscription-savings data-total-annual-cents="{{ $analytics['annualCostCents'] }}">
                <div class="card-body gap-5">
                    <div><h3 id="savings-calculator-title" class="card-title">What could you save?</h3><p class="mt-1 text-sm opacity-70">Select subscriptions to explore removing from future commitments.</p></div>
                    <form class="space-y-5" method="get" action="{{ route('planning-scenarios.index') }}"><input type="hidden" name="kind" value="subscriptions">
                    <fieldset class="max-h-56 space-y-3 overflow-y-auto rounded-sm border border-base-300 p-4">
                        <legend class="px-1 text-sm font-medium">Subscriptions to review</legend>
                        @foreach($analytics['activeSubscriptions'] as $subscriptionCost)
                            <label class="flex cursor-pointer items-start justify-between gap-3 text-sm">
                                <span class="flex items-start gap-3"><input type="checkbox" class="checkbox checkbox-sm" name="inputs[subscription_ids][]" value="{{ $subscriptionCost['id'] }}" data-savings-subscription data-annual-cents="{{ $subscriptionCost['annual_cost_cents'] }}"><span>{{ $subscriptionCost['name'] }}</span></span>
                                <span class="shrink-0 opacity-70">ZAR {{ number_format($subscriptionCost['monthly_cost_cents'] / 100, 2) }}/mo</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <div class="grid gap-4 sm:grid-cols-2" role="status" aria-live="polite" aria-atomic="true">
                        <div><p class="text-sm opacity-70">Monthly equivalent reduction</p><p class="mt-1 text-xl font-bold text-success" data-savings-monthly>ZAR 0.00</p></div>
                        <div><p class="text-sm opacity-70">Annual equivalent reduction</p><p class="mt-1 text-xl font-bold text-success" data-savings-annual>ZAR 0.00</p></div>
                    </div>
                    <div class="space-y-3 rounded-sm bg-base-200 p-4">
                        <div><div class="mb-1 flex justify-between gap-3 text-xs"><span>Current monthly equivalent</span><span>ZAR {{ number_format($monthlyCostCents / 100, 2) }}</span></div><div class="h-3 rounded-sm bg-primary" aria-hidden="true"></div></div>
                        <div><div class="mb-1 flex justify-between gap-3 text-xs"><span>After selected changes</span><span data-savings-remaining>ZAR {{ number_format($monthlyCostCents / 100, 2) }}</span></div><div class="h-3 overflow-hidden rounded-sm bg-base-300" aria-hidden="true"><div class="h-full rounded-sm bg-success" style="width: 100%" data-savings-bar></div></div></div>
                    </div>
                    <div><label for="subscription-monthly-target" class="label">Monthly subscription target (optional, ZAR)</label><input id="subscription-monthly-target" name="inputs[target]" type="number" min="0" max="9999999.99" step="0.01" class="input w-full" placeholder="e.g. 500.00" data-savings-target><p class="mt-2 text-sm opacity-70" data-savings-target-result role="status">Enter a target to compare it with your remaining monthly equivalent.</p></div>
                    <button type="button" class="btn btn-sm btn-ghost self-start" data-savings-reset>Reset selections</button>
                    <button class="btn btn-sm btn-outline" type="submit">Keep this plan in Planning scenarios</button></form>
                    <p class="text-xs opacity-60">This scenario does not cancel or change subscriptions. Reductions assume future charges stop at current prices; paid charges and cancellation fees are excluded. Use Planning scenarios to name and save these assumptions.</p>
                    <noscript><p class="text-sm">Enable JavaScript to use the savings calculator. The charts and figures above remain available.</p></noscript>
                </div>
            </section>
        </div>
        <p class="text-xs opacity-60">Charts cover all your active subscriptions, regardless of list filters. Monthly and annual equivalents use standard billing frequencies; the forecast counts actual scheduled renewal dates.</p>
    @endif
</section>

