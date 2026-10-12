@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="mx-auto max-w-7xl space-y-4" data-dashboard-customization data-layout-url="{{ route('dashboard.layout.update') }}" x-data="dashboardLayout" x-on:click="handleClick($event)" x-on:dragstart="handleDragStart($event)" x-on:dragover="handleDragOver($event)" x-on:drop="handleDrop($event)" x-on:dragend="handleDragEnd()" x-on:pointerdown="handlePointerDown($event)" x-on:pointermove="handlePointerMove($event)" x-on:pointerup="handlePointerUp($event)" x-on:pointercancel="handlePointerCancel()" x-on:beforeunload.window="handleBeforeUnload($event)">
    @php
        $activeBudgetCount = $budgetOverview['cards']->count();
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
        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-success/70 bg-success/5 p-3"><dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-success/10 text-success"><x-lucide-wallet class="size-4" aria-hidden="true" /></span>Unreserved cash</dt><dd class="mt-1 break-words text-base font-semibold tabular-nums">{{ $money($liquidityOverview['free']) }}</dd><p class="mt-0.5 text-[10px] opacity-55">Classified cash after reserves</p></div>
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-primary/70 bg-primary/5 p-3"><dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-primary/10 text-primary"><x-lucide-chart-no-axes-combined class="size-4" aria-hidden="true" /></span>Budgets in progress</dt><dd class="mt-1 text-base font-semibold tabular-nums">{{ $activeBudgetCount }}</dd><p class="mt-0.5 text-[10px] opacity-55">Visible personal and shared budgets</p></div>
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-info/70 bg-info/5 p-3"><dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-info/10 text-info"><x-lucide-target class="size-4" aria-hidden="true" /></span>Saved toward goals</dt><dd class="mt-1 break-words text-base font-semibold tabular-nums">{{ $money($savingsOverview['saved']) }}</dd><p class="mt-0.5 text-[10px] opacity-55">@if($savingsOverview['target'] > 0){{ number_format($goalProgress) }}% of {{ $money($savingsOverview['target']) }} target @else No active goal targets @endif</p></div>
            <div class="min-w-0 rounded-sm border border-l-2 border-base-300 border-l-warning/70 bg-warning/5 p-3"><dt class="flex items-center gap-2 text-[11px] opacity-75"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-warning/10 text-warning"><x-lucide-repeat-2 class="size-4" aria-hidden="true" /></span>Monthly subscriptions</dt><dd class="mt-1 break-words text-base font-semibold tabular-nums">{{ $money($monthlyCostCents) }}</dd><p class="mt-0.5 text-[10px] opacity-55">{{ $activeCount }} active recurring {{ $activeCount === 1 ? 'payment' : 'payments' }}</p></div>
        </dl>
        <p class="mt-2 text-[10px] leading-snug opacity-55">Unreserved cash is based on assets you've classified as liquid, not a live bank balance. Budget totals stay separate because shared budgets can overlap.</p>
    </section>
    <div class="grid items-stretch gap-3 lg:grid-cols-2">
        @include('dashboard.monthly-budget')
        <section class="flex min-w-0 flex-col overflow-hidden rounded-sm border border-base-300 bg-base-100 p-3 sm:p-4" aria-labelledby="dashboard-cash-flow-title">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="dashboard-cash-flow-title" class="flex items-center gap-2 text-base font-semibold"><span class="grid size-7 shrink-0 place-items-center rounded-sm bg-info/10 text-info"><x-lucide-arrow-left-right class="size-4" aria-hidden="true" /></span>Cash-flow outlook</h2>
                    <p class="mt-0.5 text-[11px] opacity-60">Expected income and spending over the next {{ $liquidityOverview['horizon'] }} days.</p>
                </div>
                <a class="btn btn-ghost btn-xs shrink-0 text-primary" href="{{ route('net-worth.index', ['tab' => 'liquidity']) }}">Plan cash flow <x-lucide-arrow-up-right class="size-3.5" aria-hidden="true" /></a>
            </header>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-2 rounded-sm bg-base-200/60 px-3 py-2">
                <div>
                    <p class="text-[10px] opacity-60">Forecast low point</p>
                    <p @class(['mt-0.5 text-base font-semibold tabular-nums', 'text-error' => $liquidityOverview['lowest']['low'] < 0, 'text-success' => $liquidityOverview['lowest']['low'] >= 0])>{{ $currencyPrefix }}{{ number_format($liquidityOverview['lowest']['low'] / 100, 2) }}</p>
                </div>
                <p class="text-right text-[11px] opacity-65">Around {{ \Carbon\CarbonImmutable::parse($liquidityOverview['lowest']['date'])->format('j M') }}</p>
            </div>
            <h3 class="mt-3 flex items-center gap-1.5 text-xs font-medium opacity-80"><x-lucide-calendar-clock class="size-3.5 text-primary" aria-hidden="true" />Upcoming events</h3>
            <div class="mt-1.5 max-h-52 flex-1 divide-y divide-base-300 overflow-y-auto rounded-sm border border-base-300/70" tabindex="0" role="region" aria-label="Upcoming cash-flow events">
                @forelse($liquidityOverview['events'] as $event)
                    @php
                        $eventDate = \Carbon\CarbonImmutable::parse($event['date']);
                        $eventClasses = 'flex items-center gap-2.5 px-2.5 py-2 hover:bg-base-200/50';
                    @endphp
                    @if($event['url'])
                        <a class="{{ $eventClasses }}" href="{{ $event['url'] }}">
                    @else
                        <div class="{{ $eventClasses }}">
                    @endif
                        <time class="flex w-9 shrink-0 flex-col items-center rounded-sm {{ $event['kind'] === 'income' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }} py-1" datetime="{{ $eventDate->toDateString() }}">
                            <span class="text-[9px] uppercase opacity-70">{{ $eventDate->format('M') }}</span>
                            <span class="text-sm font-semibold leading-tight tabular-nums">{{ $eventDate->format('d') }}</span>
                        </time>
                        <span class="min-w-0 flex-1">
                            <span class="block break-words text-xs font-medium">{{ $event['name'] }}</span>
                            <span class="mt-0.5 block text-[10px] {{ $event['kind'] === 'income' ? 'text-success' : 'opacity-55' }}">{{ $event['kind'] === 'income' ? 'Expected income' : 'Forecast outflow' }}</span>
                        </span>
                        <span class="shrink-0 text-right text-xs font-semibold tabular-nums {{ $event['kind'] === 'income' ? 'text-success' : 'text-warning' }}">{{ $event['kind'] === 'income' ? '+' : '−' }}{{ $money($event['amount']) }}</span>
                    @if($event['url'])
                        </a>
                    @else
                        </div>
                    @endif
                @empty
                    <p class="px-3 py-4 text-xs opacity-65">No upcoming income or spending is scheduled in this forecast window.</p>
                @endforelse
            </div>
            <p class="mt-2 text-[10px] leading-snug opacity-55">Forecasts use your recorded schedules, selected budget assumptions, and classified cash; they are estimates, not confirmed payments.</p>
        </section>
    </div>
    <section class="rounded-sm border border-base-300 bg-base-100" aria-labelledby="attention-title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-3 py-2"><div><h2 id="attention-title" class="flex items-center gap-2 text-sm font-semibold">@if($attentionItems->isEmpty())<x-lucide-badge-check class="size-4 text-success" aria-hidden="true" />@else<x-lucide-circle-alert class="size-4 text-warning" aria-hidden="true" />@endif Needs attention</h2><p class="text-[10px] opacity-60">Highest-priority issues from your current financial records.</p></div><span class="badge badge-sm {{ $attentionItems->isEmpty() ? 'badge-success' : 'badge-warning' }}">{{ $attentionItems->isEmpty() ? 'All clear' : $attentionItems->count().' to review' }}</span></div>
        @if($attentionItems->isNotEmpty())
            <ul class="divide-y divide-base-300">
                @foreach($attentionItems as $item)
                    <li><a class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 hover:bg-base-200/40" href="{{ $item['url'] }}"><span class="min-w-0 flex-1"><span class="block text-xs font-semibold {{ $item['tone'] === 'error' ? 'text-error' : 'text-warning' }}">{{ $item['title'] }}@if($item['amount'] !== null)<span class="ml-1 tabular-nums">{{ $money($item['amount']) }}</span>@endif</span><span class="mt-0.5 block text-[11px] opacity-65">{{ $item['detail'] }}</span></span><span class="link link-primary shrink-0 text-xs">{{ $item['action'] }} <span aria-hidden="true">→</span></span></a></li>
                @endforeach
            </ul>
        @else
            <p class="px-3 py-3 text-xs opacity-65">No urgent issues found in the records we track. Check your upcoming payments before making spending decisions.</p>
        @endif
    </section>
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
