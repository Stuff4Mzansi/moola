<?php

namespace App;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

class PlanningScenarios
{
    /** @return array<string, string> */
    public function kinds(): array
    {
        return ['debt' => 'Debt payoff', 'subscriptions' => 'Subscription savings', 'liquidity' => 'Cash flow'];
    }

    /** @return array<string, mixed> */
    public function defaults(string $kind): array
    {
        return match ($kind) {
            'subscriptions' => ['subscription_ids' => [], 'target' => null],
            'liquidity' => ['horizon' => 30, 'income_delay' => 0, 'extra_debt' => '0.00', 'extra_reserve' => '0.00', 'purchase' => '0.00', 'purchase_after_days' => 0],
            default => ['extra' => '0.00', 'strategy' => 'avalanche'],
        };
    }

    /** @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    public function evaluate(User $user, string $kind, array $inputs, ?int $horizon = null): array
    {
        return match ($kind) {
            'debt' => $this->debt($user, $inputs),
            'subscriptions' => $this->subscriptions($user, $inputs),
            'liquidity' => $this->liquidity($user, $inputs, $horizon),
        };
    }

    /** @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private function debt(User $user, array $inputs): array
    {
        $data = app(DebtWorkspace::class)->build($user);
        $debts = $data['rows']->map(fn (array $row): array => ['id' => $row['debt']->id, 'name' => $row['debt']->name, 'balance' => $row['balance'], 'rate' => $row['debt']->annual_rate_basis_points, 'minimum' => $row['debt']->minimum_payment_cents])->all();
        $plan = app(DebtPayoff::class)->simulate($debts, BudgetMoney::cents($inputs['extra']), $inputs['strategy']);

        return ['metrics' => ['Monthly payment budget' => $plan['budget'], 'Estimated interest' => $plan['interest']], 'headline' => $data['total'] === 0 ? 'No outstanding debt' : ($plan['date'] ?? 'Debt not cleared in the forecast'), 'detail' => $plan['months'] !== null ? $plan['months'].' months to clear current debt' : 'Interest covers the modeled period only; the balance is not fully repaid.', 'score' => $plan['months'], 'scoreLabel' => 'Months to payoff', 'warnings' => $data['total'] === 0 ? ['Add debts to make this comparison useful.'] : [], 'plan' => $plan];
    }

    /** @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private function subscriptions(User $user, array $inputs): array
    {
        $active = $user->subscriptions()->get()->where('status', SubscriptionStatus::Active);
        $selected = $active->whereIn('id', $inputs['subscription_ids']);
        $annual = $active->sum(fn (Subscription $subscription): int => $subscription->annualCostCents());
        $reduction = $selected->sum(fn (Subscription $subscription): int => $subscription->annualCostCents());
        $monthly = (int) round(($annual - $reduction) / 12);
        $target = ($inputs['target'] ?? null) !== null ? BudgetMoney::cents($inputs['target']) : null;
        $warnings = count($inputs['subscription_ids']) > $selected->count() ? ['Some selected subscriptions are paused, cancelled, or removed. They are excluded from estimated savings.'] : [];

        return ['metrics' => ['Remaining monthly equivalent' => $monthly, 'Monthly savings' => (int) round($reduction / 12), 'Annual savings' => $reduction], 'headline' => 'ZAR '.number_format($monthly / 100, 2).' / month', 'detail' => $target === null ? 'Current billing frequencies, with selected services excluded.' : ($monthly <= $target ? 'Within your monthly target.' : 'ZAR '.number_format(($monthly - $target) / 100, 2).' above your monthly target.'), 'score' => $monthly, 'scoreLabel' => 'Remaining monthly equivalent (ZAR)', 'warnings' => $warnings];
    }

    /** @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private function liquidity(User $user, array $inputs, ?int $horizon): array
    {
        $horizon ??= (int) $inputs['horizon'];
        $forecast = app(LiquidityAnalytics::class)->build($user, [...$inputs, 'horizon' => $horizon, 'purchase_date' => CarbonImmutable::today()->addDays((int) $inputs['purchase_after_days'])->toDateString()]);
        $warnings = [];
        if ($forecast['unknown'] || $forecast['stale'] || $forecast['overallocated'] || $forecast['missingIncome'] || $forecast['overdue'] || $forecast['foreignSubscriptions']) {
            $warnings[] = 'Cash-flow inputs need review. Check asset access, valuations, reserves, and dated income in Net worth → Liquidity.';
        }
        if ((int) $inputs['purchase_after_days'] >= $horizon && BudgetMoney::cents($inputs['purchase']) > 0) {
            $warnings[] = 'The purchase falls after this comparison window and is excluded.';
        }

        return ['metrics' => ['Lowest available cash' => $forecast['lowest']['low'], 'Closing available cash' => $forecast['daily']->last()['closing'], 'Extra cash to maintain buffer' => $forecast['bufferGap']], 'headline' => $forecast['cashGap'] > 0 ? 'Cash shortfall: ZAR '.number_format($forecast['cashGap'] / 100, 2) : 'No cash shortfall in this window', 'detail' => $horizon.' days from today. Lowest cash on '.CarbonImmutable::parse($forecast['lowest']['date'])->format('d M Y').'. Uses current liquidity settings.', 'score' => $forecast['lowest']['low'], 'scoreLabel' => 'Lowest available cash (ZAR)', 'warnings' => $warnings, 'daily' => $forecast['daily']->all(), 'buffer' => $forecast['preference']->buffer_cents];
    }
}
