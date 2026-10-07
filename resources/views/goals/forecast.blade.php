@php
    $forecastChartWidth = 1000;
    $forecastChartHeight = 300;
    $forecastLeft = 88;
    $forecastRight = 960;
    $forecastTop = 24;
    $forecastBottom = 232;
    $forecastLastIndex = max(1, count($forecast) - 1);
    $forecastMaximum = max(1, $target, $forecast->max('saved') ?? 0);
    $forecastColours = ['#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9', '#8b5cf6', '#84cc16', '#f97316'];
    $forecastX = fn (int $index): float => $forecastLeft + ($index / $forecastLastIndex) * ($forecastRight - $forecastLeft);
    $forecastY = fn (int $amount): float => $forecastBottom - ($amount / $forecastMaximum) * ($forecastBottom - $forecastTop);
    $forecastPath = '';
    $previousForecastPoint = null;
    foreach ($forecast as $index => $point) {
        $chartPoint = ['x' => $forecastX($index), 'y' => $forecastY($point['saved'])];
        if ($previousForecastPoint === null) {
            $forecastPath = 'M '.round($chartPoint['x'], 2).' '.round($chartPoint['y'], 2);
        } else {
            $controlOffset = ($chartPoint['x'] - $previousForecastPoint['x']) / 3;
            $forecastPath .= ' C '.round($previousForecastPoint['x'] + $controlOffset, 2).' '.round($previousForecastPoint['y'], 2).' '.round($chartPoint['x'] - $controlOffset, 2).' '.round($chartPoint['y'], 2).' '.round($chartPoint['x'], 2).' '.round($chartPoint['y'], 2);
        }
        $previousForecastPoint = $chartPoint;
    }
    $forecastAreaPath = $forecastPath.' L '.$forecastRight.' '.$forecastBottom.' L '.$forecastLeft.' '.$forecastBottom.' Z';
    $forecastTickInterval = max(1, (int) ceil($forecastLastIndex / 6));
    $unprojectedGoals = $rows->filter(fn (array $row): bool => $row['forecastDate'] === null);
@endphp
<section class="rounded-sm border border-base-300 bg-base-100 p-3 sm:p-4" aria-labelledby="goal-forecast-title">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div><h2 id="goal-forecast-title" class="text-sm font-semibold">Projected savings progress</h2><p class="mt-1 text-[10px] opacity-65">Estimated total saved over time, assuming you continue each monthly plan.</p></div>
        <p class="text-[10px] tabular-nums opacity-65">{{ $money($target) }} combined targets</p>
    </div>
    @if($rows->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[9px]">
            <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 bg-green-600" aria-hidden="true"></span>Projected savings</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-3 border-l-2 border-dashed border-primary" aria-hidden="true"></span>Goal reached</span>
        </div>
        <div class="mt-2 w-full overflow-x-auto" role="region" aria-label="Projected savings progress chart" tabindex="0">
            <svg viewBox="0 0 {{ $forecastChartWidth }} {{ $forecastChartHeight }}" class="block min-w-[34rem] w-full" role="img" aria-labelledby="goal-forecast-title goal-forecast-description">
                <title id="goal-forecast-description">Projected savings grow from {{ $money($saved) }} today to {{ $money($forecast->last()['saved']) }} by {{ \Carbon\CarbonImmutable::parse($forecast->last()['date'])->format('M Y') }} if monthly plans continue. Vertical markers show each goal's estimated completion date.</title>
                @foreach([0, 1, 2, 3] as $tick)
                    @php
                        $tickAmount = (int) round($forecastMaximum * $tick / 3);
                        $tickY = $forecastY($tickAmount);
                    @endphp
                    <line x1="{{ $forecastLeft }}" y1="{{ round($tickY, 2) }}" x2="{{ $forecastRight }}" y2="{{ round($tickY, 2) }}" stroke="currentColor" class="text-base-content/10" />
                    <text x="{{ $forecastLeft - 10 }}" y="{{ round($tickY + 4, 2) }}" text-anchor="end" fill="currentColor" class="text-base-content/60" font-size="8">{{ $money($tickAmount) }}</text>
                @endforeach
                @if($target > 0)
                    <line x1="{{ $forecastLeft }}" y1="{{ round($forecastY($target), 2) }}" x2="{{ $forecastRight }}" y2="{{ round($forecastY($target), 2) }}" stroke="currentColor" class="text-primary/50" stroke-dasharray="5 5"><title>Combined goal targets: {{ $money($target) }}</title></line>
                @endif
                <path d="{{ $forecastAreaPath }}" fill="currentColor" class="text-green-600" opacity="0.08" />
                <path data-goal-forecast-line d="{{ $forecastPath }}" fill="none" stroke="currentColor" class="text-green-600" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                @foreach($forecast as $index => $point)
                    @if($index === 0 || $index === $forecastLastIndex || $index % $forecastTickInterval === 0)
                        <text x="{{ round($forecastX($index), 2) }}" y="{{ $forecastBottom + 21 }}" text-anchor="{{ $index === 0 ? 'start' : ($index === $forecastLastIndex ? 'end' : 'middle') }}" fill="currentColor" class="text-base-content/65" font-size="8">{{ \Carbon\CarbonImmutable::parse($point['date'])->format('M Y') }}</text>
                    @endif
                @endforeach
                @foreach($rows as $index => $row)
                    @if($row['forecastDate'] !== null)
                        @php
                            $markerIndex = min($row['forecastMonths'], $forecastLastIndex);
                            $markerX = $forecastX($markerIndex);
                            $markerColour = $forecastColours[$index % count($forecastColours)];
                        @endphp
                        <line data-goal-forecast-marker="{{ $row['goal']->id }}" data-goal-forecast-date="{{ $row['forecastDate']->toDateString() }}" x1="{{ round($markerX, 2) }}" y1="{{ $forecastTop }}" x2="{{ round($markerX, 2) }}" y2="{{ $forecastBottom }}" stroke="{{ $markerColour }}" stroke-width="2" stroke-dasharray="5 4" vector-effect="non-scaling-stroke"><title>{{ $row['goal']->name }} reached {{ $row['forecastDate']->format('d M Y') }}</title></line>
                    @endif
                @endforeach
            </svg>
        </div>
        <ul class="mt-2 max-h-28 divide-y divide-base-300/60 overflow-y-auto pr-1 text-[9px]" tabindex="0" aria-label="Goal completion forecast dates">
            @foreach($rows as $index => $row)
                <li class="flex items-start justify-between gap-2 py-1">
                    <span class="flex min-w-0 items-start gap-1.5"><span class="mt-0.5 size-2 shrink-0 rounded-full" style="background-color: {{ $row['forecastDate'] ? $forecastColours[$index % count($forecastColours)] : 'currentColor' }}" aria-hidden="true"></span><span class="break-words">{{ $row['goal']->name }}</span></span>
                    <span class="shrink-0 text-right tabular-nums">@if($row['forecastDate']){{ $row['forecastMonths'] === 0 ? 'Reached' : 'Est.' }} {{ $row['forecastDate']->format('d M Y') }}@else<span class="opacity-65">Set a monthly plan to forecast</span>@endif</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-[9px] leading-snug opacity-60">Forecast includes this month's remaining plan, then assumes the monthly plan continues. It does not include interest or unrecorded contributions.</p>
        @if($unprojectedGoals->isNotEmpty())<p class="mt-1 text-[9px] leading-snug opacity-60">No projected completion date for {{ $unprojectedGoals->pluck('goal.name')->join(', ') }} because no monthly amount or target-date plan is set.</p>@endif
    @else
        <p class="mt-3 text-[11px] opacity-65">Add a savings goal with a monthly plan to forecast when you could reach it.</p>
    @endif
</section>
