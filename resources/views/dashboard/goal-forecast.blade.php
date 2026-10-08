@php
    $goalForecast = $savingsOverview['forecast'];
    $goalRows = $savingsOverview['rows'];
    $chartWidth = 1000;
    $chartHeight = 220;
    $chartLeft = 72;
    $chartRight = 970;
    $chartTop = 18;
    $chartBottom = 150;
    $lastIndex = max(1, $goalForecast->count() - 1);
    $maximum = max(1, $savingsOverview['target'], $goalForecast->max('saved') ?? 0);
    $colours = ['#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9', '#8b5cf6', '#84cc16', '#f97316'];
    $x = fn (int $index): float => $chartLeft + ($index / $lastIndex) * ($chartRight - $chartLeft);
    $y = fn (int $amount): float => $chartBottom - ($amount / $maximum) * ($chartBottom - $chartTop);
    $path = '';
    foreach ($goalForecast as $index => $point) {
        $path .= ($index === 0 ? 'M ' : ' L ').round($x($index), 2).' '.round($y($point['saved']), 2);
    }
    $tickInterval = max(1, (int) ceil($lastIndex / 5));
@endphp
<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-labelledby="dashboard-goal-forecast-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-3 py-2"><div><h2 id="dashboard-goal-forecast-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-chart-no-axes-combined class="size-3.5 text-primary" aria-hidden="true" />Savings goal forecast</h2><p class="mt-0.5 text-[10px] opacity-60">Estimated progress if monthly plans continue.</p></div><a class="link link-primary text-[11px]" href="{{ route('goals.index') }}">View goals</a></div>
    @if($goalRows->isEmpty())
        <p class="px-3 py-3 text-xs opacity-65">Add a goal with a monthly plan to see its projected completion date.</p>
    @else
        <div class="w-full overflow-x-auto px-2 pt-2" role="region" aria-label="Projected savings goal chart" tabindex="0">
            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="block min-w-[30rem] w-full" role="img" aria-labelledby="dashboard-goal-forecast-title dashboard-goal-forecast-description">
                <title id="dashboard-goal-forecast-description">Projected combined savings from {{ $currencyPrefix }}{{ number_format($savingsOverview['saved'] / 100, 2) }} to {{ $currencyPrefix }}{{ number_format($goalForecast->last()['saved'] / 100, 2) }}. Markers show goal completion estimates.</title>
                @foreach([0, 1, 2, 3] as $tick)
                    @php $tickAmount = (int) round($maximum * $tick / 3); $tickY = $y($tickAmount); @endphp
                    <line x1="{{ $chartLeft }}" x2="{{ $chartRight }}" y1="{{ round($tickY, 2) }}" y2="{{ round($tickY, 2) }}" stroke="currentColor" class="text-base-content/10" />
                    <text x="{{ $chartLeft - 8 }}" y="{{ round($tickY + 3, 2) }}" text-anchor="end" fill="currentColor" class="text-base-content/60" font-size="8">{{ $currencyPrefix }}{{ number_format($tickAmount / 100, 0) }}</text>
                @endforeach
                <line x1="{{ $chartLeft }}" x2="{{ $chartRight }}" y1="{{ round($y($savingsOverview['target']), 2) }}" y2="{{ round($y($savingsOverview['target']), 2) }}" stroke="currentColor" class="text-primary/50" stroke-dasharray="5 5" />
                <path d="{{ $path }}" fill="none" stroke="currentColor" class="text-green-600" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                @foreach($goalForecast as $index => $point)
                    @if($index === 0 || $index === $lastIndex || $index % $tickInterval === 0)
                        <text x="{{ round($x($index), 2) }}" y="{{ $chartBottom + 20 }}" text-anchor="{{ $index === 0 ? 'start' : ($index === $lastIndex ? 'end' : 'middle') }}" fill="currentColor" class="text-base-content/65" font-size="9">{{ \Carbon\CarbonImmutable::parse($point['date'])->format('M Y') }}</text>
                    @endif
                @endforeach
                @foreach($goalRows as $index => $row)
                    @if($row['forecastDate'] !== null)
                        @php $markerX = $x(min($row['forecastMonths'], $lastIndex)); @endphp
                        <line x1="{{ round($markerX, 2) }}" x2="{{ round($markerX, 2) }}" y1="{{ $chartTop }}" y2="{{ $chartBottom }}" stroke="{{ $colours[$index % count($colours)] }}" stroke-width="2" stroke-dasharray="5 4"><title>{{ $row['goal']->name }}: {{ $row['forecastMonths'] === 0 ? 'reached' : 'estimated' }} {{ $row['forecastDate']->format('d M Y') }}</title></line>
                    @endif
                @endforeach
            </svg>
        </div>
        <ul class="mx-3 mb-2 max-h-24 divide-y divide-base-300/60 overflow-y-auto border-y border-base-300/60 pr-1 text-[10px]" tabindex="0" aria-label="Goal completion forecast dates">
            @foreach($goalRows as $index => $row)
                <li class="flex items-center justify-between gap-2 py-1"><span class="flex min-w-0 items-center gap-1.5"><span class="size-2 shrink-0 rounded-full" style="background-color: {{ $row['forecastDate'] ? $colours[$index % count($colours)] : 'currentColor' }}" aria-hidden="true"></span><span class="break-words">{{ $row['goal']->name }}</span></span><span class="shrink-0 text-right tabular-nums">@if($row['forecastDate']){{ $row['forecastMonths'] === 0 ? 'Reached' : 'Est.' }} {{ $row['forecastDate']->format('d M Y') }}@else<span class="opacity-65">Set a monthly plan</span>@endif</span></li>
            @endforeach
        </ul>
        <p class="border-t border-base-300 bg-base-200/40 px-3 py-1.5 text-[10px] leading-snug opacity-60">Includes this month's remaining plan, then assumes plans continue; excludes interest and unrecorded contributions.</p>
    @endif
</section>
