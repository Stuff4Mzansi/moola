@extends('layouts.app')

@section('title', 'Subscriptions')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="text-3xl font-bold">Subscriptions</h1><p class="mt-2 opacity-70">Understand your recurring costs and see what is due next.</p></div>
            <a class="btn btn-primary" href="{{ route('subscriptions.create') }}"><x-lucide-plus class="size-4" aria-hidden="true" />Add subscription</a>
        </div>
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card border border-base-300 bg-base-100"><div class="card-body"><p class="text-sm opacity-70">Active subscriptions</p><p class="text-3xl font-bold">{{ $activeCount }}</p></div></div>
            <div class="card border border-base-300 bg-base-100"><div class="card-body"><p class="text-sm opacity-70">Monthly equivalent</p><p class="text-2xl font-bold">ZAR {{ number_format($monthlyCostCents / 100, 2) }}</p></div></div>
            <div class="card border border-base-300 bg-base-100"><div class="card-body"><p class="text-sm opacity-70">Annual equivalent</p><p class="text-2xl font-bold">ZAR {{ number_format($annualCostCents / 100, 2) }}</p></div></div>
            <div class="card border border-primary/30 bg-primary/5"><div class="card-body"><p class="text-sm opacity-70">Expected in next 30 days</p><p class="text-2xl font-bold">ZAR {{ number_format($upcomingCostCents / 100, 2) }}</p></div></div>
        </div>
        <p class="text-sm opacity-70">Estimates cover all your active subscriptions. Weekly costs use 52 payments per year. Renewals include today and are forecasts, not confirmed payments.</p>
        @if($hasSubscriptions)
            @include('subscriptions.analytics')
        @endif
        @if(!$hasSubscriptions)
            <div class="card border border-dashed border-base-300 bg-base-100"><div class="card-body items-center py-14 text-center"><x-lucide-repeat class="size-10 text-primary" aria-hidden="true" /><h2 class="card-title mt-3">Start with your first subscription</h2><p class="max-w-md opacity-70">Add a streaming service, gym membership, or software plan to see your recurring commitments in one place.</p><a class="btn btn-primary mt-4" href="{{ route('subscriptions.create') }}">Add your first subscription</a></div></div>
        @else
            <div class="grid gap-6 xl:grid-cols-3">
                <section class="space-y-4 xl:col-span-2" aria-labelledby="subscription-list-title">
                    <h2 id="subscription-list-title" class="text-xl font-semibold">Your subscriptions</h2>
                    <form method="get" action="{{ route('subscriptions.index') }}" class="grid gap-3 rounded-box border border-base-300 bg-base-100 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div><label class="label" for="search">Search by name</label><input id="search" class="input w-full" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="255" placeholder="Find a subscription"></div>
                        <div><label class="label" for="filter-status">Status</label><select id="filter-status" class="select w-full" name="status"><option value="all">All statuses</option>@foreach(\App\SubscriptionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? 'all') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                        <div><label class="label" for="filter-category">Category</label><select id="filter-category" class="select w-full" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></div>
                        <div><label class="label" for="sort">Sort by</label><select id="sort" class="select w-full" name="sort"><option value="next_billing_date" @selected(($filters['sort'] ?? 'next_billing_date') === 'next_billing_date')>Next renewal</option><option value="monthly_cost" @selected(($filters['sort'] ?? '') === 'monthly_cost')>Monthly equivalent cost</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name</option></select></div>
                        <div><label class="label" for="direction">Order</label><select id="direction" class="select w-full" name="direction"><option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>Ascending</option><option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>Descending</option></select></div>
                        <input type="hidden" name="renewal_window" value="{{ $renewalWindow }}">
                        <div class="flex items-end gap-2"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-ghost" href="{{ route('subscriptions.index') }}">Reset</a></div>
                    </form>
                    <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
                        <table class="table">
                            <thead><tr><th>Subscription</th><th>Price</th><th>Next renewal</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($subscriptions as $subscription)
                                    <tr>
                                        <td><a class="link link-hover font-semibold" href="{{ route('subscriptions.show', $subscription) }}">{{ $subscription->name }}</a><p class="mt-1 text-xs opacity-60">{{ $subscription->category ?? 'Uncategorised' }}</p></td>
                                        <td class="whitespace-nowrap"><span class="font-medium">ZAR {{ number_format($subscription->amount_cents / 100, 2) }}</span><p class="mt-1 text-xs opacity-60">{{ $subscription->billing_frequency->label() }}</p></td>
                                        <td class="whitespace-nowrap">{{ $subscription->nextRenewalDate()?->format('d M Y') ?? '—' }}</td>
                                        <td><span @class(['badge badge-sm', 'badge-success' => $subscription->status === \App\SubscriptionStatus::Active, 'badge-warning' => $subscription->status === \App\SubscriptionStatus::Paused, 'badge-ghost' => $subscription->status === \App\SubscriptionStatus::Cancelled])>{{ $subscription->status->label() }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-10 text-center opacity-70">No subscriptions match your filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $subscriptions->links() }}
                </section>
                <section class="space-y-4" aria-labelledby="upcoming-renewals-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="upcoming-renewals-title" class="text-xl font-semibold">Upcoming renewals</h2>
                        <div class="join" aria-label="Renewal period">
                            <a @class(['btn btn-sm join-item', 'btn-active' => $renewalWindow === 7]) href="{{ route('subscriptions.index', [...request()->except(['page', 'renewal_window']), 'renewal_window' => 7]) }}" @if($renewalWindow === 7) aria-current="true" @endif>7 days</a>
                            <a @class(['btn btn-sm join-item', 'btn-active' => $renewalWindow === 30]) href="{{ route('subscriptions.index', [...request()->except(['page', 'renewal_window']), 'renewal_window' => 30]) }}" @if($renewalWindow === 30) aria-current="true" @endif>30 days</a>
                        </div>
                    </div>
                    <div class="rounded-box border border-base-300 bg-base-100">
                        @forelse($renewals as $renewal)
                            <a class="flex items-start justify-between gap-3 border-b border-base-300 p-4 last:border-b-0 hover:bg-base-200" href="{{ route('subscriptions.show', $renewal['subscription']) }}">
                                <div><p class="font-semibold">{{ $renewal['subscription']->name }}</p><p class="mt-1 text-sm opacity-60">{{ $renewal['date']->format('d M Y') }}</p></div>
                                <span class="whitespace-nowrap text-sm font-semibold">ZAR {{ number_format($renewal['subscription']->amount_cents / 100, 2) }}</span>
                            </a>
                        @empty
                            <p class="p-6 text-sm opacity-70">No active subscriptions are due in the next {{ $renewalWindow }} days.</p>
                        @endforelse
                    </div>
                    <p class="text-xs opacity-60">All your active subscriptions are included here, regardless of list filters.</p>
                </section>
            </div>
        @endif
    </div>
@endsection
