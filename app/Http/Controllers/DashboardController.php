<?php

namespace App\Http\Controllers;

use App\BudgetDashboard;
use App\BudgetTrends;
use App\DashboardLayout;
use App\DebtPayoff;
use App\DebtWorkspace;
use App\LiquidityAnalytics;
use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\Subscription;
use App\NetWorthWorkspace;
use App\SavingsWorkspace;
use App\SubscriptionAnalytics;
use App\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $debtOverview = null;
        $debtPlan = null;
        if (config('features.debt_tracking')) {
            $debtOverview = $debtWorkspace->build($request->user());
            $debtPlan = $debtPayoff->simulate($debtOverview['rows']->map(fn (array $row): array => ['id' => $row['debt']->id, 'name' => $row['debt']->name, 'balance' => $row['balance'], 'rate' => $row['debt']->annual_rate_basis_points, 'minimum' => $row['debt']->minimum_payment_cents])->all(), 0, 'avalanche');
        }

        $netWorthOverview = $netWorthWorkspace->build($request->user(), false);
        $budgetOverview = $budgetDashboard->build($request->user());
        $dashboardBudgetOptions = Budget::visibleTo($request->user())
            ->with(['periods' => fn (HasMany $query): HasMany => $query->orderByDesc('start_date')])
            ->orderBy('name')
            ->get();
        $availablePeriods = $dashboardBudgetOptions->flatMap->periods;
        $defaultDashboardBudget = $budgetOverview['cards']->first(
            fn (array $card): bool => $card['budget']->scope === 'personal'
                && $card['period']->start_date->isSameDay($today->startOfMonth())
                && $card['period']->end_date->isSameDay($today->endOfMonth())
        ) ?? $budgetOverview['cards']->first(fn (array $card): bool => $card['budget']->scope === 'personal')
            ?? $budgetOverview['cards']->first();
        $defaultPeriod = $defaultDashboardBudget['period'] ?? $availablePeriods->first(
            fn (BudgetPeriod $period): bool => $period->start_date->lte($today) && $period->end_date->gte($today)
        ) ?? $availablePeriods->filter(fn (BudgetPeriod $period): bool => $period->start_date->gt($today))
            ->sortBy('start_date')->first() ?? $availablePeriods->sortByDesc('end_date')->first();
        $dashboardPeriod = $availablePeriods->firstWhere('id', $request->user()->dashboard_budget_period_id)
            ?? $defaultPeriod;
        $dashboardBudget = $dashboardPeriod === null
            ? null
            : $budgetOverview['cards']->first(fn (array $card): bool => $card['period']->is($dashboardPeriod))
                ?? $budgetDashboard->forPeriod($request->user(), $dashboardPeriod);
        $liquidityOverview = $liquidityAnalytics->build($request->user(), [], $netWorthOverview, $dashboardPeriod);
        $dashboardBudgetOverview = [...$budgetOverview, 'cards' => $dashboardBudget === null ? collect() : collect([$dashboardBudget])];
        $savingsOverview = $savingsWorkspace->build($request->user());

        return view('dashboard.index', [
            'dashboardLayout' => app(DashboardLayout::class)->build($request->user()),
            'dashboardDefaults' => app(DashboardLayout::class)->defaults($request->user()),
            'dashboardWidgets' => app(DashboardLayout::class)->widgets($request->user()),
            'liquidityOverview' => $liquidityOverview,
            'dashboardBudgetOptions' => $dashboardBudgetOptions,
            'dashboardPeriod' => $dashboardPeriod,
            'activeBudgetCount' => $budgetOverview['cards']->count(),
            'netWorthOverview' => $netWorthOverview,
            'savingsOverview' => $savingsOverview,
            'debtOverview' => $debtOverview,
            'debtPlan' => $debtPlan,
            'budgetOverview' => $budgetOverview,
            'dashboardBudgetOverview' => $dashboardBudgetOverview,
            'dashboardBudget' => $dashboardBudget,
            'budgetTrends' => $budgetTrends->build($request->user(), $dashboardPeriod?->budget, $dashboardPeriod),
            'analytics' => $analytics,
            'activeCount' => $analytics['statusCounts']['active'],
            'monthlyCostCents' => (int) round($analytics['annualCostCents'] / 12),
            'renewals' => $renewals,
            'hasSubscriptions' => $subscriptions->isNotEmpty(),
        ]);
    }

    public function updateBudgetPeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate(['period_id' => ['required', 'integer', 'exists:budget_periods,id']]);
        $period = BudgetPeriod::query()->findOrFail($validated['period_id']);
        Gate::authorize('view', $period->budget);

        $request->user()->dashboard_budget_period_id = $period->id;
        $request->user()->save();

        return redirect()->route('dashboard');
    }
}
