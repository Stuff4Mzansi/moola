<?php

namespace App;

use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SubscriptionAnalytics
{
    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return array{
     *     categories: list<array{name: string, annual_cost_cents: int, monthly_cost_cents: int, share: float}>,
     *     monthlyForecast: list<array{month: string, label: string, amount_cents: int, payment_count: int, is_partial: bool}>,
     *     activeSubscriptions: list<array{id: int, name: string, annual_cost_cents: int, monthly_cost_cents: int, share: float}>,
     *     largestSubscriptions: list<array{id: int, name: string, annual_cost_cents: int, monthly_cost_cents: int, share: float}>,
     *     annualCostCents: int, forecastTotalCents: int,
     *     next7DaysCostCents: int, next7DaysPaymentCount: int, topThreeShare: float,
     *     peakMonth: ?array{month: string, label: string, amount_cents: int, payment_count: int, is_partial: bool},
     *     statusCounts: array{active: int, paused: int, cancelled: int}
     * }
     */
    public function build(Collection $subscriptions, CarbonImmutable $today): array
    {
        $activeSubscriptions = $subscriptions->where('status', SubscriptionStatus::Active);
        $annualCostCents = $activeSubscriptions->sum(fn (Subscription $subscription): int => $subscription->annualCostCents());
        $categoryTotals = [];
        $monthlyForecast = [];
        $next7DaysCostCents = 0;
        $next7DaysPaymentCount = 0;

        for ($index = 0; $index < 12; $index++) {
            $month = $today->startOfMonth()->addMonths($index);
            $monthlyForecast[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'amount_cents' => 0,
                'payment_count' => 0,
                'is_partial' => $index === 0 && $today->day > 1,
            ];
        }

        $forecastEnd = $today->startOfMonth()->addMonths(11)->endOfMonth();

        foreach ($activeSubscriptions as $subscription) {
            $category = $subscription->category ?? 'Uncategorised';
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $subscription->annualCostCents();

            foreach ($subscription->renewalsBetween($today, $forecastEnd) as $date) {
                $monthKey = $date->format('Y-m');
                $monthlyForecast[$monthKey]['amount_cents'] += $subscription->amount_cents;
                $monthlyForecast[$monthKey]['payment_count']++;

                if ($date->lte($today->addDays(6))) {
                    $next7DaysCostCents += $subscription->amount_cents;
                    $next7DaysPaymentCount++;
                }
            }
        }

        arsort($categoryTotals);
        $categories = [];

        foreach ($categoryTotals as $name => $costCents) {
            $categories[] = [
                'name' => (string) $name,
                'annual_cost_cents' => $costCents,
                'monthly_cost_cents' => (int) round($costCents / 12),
                'share' => $annualCostCents > 0 ? $costCents / $annualCostCents * 100 : 0.0,
            ];
        }

        $rankedSubscriptions = $activeSubscriptions->sortByDesc(fn (Subscription $subscription): int => $subscription->annualCostCents())
            ->map(fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'annual_cost_cents' => $subscription->annualCostCents(),
                'monthly_cost_cents' => $subscription->monthlyCostCents(),
                'share' => $annualCostCents > 0 ? $subscription->annualCostCents() / $annualCostCents * 100 : 0.0,
            ])->values();
        $peakMonth = collect($monthlyForecast)->sortByDesc('amount_cents')->first();

        return [
            'categories' => $categories,
            'monthlyForecast' => array_values($monthlyForecast),
            'activeSubscriptions' => $rankedSubscriptions->all(),
            'largestSubscriptions' => $rankedSubscriptions->take(5)->all(),
            'annualCostCents' => $annualCostCents,
            'forecastTotalCents' => array_sum(array_column($monthlyForecast, 'amount_cents')),
            'next7DaysCostCents' => $next7DaysCostCents,
            'next7DaysPaymentCount' => $next7DaysPaymentCount,
            'topThreeShare' => $annualCostCents > 0 ? $rankedSubscriptions->take(3)->sum('annual_cost_cents') / $annualCostCents * 100 : 0.0,
            'peakMonth' => $peakMonth['amount_cents'] > 0 ? $peakMonth : null,
            'statusCounts' => [
                'active' => $activeSubscriptions->count(),
                'paused' => $subscriptions->where('status', SubscriptionStatus::Paused)->count(),
                'cancelled' => $subscriptions->where('status', SubscriptionStatus::Cancelled)->count(),
            ],
        ];
    }
}
