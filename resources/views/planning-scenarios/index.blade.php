@extends('layouts.app')
@section('title', 'Planning scenarios')
@section('content')
@php
    $money = fn (int $value): string => $currencyPrefix.number_format($value / 100, 2);
    $values = old('inputs', $inputs);
@endphp
<div class="mx-auto max-w-7xl space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-bold">Planning scenarios</h1><p class="mt-1 text-sm opacity-65">Keep your ideas. Compare the trade-offs before choosing your next step.</p></div><a class="btn btn-outline" href="{{ route('planning-scenarios.index', ['kind' => $kind]) }}"><x-lucide-plus class="size-4" /> New scenario</a></header>
    @if(session('status'))<div class="alert alert-success text-sm" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-error text-sm" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <nav class="tabs tabs-border" aria-label="Planning tools">@foreach($kinds as $key => $label)<a @class(['tab', 'tab-active' => $kind === $key]) href="{{ route('planning-scenarios.index', ['kind' => $key]) }}" @if($kind === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <section class="rounded-sm border border-base-300 bg-base-100 p-4 sm:p-5" aria-labelledby="scenario-editor-title">
            <h2 id="scenario-editor-title" class="text-lg font-semibold">{{ $editingScenario ? 'Edit '.$editingScenario->name : 'Explore a new plan' }}</h2>
            <p class="mt-1 text-xs opacity-60">{{ $kinds[$kind] }} assumptions. Preview first, then save when you are ready.</p>
            <form id="scenario-editor" class="mt-4 space-y-4" method="post" action="{{ $editingScenario ? route('planning-scenarios.update', $editingScenario) : route('planning-scenarios.store') }}">
                @csrf
                @if($editingScenario) @method('PUT') @endif
                <input type="hidden" name="kind" value="{{ $kind }}">
                @if($editingScenario)<input type="hidden" name="scenario" value="{{ $editingScenario->id }}">@endif
                <div class="grid gap-3 sm:grid-cols-2">
                    @if($kind === 'debt')
                        <label><span class="label">Extra monthly payment ({{ $currencySymbol }})</span><input class="input w-full" name="inputs[extra]" type="number" min="0" max="9999999.99" step="0.01" value="{{ $values['extra'] }}" required></label>
                        <label><span class="label">Payoff strategy</span><select class="select w-full" name="inputs[strategy]">@foreach(['avalanche' => 'Avalanche · highest interest first', 'snowball' => 'Snowball · smallest balance first'] as $key => $label)<option value="{{ $key }}" @selected($values['strategy'] === $key)>{{ $label }}</option>@endforeach</select></label>
                    @elseif($kind === 'liquidity')
                        <label><span class="label">Forecast window</span><select class="select w-full" name="inputs[horizon]">@foreach([30, 60, 90] as $days)<option value="{{ $days }}" @selected((int) $values['horizon'] === $days)>{{ $days }} days</option>@endforeach</select></label>
                        <label><span class="label">Income delay (days)</span><input class="input w-full" name="inputs[income_delay]" type="number" min="0" max="60" value="{{ $values['income_delay'] }}" required></label>
                        @foreach(['extra_debt' => 'Extra debt payment / month ('.$currencySymbol.')', 'extra_reserve' => 'Extra protected savings / month ('.$currencySymbol.')', 'purchase' => 'One-off purchase ('.$currencySymbol.')'] as $key => $label)<label><span class="label">{{ $label }}</span><input class="input w-full" name="inputs[{{ $key }}]" type="number" min="0" max="9999999.99" step="0.01" value="{{ $values[$key] }}" required></label>@endforeach
                        <label><span class="label">Purchase: days from today</span><input class="input w-full" name="inputs[purchase_after_days]" type="number" min="0" max="89" value="{{ $values['purchase_after_days'] }}" required></label>
                    @else
                        <fieldset class="max-h-64 space-y-3 overflow-y-auto rounded-sm border border-base-300 p-3 sm:col-span-2"><legend class="px-1 text-xs font-medium">Subscriptions to exclude from this plan</legend>
                            @forelse($activeSubscriptions as $subscription)<label class="flex items-start justify-between gap-3 text-sm"><span class="flex items-start gap-2"><input class="checkbox checkbox-sm" type="checkbox" name="inputs[subscription_ids][]" value="{{ $subscription->id }}" @checked(in_array($subscription->id, $values['subscription_ids']))>{{ $subscription->name }}</span><span class="shrink-0 text-xs opacity-65">{{ $money($subscription->monthlyCostCents()) }}/mo</span></label>@empty<p class="text-sm opacity-60">No active subscriptions yet. <a class="link" href="{{ route('subscriptions.create') }}">Add a subscription</a>.</p>@endforelse
                        </fieldset>
                        <label><span class="label">Monthly target ({{ $currencySymbol }}, optional)</span><input class="input w-full" name="inputs[target]" type="number" min="0" max="9999999.99" step="0.01" value="{{ $values['target'] }}"></label>
                    @endif
                </div>
                <div class="border-t border-base-300 pt-4"><label class="block"><span class="label">Scenario name</span><input class="input w-full" name="name" maxlength="100" value="{{ old('name', request('name', $editingScenario?->name)) }}" placeholder="e.g. Pay off faster without stretching cash" required></label><label class="mt-3 block"><span class="label">Notes (optional)</span><textarea class="textarea w-full" name="notes" maxlength="500" rows="2" placeholder="What would make this plan work for you?">{{ old('notes', request('notes', $editingScenario?->notes)) }}</textarea></label></div>
                <div class="flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">{{ $editingScenario ? 'Update scenario' : 'Save scenario' }}</button><button class="btn btn-outline" type="submit" formaction="{{ route('planning-scenarios.preview') }}" formnovalidate>Preview changes</button>@if($editingScenario)<a class="btn btn-ghost" href="{{ route('planning-scenarios.index', ['scenario' => $editingScenario->id]) }}">Discard edits</a>@endif</div>
            </form>
        </section>
        <section class="space-y-4 rounded-sm border border-primary/25 bg-base-100 p-4 sm:p-5" aria-labelledby="scenario-preview-title">
            <div><h2 id="scenario-preview-title" class="text-xs font-semibold uppercase tracking-wide text-primary">Current preview</h2><p class="mt-2 text-xl font-semibold">{{ $result['headline'] }}</p><p class="mt-1 text-xs opacity-65">{{ $result['detail'] }}</p><p class="mt-2 text-xs opacity-60">Preview reflects the last submitted assumptions. Use Preview changes to refresh it.</p></div>
            <div class="grid gap-3 sm:grid-cols-2">@foreach($result['metrics'] as $label => $value)<div class="rounded-sm bg-base-200/60 p-3"><p class="text-xs opacity-60">{{ $label }}</p><p class="mt-1 break-words text-lg font-semibold {{ $value < 0 ? 'text-error' : '' }}">{{ $money($value) }}</p></div>@endforeach</div>
            @include('planning-scenarios.warnings', ['warnings' => $result['warnings']])
            @if($kind === 'liquidity')
                <div data-liquidity-chart data-buffer="{{ $result['buffer'] }}"><div class="overflow-x-auto rounded-sm bg-base-200/40" data-liquidity-canvas></div><script type="application/json" data-liquidity-points>{!! json_encode($result['daily'], JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script></div><p class="text-xs opacity-60">Solid: daily low · dashed: closing cash. Purchase day 0 means today. Monthly extras begin today. <a class="link" href="{{ route('net-worth.index', ['tab' => 'liquidity']) }}">Review liquidity settings</a>.</p>
            @elseif($kind === 'debt')
                <ol class="space-y-2 text-xs">@foreach($result['plan']['order'] as $milestone)<li class="flex justify-between gap-3 border-t border-base-300 pt-2"><span>{{ $milestone['name'] }}</span><span class="shrink-0 font-medium">Month {{ $milestone['month'] }}</span></li>@endforeach</ol><p class="text-xs opacity-60">Monthly estimates from next month with fixed minimums and rates, rounded interest, and freed payments rolled into remaining debts. Fees, new borrowing, rate changes, and lender calculations can differ.</p>
            @else
                <p class="text-xs opacity-60">Annual equivalents use current prices and standard billing frequencies. Removing a service in this plan assumes future charges stop; paid charges and cancellation fees are excluded.</p>
            @endif
        </section>
    </div>
    <section class="rounded-sm border border-base-300 bg-base-100 p-4 sm:p-5" aria-labelledby="saved-scenarios-title">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="saved-scenarios-title" class="text-lg font-semibold">Saved {{ strtolower($kinds[$kind]) }} scenarios</h2><span class="text-xs opacity-60">{{ $scenarios->total() }} saved · private to your account</span></div>
        <form id="scenario-comparison" class="mt-3 flex flex-wrap items-end gap-3" method="get" action="{{ route('planning-scenarios.index') }}"><input type="hidden" name="kind" value="{{ $kind }}">@if($kind === 'liquidity')<label><span class="label text-xs">Shared comparison window</span><select class="select select-sm" name="comparison_window">@foreach([30, 60, 90] as $days)<option value="{{ $days }}" @selected($comparisonWindow === $days)>{{ $days }} days</option>@endforeach</select></label>@endif<button class="btn btn-outline btn-sm" type="submit">Compare selected</button><p class="text-xs opacity-60">Choose up to three plans below. Current baseline is included.</p></form>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($scenarios as $scenario)
                <article class="flex flex-col gap-3 rounded-sm border border-base-300 p-3"><label class="flex items-start gap-2"><input class="checkbox checkbox-sm mt-0.5" type="checkbox" form="scenario-comparison" name="compare[]" value="{{ $scenario->id }}" @checked(in_array($scenario->id, request('compare', [])))><span class="min-w-0"><span class="block break-words text-sm font-semibold">{{ $scenario->name }}</span><span class="mt-1 block text-xs opacity-50">Updated {{ $scenario->updated_at->format('d M Y') }}</span></span></label>
                    @if($scenario->notes)<p class="break-words text-xs opacity-65">{{ $scenario->notes }}</p>@endif
                    <p class="text-xs opacity-65">@if($kind === 'debt'){{ ucfirst($scenario->inputs['strategy']) }} · {{ $money(\App\BudgetMoney::cents($scenario->inputs['extra'])) }} extra / month @elseif($kind === 'subscriptions'){{ count($scenario->inputs['subscription_ids']) }} services selected @if($scenario->inputs['target'] !== null) · Target {{ $money(\App\BudgetMoney::cents($scenario->inputs['target'])) }}/month @endif @else{{ $scenario->inputs['horizon'] }} days · Income delay {{ $scenario->inputs['income_delay'] }} days · Purchase {{ $money(\App\BudgetMoney::cents($scenario->inputs['purchase'])) }} @endif</p>
                    <div class="mt-auto flex flex-wrap gap-1"><a class="btn btn-ghost btn-xs" href="{{ route('planning-scenarios.index', ['scenario' => $scenario->id]) }}">Open / edit</a><form method="post" action="{{ route('planning-scenarios.duplicate', $scenario) }}">@csrf<button class="btn btn-ghost btn-xs">Duplicate</button></form><form method="post" action="{{ route('planning-scenarios.destroy', $scenario) }}" data-confirm="Delete this saved scenario? Your financial records are kept." data-confirm-title="Delete scenario?" data-confirm-label="Delete">@csrf @method('DELETE')<button class="btn btn-ghost btn-xs text-error">Delete</button></form></div>
                </article>
            @empty<div class="rounded-sm border border-dashed border-base-300 p-6 md:col-span-2 xl:col-span-3"><p class="font-semibold">Your first plan starts here</p><p class="mt-1 text-sm opacity-65">Try assumptions above, give the plan a name, and save it for your next review.</p></div>@endforelse
        </div>
        <div class="mt-4">{{ $scenarios->links() }}</div>
    </section>
    @if($comparison !== []) @include('planning-scenarios.comparison') @endif
    <p class="text-xs opacity-60">Saved scenarios keep assumptions, not historical results. Reopening a plan uses your current balances, payments, subscriptions, and liquidity settings. Scenarios do not record payments, cancel services, or change protected funds.</p>
</div>
@endsection
