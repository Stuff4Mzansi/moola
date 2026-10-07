@extends('layouts.app')

@section('title', 'Subscriptions')

@section('content')
    <div class="mx-auto max-w-7xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="text-2xl font-bold">Subscriptions</h1><p class="mt-1 text-sm opacity-65">Understand your recurring costs and see what is due next.</p></div>
            <a class="btn btn-primary" href="{{ route('subscriptions.create') }}"><x-lucide-plus class="size-4" aria-hidden="true" />Add subscription</a>
        </div>
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="overflow-hidden rounded-sm border border-base-300 bg-base-100" aria-label="Subscription summary">
            <dl class="grid grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0 border-b border-r border-base-300 p-3 lg:border-b-0"><dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-repeat class="size-3.5 text-primary" aria-hidden="true" />Active subscriptions</dt><dd class="mt-1 text-lg font-semibold tabular-nums">{{ $activeCount }}</dd></div>
                <div class="min-w-0 border-b border-base-300 p-3 lg:border-b-0 lg:border-r"><dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-calendar-days class="size-3.5 text-orange-600" aria-hidden="true" />Monthly equivalent</dt><dd class="mt-1 break-words text-base font-semibold tracking-tight tabular-nums sm:text-lg">{{ $currencyPrefix }}{{ number_format($monthlyCostCents / 100, 2) }}</dd></div>
                <div class="min-w-0 border-r border-base-300 p-3"><dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-chart-no-axes-combined class="size-3.5 text-primary" aria-hidden="true" />Annual equivalent</dt><dd class="mt-1 break-words text-base font-semibold tracking-tight tabular-nums sm:text-lg">{{ $currencyPrefix }}{{ number_format($annualCostCents / 100, 2) }}</dd></div>
                <div class="min-w-0 bg-primary/5 p-3"><dt class="flex items-center gap-1.5 text-[11px] font-medium opacity-65"><x-lucide-clock-3 class="size-3.5 text-primary" aria-hidden="true" />Expected in next 30 days</dt><dd class="mt-1 break-words text-base font-semibold tracking-tight tabular-nums text-primary sm:text-lg">{{ $currencyPrefix }}{{ number_format($upcomingCostCents / 100, 2) }}</dd></div>
            </dl>
            <p class="border-t border-base-300 bg-base-200/40 px-3 py-2 text-[11px] leading-snug opacity-60">Estimates cover all your active subscriptions. Weekly costs use 52 payments per year. Renewals include today and are forecasts, not confirmed payments.</p>
        </section>
        @if(!$hasSubscriptions)
            <div class="rounded-sm border border-dashed border-base-300 bg-base-100 p-6 text-center"><x-lucide-repeat class="mx-auto size-6 text-primary" aria-hidden="true" /><h2 class="mt-2 text-base font-semibold">Start with your first subscription</h2><p class="mx-auto mt-1 max-w-md text-sm opacity-65">Add a streaming service, gym membership, or software plan to see your recurring commitments in one place.</p><a class="btn btn-primary btn-sm mt-3" href="{{ route('subscriptions.create') }}">Add your first subscription</a></div>
        @else
            <div class="grid items-stretch gap-4 xl:grid-cols-3">
                <section class="min-w-0 space-y-3 xl:col-span-2" aria-labelledby="subscription-list-title">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="subscription-list-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-list class="size-3.5 text-primary" aria-hidden="true" />Your subscriptions</h2><span class="text-[11px] opacity-50">{{ $subscriptions->total() }} {{ $subscriptions->total() === 1 ? 'result' : 'results' }}</span></div>
                    <form method="get" action="{{ route('subscriptions.index') }}" class="grid gap-2 rounded-sm border border-base-300 bg-base-100 p-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div><label class="label text-[11px]" for="search">Search by name</label><input id="search" class="input input-sm w-full" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="255" placeholder="Find a subscription"></div>
                        <div><label class="label text-[11px]" for="filter-status">Status</label><select id="filter-status" class="select select-sm w-full" name="status"><option value="all">All statuses</option>@foreach(\App\SubscriptionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? 'all') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                        <div><label class="label text-[11px]" for="filter-category">Category</label><select id="filter-category" class="select select-sm w-full" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></div>
                        <div><label class="label text-[11px]" for="sort">Sort by</label><select id="sort" class="select select-sm w-full" name="sort"><option value="next_billing_date" @selected(($filters['sort'] ?? 'next_billing_date') === 'next_billing_date')>Next renewal</option><option value="monthly_cost" @selected(($filters['sort'] ?? '') === 'monthly_cost')>Monthly equivalent cost</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name</option></select></div>
                        <div><label class="label text-[11px]" for="direction">Order</label><select id="direction" class="select select-sm w-full" name="direction"><option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>Ascending</option><option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>Descending</option></select></div>
                        <input type="hidden" name="renewal_window" value="{{ $renewalWindow }}">
                        <div class="flex items-end gap-2"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="{{ route('subscriptions.index') }}">Reset</a></div>
                    </form>
                    <div class="max-h-80 overflow-auto rounded-sm border border-base-300 bg-base-100" tabindex="0" role="region" aria-label="Subscriptions; scroll to view all rows and columns">
                        <table class="table table-xs w-full">
                            <thead class="sticky top-0 z-10 bg-base-200 text-[10px]"><tr><th scope="col">Subscription</th><th scope="col">Price</th><th scope="col">Next renewal</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @forelse($subscriptions as $subscription)
                                    <tr class="hover:bg-base-200/40">
                                        <td class="py-2.5"><a class="link link-hover block max-w-64 whitespace-normal break-words text-xs font-semibold" href="{{ route('subscriptions.show', $subscription) }}">{{ $subscription->name }}</a><p class="mt-0.5 text-[10px] opacity-55">{{ $subscription->category ?? 'Uncategorised' }}</p></td>
                                        <td class="whitespace-nowrap tabular-nums"><span class="text-xs font-semibold text-orange-600">{{ $currencyPrefix }}{{ number_format($subscription->amount_cents / 100, 2) }}</span><p class="mt-0.5 text-[10px] opacity-55">{{ $subscription->billing_frequency->label() }}</p></td>
                                        <td class="whitespace-nowrap">{{ $subscription->nextRenewalDate()?->format('d M Y') ?? '—' }}</td>
                                        <td><span @class(['badge badge-xs', 'badge-success' => $subscription->status === \App\SubscriptionStatus::Active, 'badge-warning' => $subscription->status === \App\SubscriptionStatus::Paused, 'badge-ghost' => $subscription->status === \App\SubscriptionStatus::Cancelled])>{{ $subscription->status->label() }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 text-center text-xs opacity-65">No subscriptions match your filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $subscriptions->links() }}
                </section>
                <section class="min-w-0 space-y-3" aria-labelledby="upcoming-renewals-title">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="upcoming-renewals-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-clock-3 class="size-3.5 text-primary" aria-hidden="true" />Upcoming renewals</h2>
                        <div class="join" aria-label="Renewal period">
                            <a @class(['btn btn-xs join-item', 'btn-active' => $renewalWindow === 7]) href="{{ route('subscriptions.index', [...request()->except(['page', 'renewal_window']), 'renewal_window' => 7]) }}" @if($renewalWindow === 7) aria-current="true" @endif>7 days</a>
                            <a @class(['btn btn-xs join-item', 'btn-active' => $renewalWindow === 30]) href="{{ route('subscriptions.index', [...request()->except(['page', 'renewal_window']), 'renewal_window' => 30]) }}" @if($renewalWindow === 30) aria-current="true" @endif>30 days</a>
                        </div>
                    </div>
                    <div class="max-h-80 overflow-auto rounded-sm border border-base-300 bg-base-100" tabindex="0" role="region" aria-label="Upcoming renewals; scroll to view all payments">
                        @forelse($renewals as $renewal)
                            <a class="flex items-center gap-2.5 border-b border-base-300 px-3 py-2.5 last:border-b-0 hover:bg-base-200/50" href="{{ route('subscriptions.show', $renewal['subscription']) }}">
                                <time class="flex w-9 shrink-0 flex-col items-center rounded-sm bg-primary/5 py-1 text-primary" datetime="{{ $renewal['date']->toDateString() }}"><span class="text-[9px] uppercase opacity-65">{{ $renewal['date']->format('M') }}</span><span class="text-sm font-semibold leading-tight tabular-nums">{{ $renewal['date']->format('d') }}</span></time>
                                <div class="min-w-0 flex-1"><p class="break-words text-xs font-semibold">{{ $renewal['subscription']->name }}</p><p class="mt-0.5 text-[10px] opacity-55">{{ $renewal['date']->format('d M Y') }}</p></div>
                                <span class="whitespace-nowrap text-xs font-semibold tabular-nums text-orange-600">{{ $currencyPrefix }}{{ number_format($renewal['subscription']->amount_cents / 100, 2) }}</span>
                            </a>
                        @empty
                            <p class="p-4 text-xs opacity-65">No active subscriptions are due in the next {{ $renewalWindow }} days.</p>
                        @endforelse
                    </div>
                    <p class="text-[11px] leading-snug opacity-60">All your active subscriptions are included here, regardless of list filters.</p>
                </section>
            </div>
            @include('subscriptions.analytics')
        @endif
    </div>
@endsection
