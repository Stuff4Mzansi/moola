@php
    $tipsBudgetUrl = $dashboardPeriod
        ? route('budgets.index', ['period' => $dashboardPeriod->id])
        : route('budgets.index');
@endphp
<section class="grid overflow-hidden sm:grid-cols-[7.5rem_minmax(0,1fr)] py-1" aria-label="Budget tips" data-dashboard-tips x-data="dashboardTips" x-on:mouseenter="pauseForInteraction()" x-on:mouseleave="resumeAfterInteraction()" x-on:focusin="pauseForInteraction()" x-on:focusout="resumeAfterInteraction($event)">
    <div class="flex items-center gap-3 rounded-sm bg-primary px-4 py-3 text-primary-content sm:rounded-sm sm:flex-col sm:justify-center sm:gap-2 sm:px-3 sm:py-5" aria-hidden="true">
        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-content/15 sm:size-14">
            <x-lucide-lightbulb class="size-6 sm:size-8" />
        </span>
        <span class="text-[10px] font-semibold uppercase tracking-[0.16em]">Budget tip</span>
        <span class="ml-auto font-mono text-sm font-semibold tabular-nums text-primary-content/75 sm:ml-0 sm:text-base"><span x-text="tipNumber">01</span><span class="mx-1 opacity-50">/</span><span x-text="String(tips.length).padStart(2, '0')">05</span></span>
    </div>
    <div class="flex min-w-0 flex-col justify-center gap-3 p-3 sm:p-4 lg:flex-row lg:items-center lg:gap-6">
        <div class="min-w-0 flex-1" aria-live="polite" aria-atomic="true" aria-relevant="text">
            <h2 class="text-sm font-semibold leading-snug sm:text-base" x-text="currentTip.title">Adjust dates when your pay cycle changes</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-base-content/70 sm:text-sm"><span x-text="currentTip.body">Open your budget options and choose Change dates. Preview the impact first; recorded expenses and received income must stay inside the period.</span></p>
            <a class="link link-primary mt-1.5 inline-flex min-h-9 items-center gap-1 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="{{ $tipsBudgetUrl }}">{{ $dashboardPeriod ? 'Open this budget' : 'Go to budgets' }} <x-lucide-arrow-right class="size-3.5" aria-hidden="true" /></a>
        </div>
        <div class="flex shrink-0 items-center justify-between gap-3 lg:flex-col lg:items-end" role="group" aria-label="Tip controls">
            <div class="flex items-center gap-1.5">
                <button class="btn btn-square btn-sm" type="button" x-on:click="previous()" aria-label="Previous budget tip"><x-lucide-chevron-left class="size-4" aria-hidden="true" /></button>
                <button class="btn btn-square btn-sm" type="button" x-on:click="togglePause()" x-bind:aria-label="isPaused ? 'Resume automatic tips' : 'Pause automatic tips'" x-bind:aria-pressed="isPaused">
                    <x-lucide-pause class="size-4" aria-hidden="true" x-bind:hidden="isPaused" />
                    <x-lucide-play class="size-4" aria-hidden="true" x-bind:hidden="!isPaused" />
                </button>
                <button class="btn btn-square btn-sm" type="button" x-on:click="next()" aria-label="Next budget tip"><x-lucide-chevron-right class="size-4" aria-hidden="true" /></button>
            </div>
            <div class="flex gap-1.5" aria-hidden="true">
                <template x-for="(tip, index) in tips" x-bind:key="index">
                    <span class="h-1 rounded-full transition-all duration-300 motion-reduce:transition-none" x-bind:class="activeIndex === index ? 'w-7 bg-primary' : 'w-2 bg-base-300'"></span>
                </template>
            </div>
        </div>
    </div>
</section>
