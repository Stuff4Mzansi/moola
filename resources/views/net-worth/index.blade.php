@extends('layouts.app')
@section('title', 'Net worth')
@section('content')
@php
    $money = fn (int $cents): string => $currencyPrefix.number_format($cents / 100, 2);
    $amount = fn (int $cents): string => number_format($cents / 100, 2, '.', '');
    $kinds = ['bank' => 'Bank / cash', 'investment' => 'Investments', 'property' => 'Property', 'vehicle' => 'Vehicles', 'other' => 'Other assets'];
    $liabilityKinds = ['credit_card' => 'Credit card', 'mortgage' => 'Mortgage', 'auto_loan' => 'Auto loan', 'student_loan' => 'Student loan', 'personal_loan' => 'Personal loan', 'tax' => 'Tax owed', 'other' => 'Other liability'];
    $accessLabels = ['unknown' => 'Unclassified', 'immediate' => 'Accessible now', 'delayed' => 'Withdrawal notice', 'dated' => 'Confirmed access date', 'sale' => 'Requires sale'];
    $obligationsLabel = config('features.debt_tracking') ? 'Tracked debt + liabilities' : 'Tracked liabilities';
    $tab = in_array(request('tab'), ['assets', 'liabilities', 'liquidity', 'history'], true) ? request('tab') : 'overview';
@endphp
<div class="mx-auto max-w-7xl space-y-4" data-net-worth-page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Net worth</h1><p class="mt-1 text-sm opacity-65">See what you own, what you owe, and how that changes.</p></div>
        <div class="flex flex-wrap items-center gap-2">
            @if($tab === 'overview')<button class="btn btn-ghost btn-sm" type="button" data-worth-snapshot><x-lucide-camera class="size-4" aria-hidden="true" />Save snapshot</button>@endif
            @if(in_array($tab, ['overview', 'assets'], true))<button class="btn btn-primary btn-sm" type="button" data-asset-new><x-lucide-plus class="size-4" aria-hidden="true" />Add asset</button>@endif
            @if(in_array($tab, ['overview', 'liabilities'], true))<button class="btn btn-outline btn-sm" type="button" data-liability-new><x-lucide-plus class="size-4" aria-hidden="true" />Add liability</button>@endif
        </div>
    </div>
    @if(session('status'))<div class="alert alert-success text-sm" role="status">{{ session('status') }}@if(session('undo_asset'))<form method="post" action="{{ route('net-worth.assets.restore', session('undo_asset')) }}">@csrf<button class="btn btn-ghost">Undo</button></form>@elseif(session('undo_valuation'))<form method="post" action="{{ route('net-worth.values.restore', session('undo_valuation')) }}">@csrf<button class="btn btn-ghost">Undo</button></form>@elseif(session('undo_liability'))<form method="post" action="{{ route('net-worth.liabilities.restore', session('undo_liability')) }}">@csrf<button class="btn btn-ghost">Undo</button></form>@elseif(session('undo_liability_valuation'))<form method="post" action="{{ route('net-worth.liabilities.values.restore', ['liability' => session('undo_liability_valuation')['liability'], 'valuation' => session('undo_liability_valuation')['valuation']]) }}">@csrf<button class="btn btn-ghost">Undo</button></form>@elseif(session('undo_reserve'))<form method="post" action="{{ route('liquidity.reserves.restore', session('undo_reserve')) }}">@csrf<button class="btn btn-ghost">Undo</button></form>@elseif(session('undo_snapshot'))<form method="post" action="{{ route('net-worth.snapshots.restore', session('undo_snapshot')) }}">@csrf<button class="btn btn-ghost">Undo</button></form>@endif</div>@endif
    @if($errors->any())<div class="alert alert-error text-sm" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="overflow-x-auto" role="region" aria-label="Net worth tabs" tabindex="0">
    <nav class="tabs tabs-border w-max min-w-full flex-nowrap" aria-label="Net worth sections">
        @foreach(['overview' => ['Overview', 'chart-no-axes-combined'], 'assets' => ['Assets', 'wallet'], 'liabilities' => ['Liabilities', 'credit-card'], 'liquidity' => ['Liquidity', 'banknote'], 'history' => ['History', 'history']] as $name => [$label, $icon])
        <a @class(['tab inline-flex gap-1.5 whitespace-nowrap', 'tab-active' => $tab === $name]) href="{{ route('net-worth.index', ['tab' => $name]) }}" @if($tab === $name) aria-current="page" @endif>
            @if($icon === 'chart-no-axes-combined')<x-lucide-chart-no-axes-combined class="size-4" aria-hidden="true" />@elseif($icon === 'wallet')<x-lucide-wallet class="size-4" aria-hidden="true" />@elseif($icon === 'credit-card')<x-lucide-credit-card class="size-4" aria-hidden="true" />@elseif($icon === 'banknote')<x-lucide-banknote class="size-4" aria-hidden="true" />@else<x-lucide-history class="size-4" aria-hidden="true" />@endif
            {{ $label }}
        </a>
        @endforeach
    </nav>
    </div>
    @if($tab === 'overview')
        <div class="grid items-stretch gap-3 lg:grid-cols-5">
            <section class="relative flex min-w-0 flex-col justify-between overflow-hidden rounded-sm border border-primary/25 bg-primary/5 p-4 sm:p-5 lg:col-span-3" aria-labelledby="net-worth-current-title">
                <div class="pointer-events-none absolute -right-10 -top-14 size-44 rounded-full bg-primary/5 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p id="net-worth-current-title" class="flex items-center gap-2 text-xs font-medium text-primary"><x-lucide-chart-no-axes-combined class="size-4" aria-hidden="true" />Current net worth</p>
                        <p class="mt-1 text-[11px] opacity-60">Assets minus {{ config('features.debt_tracking') ? 'debts and liabilities' : 'liabilities' }} &middot; Live estimate</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border border-base-300/70 bg-base-100/70 px-2 py-1 text-[10px] tabular-nums opacity-75"><x-lucide-clock-3 class="size-3" aria-hidden="true" />As of {{ $date->format('d M Y') }}</span>
                </div>
                <p @class(['relative mt-4 break-words text-3xl font-bold tracking-tight tabular-nums sm:text-4xl', 'text-error' => $netWorth < 0, 'text-primary' => $netWorth >= 0])>{{ $money($netWorth) }}</p>
                @if($latest)
                <p @class(['relative mt-3 flex flex-wrap items-center gap-1.5 text-xs', 'text-success' => $change >= 0, 'text-error' => $change < 0])>
                    @if($change >= 0)<x-lucide-trending-up class="size-4" aria-hidden="true" />@else<x-lucide-trending-down class="size-4" aria-hidden="true" />@endif
                    {{ $money(abs($change)) }} {{ $change >= 0 ? 'higher' : 'lower' }} than your last snapshot
                    <span class="text-base-content/55">({{ $latest->date->format('d M Y') }})</span>
                </p>
                @else
                <p class="relative mt-3 text-xs opacity-65">Save a snapshot to see how your net worth changes over time.</p>
                @endif
            </section>
            <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:col-span-2 lg:grid-cols-1">
                <section class="flex min-w-0 items-center gap-3 rounded-sm border border-success/20 bg-success/5 p-3 sm:p-4" aria-label="Total assets">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0"><p class="text-xs text-success">Total assets</p><p class="mt-0.5 break-words text-xl font-semibold tabular-nums">{{ $money($assetTotal) }}</p></div>
                </section>
                <section class="flex min-w-0 items-center gap-3 rounded-sm border border-error/20 bg-error/5 p-3 sm:p-4" aria-label="{{ $obligationsLabel }}">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-sm bg-error/10 text-error"><x-lucide-credit-card class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0"><p class="text-xs text-error">{{ $obligationsLabel }}</p><p class="mt-0.5 break-words text-xl font-semibold tabular-nums">{{ $money($debtTotal + $liabilityTotal) }}</p></div>
                </section>
            </div>
        </div>
        @if($stale > 0)<div class="flex flex-wrap items-center justify-between gap-2 rounded-sm bg-warning/10 p-3 text-xs"><p>{{ $stale }} asset or liability {{ $stale === 1 ? 'balance is' : 'balances are' }} more than 30 days old. Refresh them for a clearer picture.</p><a class="btn btn-ghost" href="{{ route('net-worth.index', ['tab' => 'assets']) }}">Update values</a></div>@endif
        <section class="rounded-sm border border-base-300 bg-base-100 p-4">@include('net-worth.chart', ['chartSnapshots' => $snapshots, 'chartId' => 'net-worth-main', 'compact' => false])</section>
        <div class="grid items-stretch gap-3 lg:grid-cols-2">
            <section class="flex min-w-0 flex-col rounded-sm border border-base-300 bg-base-100 p-4" aria-labelledby="net-worth-assets-title">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 id="net-worth-assets-title" class="flex items-center gap-2 font-semibold"><span class="flex size-8 items-center justify-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-4" aria-hidden="true" /></span>What you own</h2><p class="mt-1 text-xs opacity-60">Asset mix by type</p></div>
                    <a class="link link-primary mt-1 shrink-0 text-xs" href="{{ route('net-worth.index', ['tab' => 'assets']) }}">View assets <x-lucide-arrow-right class="inline size-3.5" aria-hidden="true" /></a>
                </div>
                @if($groups->isNotEmpty())
                <div class="mt-3 max-h-64 space-y-3 overflow-y-auto pr-1" tabindex="0" role="region" aria-label="Asset values by type">
                    @foreach($groups as $group)
                    <div>
                        <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2 text-xs"><span class="font-medium">{{ $kinds[$group['kind']] }}</span><span class="tabular-nums">{{ $money($group['amount']) }} <span class="opacity-60">&middot; {{ $group['share'] }}%</span></span></div>
                        <progress class="progress progress-success h-2 w-full rounded-sm" value="{{ $group['share'] }}" max="100" aria-label="{{ $kinds[$group['kind']] }} share of assets"></progress>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="mt-4 rounded-sm bg-base-200/40 p-3"><p class="text-sm opacity-70">Add bank balances, investments, property, or other assets to build your picture.</p><button class="btn btn-primary btn-sm mt-3" type="button" data-asset-new><x-lucide-plus class="size-4" aria-hidden="true" />Add your first asset</button></div>
                @endif
            </section>
            <section class="flex min-w-0 flex-col rounded-sm border border-base-300 bg-base-100 p-4" aria-labelledby="net-worth-obligations-title">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 id="net-worth-obligations-title" class="flex items-center gap-2 font-semibold"><span class="flex size-8 items-center justify-center rounded-sm bg-error/10 text-error"><x-lucide-credit-card class="size-4" aria-hidden="true" /></span>What you owe</h2><p class="mt-1 text-xs opacity-60">Tracked debts and liabilities</p></div>
                    <a class="link link-primary mt-1 shrink-0 text-xs" href="{{ route('net-worth.index', ['tab' => 'liabilities']) }}">View details <x-lucide-arrow-right class="inline size-3.5" aria-hidden="true" /></a>
                </div>
                @php
                    $obligations = $debts->concat($liabilities->map(fn (array $row): array => ['name' => $row['liability']->name, 'amount' => $row['amount'], 'kind' => 'liability']));
                @endphp
                @if($obligations->isNotEmpty())
                <ul class="mt-3 max-h-64 divide-y divide-base-300 overflow-y-auto pr-1" tabindex="0" role="region" aria-label="Tracked debts and liabilities">
                    @foreach($obligations as $row)
                    <li class="flex min-w-0 items-center justify-between gap-3 py-2 text-sm"><span class="min-w-0 break-words">{{ $row['name'] ?? $row['debt']->name }}</span><span class="shrink-0 font-medium tabular-nums text-error">{{ $money($row['amount']) }}</span></li>
                    @endforeach
                </ul>
                @else
                <div class="mt-4 rounded-sm bg-base-200/40 p-3"><p class="text-sm opacity-70">{{ config('features.debt_tracking') ? 'No tracked debts or liabilities. Add loans or manual balances here.' : 'No manual liabilities yet. Add a liability to record a balance.' }}</p><button class="btn btn-outline btn-sm mt-3" type="button" data-liability-new><x-lucide-plus class="size-4" aria-hidden="true" />Add liability</button></div>
                @endif
                <p class="mt-auto pt-3 text-xs opacity-60">@if(config('features.debt_tracking'))Debt balances use starting principal minus recorded principal payments. @endif Manual liabilities are snapshot-based estimates and do not create transactions.</p>
            </section>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-info/20 bg-info/5 p-3">
            <div class="flex items-center gap-2 text-sm"><span class="flex size-8 items-center justify-center rounded-sm bg-info/10 text-info"><x-lucide-banknote class="size-4" aria-hidden="true" /></span><p><span class="text-xs opacity-65">Accessible cash</span><br><span class="font-semibold tabular-nums">{{ $money($liquidity['accessible']) }}</span> <span class="text-xs opacity-60">&middot; {{ $money($liquidity['free']) }} unreserved</span></p></div>
            <a class="btn btn-outline btn-sm" href="{{ route('net-worth.index', ['tab' => 'liquidity']) }}">Explore liquidity and cash flow <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
        </div>
        <p class="text-xs opacity-60">Your assets and {{ config('features.debt_tracking') ? 'debts and liabilities' : 'liabilities' }}, in {{ $currencyCode }}. Savings goals earmark money already held in your assets and are not added again. Values are manually recorded estimates; no bank sync or automatic investment growth.</p>
    @elseif($tab === 'assets')
        <section class="rounded-sm border border-base-300 bg-base-100 p-4" aria-labelledby="net-worth-assets-list-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="net-worth-assets-list-title" class="flex items-center gap-2 font-semibold"><span class="flex size-8 items-center justify-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-4" aria-hidden="true" /></span>Your assets <span class="text-xs font-normal opacity-60">({{ $assets->count() }})</span></h2><p class="mt-1 text-xs opacity-65">Latest recorded balances. Update values without losing older dates.</p></div>
                <div class="flex flex-wrap items-center gap-2">
                    <label><span class="sr-only">Filter assets by type</span><select class="select select-sm" data-asset-filter><option value="">All types</option>@foreach($kinds as $kind => $label)<option value="{{ $kind }}">{{ $label }}</option>@endforeach</select></label>
                </div>
            </div>
        </section>
        <div class="grid items-stretch gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($assets as $row)
            @php $asset = $row['asset']; $value = $row['value']; @endphp
            <article class="flex min-w-0 flex-col overflow-hidden rounded-sm border border-base-300 bg-base-100" data-asset-row="{{ $asset->kind }}">
                <div class="h-1 bg-success/70" aria-hidden="true"></div>
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex min-w-0 items-start gap-2.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-sm bg-success/10 text-success">
                                @if($asset->kind === 'bank')<x-lucide-landmark class="size-4" aria-hidden="true" />@elseif($asset->kind === 'investment')<x-lucide-chart-no-axes-combined class="size-4" aria-hidden="true" />@elseif($asset->kind === 'property')<x-lucide-house class="size-4" aria-hidden="true" />@elseif($asset->kind === 'vehicle')<x-lucide-car-front class="size-4" aria-hidden="true" />@else<x-lucide-box class="size-4" aria-hidden="true" />@endif
                            </span>
                            <div class="min-w-0"><h3 class="break-words font-semibold">{{ $asset->name }}</h3><p class="mt-0.5 break-words text-xs opacity-60">{{ $kinds[$asset->kind] }}@if($asset->institution) <span aria-hidden="true">&middot;</span> {{ $asset->institution }}@endif</p></div>
                        </div>
                        <button class="btn btn-ghost btn-xs shrink-0" type="button" aria-label="Edit {{ $asset->name }}" data-asset-edit="{{ json_encode(['asset_id' => $asset->id, 'action' => route('net-worth.assets.update', $asset), 'name' => $asset->name, 'kind' => $asset->kind, 'institution' => $asset->institution, 'notes' => $asset->notes, 'liquidity' => $asset->liquidity, 'access_days' => $asset->access_days, 'available_date' => $asset->available_date?->toDateString(), 'withdrawal_cost' => $amount($asset->withdrawal_cost_cents), 'value_uncertain' => $asset->value_uncertain]) }}"><x-lucide-pencil class="size-3.5" aria-hidden="true" />Edit</button>
                    </div>
                    <div class="rounded-sm bg-success/5 px-3 py-2.5">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-success/80">Current value</p>
                        <p class="mt-0.5 break-words text-xl font-semibold tabular-nums">{{ $money($row['amount']) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span @class(['inline-flex items-center gap-1.5', 'text-warning' => $row['stale'], 'opacity-65' => !$row['stale']])>
                            @if($row['stale'])<x-lucide-triangle-alert class="size-3.5" aria-hidden="true" />Refresh recommended @else<x-lucide-clock-3 class="size-3.5" aria-hidden="true" />@endif
                            Valued {{ $value->date->format('d M Y') }}
                        </span>
                        <span class="rounded-sm bg-base-200/70 px-2 py-1 text-[10px]">{{ $accessLabels[$asset->liquidity] ?? 'Unclassified' }}</span>
                    </div>
                    @if($row['amount'] !== $value->amount_cents)<p class="text-xs leading-relaxed opacity-65">Balance includes {{ $row['amount'] > $value->amount_cents ? '+' : '-' }}{{ $money(abs($row['amount'] - $value->amount_cents)) }} in savings movements since that recorded value.</p>@endif
                    @if($asset->notes)<p class="break-words text-xs leading-relaxed opacity-65">{{ $asset->notes }}</p>@endif
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-base-300 pt-3">
                        <button class="btn btn-primary btn-sm" type="button" data-asset-value="{{ json_encode(['asset_id' => $asset->id, 'action' => route('net-worth.values.store', $asset), 'amount' => $amount($row['amount']), 'date' => today()->toDateString()]) }}"><x-lucide-refresh-cw class="size-3.5" aria-hidden="true" />Update value</button>
                        <form method="post" action="{{ route('net-worth.assets.destroy', $asset) }}" data-confirm="Remove this asset from current net worth? Its valuation history and saved snapshots are kept. You can restore it." data-confirm-title="Remove asset?" data-confirm-label="Remove">@csrf @method('DELETE')<button class="btn btn-ghost btn-xs text-error"><x-lucide-trash-2 class="size-3.5" aria-hidden="true" />Remove</button></form>
                    </div>
                </div>
            </article>
            @empty
            <div class="rounded-sm border border-dashed border-base-300 bg-base-100 p-8 text-center md:col-span-2 xl:col-span-3"><span class="mx-auto flex size-11 items-center justify-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-5" aria-hidden="true" /></span><h3 class="mt-3 font-semibold">Start with a bank balance</h3><p class="mt-1 text-sm opacity-65">Include savings once as an asset, even if that money funds several goals.</p><button class="btn btn-primary btn-sm mt-3" type="button" data-asset-new><x-lucide-plus class="size-4" aria-hidden="true" />Add asset</button></div>
            @endforelse
        </div>
        <p class="hidden text-xs opacity-60" data-asset-filter-empty>No assets match this type.</p>
        @if($deletedAssets->isNotEmpty())<details class="rounded-sm border border-base-300 bg-base-100 p-3"><summary class="cursor-pointer text-sm font-medium">Removed assets ({{ $deletedAssets->count() }})</summary><div class="mt-3 space-y-2">@foreach($deletedAssets as $asset)<div class="flex justify-between gap-2 text-sm"><span>{{ $asset->name }}</span><form method="post" action="{{ route('net-worth.assets.restore', $asset) }}">@csrf<button class="btn btn-ghost">Restore</button></form></div>@endforeach</div></details>@endif
    @elseif($tab === 'liabilities')
        <div class="flex flex-wrap items-end justify-between gap-3 rounded-sm border border-base-300 bg-base-100 p-4">
            <div><h2 class="flex items-center gap-2 font-semibold"><span class="flex size-8 items-center justify-center rounded-sm bg-error/10 text-error"><x-lucide-credit-card class="size-4" aria-hidden="true" /></span>Your liabilities <span class="text-xs font-normal opacity-60">({{ $liabilities->count() }})</span></h2><p class="mt-1 text-xs opacity-65">Latest recorded balances. Update snapshots to track what you owe over time.</p></div>
            <div class="flex flex-wrap items-center gap-2"><label><span class="sr-only">Filter liabilities by type</span><select class="select select-sm" data-liability-filter><option value="">All types</option>@foreach($liabilityKinds as $kind => $label)<option value="{{ $kind }}">{{ $label }}</option>@endforeach</select></label></div>
        </div>
        <div class="grid items-stretch gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($liabilities as $row)
            @php $liability = $row['liability']; $value = $row['value']; @endphp
            <article class="flex min-w-0 flex-col overflow-hidden rounded-sm border border-base-300 bg-base-100" data-liability-row="{{ $liability->kind }}">
                <div class="h-1 bg-error/70" aria-hidden="true"></div>
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex min-w-0 items-start gap-2.5"><span class="flex size-9 shrink-0 items-center justify-center rounded-sm bg-error/10 text-error">@if($liability->kind === 'mortgage')<x-lucide-house class="size-4" aria-hidden="true" />@elseif($liability->kind === 'auto_loan')<x-lucide-car-front class="size-4" aria-hidden="true" />@else<x-lucide-credit-card class="size-4" aria-hidden="true" />@endif</span><div class="min-w-0"><h3 class="break-words font-semibold">{{ $liability->name }}</h3><p class="mt-0.5 break-words text-xs opacity-60">{{ $liabilityKinds[$liability->kind] }}@if($liability->institution) <span aria-hidden="true">&middot;</span> {{ $liability->institution }}@endif</p></div></div>
                        <button class="btn btn-ghost btn-xs shrink-0" type="button" aria-label="Edit {{ $liability->name }}" data-liability-edit="{{ json_encode(['liability_id' => $liability->id, 'action' => route('net-worth.liabilities.update', $liability), 'name' => $liability->name, 'kind' => $liability->kind, 'institution' => $liability->institution, 'notes' => $liability->notes]) }}"><x-lucide-pencil class="size-3.5" aria-hidden="true" />Edit</button>
                    </div>
                    <div class="rounded-sm bg-error/5 px-3 py-2.5"><p class="text-[10px] font-medium uppercase tracking-wide text-error/80">Outstanding balance</p><p class="mt-0.5 break-words text-xl font-semibold tabular-nums">{{ $money($row['amount']) }}</p></div>
                    <span @class(['inline-flex items-center gap-1.5 text-xs', 'text-warning' => $row['stale'], 'opacity-65' => !$row['stale']])>@if($row['stale'])<x-lucide-triangle-alert class="size-3.5" aria-hidden="true" />Refresh recommended @else<x-lucide-clock-3 class="size-3.5" aria-hidden="true" />@endif Recorded {{ $value->date->format('d M Y') }}</span>
                    @if($liability->notes)<p class="text-xs leading-relaxed opacity-65">{{ $liability->notes }}</p>@endif
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-base-300 pt-3">
                        <button class="btn btn-outline btn-sm" type="button" data-liability-value="{{ json_encode(['liability_id' => $liability->id, 'action' => route('net-worth.liabilities.values.store', $liability), 'amount' => $amount($row['amount']), 'date' => today()->toDateString()]) }}"><x-lucide-refresh-cw class="size-3.5" aria-hidden="true" />Update value</button>
                        <form method="post" action="{{ route('net-worth.liabilities.destroy', $liability) }}" data-confirm="Remove this liability from current net worth? Its valuation history and saved snapshots are kept. You can restore it." data-confirm-title="Remove liability?" data-confirm-label="Remove">@csrf @method('DELETE')<button class="btn btn-ghost btn-xs text-error"><x-lucide-trash-2 class="size-3.5" aria-hidden="true" />Remove</button></form>
                    </div>
                </div>
            </article>
            @empty
            <div class="rounded-sm border border-dashed border-base-300 bg-base-100 p-8 text-center md:col-span-2 xl:col-span-3"><span class="mx-auto flex size-11 items-center justify-center rounded-sm bg-error/10 text-error"><x-lucide-credit-card class="size-5" aria-hidden="true" /></span><h3 class="mt-3 font-semibold">No liabilities recorded</h3><p class="mt-1 text-sm opacity-65">Add a liability to track what you owe without creating debt transactions.</p><button class="btn btn-outline btn-sm mt-3" type="button" data-liability-new><x-lucide-plus class="size-4" aria-hidden="true" />Add liability</button></div>
            @endforelse
        </div>
        <p class="hidden text-xs opacity-60" data-liability-filter-empty>No liabilities match this type.</p>
        @if($deletedLiabilities->isNotEmpty())<details class="rounded-sm border border-base-300 bg-base-100 p-3"><summary class="cursor-pointer text-sm font-medium">Removed liabilities ({{ $deletedLiabilities->count() }})</summary><div class="mt-3 space-y-2">@foreach($deletedLiabilities as $liability)<div class="flex justify-between gap-2 text-sm"><span>{{ $liability->name }}</span><form method="post" action="{{ route('net-worth.liabilities.restore', $liability) }}">@csrf<button class="btn btn-ghost">Restore</button></form></div>@endforeach</div></details>@endif
    @elseif($tab === 'liquidity')
        @include('net-worth.liquidity')
    @else
        @include('net-worth.history')
    @endif
    @include('net-worth.dialogs')
    @php
        $oldAssetRow = $assets->first(fn (array $row): bool => $row['asset']->id === (int) old('asset_id'));
        $oldLiabilityRow = $liabilities->first(fn (array $row): bool => $row['liability']->id === (int) old('liability_id'));
        $reopen = null;
        if ($errors->any() && old('form_kind') === 'value' && $oldAssetRow !== null) {
            $reopen = ['kind' => 'value', 'data' => [...old(), 'action' => route('net-worth.values.store', $oldAssetRow['asset'])]];
        } elseif ($errors->any() && old('form_kind') === 'liability-value' && $oldLiabilityRow !== null) {
            $reopen = ['kind' => 'liability-value', 'data' => [...old(), 'action' => route('net-worth.liabilities.values.store', $oldLiabilityRow['liability'])]];
        } elseif ($errors->any() && old('form_kind') === 'liability') {
            $reopen = ['kind' => 'liability', 'data' => [...old(), 'action' => $oldLiabilityRow ? route('net-worth.liabilities.update', $oldLiabilityRow['liability']) : route('net-worth.liabilities.store'), 'method' => $oldLiabilityRow ? 'PUT' : 'POST']];
        } elseif ($errors->any() && old('form_kind') === 'asset') {
            $reopen = ['kind' => 'asset', 'data' => [...old(), 'action' => $oldAssetRow ? route('net-worth.assets.update', $oldAssetRow['asset']) : route('net-worth.assets.store'), 'method' => $oldAssetRow ? 'PUT' : 'POST']];
        } elseif ($errors->any() && old('form_kind') === 'snapshot') {
            $reopen = ['kind' => 'snapshot', 'data' => old()];
        }
    @endphp
    <script type="application/json" data-worth-reopen>{!! json_encode($reopen, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</div>
@endsection
