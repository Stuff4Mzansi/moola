<?php

namespace App\Http\Controllers;

use App\BudgetDashboard;
use App\BudgetTrends;
use App\DebtPayoff;
use App\DebtWorkspace;
use App\LiquidityAnalytics;
use App\Models\Subscription;
use App\NetWorthWorkspace;
use App\SavingsWorkspace;
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
    public function __invoke(Request $request, SubscriptionAnalytics $subscriptionAnalytics, BudgetDashboard $budgetDashboard, BudgetTrends $budgetTrends, DebtWorkspace $debtWorkspace, DebtPayoff $debtPayoff, SavingsWorkspace $savingsWorkspace, NetWorthWorkspace $netWorthWorkspace, LiquidityAnalytics $liquidityAnalytics): View
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

        $debtOverview = $debtWorkspace->build($request->user());
        $debtPlan = $debtPayoff->simulate($debtOverview['rows']->map(fn (array $row): array => ['id' => $row['debt']->id, 'name' => $row['debt']->name, 'balance' => $row['balance'], 'rate' => $row['debt']->annual_rate_basis_points, 'minimum' => $row['debt']->minimum_payment_cents])->all(), 0, 'avalanche');

        $netWorthOverview = $netWorthWorkspace->build($request->user(), false);
        $liquidityOverview = $liquidityAnalytics->build($request->user(), [], $netWorthOverview);

        return view('dashboard.index', [
            'liquidityOverview' => $liquidityOverview,
            'netWorthOverview' => $netWorthOverview,
            'savingsOverview' => $savingsWorkspace->build($request->user()),
            'debtOverview' => $debtOverview,
            'debtPlan' => $debtPlan,
            'budgetOverview' => $budgetDashboard->build($request->user()),
            'budgetTrends' => $budgetTrends->build($request->user()),
            'analytics' => $analytics,
            'activeCount' => $analytics['statusCounts']['active'],
            'monthlyCostCents' => (int) round($analytics['annualCostCents'] / 12),
            'renewals' => $renewals,
            'hasSubscriptions' => $subscriptions->isNotEmpty(),
        ]);
    }
}
