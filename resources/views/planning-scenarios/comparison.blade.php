@php
    $scores = array_column(array_column($comparison, 'result'), 'score');
    $minimum = min([0, ...array_filter($scores, fn (mixed $value): bool => $value !== null)]);
    $maximum = max([1, ...array_filter($scores, fn (mixed $value): bool => $value !== null)]);
    $position = fn (int $value): float => 240 + ($value - $minimum) / ($maximum - $minimum) * 430;
    $axis = $position(0);
@endphp
<section class="space-y-4 rounded-sm border border-primary/25 bg-base-100 p-4 sm:p-5" aria-labelledby="scenario-comparison-title">
    <div><h2 id="scenario-comparison-title" class="text-lg font-semibold">Compare your options</h2><p class="mt-1 text-xs opacity-65">{{ $comparison[0]['result']['scoreLabel'] }} · {{ $kind === 'liquidity' ? 'Higher is more cash headroom. All plans use '.$comparisonWindow.' days from today.' : 'Lower is better. All plans use current financial records.' }}</p></div>
    <div class="overflow-x-auto rounded-sm bg-base-200/40 p-2">
        <svg class="block w-full min-w-[640px]" viewBox="0 0 820 {{ count($comparison) * 56 + 16 }}" role="img" aria-label="Saved scenario comparison: {{ $comparison[0]['result']['scoreLabel'] }}">
            <title>{{ $comparison[0]['result']['scoreLabel'] }}</title><line x1="{{ $axis }}" x2="{{ $axis }}" y1="8" y2="{{ count($comparison) * 56 }}" stroke="currentColor" opacity="0.2" />
            @foreach($comparison as $option)
                @php $score = $option['result']['score']; $y = $loop->index * 56 + 14; @endphp
                <text x="8" y="{{ $y + 19 }}" fill="currentColor" font-size="12">{{ \Illuminate\Support\Str::limit($option['name'], 32) }}<title>{{ $option['name'] }}</title></text>
                @if($score !== null)<rect x="{{ min($axis, $position($score)) }}" y="{{ $y }}" width="{{ abs($position($score) - $axis) }}" height="28" rx="3" fill="var(--color-{{ $score < 0 ? 'error' : ($loop->first ? 'info' : 'primary') }})" opacity="{{ $loop->first ? '0.5' : '0.85' }}"><title>{{ $option['name'] }}: {{ $kind === 'debt' ? $score.' months' : $money($score) }}</title></rect>@endif
                <text x="690" y="{{ $y + 19 }}" fill="currentColor" font-size="12">{{ $score === null ? 'Not cleared' : ($kind === 'debt' ? $score.' months' : $money($score)) }}</text>
            @endforeach
        </svg>
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">@foreach($comparison as $option)<article class="min-w-0 rounded-sm border border-base-300 p-3"><h3 class="break-words text-sm font-semibold">{{ $option['name'] }}</h3><p class="mt-2 text-sm font-medium">{{ $option['result']['headline'] }}</p><p class="mt-1 text-xs opacity-60">{{ $option['result']['detail'] }}</p><dl class="mt-3 space-y-2">@foreach($option['result']['metrics'] as $label => $value)<div><dt class="text-xs opacity-60">{{ $label }}</dt><dd class="text-sm font-semibold {{ $value < 0 ? 'text-error' : '' }}">{{ $money($value) }}</dd></div>@endforeach</dl><div class="mt-3">@include('planning-scenarios.warnings', ['warnings' => $option['result']['warnings']])</div></article>@endforeach</div>
</section>
