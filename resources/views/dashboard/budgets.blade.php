<section class="space-y-3" aria-labelledby="dashboard-budgets-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div><h2 id="dashboard-budgets-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-chart-pie class="size-3.5 text-primary" aria-hidden="true" />Your budgets at a glance</h2><p class="mt-0.5 text-[10px] opacity-60">Current periods, useful signals, and your next move.</p></div>
        <a class="btn btn-xs btn-outline" href="{{ route('budgets.index') }}">View all budgets <x-lucide-arrow-up-right class="size-3.5" aria-hidden="true" /></a>
    </div>
    @if($budgetOverview['cards']->isNotEmpty())
        <div class="grid items-stretch gap-3 xl:grid-cols-2">
            @foreach($budgetOverview['cards'] as $card)
                @php
                    $totals = $card['totals'];
                    $fillsRow = $loop->last && $loop->count % 2 === 1;
                    $ringClass = $card['risk'] === 2 ? 'text-error' : ($card['risk'] === 1 ? 'text-warning' : 'text-primary');
                    $insightClasses = ['error' => ['bg-error/5', 'text-error'], 'warning' => ['bg-warning/5', 'text-warning'], 'info' => ['bg-info/5', 'text-info']];
                    $ringProgress = min(100, max(0, $card['spentPercent'] ?? 0));
                @endphp
                <article @class(['min-w-0 overflow-hidden rounded-sm border border-base-300 bg-base-100', 'xl:col-span-2 xl:grid xl:grid-cols-2' => $fillsRow]) aria-labelledby="budget-card-{{ $card['period']->id }}">
                    <div class="min-w-0 border-b border-base-300 bg-primary/5 p-3 xl:border-b-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0"><div class="mb-1.5 flex flex-wrap gap-1.5"><span class="badge badge-xs badge-outline">{{ $card['budget']->scope === 'household' ? 'Household' : 'Personal' }}</span>@if($card['role'] === 'viewer')<span class="badge badge-xs badge-ghost">View only</span>@endif</div><h3 id="budget-card-{{ $card['period']->id }}" class="break-words text-sm font-semibold"><a class="link-hover" href="{{ $card['url'] }}">{{ $card['budget']->name }}</a></h3><p class="text-[10px] opacity-60">{{ $card['period']->start_date->format('d M') }} &ndash; {{ $card['period']->end_date->format('d M Y') }}</p></div>
                            <a class="btn btn-square btn-xs btn-ghost shrink-0" href="{{ $card['url'] }}" aria-label="Open {{ $card['budget']->name }}"><x-lucide-arrow-up-right class="size-3.5" aria-hidden="true" /></a>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div class="min-w-0"><p class="text-xs opacity-65">{{ $totals['after_commitments'] < 0 ? 'Forecast shortfall' : 'Room after scheduled expenses' }}</p><p class="mt-0.5 break-words text-xl font-bold tracking-tight tabular-nums {{ $totals['after_commitments'] < 0 ? 'text-error' : '' }}">{{ $currencyPrefix }}{{ number_format(abs($totals['after_commitments']) / 100, 2) }}</p><p class="mt-1 text-[10px] opacity-60">Income less recorded spending and unpaid forecasts.</p></div>
                            <div class="shrink-0 text-center"><div class="radial-progress {{ $ringClass }}" style="--value: {{ $ringProgress }}; --size: 4rem; --thickness: 4px;" role="img" aria-label="{{ $card['spentPercent'] === null ? 'No category allocations yet' : $card['spentPercent'].' percent of category allocations spent' }}"><span class="text-xs font-bold text-base-content">{{ $card['spentPercent'] === null ? '-' : number_format($card['spentPercent'], 0).'%' }}</span></div><p class="mt-1 text-[10px] opacity-60">of plan spent</p></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-base-300 pt-2">
                            <div class="min-w-0"><p class="text-[10px] opacity-60">Recorded spending</p><p class="mt-0.5 break-words text-xs font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($totals['spent'] / 100, 2) }}</p></div>
                            <div class="min-w-0"><p class="text-[10px] opacity-60">Unpaid forecasts</p><p class="mt-0.5 break-words text-xs font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($totals['upcoming'] / 100, 2) }}</p></div>
                            <div class="min-w-0"><p class="text-[10px] opacity-60">Expected income</p><p class="mt-0.5 break-words text-xs font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($totals['expected'] / 100, 2) }}</p></div>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-between gap-2 text-xs opacity-70"><span>{{ $card['daysLeft'] }} {{ $card['daysLeft'] === 1 ? 'day' : 'days' }} left, including today</span>@if($totals['expected'] > 0 && $totals['after_commitments'] >= 0)<span>Forecast room: {{ $currencyPrefix }}{{ number_format($card['dailyRoom'] / 100, 2) }}/day</span>@endif</div>
                        <progress class="progress progress-primary mt-2 h-1 w-full opacity-40" value="{{ $card['elapsedPercent'] }}" max="100" aria-label="Budget period elapsed"></progress>
                    </div>
                    <div @class(['min-w-0 space-y-3 p-3', 'xl:border-l xl:border-base-300' => $fillsRow])>
                        @if($card['rows']->isNotEmpty())
                            <div><div class="mb-2 flex items-center justify-between gap-2"><h4 class="text-xs font-semibold">{{ $card['grouped'] ? 'Spending by group' : 'Spending by category' }}</h4><a class="link link-primary text-[10px]" href="{{ $card['url'] }}">All analytics</a></div>
                                <div class="space-y-2">
                                    @foreach($card['rows'] as $row)
                                        @php
                                            $target = $row['limit'] ?? $row['planned'];
                                            $actualWidth = $target > 0 ? min(100, $row['spent'] / $target * 100) : ($row['spent'] > 0 ? 100 : 0);
                                            $forecastWidth = $target > 0 ? min(100 - $actualWidth, $row['upcoming'] / $target * 100) : 0;
                                            $over = $row['limit'] !== null && $row['spent'] > $row['limit'];
                                        @endphp
                                        <div><div class="mb-1 flex items-baseline justify-between gap-2 text-[10px]"><span class="break-words font-medium">{{ $row['name'] }}</span><span class="shrink-0 tabular-nums {{ $over ? 'text-error' : 'opacity-65' }}">{{ $currencyPrefix }}{{ number_format($row['spent'] / 100, 2) }}@if($row['limit'] !== null) / {{ number_format($row['limit'] / 100, 2) }}@else <span class="opacity-60">&middot; no limit</span>@endif</span></div><div class="flex h-1.5 overflow-hidden rounded-sm bg-base-200" role="img" aria-label="{{ $row['name'] }}: {{ $currencyPrefix }}{{ number_format($row['spent'] / 100, 2) }} spent, {{ $currencyPrefix }}{{ number_format($row['upcoming'] / 100, 2) }} unpaid forecasts{{ $over ? ', over limit' : '' }}"><span class="{{ $over ? 'bg-error' : 'bg-primary' }}" style="width: {{ $actualWidth }}%"></span><span class="bg-primary/25" style="width: {{ $forecastWidth }}%"></span></div></div>
                                    @endforeach
                                </div><p class="mt-1.5 text-[10px] opacity-50">Solid: recorded · pale: unpaid forecast. Top three by spending and forecasts.</p>
                            </div>
                        @endif
                        <div class="rounded-sm border border-base-300 p-2.5"><div class="flex items-center justify-between gap-2"><h4 class="flex items-center gap-1.5 text-xs font-semibold"><x-lucide-calendar-days class="size-3.5 text-primary" aria-hidden="true" />Next 7 days</h4><span class="text-xs font-bold tabular-nums">{{ $currencyPrefix }}{{ number_format($card['dueSoonCents'] / 100, 2) }}</span></div><p class="mt-1 text-[10px] opacity-60">{{ $card['dueSoonCount'] }} scheduled {{ $card['dueSoonCount'] === 1 ? 'payment' : 'payments' }} still to record @if($card['overdueCount'] > 0) &middot; {{ $card['overdueCount'] }} earlier {{ $card['overdueCount'] === 1 ? 'payment' : 'payments' }} not recorded @endif.</p>
                            <div class="mt-2 space-y-1">
                                @forelse($card['payments'] as $payment)
                                    <a class="flex items-center justify-between gap-3 rounded-sm bg-base-200/50 px-2 py-1.5 text-[10px] transition hover:bg-base-200" href="{{ route('budgets.index', ['period' => $card['period']->id, 'tab' => $payment['tab']]) }}"><span class="min-w-0"><span class="block truncate font-medium">{{ $payment['name'] }}</span><span class="{{ $payment['date']->lt(today()) ? 'text-warning' : 'opacity-60' }}">{{ $payment['date']->format('d M') }}{{ $payment['date']->lt(today()) ? ' - Not recorded' : '' }}</span></span><span class="shrink-0 font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($payment['amount'] / 100, 2) }}</span></a>
                                @empty
                                    <p class="text-xs opacity-60">No unpaid scheduled expenses due in this window.</p>
                                @endforelse
                            </div>
                            @if($card['dueSoonCount'] + $card['overdueCount'] > 3)<p class="mt-2 text-xs opacity-50">Showing the earliest three. Open the budget for all scheduled expenses.</p>@endif
                        </div>
                        <div class="space-y-3">
                            @foreach($card['insights'] as $insight)
                                <div class="flex items-start gap-2 rounded-sm {{ $insightClasses[$insight['tone']][0] }} p-2.5"><x-lucide-lightbulb class="mt-0.5 size-3.5 shrink-0 {{ $insightClasses[$insight['tone']][1] }}" aria-hidden="true" /><div class="min-w-0"><p class="text-xs font-semibold">{{ $insight['title'] }}@if($insight['amount'] !== null)<span class="ml-1 {{ $insightClasses[$insight['tone']][1] }}">{{ $currencyPrefix }}{{ number_format($insight['amount'] / 100, 2) }}</span>@endif</p><p class="mt-0.5 text-[10px] opacity-65">{{ $insight['detail'] }}</p><a class="link link-primary mt-1 inline-flex items-center gap-1 text-[10px] font-semibold" href="{{ route('budgets.index', ['period' => $card['period']->id, 'tab' => $insight['tab']]) }}">{{ $insight['action'] }} <x-lucide-arrow-right class="size-3" aria-hidden="true" /></a></div></div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="text-xs opacity-60">Each budget is shown separately. Forecast room uses expected income, not your bank balance. Scheduled expenses may already be paid if you have not recorded them yet.</p>
    @else
        <div class="flex flex-col items-start gap-5 rounded-sm border border-dashed border-primary/30 bg-gradient-to-br from-primary/10 to-base-100 p-6 sm:flex-row sm:items-center">
            <div class="flex size-14 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-primary"><x-lucide-chart-pie class="size-7" /></div>
            <div class="flex-1">
                @if($budgetOverview['totalBudgets'] === 0)
                    <h3 class="text-lg font-semibold">Give your money a plan</h3><p class="mt-1 text-sm opacity-65">Set your dates, add expected income, and plan your categories. Your spending signals will appear here.</p>
                @else
                    <h3 class="text-lg font-semibold">No budget period covers today</h3>
                    @if($budgetOverview['nextPeriod'])
                        <p class="mt-1 text-sm opacity-65">Next up: {{ $budgetOverview['nextPeriod']->budget->name }}, starting {{ $budgetOverview['nextPeriod']->start_date->format('d M Y') }}. Review the plan before it begins.</p>
                    @elseif($budgetOverview['latestPeriod'])
                        <p class="mt-1 text-sm opacity-65">Your latest period ended {{ $budgetOverview['latestPeriod']->end_date->format('d M Y') }}. Open it to review results or prepare your next period.</p>
                    @else
                        <p class="mt-1 text-sm opacity-65">Open your budgets to set up a period and see current insights here.</p>
                    @endif
                @endif
            </div>
            <a class="btn btn-primary shrink-0" href="{{ route('budgets.index', ['period' => ($budgetOverview['nextPeriod'] ?? $budgetOverview['latestPeriod'])?->id, 'tab' => 'overview']) }}">{{ $budgetOverview['totalBudgets'] === 0 ? 'Create a budget' : ($budgetOverview['nextPeriod'] ? 'Review next period' : 'Open budgets') }} <x-lucide-arrow-right class="size-4" /></a>
        </div>
    @endif
</section>
