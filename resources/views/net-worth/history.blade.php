<div class="space-y-4">
    <section class="overflow-hidden rounded-sm border border-base-300 bg-base-100">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 p-4">
            <div class="flex items-center gap-2">
                <span class="flex size-9 items-center justify-center rounded-sm bg-primary/10 text-primary"><x-lucide-camera class="size-4" aria-hidden="true" /></span>
                <div>
                    <h2 class="font-semibold">Saved snapshots <span class="text-xs font-normal opacity-60">({{ $snapshots->count() }})</span></h2>
                    <p class="mt-0.5 text-xs opacity-65">A saved picture of the records available for that date. Later changes do not rewrite it.</p>
                </div>
            </div>
            <button class="btn btn-primary btn-sm" type="button" data-worth-snapshot><x-lucide-camera class="size-4" aria-hidden="true" />Save snapshot</button>
        </div>
        <div class="max-h-[32rem] overflow-auto" role="region" aria-label="Saved net worth snapshots" tabindex="0">
            <table class="table table-sm">
                <thead class="sticky top-0 z-10 bg-base-200 text-xs">
                    <tr><th>Date</th><th>Assets</th><th>Debts and liabilities</th><th>Net worth</th><th>Details</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($snapshots->reverse() as $snapshot)
                    <tr>
                        <td class="whitespace-nowrap">{{ $snapshot->date->format('d M Y') }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ $money($snapshot->assets_cents) }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ $money($snapshot->debts_cents) }}</td>
                        <td class="whitespace-nowrap font-semibold tabular-nums">{{ $money($snapshot->net_worth_cents) }}</td>
                        <td>
                            <details class="text-xs">
                                <summary class="cursor-pointer rounded-sm py-1 font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-2">View breakdown</summary>
                                <div class="mt-2 min-w-48 space-y-1.5 rounded-sm bg-base-200/50 p-2">
                                    @foreach($snapshot->details['assets'] ?? [] as $item)<p class="break-words">{{ $item['name'] }}: <span class="font-medium tabular-nums">{{ $money($item['amount']) }}</span> &middot; valued {{ $item['date'] }}</p>@endforeach
                                    @foreach($snapshot->details['debts'] ?? [] as $item)<p class="break-words">{{ $item['name'] }} (debt): <span class="font-medium tabular-nums">{{ $money($item['amount']) }}</span></p>@endforeach
                                    @foreach($snapshot->details['liabilities'] ?? [] as $item)<p class="break-words">{{ $item['name'] }} (liability): <span class="font-medium tabular-nums">{{ $money($item['amount']) }}</span> &middot; valued {{ $item['date'] }}</p>@endforeach
                                    @if(($snapshot->details['excluded'] ?? 0) > 0)<p class="text-warning">{{ $snapshot->details['excluded'] }} currently tracked items had no recorded value by this date and were excluded.</p>@endif
                                    @if(($snapshot->details['stale'] ?? 0) > 0)<p class="text-warning">{{ $snapshot->details['stale'] }} valuations were more than 30 days old.</p>@endif
                                </div>
                            </details>
                        </td>
                        <td>
                            <form method="post" action="{{ route('net-worth.snapshots.destroy', $snapshot) }}" data-confirm="Remove this snapshot from your trend? Current assets and liabilities will be kept. You can restore the snapshot." data-confirm-title="Remove snapshot?" data-confirm-label="Remove">
                                @csrf @method('DELETE')
                                <button class="btn btn-ghost btn-xs text-error"><x-lucide-trash-2 class="size-3.5" aria-hidden="true" />Remove</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="p-8 text-center"><span class="mx-auto flex size-10 items-center justify-center rounded-sm bg-primary/10 text-primary"><x-lucide-camera class="size-5" aria-hidden="true" /></span><p class="mt-2 font-medium">Your timeline starts with a snapshot</p><p class="mt-1 text-xs opacity-65">Save one to start tracking changes over time.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-sm border border-base-300 bg-base-100">
        <div class="flex items-center gap-2 border-b border-base-300 p-4">
            <span class="flex size-9 items-center justify-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-4" aria-hidden="true" /></span>
            <div><h2 class="font-semibold">Asset valuation history <span class="text-xs font-normal opacity-60">({{ $history->count() }})</span></h2><p class="mt-0.5 text-xs opacity-65">Every recorded asset value, including older estimates.</p></div>
        </div>
        <div class="max-h-[32rem] overflow-auto" role="region" aria-label="Asset valuation history" tabindex="0">
            <table class="table table-sm">
                <thead class="sticky top-0 z-10 bg-base-200 text-xs"><tr><th>Date</th><th>Asset</th><th>Value</th><th>Notes</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($history as $value)
                    @php $asset = $value->asset; $current = $assets->firstWhere('asset.id', $asset->id); $canRemoveValue = $current !== null && $current['asset']->valuations->count() > 1; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $value->date->format('d M Y') }}</td>
                        <td class="min-w-36 break-words font-medium">{{ $asset->name }}</td>
                        <td class="whitespace-nowrap tabular-nums">{{ $money($value->amount_cents) }}</td>
                        <td class="min-w-40 max-w-sm whitespace-normal text-xs">{{ $value->notes }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <button class="btn btn-ghost btn-xs" type="button" aria-label="Edit valuation for {{ $asset->name }} on {{ $value->date->format('d M Y') }}" data-asset-value="{{ json_encode(['asset_id' => $asset->id, 'valuation_id' => $value->id, 'action' => route('net-worth.values.store', $asset), 'amount' => $amount($value->amount_cents), 'date' => $value->date->toDateString(), 'notes' => $value->notes]) }}"><x-lucide-pencil class="size-3.5" aria-hidden="true" />Edit</button>
                                @if($canRemoveValue)
                                <form method="post" action="{{ route('net-worth.values.destroy', [$asset, $value]) }}" data-confirm="Remove this valuation? The latest remaining value will be used for current net worth. Saved snapshots are kept. You can undo this." data-confirm-title="Remove valuation?" data-confirm-label="Remove">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-xs text-error"><x-lucide-trash-2 class="size-3.5" aria-hidden="true" />Remove</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="p-8 text-center"><span class="mx-auto flex size-10 items-center justify-center rounded-sm bg-success/10 text-success"><x-lucide-clock-3 class="size-5" aria-hidden="true" /></span><p class="mt-2 font-medium">No valuations yet</p><p class="mt-1 text-xs opacity-65">Record an asset value to see its history here.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($deletedSnapshots->isNotEmpty() || $deletedValues->isNotEmpty())
    <details class="rounded-sm border border-base-300 bg-base-100 p-4">
        <summary class="flex cursor-pointer items-center gap-2 font-semibold"><x-lucide-trash-2 class="size-4 opacity-60" aria-hidden="true" />Removed history <span class="text-xs font-normal opacity-60">({{ $deletedSnapshots->count() + $deletedValues->count() }})</span></summary>
        <div class="mt-3 max-h-64 space-y-2 overflow-y-auto" aria-label="Removed records available to restore">
            @foreach($deletedSnapshots as $snapshot)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-sm border border-base-300 p-2 text-sm"><span>Snapshot &middot; {{ $snapshot->date->format('d M Y') }}</span><form method="post" action="{{ route('net-worth.snapshots.restore', $snapshot) }}">@csrf<button class="btn btn-ghost btn-xs"><x-lucide-undo-2 class="size-3.5" aria-hidden="true" />Restore</button></form></div>
            @endforeach
            @foreach($deletedValues as $value)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-sm border border-base-300 p-2 text-sm"><span class="break-words">{{ $value->asset->name }} &middot; {{ $value->date->format('d M Y') }}</span><form method="post" action="{{ route('net-worth.values.restore', [$value->asset_id, $value]) }}">@csrf<button class="btn btn-ghost btn-xs"><x-lucide-undo-2 class="size-3.5" aria-hidden="true" />Restore value</button></form></div>
            @endforeach
        </div>
    </details>
    @endif
</div>
