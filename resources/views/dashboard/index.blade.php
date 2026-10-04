@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Welcome, {{ auth()->user()->name }}</h1>
            <p class="mt-2 opacity-70">Your space for managing individual and household finances.</p>
        </div>
        @include('dashboard.budget-trends')
        @include('dashboard.budgets')
        @include('dashboard.debts')
    @include('dashboard.goals')
    @include('dashboard.net-worth')
        <section class="space-y-5" aria-labelledby="dashboard-subscriptions-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="dashboard-subscriptions-title" class="text-xl font-semibold">Subscription overview</h2>
                <a class="btn btn-sm btn-outline" href="{{ route('subscriptions.index') }}">View subscriptions and analytics</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="card border border-base-300 bg-base-100"><div class="card-body"><p class="text-sm opacity-70">Active subscriptions</p><p class="text-3xl font-bold">{{ $activeCount }}</p></div></div>
                <div class="card border border-base-300 bg-base-100"><div class="card-body"><p class="text-sm opacity-70">Monthly equivalent</p><p class="text-2xl font-bold">ZAR {{ number_format($monthlyCostCents / 100, 2) }}</p></div></div>
                <div class="card border border-base-300 bg-base-100"><div class="card-body"><p class="text-sm opacity-70">Annual equivalent</p><p class="text-2xl font-bold">ZAR {{ number_format($analytics['annualCostCents'] / 100, 2) }}</p></div></div>
                <div class="card border border-primary/30 bg-primary/5"><div class="card-body"><p class="text-sm opacity-70">Expected in next 7 days</p><p class="text-2xl font-bold">ZAR {{ number_format($analytics['next7DaysCostCents'] / 100, 2) }}</p><p class="text-xs opacity-60">{{ $analytics['next7DaysPaymentCount'] }} expected {{ $analytics['next7DaysPaymentCount'] === 1 ? 'payment' : 'payments' }}</p></div></div>
            </div>
            <p class="text-sm opacity-70">Your active subscriptions only. Monthly and annual equivalents are estimates; upcoming renewals are forecasts, not confirmed payments.</p>
            @if($activeCount > 0)
                <div class="grid gap-5 xl:grid-cols-3">
                    @include('subscriptions.category-chart', ['chartId' => 'dashboard-category-chart'])
                    <section class="card border border-base-300 bg-base-100 xl:col-span-2" aria-labelledby="dashboard-renewals-title">
                        <div class="card-body gap-5">
                            <div><h3 id="dashboard-renewals-title" class="card-title">Upcoming renewals</h3><p class="mt-1 text-sm opacity-70">The next renewal for up to five subscriptions due within 30 days.</p></div>
                            <div class="divide-y divide-base-300">
                                @forelse($renewals as $renewal)
                                    <a class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0 link-hover" href="{{ route('subscriptions.show', $renewal['subscription']) }}">
                                        <div><p class="font-semibold">{{ $renewal['subscription']->name }}</p><p class="mt-1 text-sm opacity-60">{{ $renewal['date']->format('d M Y') }}</p></div>
                                        <span class="shrink-0 font-semibold">ZAR {{ number_format($renewal['subscription']->amount_cents / 100, 2) }}</span>
                                    </a>
                                @empty
                                    <p class="py-4 text-sm opacity-70">No active subscriptions are due in the next 30 days.</p>
                                @endforelse
                            </div>
                            @if($analytics['largestSubscriptions'])
                                @php $largestCost = $analytics['largestSubscriptions'][0]; @endphp
                                <div class="rounded-sm bg-base-200 p-4"><p class="text-xs opacity-60">Largest recurring cost</p><p class="mt-1 text-sm"><a class="link link-hover font-semibold" href="{{ route('subscriptions.show', $largestCost['id']) }}">{{ $largestCost['name'] }}</a> · ZAR {{ number_format($largestCost['monthly_cost_cents'] / 100, 2) }}/month equivalent</p></div>
                            @endif
                            <a class="link link-primary self-start text-sm" href="{{ route('subscriptions.index') }}">Explore forecasts and potential savings</a>
                        </div>
                    </section>
                </div>
            @else
                <div class="card border border-dashed border-base-300 bg-base-100"><div class="card-body items-start"><h3 class="card-title">{{ $hasSubscriptions ? 'No active subscriptions' : 'Track your first subscription' }}</h3><p class="opacity-70">{{ $hasSubscriptions ? 'Reactivate a subscription to see your category chart and upcoming renewals.' : 'Add a recurring expense to see spending insights here.' }}</p><a class="btn btn-primary mt-2" href="{{ $hasSubscriptions ? route('subscriptions.index') : route('subscriptions.create') }}">{{ $hasSubscriptions ? 'View subscriptions' : 'Add subscription' }}</a></div></div>
            @endif
        </section>
        @can('users.manage')
            <div class="card border border-base-300 bg-base-100">
                <div class="card-body items-start">
                    <h2 class="card-title">Manage your household</h2>
                    <p>Add users and assign their roles to get everyone set up.</p>
                    <a class="btn btn-primary mt-2" href="{{ route('admin.users.index') }}">Manage users</a>
                </div>
            </div>
        @endcan
    </div>
@endsection
