<div class="space-y-8">
    @include('budgets.groups')

    <p class="text-sm opacity-60">Set your income and category limits. Changes save automatically.</p>

    <section class="space-y-3" aria-labelledby="budget-categories-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-xl font-semibold" id="budget-categories-title">Your category plan</h3>
            @if($canEdit)
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline" type="button" data-open-dialog="budget-category">Add category</button>
                    <button class="btn btn-sm btn-ghost" type="button" data-open-dialog="budget-transfer">Move money</button>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @foreach($categoryRows->groupBy(fn (array $row): int => $row['category']->budget_group_id ?? 0)->sortBy(fn ($rows): int => $rows->contains(fn (array $row): bool => $row['category']->kind === 'other') ? 1 : 0) as $groupId => $rows)
                <details class="group space-y-2" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-sm px-1 py-1 focus-visible:outline-2 focus-visible:outline-primary">
                        <span class="flex min-w-0 flex-wrap items-center gap-2">
                            <h4 class="font-semibold">{{ $groups->isNotEmpty() ? ($groups->firstWhere('id', $groupId)?->name ?? 'Ungrouped') : 'Categories' }}</h4>
                            <span class="badge badge-sm badge-ghost">{{ $rows->count() }} categories</span>
                        </span>
                        <x-lucide-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
                    </summary>

                    <div class="space-y-2">
                    @foreach($rows->sortBy(fn (array $row): int => $row['category']->kind === 'other' ? 1 : 0) as $row)
                        @php $category = $row['category']; @endphp
                        <article class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-sm border border-base-300 bg-base-100 p-3 md:grid-cols-[repeat(11,minmax(0,1fr))_2rem] md:items-start">
                            @if($canEdit)
                                <form method="post" action="{{ $actionUrl('category-save') }}" data-budget-action data-budget-autosave @class(['col-span-2 grid min-w-0 grid-cols-2 gap-2 md:grid-cols-8', 'md:col-span-8' => $groups->isNotEmpty(), 'md:col-span-11' => $groups->isEmpty()])>
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $period->version }}">
                                    <input type="hidden" name="id" value="{{ $category->id }}">
                                    <label class="col-span-2 block min-w-0 md:col-span-6">
                                        <span class="label py-0 text-xs font-medium">Category</span>
                                        <input class="input input-sm w-full" name="name" value="{{ $category->name }}" maxlength="100" required @readonly($category->kind !== 'custom')>
                                    </label>
                                    <label class="block min-w-0 md:col-span-2">
                                        <span class="label py-0 text-xs font-medium">Limit ({{ $currencySymbol }})</span>
                                        <input class="input input-sm w-full" type="number" name="amount" step="0.01" min="0" value="{{ $amount($row['planned']) }}" required data-category-limit>
                                    </label>
                                    @if($category->kind === 'subscriptions')
                                        <input type="hidden" name="automatic" value="0">
                                        <label class="col-span-2 flex min-h-8 items-center gap-2 px-1 text-xs md:col-span-7">
                                            <input class="checkbox checkbox-sm" type="checkbox" name="automatic" value="1" @checked($category->allocated_cents === null)>
                                            <span>Follow subscription forecast</span>
                                        </label>
                                    @endif
                                    <noscript><button type="submit" class="btn btn-xs md:col-span-7">Save</button></noscript>
                                </form>
                            @else
                                <h4 class="col-span-2 font-semibold md:col-span-12">{{ $category->name }}</h4>
                            @endif

                            @if($canEdit && $groups->isNotEmpty())
                                <form method="post" action="{{ $actionUrl('category-group') }}" data-budget-action data-budget-group-assignment class="col-span-1 min-w-0 md:col-span-3">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $period->version }}">
                                    <input type="hidden" name="id" value="{{ $category->id }}">
                                    <label class="block min-w-0">
                                        <span class="label py-0 text-xs font-medium">Group</span>
                                        <select class="select select-sm w-full" name="group_id" aria-label="Group for {{ $category->name }}">
                                            <option value="">Ungrouped</option>
                                            @foreach($groups as $group)
                                                <option value="{{ $group->id }}" @selected($category->budget_group_id === $group->id)>{{ $group->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <noscript><button class="btn btn-xs" type="submit">Assign</button></noscript>
                                </form>
                            @endif

                            @if($canEdit && $category->kind === 'custom')
                                <form method="post" action="{{ $actionUrl('category-remove') }}" data-budget-action data-confirm="Remove this category? Its expenses move to Other and its allocation is freed." class="col-span-1 flex justify-end md:col-span-1 md:mt-6">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $period->version }}">
                                    <input type="hidden" name="id" value="{{ $category->id }}">
                                    <button class="btn btn-square btn-sm btn-ghost text-error" type="submit" aria-label="Remove {{ $category->name }} category" title="Remove category">
                                        <x-lucide-trash-2 class="size-4" aria-hidden="true" />
                                    </button>
                                </form>
                            @endif

                            <div class="col-span-2 space-y-1 border-t border-base-200 pt-2 md:col-span-full" data-budget-live="category-{{ $category->id }}">
                                <div class="flex flex-wrap justify-between gap-2 text-xs">
                                    <span>Spent {{ $money($row['spent']) }} of {{ $money($row['planned']) }}</span>
                                    <span @class(['font-semibold tabular-nums', 'text-error' => $row['remaining'] < 0])>{{ $row['remaining'] < 0 ? 'Over by ' : 'Remaining ' }}{{ $money(abs($row['remaining'])) }}</span>
                                </div>
                                <progress @class(['progress progress-xs w-full', 'progress-error' => $row['spent'] > $row['planned'], 'progress-primary' => $row['spent'] <= $row['planned']]) value="{{ min($row['spent'], max(1, $row['planned'])) }}" max="{{ max(1, $row['planned']) }}" aria-label="Spending against the {{ $category->name }} limit"></progress>
                                @if($row['upcoming'] > 0)
                                    <p @class(['text-xs', 'text-error' => $row['available'] < 0, 'opacity-60' => $row['available'] >= 0])>Unpaid charges: {{ $money($row['upcoming']) }} · Available after these: {{ $money($row['available']) }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    <section id="budget-incomes" class="space-y-4" aria-labelledby="budget-incomes-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-xl font-semibold" id="budget-incomes-title">Income sources</h3>
                <p class="mt-1 text-sm opacity-70">Expected income funds your plan. Record received income separately.</p>
            </div>
            @if($canEdit)
                <button class="btn btn-sm btn-outline" data-open-dialog="budget-income" type="button">Add income</button>
            @endif
        </div>

        @forelse($incomes as $income)
            <div class="space-y-2 rounded-sm border border-base-300 bg-base-100 p-3">
                @if($canEdit)
                    <form action="{{ $actionUrl('income-save') }}" method="post" data-budget-action data-budget-autosave class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        @csrf
                        <input type="hidden" name="version" value="{{ $period->version }}">
                        <input type="hidden" name="id" value="{{ $income->id }}">
                        <label class="block min-w-0">
                            <span class="label py-0 text-xs font-medium">Source</span>
                            <input class="input input-sm w-full" name="name" value="{{ $income->name }}" required maxlength="100">
                        </label>
                        <label class="block min-w-0">
                            <span class="label py-0 text-xs font-medium">Expected ({{ $currencySymbol }})</span>
                            <input class="input input-sm w-full" type="number" name="expected_amount" value="{{ $amount($income->expected_cents) }}" step="0.01" min="0" required>
                        </label>
                        <label class="block min-w-0">
                            <span class="label py-0 text-xs font-medium">Expected date · optional</span>
                            <input class="input input-sm w-full" type="date" name="expected_date" min="{{ $period->start_date->toDateString() }}" max="{{ $period->end_date->toDateString() }}" value="{{ $income->expected_date?->toDateString() }}">
                        </label>
                        <label class="block min-w-0">
                            <span class="label py-0 text-xs font-medium">Received ({{ $currencySymbol }})</span>
                            <input class="input input-sm w-full" type="number" name="received_amount" value="{{ $amount($income->received_cents) }}" step="0.01" min="0" required>
                        </label>
                        <label class="block min-w-0">
                            <span class="label py-0 text-xs font-medium">Date received</span>
                            <input class="input input-sm w-full" type="date" name="received_date" min="{{ $period->start_date->toDateString() }}" max="{{ $period->end_date->toDateString() }}" value="{{ $income->received_date?->toDateString() }}">
                        </label>
                        <noscript><button type="submit" class="btn btn-sm sm:col-span-2 xl:col-span-5">Save income</button></noscript>
                    </form>

                    <form method="post" action="{{ $actionUrl('income-remove') }}" data-budget-action data-confirm="Remove this income source and its received amount from this period?" class="flex justify-end border-t border-base-200 pt-2">
                        @csrf
                        <input type="hidden" name="version" value="{{ $period->version }}">
                        <input type="hidden" name="id" value="{{ $income->id }}">
                        <button class="btn btn-xs btn-ghost text-error" type="submit">Remove source</button>
                    </form>
                @else
                    <p>{{ $income->name }} · Expected {{ $money($income->expected_cents) }} · Received {{ $money($income->received_cents) }}</p>
                @endif
            </div>
        @empty
            <p class="rounded-sm border border-dashed border-base-300 p-5 text-sm opacity-70">Add your salary or another income source to start allocating money.</p>
        @endforelse
    </section>
</div>
