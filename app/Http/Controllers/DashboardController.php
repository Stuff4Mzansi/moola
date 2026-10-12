<?php

namespace App\Http\Controllers;

use App\BudgetDashboard;
use App\BudgetTrends;
use App\DashboardLayout;
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

        $debtOverview = null;
        $debtPlan = null;
        if (config('features.debt_tracking')) {
            $debtOverview = $debtWorkspace->build($request->user());
            $debtPlan = $debtPayoff->simulate($debtOverview['rows']->map(fn (array $row): array => ['id' => $row['debt']->id, 'name' => $row['debt']->name, 'balance' => $row['balance'], 'rate' => $row['debt']->annual_rate_basis_points, 'minimum' => $row['debt']->minimum_payment_cents])->all(), 0, 'avalanche');
        }

        $netWorthOverview = $netWorthWorkspace->build($request->user(), false);
        $liquidityOverview = $liquidityAnalytics->build($request->user(), [], $netWorthOverview);
        $budgetOverview = $budgetDashboard->build($request->user());
        $dashboardBudget = $budgetOverview['cards']->first(
            fn (array $card): bool => (string) $card['period']->id === (string) $request->query('budget')
        );
        if ($dashboardBudget === null) {
            $calendarMonthBudget = $budgetOverview['cards']->first(fn (array $card): bool => $card['budget']->scope === 'personal'
                && $card['period']->start_date->isSameDay($today->startOfMonth())
                && $card['period']->end_date->isSameDay($today->endOfMonth()));
            $dashboardBudget = $calendarMonthBudget
                ?? $budgetOverview['cards']->first(fn (array $card): bool => $card['budget']->scope === 'personal')
                ?? $budgetOverview['cards']->first();
        }
        $savingsOverview = $savingsWorkspace->build($request->user());
        $attentionItems = collect();

        foreach ($budgetOverview['cards'] as $card) {
            foreach ($card['insights'] as $insight) {
                if (! in_array($insight['tone'], ['error', 'warning'], true)) {
                    continue;
                }

                $attentionItems->push([
                    'tone' => $insight['tone'],
                    'title' => $insight['title'],
                    'detail' => $insight['detail'],
                    'amount' => $insight['amount'],
                    'action' => $insight['action'],
                    'url' => route('budgets.index', ['period' => $card['period']->id, 'tab' => $insight['tab']]),
                ]);
            }
        }

        if ($debtOverview !== null) {
            $unrecordedDebts = $debtOverview['rows']->where('unrecorded', true);
            if ($unrecordedDebts->isNotEmpty()) {
                $attentionItems->push([
                    'tone' => 'warning',
                    'title' => $unrecordedDebts->count().' debt '.str('payment')->plural($unrecordedDebts->count()).' need review',
                    'detail' => 'A scheduled payment date has passed without a recorded payment.',
                    'amount' => null,
                    'action' => 'Review payments',
                    'url' => route('debts.index', ['tab' => 'payments']),
                ]);
            }
        }

        if ($netWorthOverview['stale'] > 0) {
            $attentionItems->push([
                'tone' => 'warning',
                'title' => $netWorthOverview['stale'].' asset '.str('valuation')->plural($netWorthOverview['stale']).' may be out of date',
                'detail' => 'Refresh older values to keep your net worth estimate useful.',
                'amount' => null,
                'action' => 'Review assets',
                'url' => route('net-worth.index', ['tab' => 'assets']),
            ]);
        }

        if ($liquidityOverview['cashGap'] > 0) {
            $attentionItems->push([
                'tone' => 'error',
                'title' => 'Forecast cash gap',
                'detail' => 'Your current cash-flow assumptions show a projected low point. Review timing and upcoming payments.',
                'amount' => $liquidityOverview['cashGap'],
                'action' => 'Review cash flow',
                'url' => route('net-worth.index', ['tab' => 'liquidity']),
            ]);
        }

        $attentionItems = $attentionItems
            ->sortBy(fn (array $item): int => $item['tone'] === 'error' ? 0 : 1)
            ->take(5)
            ->values();

        return view('dashboard.index', [
            'dashboardLayout' => app(DashboardLayout::class)->build($request->user()),
            'dashboardDefaults' => app(DashboardLayout::class)->defaults($request->user()),
            'dashboardWidgets' => app(DashboardLayout::class)->widgets($request->user()),
            'liquidityOverview' => $liquidityOverview,
            'netWorthOverview' => $netWorthOverview,
            'savingsOverview' => $savingsOverview,
            'debtOverview' => $debtOverview,
            'debtPlan' => $debtPlan,
            'budgetOverview' => $budgetOverview,
            'dashboardBudget' => $dashboardBudget,
            'budgetTrends' => $budgetTrends->build($request->user()),
            'analytics' => $analytics,
            'activeCount' => $analytics['statusCounts']['active'],
            'monthlyCostCents' => (int) round($analytics['annualCostCents'] / 12),
            'renewals' => $renewals,
            'hasSubscriptions' => $subscriptions->isNotEmpty(),
            'attentionItems' => $attentionItems,
        ]);
    }
}
