<?php

namespace App;

use App\Models\Budget;
use App\Models\BudgetNotificationPreference;
use App\Models\BudgetPeriod;
use App\Models\FinancialNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BudgetNotifications
{
    public const TYPES = ['subscription' => 'Subscription reminders', 'recurring' => 'Recurring expense reminders', 'budget_limit' => 'Overall budget warnings', 'category_limit' => 'Category warnings', 'group_limit' => 'Group warnings', 'period_end' => 'Period ending reminders'];

    public function scan(): void
    {
        Budget::query()->each(function (Budget $budget): void {
            Cache::store('database')->lock('budget:'.$budget->id, 30)->block(5, function () use ($budget): void {
                DB::transaction(function () use ($budget): void {
                    $today = CarbonImmutable::today();
                    $periods = $budget->periods()->whereDate('end_date', '>=', $today->subDays(7)->toDateString())->whereDate('start_date', '<=', $today->addDays($budget->reminder_days)->toDateString())->get();
                    foreach ($periods as $period) {
                        $this->evaluate($period, app(BudgetWorkspace::class)->data($period));
                    }
                    FinancialNotification::query()->where('budget_id', $budget->id)->whereNotIn('budget_period_id', $periods->modelKeys())->whereNull('resolved_at')->update(['resolved_at' => now()]);
                });
            });
        });
    }

    /** @param array<string, mixed> $data */
    public function evaluate(BudgetPeriod $period, array $data): void
    {
        $budget = $period->budget;
        $events = $budget->notifications_enabled ? $this->events($period, $data) : [];
        $recipients = collect([$budget->user_id]);
        if ($budget->scope === 'household') {
            $recipients = $recipients->merge($budget->members()->pluck('users.id'));
        }
        $recipients = $recipients->unique();
        $preferences = BudgetNotificationPreference::query()->where('budget_id', $budget->id)->get()->keyBy('user_id');
        foreach ($recipients as $userId) {
            $muted = $preferences->get($userId)?->muted_types ?? [];
            $active = [];
            foreach ($events as $event) {
                if (in_array($event['type'], $muted, true)) {
                    continue;
                }
                $key = $period->id.':'.$event['key'];
                $active[] = $key;
                $payload = collect($event)->except('key')->all();
                $notification = FinancialNotification::query()->firstOrCreate(['user_id' => $userId, 'event_key' => $key], ['budget_id' => $budget->id, 'budget_period_id' => $period->id, ...$payload]);
                if ($notification->resolved_at === null) {
                    $notification->fill($payload);
                    if ($notification->isDirty()) {
                        $notification->save();
                    }
                }
            }
            FinancialNotification::query()->where('user_id', $userId)->where('budget_period_id', $period->id)->whereNull('resolved_at')->whereNotIn('event_key', $active)->update(['resolved_at' => now()]);
        }
        FinancialNotification::query()->where('budget_id', $budget->id)->whereNotIn('user_id', $recipients)->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }

    /** @param array<string, mixed> $data
     * @return list<array{key: string, type: string, title: string, message: string, tab: string}>
     */
    private function events(BudgetPeriod $period, array $data): array
    {
        $today = CarbonImmutable::today();
        $budget = $period->budget;
        $events = [];
        $money = fn (int $cents): string => 'ZAR '.number_format($cents / 100, 2);
        $expected = collect(app(BudgetWorkspace::class)->expectedCommitments($period))->keyBy(fn (array $charge): string => $charge['subscription_id'].'|'.$charge['scheduled_date']);
        $expectedRecurring = collect(app(BudgetRecurringExpenses::class)->expected($period))->keyBy(fn (array $charge): string => $charge['budget_recurring_expense_id'].'|'.$charge['scheduled_date']);
        foreach (['subscription' => $data['commitments'], 'recurring' => $data['recurringCharges']] as $type => $charges) {
            foreach ($charges as $charge) {
                if (! $charge->is_current || $charge->transaction !== null) {
                    continue;
                }
                if ($type === 'subscription' && ! $expected->has($charge->subscription_id.'|'.$charge->scheduled_date->toDateString())) {
                    continue;
                }
                if ($type === 'recurring' && ! $expectedRecurring->has($charge->budget_recurring_expense_id.'|'.$charge->scheduled_date->toDateString())) {
                    continue;
                }
                $days = (int) $today->diffInDays($charge->scheduled_date);
                if ($days > $budget->reminder_days || $period->end_date->lt($today->subDays(7))) {
                    continue;
                }
                $overdue = $days < 0;
                $events[] = ['key' => $type.':'.$charge->id.':'.($overdue ? 'overdue' : 'due'), 'type' => $type, 'title' => $charge->name.($overdue ? ' is overdue' : ($days === 0 ? ' is due today' : ' is due soon')), 'message' => $money($charge->amount_cents).' due '.$charge->scheduled_date->format('d M Y').'. Record the payment when paid.', 'tab' => $type === 'subscription' ? 'subscriptions' : 'recurring'];
            }
        }
        if ($period->start_date->lte($today) && $period->end_date->gte($today)) {
            $limit = $data['totals']['expected'] > 0 ? $data['totals']['expected'] : $data['totals']['planned'];
            if ($limit > 0) {
                $this->limitEvent($events, 'budget', 'budget_limit', $budget->name, $data['totals']['spent'], $limit, $budget->notification_threshold);
            }
            foreach ($data['categoryRows'] as $row) {
                if ($row['planned'] > 0 || $row['category']->allocated_cents === 0) {
                    $this->limitEvent($events, 'category:'.$row['category']->id, 'category_limit', $row['category']->name, $row['spent'], $row['planned'], $budget->notification_threshold);
                }
            }
            foreach ($data['groupRows'] as $row) {
                if ($row['limit'] !== null) {
                    $this->limitEvent($events, 'group:'.$row['group']->id, 'group_limit', $row['group']->name, $row['spent'], $row['limit'], $budget->notification_threshold);
                }
            }
            if ($today->diffInDays($period->end_date) <= $budget->period_reminder_days) {
                $events[] = ['key' => 'period:end', 'type' => 'period_end', 'title' => $budget->name.' period ends '.$period->end_date->format('d M'), 'message' => $money($data['totals']['remaining']).' remains against expected income, with '.$money($data['totals']['upcoming']).' in outstanding payments. Review expenses before closing this period.', 'tab' => 'overview'];
            }
        }

        return array_map(fn (array $event): array => [...$event, 'message' => $budget->name.' / '.$period->name.': '.$event['message']], $events);
    }

    /** @param list<array{key: string, type: string, title: string, message: string, tab: string}> $events */
    private function limitEvent(array &$events, string $key, string $type, string $name, int $spent, int $limit, int $threshold): void
    {
        if ($spent <= 0 || ($spent <= $limit && ($limit <= 0 || $spent * 100 < $limit * $threshold))) {
            return;
        }
        $over = $spent > $limit;
        $money = fn (int $cents): string => 'ZAR '.number_format($cents / 100, 2);
        $events[] = ['key' => $key.':'.($over ? 'over' : 'near'), 'type' => $type, 'title' => $name.($over ? ' is over its limit' : ' is nearing its limit'), 'message' => $money($spent).' spent of '.$money($limit).'. '.($over ? $money($spent - $limit).' over the limit.' : $money($limit - $spent).' remains.').' Review your spending or adjust the plan.', 'tab' => 'overview'];
    }
}
