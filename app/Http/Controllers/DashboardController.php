<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\SubscriptionAnalytics;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SubscriptionAnalytics $subscriptionAnalytics): View
    {
        $subscriptions = $request->user()->subscriptions()->get();
        $today = CarbonImmutable::today();
        $analytics = $subscriptionAnalytics->build($subscriptions, $today);
        $renewals = $subscriptions->where('status', SubscriptionStatus::Active)
            ->map(fn (Subscription $subscription): array => [
                'subscription' => $subscription,
                'date' => $subscription->nextRenewalDate($today),
            ])
            ->filter(fn (array $renewal): bool => $renewal['date']->lte($today->addDays(29)))
            ->sortBy(fn (array $renewal): string => $renewal['date']->toDateString())
            ->take(5)->values();

        return view('dashboard.index', [
            'analytics' => $analytics,
            'activeCount' => $analytics['statusCounts']['active'],
            'monthlyCostCents' => (int) round($analytics['annualCostCents'] / 12),
            'renewals' => $renewals,
            'hasSubscriptions' => $subscriptions->isNotEmpty(),
        ]);
    }
}
