@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="mx-auto max-w-7xl space-y-4" data-dashboard-customization data-layout-url="{{ route('dashboard.layout.update') }}" x-data="dashboardLayout" x-on:click="handleClick($event)" x-on:dragstart="handleDragStart($event)" x-on:dragover="handleDragOver($event)" x-on:drop="handleDrop($event)" x-on:dragend="handleDragEnd()" x-on:pointerdown="handlePointerDown($event)" x-on:pointermove="handlePointerMove($event)" x-on:pointerup="handlePointerUp($event)" x-on:pointercancel="handlePointerCancel()" x-on:beforeunload.window="handleBeforeUnload($event)">
    @php
        $goalProgress = $savingsOverview['target'] > 0 ? min(100, (int) floor($savingsOverview['saved'] * 100 / $savingsOverview['target'])) : 0;
        $money = fn (int $cents): string => $currencyPrefix.number_format($cents / 100, 2);
    @endphp
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-xl font-semibold">Your financial snapshot</h1><p class="mt-0.5 text-xs opacity-65">{{ now()->format('l, j F') }} · Here's what needs your attention.</p></div>
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-primary btn-sm" href="{{ route('budgets.index', ['tab' => 'expenses']) }}">Record spending</a>
            <a class="btn btn-outline btn-sm" href="{{ route('payment-calendar.index') }}">Payment calendar</a>
            <button class="btn btn-ghost btn-sm" type="button" x-on:click="$refs.dashboardDetails.open = true; $nextTick(() => $refs.layoutEdit.focus())">Customize</button>
        </div>
    </header>
    <p class="{{ session('status') ? 'text-sm opacity-65' : 'sr-only' }}" data-layout-status role="status" aria-live="polite" x-text="statusMessage || @js(session('status'))">{{ session('status') }}</p>
    <section aria-label="Financial summary">
        <dl class="grid grid-cols-2 items-stretch gap-2 sm:grid-cols-3 xl:grid-cols-4">
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-success/70 bg-success/5 p-3"><dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-4" aria-hidden="true" /></span>Unreserved cash</dt><dd class="mt-1 break-words text-base font-semibold tabular-nums">{{ $money($liquidityOverview['free']) }}</dd><p class="mt-0.5 text-[10px] opacity-55">Classified cash after reserves</p></div>
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-primary/70 bg-primary/5 p-3">
                <dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><x-lucide-chart-no-axes-combined class="size-4" aria-hidden="true" /></span><a href="{{ route('net-worth.index') }}">Net Worth</a></dt>
                <dd class="mt-1 break-words text-base font-semibold tabular-nums {{ $netWorthOverview['netWorth'] < 0 ? 'text-error' : '' }}">{{ $money($netWorthOverview['netWorth']) }}</dd>
                <p class="mt-0.5 break-words text-[10px] opacity-55">Assets {{ $money($netWorthOverview['assetTotal']) }} · Debts and Liabilities {{ $money($netWorthOverview['debtTotal'] + $netWorthOverview['liabilityTotal']) }}</p>
                @if($netWorthOverview['change'] !== null)
                    <p @class(['mt-1 flex items-center gap-1 text-[10px] font-medium tabular-nums', 'text-success' => $netWorthOverview['change'] > 0, 'text-error' => $netWorthOverview['change'] < 0, 'opacity-60' => $netWorthOverview['change'] === 0])>
                        @if($netWorthOverview['change'] > 0)
                            <span class="grid size-5 shrink-0 place-items-center text-success"><x-lucide-trending-up class="size-3.5" aria-hidden="true" /></span>
                            Increased by {{ $money($netWorthOverview['change']) }}
                        @elseif($netWorthOverview['change'] < 0)
                            <span class="grid size-5 shrink-0 place-items-center text-error"><x-lucide-trending-down class="size-3.5" aria-hidden="true" /></span>
                            Decreased by {{ $money(abs($netWorthOverview['change'])) }}
                        @else
                            <span class="grid size-5 shrink-0 place-items-center text-base-content/60"><x-lucide-minus class="size-3.5" aria-hidden="true" /></span>
                            No change
                        @endif
                        <span class="font-normal opacity-75">since {{ $netWorthOverview['previous']->date->format('d M Y') }}</span>
                    </p>
                @endif
            </div>
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-info/70 bg-info/5 p-3">
                <dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-info/10 text-info"><x-lucide-target class="size-4" aria-hidden="true" /></span>Saved toward goals</dt>
                <dd class="mt-1 break-words text-base font-semibold tabular-nums">{{ $money($savingsOverview['saved']) }}</dd>
                <p class="mt-0.5 text-[10px] opacity-55">@if($savingsOverview['target'] > 0){{ number_format($goalProgress) }}% of {{ $money($savingsOverview['target']) }} target @else No active goal targets @endif</p>
                @if($savingsOverview['target'] > 0)
                    <progress class="progress progress-info mt-1.5 h-1.5 w-full" value="{{ $goalProgress }}" max="100" aria-label="Savings goal progress: {{ $goalProgress }} percent of target"></progress>
                @endif
            </div>
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-warning/70 bg-warning/5 p-3"><dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-warning/10 text-warning"><x-lucide-repeat-2 class="size-4" aria-hidden="true" /></span>Monthly subscriptions</dt><dd class="mt-1 break-words text-base font-semibold tabular-nums">{{ $money($monthlyCostCents) }}</dd><p class="mt-0.5 text-[10px] opacity-55">{{ $activeCount }} active recurring {{ $activeCount === 1 ? 'payment' : 'payments' }}</p></div>
        </dl>
        <p class="mt-2 text-[10px] leading-snug opacity-55">Unreserved cash is based on assets you've classified as liquid, not a live bank balance. Budget totals stay separate because shared budgets can overlap.</p>
    </section>
    <section class="flex flex-wrap items-center justify-between gap-2 rounded-sm border border-base-300 bg-base-100 px-3 py-2" aria-label="Dashboard budget filter">
        <div class="min-w-0">
            <h2 class="text-xs font-semibold">Dashboard budget period</h2>
            <p class="text-[10px] opacity-60">Budget widgets use this selection. Your choice is saved to your account.</p>
        </div>
        @if($dashboardBudgetOptions->flatMap->periods->isNotEmpty())
            <form method="post" action="{{ route('dashboard.budget-period.update') }}" class="flex min-w-0 items-center gap-2">
                @csrf
                <label for="dashboard-budget-period" class="sr-only">Choose dashboard budget and period</label>
                <select class="select select-xs w-full max-w-80" id="dashboard-budget-period" name="period_id" x-on:change="$el.form.requestSubmit()">
                    @foreach($dashboardBudgetOptions as $budget)
                        @if($budget->periods->isNotEmpty())
                            <optgroup label="{{ $budget->name }} · {{ $budget->scope === 'household' ? 'Shared' : 'Personal' }}">
                                @foreach($budget->periods as $period)
                                    <option value="{{ $period->id }}" @selected($dashboardPeriod?->id === $period->id)>{{ $budget->name }} · {{ $period->name }} · {{ $period->start_date->format('j M Y') }}–{{ $period->end_date->format('j M Y') }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline btn-xs shrink-0" type="submit">Apply</button></noscript>
            </form>
        @else
            <a class="btn btn-outline btn-xs" href="{{ route('budgets.index') }}">Create a budget period</a>
        @endif
    </section>
    @include('dashboard.budgets')
    <details class="group rounded-sm border border-base-300 bg-base-100" x-ref="dashboardDetails">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"><span><span class="block text-sm font-semibold">Explore detailed views</span><span class="mt-0.5 block text-[11px] opacity-60">Budget breakdowns, histories, forecasts and charts</span></span><x-lucide-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" /></summary>
        <div class="space-y-3 border-t border-base-300 p-3">
            <section id="dashboard-customize" class="rounded-sm border border-primary/30 bg-base-100 p-4" data-layout-panel hidden x-bind:hidden="!editing">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold">Customize your dashboard</h2><p class="mt-1 text-xs opacity-65">Choose widgets below. Reorder with arrow buttons or drag handles. Resize with the size menus or corner handle.</p></div><div class="flex flex-wrap gap-2"><button class="btn btn-primary btn-sm" type="button" data-layout-save x-bind:disabled="saving" x-on:click="save()">Save layout</button><button class="btn btn-ghost btn-sm" type="button" data-layout-cancel x-bind:disabled="saving" x-on:click="cancel()">Cancel</button><button class="btn btn-outline btn-sm" type="button" data-layout-reset x-bind:disabled="saving" x-on:click="reset()">Reset to default</button></div></div>
                <fieldset class="mt-4"><legend class="text-xs font-medium">Visible widgets</legend><div class="mt-2 flex flex-wrap gap-x-5 gap-y-3">@foreach($dashboardLayout as $widget)<label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox checkbox-sm" data-layout-visible="{{ $widget['id'] }}" @checked($widget['visible']) x-bind:checked="widget('{{ $widget['id'] }}').visible" x-on:change="setWidget('{{ $widget['id'] }}', { visible: $event.target.checked })" x-bind:disabled="saving">{{ $dashboardWidgets[$widget['id']] }}</label>@endforeach</div></fieldset>
            </section>
            <div class="flex justify-end"><button class="btn btn-outline btn-sm" type="button" data-layout-edit x-ref="layoutEdit" x-bind:hidden="editing" aria-controls="dashboard-customize" x-on:click="beginEditing()">Customize detailed views <x-lucide-sliders-horizontal class="size-4" /></button></div>
            <div class="rounded-sm border border-dashed border-base-300 p-6 text-center" data-layout-empty x-bind:hidden="hasVisibleWidgets"><p class="font-semibold">No detailed views selected</p><p class="mt-1 text-sm opacity-65">Customize the dashboard to choose the reports you want here.</p></div>
            <div class="dashboard-widget-grid" data-layout-grid x-ref="grid">
                @foreach($dashboardLayout as $widget)
                    <article class="dashboard-widget min-w-0" data-layout-widget="{{ $widget['id'] }}" data-width="{{ $widget['width'] }}" data-height="{{ $widget['height'] }}" style="order: {{ $loop->index }}" x-bind:data-width="widget('{{ $widget['id'] }}').width" x-bind:data-height="widget('{{ $widget['id'] }}').height" x-bind:style="'order: ' + position('{{ $widget['id'] }}')" @unless($widget['visible']) hidden @endunless x-bind:hidden="!widget('{{ $widget['id'] }}').visible" x-bind:data-dragging="draggedId === '{{ $widget['id'] }}' ? 'true' : null" x-bind:data-drop-target="dropTargetId === '{{ $widget['id'] }}' ? 'true' : null">
                        <div class="dashboard-widget-toolbar flex flex-wrap items-center justify-between gap-2 rounded-t-sm border border-base-300 bg-base-100 p-2" data-layout-toolbar hidden x-bind:hidden="!editing">
                            <div class="flex min-w-0 items-center gap-1"><button class="btn btn-ghost btn-xs cursor-grab" type="button" draggable="true" data-layout-drag aria-label="Drag {{ $dashboardWidgets[$widget['id']] }}"><x-lucide-grip-vertical class="size-4" /></button><span class="text-xs font-semibold">{{ $dashboardWidgets[$widget['id']] }}</span></div>
                            <div class="flex flex-wrap items-center gap-1"><button type="button" class="btn btn-ghost btn-xs" data-layout-move="up" aria-label="Move {{ $dashboardWidgets[$widget['id']] }} earlier" x-bind:disabled="saving"><x-lucide-arrow-up class="size-3" /></button><button type="button" class="btn btn-ghost btn-xs" data-layout-move="down" aria-label="Move {{ $dashboardWidgets[$widget['id']] }} later" x-bind:disabled="saving"><x-lucide-arrow-down class="size-3" /></button><label><span class="sr-only">{{ $dashboardWidgets[$widget['id']] }} width</span><select class="select select-xs" data-layout-width x-bind:value="widget('{{ $widget['id'] }}').width" x-on:change="setWidget('{{ $widget['id'] }}', { width: $event.target.value })" x-bind:disabled="saving">@foreach(['small' => 'Small', 'medium' => 'Medium', 'wide' => 'Full width'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label><span class="sr-only">{{ $dashboardWidgets[$widget['id']] }} height</span><select class="select select-xs" data-layout-height x-bind:value="widget('{{ $widget['id'] }}').height" x-on:change="setWidget('{{ $widget['id'] }}', { height: $event.target.value })" x-bind:disabled="saving">@foreach(['auto' => 'Auto height', 'compact' => 'Compact', 'regular' => 'Regular', 'tall' => 'Tall'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><button class="btn btn-ghost btn-xs" type="button" data-layout-hide aria-label="Hide {{ $dashboardWidgets[$widget['id']] }}" x-bind:disabled="saving"><x-lucide-eye-off class="size-3.5" /></button></div>
                        </div>
                        <div class="dashboard-widget-body min-w-0" tabindex="0" aria-label="{{ $dashboardWidgets[$widget['id']] }} widget">@include('dashboard.'.$widget['id'])</div>
                        <div class="flex justify-end rounded-b-sm border border-t-0 border-base-300 bg-base-100 px-2" data-layout-toolbar hidden x-bind:hidden="!editing"><button type="button" class="btn btn-ghost btn-xs touch-none cursor-nwse-resize" data-layout-resize x-bind:disabled="saving" aria-label="Resize {{ $dashboardWidgets[$widget['id']] }}; width and height menus are also available"><x-lucide-move-diagonal-2 class="size-4" /></button></div>
                    </article>
                @endforeach
            </div>
        </div>
    </details>
    <noscript><p class="text-sm opacity-65">Enable JavaScript to customize your dashboard. Your saved layout is still displayed.</p></noscript>
    <script type="application/json" data-layout-data>{!! json_encode($dashboardLayout, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/json" data-layout-defaults>{!! json_encode($dashboardDefaults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR) !!}</script>
</div>
@endsection
