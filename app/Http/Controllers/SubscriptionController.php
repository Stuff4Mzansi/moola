<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionIndexRequest;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use App\SubscriptionAnalytics;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SubscriptionIndexRequest $request, SubscriptionAnalytics $subscriptionAnalytics): View
    {
        $allSubscriptions = $request->user()->subscriptions()->get();
        $activeSubscriptions = $allSubscriptions->where('status', SubscriptionStatus::Active);
        $today = CarbonImmutable::today();
        $annualCostCents = $activeSubscriptions->sum(fn (Subscription $subscription): int => $subscription->annualCostCents());
        $renewals = $activeSubscriptions->flatMap(function (Subscription $subscription) use ($today): array {
            return array_map(fn (CarbonImmutable $date): array => [
                'subscription' => $subscription,
                'date' => $date,
            ], $subscription->renewalsBetween($today, $today->addDays(29)));
        })->sortBy(fn (array $renewal): string => $renewal['date']->toDateString())->values();

        $filters = $request->validated();
        $subscriptions = $allSubscriptions->filter(function (Subscription $subscription) use ($filters): bool {
            return (! isset($filters['search']) || Str::contains($subscription->name, $filters['search'], ignoreCase: true))
                && (! isset($filters['category']) || $subscription->category === $filters['category'])
                && (! isset($filters['status']) || $filters['status'] === 'all' || $subscription->status->value === $filters['status']);
        });

        $sort = $filters['sort'] ?? 'next_billing_date';
        $subscriptions = $subscriptions->sortBy(fn (Subscription $subscription): int|string => match ($sort) {
            'name' => Str::lower($subscription->name),
            'monthly_cost' => $subscription->annualCostCents(),
            default => $subscription->nextRenewalDate($today)?->toDateString() ?? '9999-12-31',
        }, SORT_REGULAR, ($filters['direction'] ?? 'asc') === 'desc')->values();

        $page = (int) ($filters['page'] ?? 1);
        $paginatedSubscriptions = new LengthAwarePaginator($subscriptions->forPage($page, 15), $subscriptions->count(), 15, $page, [
            'path' => route('subscriptions.index'),
            'query' => $request->query(),
        ]);
        $renewalWindow = (int) ($filters['renewal_window'] ?? 30);

        return view('subscriptions.index', [
            'subscriptions' => $paginatedSubscriptions,
            'hasSubscriptions' => $allSubscriptions->isNotEmpty(),
            'categories' => $allSubscriptions->pluck('category')->filter(fn (?string $category): bool => $category !== null && $category !== '')->unique()->sort()->values(),
            'filters' => $filters,
            'activeCount' => $activeSubscriptions->count(),
            'monthlyCostCents' => (int) round($annualCostCents / 12),
            'annualCostCents' => $annualCostCents,
            'upcomingCostCents' => $renewals->sum(fn (array $renewal): int => $renewal['subscription']->amount_cents),
            'renewals' => $renewals->filter(fn (array $renewal): bool => $renewal['date']->lte($today->addDays($renewalWindow - 1))),
            'renewalWindow' => $renewalWindow,
            'analytics' => $subscriptionAnalytics->build($allSubscriptions, $today),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        Gate::authorize('create', Subscription::class);

        return view('subscriptions.create', ['subscription' => new Subscription]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubscriptionRequest $request): RedirectResponse
    {
        $subscription = $request->user()->subscriptions()->create($request->subscriptionData());

        return redirect()->route('subscriptions.show', $subscription)->with('status', 'Subscription added.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subscription $subscription): View
    {
        Gate::authorize('view', $subscription);

        return view('subscriptions.show', ['subscription' => $subscription]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subscription $subscription): View
    {
        Gate::authorize('update', $subscription);

        return view('subscriptions.edit', ['subscription' => $subscription]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update($request->subscriptionData());

        return redirect()->route('subscriptions.show', $subscription)->with('status', 'Subscription updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscription $subscription): RedirectResponse
    {
        Gate::authorize('delete', $subscription);
        $subscription->delete();

        return redirect()->route('subscriptions.index')->with('status', 'Subscription deleted.');
    }
}
