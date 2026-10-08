<section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-labelledby="dashboard-goals-title">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-3 py-2">
        <div>
            <h2 id="dashboard-goals-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-piggy-bank class="size-3.5 text-primary" aria-hidden="true" />Savings goals</h2>
            <p class="mt-0.5 text-[10px] opacity-60">Give your next contribution a purpose.</p>
        </div>
        <a class="btn btn-xs btn-outline" href="{{ route('goals.index') }}">Manage goals</a>
    </div>
    @if($savingsOverview['rows']->isEmpty())
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-3">
            <p class="text-xs opacity-65">Build an emergency fund or start saving for something that matters.</p>
            <a class="btn btn-primary btn-xs" href="{{ route('goals.index', ['tab' => 'planning']) }}">Plan an emergency fund</a>
        </div>
    @else
        <dl class="grid grid-cols-1 divide-y divide-base-300 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="min-w-0 px-3 py-2 sm:border-r sm:border-base-300">
                <dt class="text-[10px] opacity-60">Saved towards goals</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($savingsOverview['saved'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 px-3 py-2 sm:border-r sm:border-base-300">
                <dt class="text-[10px] opacity-60">Monthly saving plan</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $currencyPrefix }}{{ number_format($savingsOverview['monthly'] / 100, 2) }}</dd>
            </div>
            <div class="min-w-0 px-3 py-2">
                <dt class="text-[10px] opacity-60">Goals reached</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold tabular-nums">{{ $savingsOverview['completed'] }} / {{ $savingsOverview['rows']->count() }}</dd>
            </div>
        </dl>
        <div class="grid items-stretch gap-2 border-t border-base-300 p-2 sm:grid-cols-2">
            @foreach($savingsOverview['rows']->sortBy(fn (array $row): int => in_array($row['status'], ['Needs a boost', 'Past target date']) ? 0 : ($row['remaining'] > 0 ? 1 : 2))->take(4) as $row)
                <a class="flex h-full flex-col gap-1.5 rounded-sm border border-base-300/70 bg-base-200/30 p-2.5 hover:bg-base-200/60 last:odd:sm:col-span-2" href="{{ route('goals.index', ['tab' => 'contributions', 'goal' => $row['goal']->id]) }}">
                    <span class="flex items-center justify-between gap-2">
                        <span class="min-w-0 break-words text-xs font-semibold">{{ $row['goal']->name }}</span>
                        <span class="shrink-0 text-[10px] tabular-nums opacity-60">{{ $row['progress'] }}%</span>
                    </span>
                    <progress class="progress progress-primary h-1.5 w-full rounded-sm" value="{{ $row['progress'] }}" max="100" aria-label="{{ $row['goal']->name }} progress"></progress>
                    <span class="flex flex-wrap justify-between gap-x-2 gap-y-0.5 text-[10px]">
                        <span>{{ $row['status'] }}</span>
                        <span class="text-right tabular-nums">{{ $row['remaining'] === 0 ? 'Goal reached' : ($row['goal']->target_date?->lt(today()) ? 'Set a new target date' : ($row['needed'] !== null ? $currencyPrefix.number_format($row['needed'] / 100, 2).' / month needed' : $currencyPrefix.number_format($row['remaining'] / 100, 2).' left to save')) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        @if($savingsOverview['behind'] > 0)
            <p class="border-t border-base-300 px-3 py-2 text-[11px] text-warning">{{ $savingsOverview['behind'] }} goals need attention. <a class="link" href="{{ route('goals.index', ['tab' => 'planning']) }}">Review monthly contributions or target dates</a>.</p>
        @endif
    @endif
</section>
