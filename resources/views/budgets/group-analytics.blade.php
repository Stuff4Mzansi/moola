<section data-budget-live="group-analytics" class="space-y-4">
    @if($groups->isNotEmpty())
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold">Your group balance</h3><p class="mt-1 text-xs opacity-60">Percentage limits follow expected income. Group totals include all assigned categories.</p></div><button class="link text-xs" type="button" data-budget-go="plan">Manage groups</button></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach($groupRows as $groupRow)
    <article class="rounded-sm border border-base-300 bg-base-100 p-5 space-y-3">
        <div class="flex items-start justify-between gap-3"><h4 class="font-semibold break-words">{{ $groupRow['group']->name }}</h4><span class="badge badge-outline shrink-0">{{ $groupRow['group']->percentage_basis_points === null ? 'No limit' : rtrim(rtrim($amount($groupRow['group']->percentage_basis_points), '0'), '.').'%' }}</span></div>
        <div><p class="text-xs opacity-60">Spent @if($groupRow['limit'] !== null) / group limit @endif</p><p @class(['text-xl font-semibold', 'text-error' => $groupRow['remaining'] !== null && $groupRow['remaining'] < 0])>{{ $money($groupRow['spent']) }}@if($groupRow['limit'] !== null)<span class="text-sm font-normal opacity-60"> / {{ $money($groupRow['limit']) }}</span>@endif</p></div>
        @if($groupRow['limit'] !== null)<progress @class(['progress w-full', 'progress-error' => $groupRow['remaining'] < 0, 'progress-primary' => $groupRow['remaining'] >= 0]) max="{{ max(1, $groupRow['limit']) }}" value="{{ min($groupRow['spent'], max(1, $groupRow['limit'])) }}" aria-label="{{ $groupRow['group']->name }} spending against group limit"></progress><p @class(['text-xs', 'text-error' => $groupRow['remaining'] < 0])>{{ $groupRow['remaining'] < 0 ? 'Over limit by ' : 'Remaining ' }}{{ $money(abs($groupRow['remaining'])) }}</p>@endif
        <p class="text-xs opacity-60">Planned: {{ $money($groupRow['planned']) }} &middot; {{ $groupRow['category_count'] }} categories</p>
        @if($groupRow['limit'] !== null && $groupRow['planned'] > $groupRow['limit'])<p class="text-xs text-error">Planned categories exceed the group limit.</p>@endif
        @if($groupRow['upcoming'] > 0)<p @class(['text-xs', 'text-error' => $groupRow['available'] !== null && $groupRow['available'] < 0])>Unpaid scheduled expenses: {{ $money($groupRow['upcoming']) }}@if($groupRow['available'] !== null) &middot; After these: {{ $money($groupRow['available']) }} @endif</p>@endif
    </article>
    @endforeach
    </div>
    <p class="text-xs opacity-60">Ungrouped categories: {{ $money($categoryRows->filter(fn (array $row): bool => $row['category']->budget_group_id === null)->sum('spent')) }} spent. @if($totals['expected'] === 0)Add expected income to give percentage limits a monetary value.@endif</p>
    @endif
</section>
