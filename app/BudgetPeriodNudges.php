<?php

namespace App;

use App\Models\BudgetPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BudgetPeriodNudges
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, title: string, message: string, tab: string, action: string, tone: string}>
     */
    public function forPeriod(BudgetPeriod $period, array $data, User $user): array
    {
        $nudges = $this->recommendations($period, $data);
        $states = DB::table('budget_period_nudges')
            ->where('budget_period_id', $period->id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('nudge_key');
        $now = now();

        return array_values(array_filter($nudges, function (array $nudge) use ($states, $now): bool {
            $state = $states->get($nudge['key']);

            return $state === null
                || ($state->dismissed_at === null && ($state->snoozed_until === null || CarbonImmutable::parse($state->snoozed_until)->lte($now)));
        }));
    }

    /** @param array<string, mixed> $data */
    public function update(BudgetPeriod $period, array $data, User $user, string $key, string $action): void
    {
        abort_unless(in_array($action, ['dismiss', 'snooze'], true), 404);
        abort_unless(collect($this->recommendations($period, $data))->contains('key', $key), 404);

        $now = now();
        $identity = ['budget_period_id' => $period->id, 'user_id' => $user->id, 'nudge_key' => $key];
        DB::table('budget_period_nudges')->insertOrIgnore([...$identity, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('budget_period_nudges')->where($identity)->update([
            'dismissed_at' => $action === 'dismiss' ? $now : null,
            'snoozed_until' => $action === 'snooze' ? $now->copy()->addDays(7) : null,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, title: string, message: string, tab: string, action: string, tone: string}>
     */
    private function recommendations(BudgetPeriod $period, array $data): array
    {
        $today = CarbonImmutable::today();
        $money = fn (int $cents): string => app(CurrencySettings::class)->format($cents);
        $nudges = [];

        if ($data['totals']['unallocated'] > 0) {
            $nudges[] = ['key' => 'unallocated-income', 'title' => 'Give unallocated income a plan', 'message' => $money($data['totals']['unallocated']).' of expected income is not assigned to a category yet.', 'tab' => 'plan', 'action' => 'Review plan', 'tone' => 'info'];
        }

        $limitedGroupIds = $data['groups']->filter(fn ($group): bool => $group->percentage_basis_points !== null)->modelKeys();
        $categoryLimits = $data['categoryRows']
            ->filter(fn (array $row): bool => ! in_array($row['category']->budget_group_id, $limitedGroupIds, true))
            ->map(fn (array $row): array => ['name' => $row['category']->name, 'spent' => $row['spent'], 'limit' => $row['planned']]);
        $groupLimits = $data['groupRows']
            ->filter(fn (array $row): bool => $row['limit'] !== null)
            ->map(fn (array $row): array => ['name' => $row['group']->name, 'spent' => $row['spent'], 'limit' => $row['limit']]);
        $limitRows = $categoryLimits->concat($groupLimits);
        $attentionRows = $limitRows->filter(fn (array $row): bool => $row['limit'] > 0 && $row['spent'] >= (int) ceil($row['limit'] * 0.8));
        if ($attentionRows->isNotEmpty()) {
            $overLimit = $attentionRows->filter(fn (array $row): bool => $row['spent'] > $row['limit']);
            $names = $attentionRows->sortByDesc(fn (array $row): float => $row['spent'] / $row['limit'])->take(2)->pluck('name')->join(', ');
            $nudges[] = [
                'key' => 'spending-limits',
                'title' => $overLimit->isNotEmpty() ? 'Some spending limits need attention' : 'Spending is approaching a limit',
                'message' => ($overLimit->isNotEmpty() ? 'Over limit: ' : 'Near limit: ').$names.($attentionRows->count() > 2 ? ' and '.($attentionRows->count() - 2).' more' : '').'. Review the plan or recent expenses.',
                'tab' => 'plan',
                'action' => 'Review plan',
                'tone' => $overLimit->isNotEmpty() ? 'warning' : 'info',
            ];
        }

        if ($period->start_date->lte($today) && $period->end_date->gte($today)) {
            $periodDays = (int) $period->start_date->diffInDays($period->end_date) + 1;
            $elapsedDays = max(1, min($periodDays, (int) $period->start_date->diffInDays($today) + 1));
            $spentToDate = $data['transactions']
                ->filter(fn ($transaction): bool => $transaction->date->lte($today))
                ->sum('amount_cents');
            $expectedIncome = $data['totals']['expected'];
            if ($expectedIncome > 0 && $spentToDate > 0) {
                $daysToExhaustIncome = intdiv($expectedIncome * $elapsedDays + $spentToDate - 1, $spentToDate);
                if ($daysToExhaustIncome <= $periodDays) {
                    $exhaustionDate = $period->start_date->addDays($daysToExhaustIncome - 1);
                    $dailySpending = (int) round($spentToDate / $elapsedDays);
                    $alreadyExhausted = $spentToDate >= $expectedIncome;
                    $nudges[] = [
                        'key' => 'income-runout',
                        'title' => $alreadyExhausted ? 'Recorded spending has reached expected income' : 'Expected income may run out before period ends',
                        'message' => 'Based on recorded spending averaging '.$money($dailySpending).' per day and expected income of '.$money($expectedIncome).', income is projected to run out around '.$exhaustionDate->format('j M').'. This is an estimate using your current spending pace.',
                        'tab' => 'expenses',
                        'action' => 'Review expenses',
                        'tone' => 'warning',
                    ];
                }
            }

            $dueSoon = $today->addDays(3);
            foreach ([
                'subscriptions' => ['charges' => $data['commitments'], 'title' => 'Subscription payments need attention', 'tab' => 'subscriptions'],
                'recurring' => ['charges' => $data['recurringCharges'], 'title' => 'Recurring expenses need attention', 'tab' => 'recurring'],
            ] as $type => $schedule) {
                $charges = $schedule['charges']->filter(fn ($charge): bool => $charge->is_current
                    && $charge->transaction === null
                    && $charge->scheduled_date->lte($dueSoon));
                if ($charges->isNotEmpty()) {
                    $overdue = $charges->filter(fn ($charge): bool => $charge->scheduled_date->lt($today))->count();
                    $nudges[] = [
                        'key' => $type.'-due',
                        'title' => $schedule['title'],
                        'message' => $overdue > 0 ? $overdue.' unpaid '.str($type === 'subscriptions' ? 'subscription payment' : 'recurring expense')->plural($overdue).' overdue; '.$charges->count().' due or overdue in this period.' : $charges->count().' unpaid '.str($type === 'subscriptions' ? 'subscription payment' : 'recurring expense')->plural($charges->count()).' due within 3 days.',
                        'tab' => $schedule['tab'],
                        'action' => 'Review payments',
                        'tone' => $overdue > 0 ? 'warning' : 'info',
                    ];
                }
            }

            $daysLeft = (int) $today->diffInDays($period->end_date);
            if ($daysLeft <= 3) {
                $nudges[] = ['key' => 'period-ending', 'title' => $daysLeft === 0 ? 'This budget period ends today' : 'This budget period is nearly over', 'message' => 'Review any expenses or income you have not recorded before the period closes.', 'tab' => 'review', 'action' => 'Review this period', 'tone' => 'warning'];
            }
        }

        return $nudges;
    }
}
