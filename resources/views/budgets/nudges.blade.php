@if($periodNudges !== [])
    <section class="space-y-3 mt-3" aria-labelledby="budget-nudges-title" x-data="budgetNudgeDeck">
        <div class="space-y-1">
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2"><x-lucide-sparkles class="size-4 shrink-0 text-primary" aria-hidden="true" /><h3 id="budget-nudges-title" class="text-sm font-semibold">Nudges for this period</h3></div>
            @if(count($periodNudges) > 1)
                <div class="flex shrink-0 items-center gap-1">
                    <span class="mr-1 text-[10px] tabular-nums opacity-60" aria-live="polite"><span x-text="index + 1"></span> of {{ count($periodNudges) }}</span>
                    <button class="btn btn-ghost btn-xs btn-square" type="button" aria-label="Previous nudge" x-bind:disabled="index === 0" x-on:click="move(-1)"><x-lucide-chevron-left class="size-4" aria-hidden="true" /></button>
                    <button class="btn btn-ghost btn-xs btn-square" type="button" aria-label="Next nudge" x-bind:disabled="index >= cards().length - 1" x-on:click="move(1)"><x-lucide-chevron-right class="size-4" aria-hidden="true" /></button>
                </div>
            @endif
            </div>
        </div>
        <div class="relative isolate px-0 pb-3 pt-1">
            <div class="grid min-w-0" role="group" aria-label="Budget period nudge cards" x-ref="deck" aria-live="polite">
                @foreach($periodNudges as $nudge)
                    <article
                        @class(['relative col-start-1 row-start-1 flex min-h-40 flex-col overflow-hidden rounded-xl border p-4 shadow-md transition-[transform,opacity] duration-300 ease-out motion-reduce:transition-none', 'border-warning/40' => $nudge['tone'] === 'warning', 'border-primary/30' => $nudge['tone'] === 'info'])
                        x-bind:style="cardStyle({{ $loop->index }}, '{{ $nudge['tone'] }}')"
                        x-bind:aria-hidden="index !== {{ $loop->index }}"
                        x-bind:inert="index !== {{ $loop->index }}"
                    >
                        <div class="absolute inset-0 z-20 rounded-xl border border-base-300/80 bg-base-100 shadow-sm" x-show="index !== {{ $loop->index }}" aria-hidden="true"></div>
                        <div class="relative z-10 flex items-start gap-3">
                            <span @class(['grid size-9 shrink-0 place-items-center rounded-lg', 'bg-warning/15 text-warning' => $nudge['tone'] === 'warning', 'bg-primary/10 text-primary' => $nudge['tone'] === 'info'])><x-lucide-lightbulb class="size-4" aria-hidden="true" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] opacity-50">Nudge {{ $loop->iteration }}</p>
                                <h4 class="mt-1 text-sm font-semibold leading-snug">{{ $nudge['title'] }}</h4>
                                <p class="mt-1.5 break-words text-xs leading-relaxed opacity-70">{{ $nudge['message'] }}</p>
                            </div>
                        </div>
                        <div class="relative z-10 mt-auto flex flex-wrap items-center justify-between gap-2 pt-4">
                            <button class="btn btn-primary btn-xs" type="button" data-budget-go="{{ $nudge['tab'] }}">{{ $nudge['action'] }} <x-lucide-arrow-up-right class="size-3.5" aria-hidden="true" /></button>
                            <div class="flex items-center gap-1">
                                @foreach(['snooze' => 'Snooze 7 days', 'dismiss' => 'Dismiss'] as $action => $label)
                                    <form method="post" action="{{ route('budgets.nudges.update', ['period' => $period, 'nudge' => $nudge['key'], 'action' => $action]) }}">@csrf<button class="btn btn-ghost btn-xs" type="submit">{{ $label }}</button></form>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
