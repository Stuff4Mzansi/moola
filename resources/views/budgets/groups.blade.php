<section class="space-y-4" aria-labelledby="budget-groups-title">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 id="budget-groups-title" class="text-xl font-semibold">Category groups <span class="badge badge-ghost badge-sm">Optional</span></h3><p class="mt-1 text-xs opacity-60">Create your own groups and assign categories below. Limits are a percentage of expected income.</p></div>@if($canEdit)<button class="btn btn-sm btn-outline" type="button" data-open-dialog="budget-group">Add group</button>@endif</div>
    @if($groups->isEmpty())
    <div class="rounded-sm border border-dashed border-base-300 p-5"><p class="text-sm opacity-60">No groups yet. Keep a simple category budget, or create groups such as Needs, Wants, and Savings for a 50/30/20 plan.</p></div>
    @else
    <p class="text-xs opacity-60" data-budget-live="group-percentage">{{ number_format($groupPercentageTotal / 100, 2) }}% assigned to group limits &middot; {{ number_format((10000 - $groupPercentageTotal) / 100, 2) }}% not assigned. Category allocations stay independent.</p>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($groupRows as $groupRow)
        @php $group = $groupRow['group']; @endphp
        <article class="rounded-sm border border-base-300 bg-base-100 p-4 space-y-3">
            @if($canEdit)<form action="{{ $actionUrl('group-save') }}" method="post" data-budget-action data-budget-autosave class="space-y-3">@csrf<input type="hidden" name="version" value="{{ $period->version }}"><input type="hidden" name="id" value="{{ $group->id }}"><label class="block"><span class="label">Group name</span><input class="input input-sm w-full" name="name" value="{{ $group->name }}" required maxlength="100"></label><label class="block"><span class="label">Limit (% of expected income)</span><input class="input input-sm w-full" type="number" name="percentage" value="{{ $group->percentage_basis_points === null ? '' : $amount($group->percentage_basis_points) }}" min="0" max="100" step="0.01" placeholder="No limit"></label><noscript><button class="btn btn-sm" type="submit">Save group</button></noscript></form>@else<h4 class="font-semibold">{{ $group->name }}</h4>@endif
            <div class="space-y-1 text-xs" data-budget-live="group-plan-{{ $group->id }}"><p>{{ $groupRow['category_count'] }} categories &middot; Planned {{ $money($groupRow['planned']) }}</p><p>Spent {{ $money($groupRow['spent']) }} @if($groupRow['limit'] !== null) / {{ $money($groupRow['limit']) }} group limit @endif</p>@if($groupRow['limit'] !== null && $groupRow['planned'] > $groupRow['limit'])<p class="text-error">Category allocations exceed this group limit by {{ $money($groupRow['planned'] - $groupRow['limit']) }}.</p>@endif</div>
            @if($canEdit)<form action="{{ $actionUrl('group-remove') }}" method="post" data-budget-action data-confirm="Remove this group? Its categories become ungrouped. Category allocations and expenses are kept.">@csrf<input type="hidden" name="version" value="{{ $period->version }}"><input type="hidden" name="id" value="{{ $group->id }}"><button class="btn btn-xs btn-ghost" type="submit">Remove group</button></form>@endif
        </article>
        @endforeach
    </div>
    @endif
</section>
