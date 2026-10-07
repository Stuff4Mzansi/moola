@php
    $chartWidth = 1000;
    $chartHeight = 360;
    $chartLeft = 82;
    $chartRight = 950;
    $chartTop = 28;
    $chartBottom = 292;
    $chartDays = $periodReview['dailySpending'];
    $lastDayIndex = max(0, count($chartDays) - 1);
    $spendingMaximum = max(1, collect($chartDays)->max('amount') ?? 0);
    $spendingScaleMaximum = $spendingMaximum * 1.15;
    $xForIndex = fn (int $index): float => $chartLeft + ($index / max(1, $lastDayIndex)) * ($chartRight - $chartLeft);
    $yForAmount = fn (int $amount): float => $chartBottom - ($amount / $spendingScaleMaximum) * ($chartBottom - $chartTop);
    $dailySpendingPath = '';
    $previousPoint = null;
    foreach ($chartDays as $index => $day) {
        $point = ['x' => $xForIndex($index), 'y' => $yForAmount($day['amount'])];
        if ($previousPoint === null) {
            $dailySpendingPath = 'M '.round($point['x'], 2).' '.round($point['y'], 2);
        } else {
            $controlOffset = ($point['x'] - $previousPoint['x']) / 3;
            $dailySpendingPath .= ' C '.round($previousPoint['x'] + $controlOffset, 2).' '.round($previousPoint['y'], 2).' '.round($point['x'] - $controlOffset, 2).' '.round($point['y'], 2).' '.round($point['x'], 2).' '.round($point['y'], 2);
        }
        $previousPoint = $point;
    }
    $spendingAreaPath = $dailySpendingPath.' L '.$chartRight.' '.$chartBottom.' L '.$chartLeft.' '.$chartBottom.' Z';
    $dateTickInterval = max(1, (int) ceil($lastDayIndex / 6));
    $plannedReachedIndex = $periodReview['plannedReachedDate'] === null ? null : array_search($periodReview['plannedReachedDate'], array_column($chartDays, 'date'), true);
    $receivedReachedIndex = $periodReview['receivedReachedDate'] === null ? null : array_search($periodReview['receivedReachedDate'], array_column($chartDays, 'date'), true);
@endphp
<section class="rounded-sm border border-base-300 bg-base-100 p-4 sm:p-5" aria-labelledby="review-daily-spending-title">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div><h4 id="review-daily-spending-title" class="font-semibold">Daily spending across the period</h4><p class="mt-1 text-xs opacity-65">Recorded expense amounts by date, including days with no spending.</p></div>
        <p class="text-xs tabular-nums opacity-65">{{ $money($totals['spent']) }} spent in period</p>
    </div>
    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-[11px]">
        <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 bg-orange-600" aria-hidden="true"></span>Spent that day</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-3 border-l-2 border-dashed border-primary" aria-hidden="true"></span>Planned amount reached @if($periodReview['plannedReachedDate'])({{ $money($totals['planned']) }} on {{ \Carbon\CarbonImmutable::parse($periodReview['plannedReachedDate'])->format('d M') }})@else(not reached)@endif</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-3 border-l-2 border-dashed border-success" aria-hidden="true"></span>Income received reached @if($periodReview['receivedReachedDate'])({{ $money($totals['received']) }} on {{ \Carbon\CarbonImmutable::parse($periodReview['receivedReachedDate'])->format('d M') }})@else(not reached)@endif</span>
    </div>
    <div class="mt-3 w-full overflow-x-auto" role="region" aria-label="Daily spending chart" tabindex="0">
        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="block min-w-[36rem] w-full" role="img" aria-labelledby="review-daily-spending-title review-daily-spending-description">
            <title id="review-daily-spending-description">Daily recorded spending from {{ $period->start_date->format('d M') }} to {{ $period->end_date->format('d M') }}. The vertical planned marker shows when cumulative spending first reached {{ $money($totals['planned']) }}. The vertical received-income marker shows when cumulative spending first reached {{ $money($totals['received']) }}.</title>
            @foreach([0, 1, 2, 3] as $tick)
                @php
                    $tickAmount = (int) round($spendingScaleMaximum * $tick / 3);
                    $tickY = $yForAmount($tickAmount);
                @endphp
                <line x1="{{ $chartLeft }}" y1="{{ round($tickY, 2) }}" x2="{{ $chartRight }}" y2="{{ round($tickY, 2) }}" stroke="currentColor" class="text-base-content/10" />
                <text x="{{ $chartLeft - 10 }}" y="{{ round($tickY + 4, 2) }}" text-anchor="end" fill="currentColor" class="text-base-content/60" font-size="11">{{ $money($tickAmount) }}</text>
            @endforeach
            <path d="{{ $spendingAreaPath }}" class="fill-orange-600" opacity="0.08" />
            <path data-review-daily-spending-line d="{{ $dailySpendingPath }}" fill="none" stroke="currentColor" class="text-orange-600" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
            @foreach($chartDays as $index => $day)
                @php $pointY = $yForAmount($day['amount']); @endphp
                <circle data-review-daily-spending-point cx="{{ round($xForIndex($index), 2) }}" cy="{{ round($pointY, 2) }}" r="2" fill="currentColor" class="text-orange-600"><title>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d M') }}: {{ $money($day['amount']) }} spent that day ({{ $money($day['cumulative']) }} cumulative)</title></circle>
                @if($index === 0 || $index === $lastDayIndex || $index % $dateTickInterval === 0)
                    <text x="{{ round($xForIndex($index), 2) }}" y="{{ $chartBottom + 23 }}" text-anchor="{{ $index === 0 ? 'start' : ($index === $lastDayIndex ? 'end' : 'middle') }}" fill="currentColor" class="text-base-content/65" font-size="11">{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d M') }}</text>
                @endif
            @endforeach
            @if($plannedReachedIndex !== false && $plannedReachedIndex !== null)
                @php $plannedX = $xForIndex($plannedReachedIndex); @endphp
                <line data-review-threshold="planned" data-review-threshold-date="{{ $periodReview['plannedReachedDate'] }}" x1="{{ round($plannedX, 2) }}" y1="{{ $chartTop }}" x2="{{ round($plannedX, 2) }}" y2="{{ $chartBottom }}" stroke="var(--color-primary)" stroke-width="3" stroke-dasharray="7 4" vector-effect="non-scaling-stroke"><title>Cumulative recorded spending reached the planned amount of {{ $money($totals['planned']) }} on {{ \Carbon\CarbonImmutable::parse($periodReview['plannedReachedDate'])->format('d M') }}</title></line>
            @endif
            @if($receivedReachedIndex !== false && $receivedReachedIndex !== null)
                @php $receivedX = $xForIndex($receivedReachedIndex); @endphp
                <line data-review-threshold="received" data-review-threshold-date="{{ $periodReview['receivedReachedDate'] }}" x1="{{ round($receivedX, 2) }}" y1="{{ $chartTop }}" x2="{{ round($receivedX, 2) }}" y2="{{ $chartBottom }}" stroke="var(--color-success)" stroke-width="3" stroke-dasharray="2 4" vector-effect="non-scaling-stroke"><title>Cumulative recorded spending reached received income of {{ $money($totals['received']) }} on {{ \Carbon\CarbonImmutable::parse($periodReview['receivedReachedDate'])->format('d M') }}</title></line>
            @endif
            <text x="16" y="{{ round(($chartTop + $chartBottom) / 2, 2) }}" transform="rotate(-90 16 {{ round(($chartTop + $chartBottom) / 2, 2) }})" text-anchor="middle" fill="currentColor" class="text-base-content/60" font-size="11">Amount spent that day</text>
        </svg>
    </div>
    <p class="mt-2 text-[11px] opacity-65">Vertical markers show when cumulative recorded spending first reached each period total; they are not the day's spending amount.</p>
</section>
