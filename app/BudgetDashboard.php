<?php

namespace App;

use App\Models\Budget;
use App\Models\BudgetCommitment;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BudgetDashboard
{
    public function __construct(private BudgetWorkspace $workspace) {}

    /** @return array{cards: Collection, totalBudgets: int, nextPeriod: ?BudgetPeriod, latestPeriod: ?BudgetPeriod} */
    public function build(User $user): array
    {
        $today = CarbonImmutable::today();
        $budgets = Budget::visibleTo($user)->with('periods')->orderBy('name')->get();
        $cards = collect();
        foreach ($budgets as $budget) {
            $period = $budget->periods->first(fn (BudgetPeriod $candidate): bool => $candidate->start_date->lte($today) && $candidate->end_date->gte($today));
            if ($period === null) {
                continue;
            }
            $card = $this->forPeriod($user, $period, $today);
            if ($card !== null) {
                $cards->push($card);
            }
        }
        $periods = $budgets->flatMap->periods;
        $next = $periods->filter(fn (BudgetPeriod $period): bool => $period->start_date->gt($today))->sortBy('start_date')->first();
        $latest = $periods->filter(fn (BudgetPeriod $period): bool => $period->end_date->lt($today))->sortByDesc('end_date')->first();

        return ['cards' => $cards->sortByDesc('risk')->values(), 'totalBudgets' => $budgets->count(), 'nextPeriod' => $next, 'latestPeriod' => $latest];
    }

    /** @return array<string, mixed>|null */
    public function forPeriod(User $user, BudgetPeriod $period, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today();

        return Cache::store('database')->lock('budget:'.$period->budget_id, 30)->block(5, function () use ($period, $today, $user): ?array {
            return DB::transaction(function () use ($period, $today, $user): ?array {
                $current = BudgetPeriod::query()->find($period->id);
                if ($current === null || ! Gate::forUser($user)->allows('view', $current->budget)) {
                    return null;
                }

                return $this->card($current, $today, $user);
            });
        });
    }

    /** @return array<string, mixed> */
    private function card(BudgetPeriod $period, CarbonImmutable $today, User $user): array
    {
        $data = $this->workspace->data($period);
        $totals = $data['totals'];
        $canEdit = Gate::forUser($user)->allows('update', $period->budget);
        $periodDays = (int) $period->start_date->diffInDays($period->end_date) + 1;
        $isPast = $period->end_date->lt($today);
        $isFuture = $period->start_date->gt($today);
        $asOf = $today->min($period->end_date);
        $daysLeft = $isPast ? 0 : ($isFuture ? $periodDays : (int) $asOf->diffInDays($period->end_date) + 1);
        $elapsedDays = $isFuture ? 0 : min($periodDays, (int) $period->start_date->diffInDays($asOf) + 1);
        $url = fn (string $tab): string => route('budgets.index', ['period' => $period->id, 'tab' => $tab]);
        $payments = $data['commitments']->filter(fn (BudgetCommitment $charge): bool => $charge->is_current && $charge->transaction === null)->map(fn (BudgetCommitment $charge): array => ['name' => $charge->name, 'date' => $charge->scheduled_date, 'amount' => $charge->amount_cents, 'tab' => 'subscriptions']);
        $payments = $payments->concat($data['recurringCharges']->filter(fn (BudgetRecurringCharge $charge): bool => $charge->is_current && $charge->transaction === null)->map(fn (BudgetRecurringCharge $charge): array => ['name' => $charge->name, 'date' => $charge->scheduled_date, 'amount' => $charge->amount_cents, 'tab' => 'recurring']));
        $dueSoon = $payments->filter(fn (array $payment): bool => $payment['date']->gte($today) && $payment['date']->lte($today->addDays(6)));
        $overdue = $payments->filter(fn (array $payment): bool => $payment['date']->lt($today));
        $sevenDayExpenses = $dueSoon->map(fn (array $payment): array => [...$payment, 'kind' => 'expense']);
        $sevenDayIncome = $data['incomes']
            ->filter(fn (BudgetIncome $income): bool => $income->expected_date !== null
                && $income->expected_date->gte($today)
                && $income->expected_date->lte($today->addDays(6))
                && $income->expected_cents > $income->received_cents)
            ->map(fn (BudgetIncome $income): array => ['name' => $income->name, 'date' => $income->expected_date, 'amount' => $income->expected_cents - $income->received_cents, 'tab' => 'plan', 'kind' => 'income']);
        $sevenDayEvents = $sevenDayExpenses->concat($sevenDayIncome)->sortBy('date')->values();
        $rows = $data['analyticsRows']->map(function (array $row) use ($data): array {
            $upcoming = $data['groups']->isEmpty() ? $data['categoryRows']->first(fn (array $category): bool => $category['category']->name === $row['name'])['upcoming'] : $row['categories']->sum('upcoming');

            return [...$row, 'upcoming' => $upcoming];
        });
        $overLimits = $rows->filter(fn (array $row): bool => $row['limit'] !== null && $row['spent'] > $row['limit'])->sortByDesc(fn (array $row): int => $row['spent'] - $row['limit']);
        $insights = [];
        if ($totals['expected'] === 0) {
            $insights[] = ['title' => 'Give this budget an income', 'detail' => 'Expected income makes your limits and forecasts useful.', 'amount' => null, 'tone' => 'warning', 'tab' => 'plan', 'action' => $canEdit ? 'Set income' : 'Review income'];
        } elseif ($totals['after_commitments'] < 0) {
            $insights[] = ['title' => 'Spending and forecasts exceed income', 'detail' => 'Recorded spending and unpaid scheduled expenses exceed expected income.', 'amount' => abs($totals['after_commitments']), 'tone' => 'error', 'tab' => 'overview', 'action' => 'Review forecast'];
        }
        if ($totals['unallocated'] < 0) {
            $insights[] = ['title' => 'Your plan exceeds expected income', 'detail' => 'Reduce category allocations or update expected income.', 'amount' => abs($totals['unallocated']), 'tone' => 'warning', 'tab' => 'plan', 'action' => $canEdit ? 'Adjust plan' : 'Review plan'];
        }
        if ($overLimits->isNotEmpty()) {
            $row = $overLimits->first();
            $insights[] = ['title' => $row['name'].' is over its limit', 'detail' => 'Review spending or move money from another category.', 'amount' => $row['spent'] - $row['limit'], 'tone' => 'error', 'tab' => 'plan', 'action' => $canEdit ? 'Adjust limits' : 'Review limits'];
        }
        if ($overdue->isNotEmpty()) {
            $insights[] = ['title' => $overdue->count().' scheduled '.str('payment')->plural($overdue->count()).' not recorded', 'detail' => 'The scheduled date has passed. Confirm whether these were paid.', 'amount' => $overdue->sum('amount'), 'tone' => 'warning', 'tab' => $overdue->sortBy('date')->first()['tab'], 'action' => 'Review payments'];
        }
        if ($totals['unallocated'] > 0 && count($insights) < 2) {
            $insights[] = ['title' => 'Give the remaining money a job', 'detail' => 'Expected income is still available to allocate to categories.', 'amount' => $totals['unallocated'], 'tone' => 'info', 'tab' => 'plan', 'action' => $canEdit ? 'Allocate money' : 'Review allocations'];
        }
        if ($insights === []) {
            $insights[] = ['title' => 'Keep your picture up to date', 'detail' => 'Log everyday expenses and confirm scheduled payments as you go.', 'amount' => null, 'tone' => 'info', 'tab' => 'expenses', 'action' => $canEdit ? 'Record spending' : 'Review spending'];
        }
        $risk = $totals['after_commitments'] < 0 || $overLimits->isNotEmpty() ? 2 : ($totals['unallocated'] < 0 || $overdue->isNotEmpty() || $totals['expected'] === 0 ? 1 : 0);
        $highlightRows = $rows->sortByDesc(fn (array $row): int => $row['spent'] + $row['upcoming'])->take(3)->values();
        $daysInPeriod = (int) $data['period']->start_date->diffInDays($data['period']->end_date) + 1;
        $today = CarbonImmutable::today();
        $dailyPace = [];
        $cumulativeSpent = 0;
        for ($date = $data['period']->start_date, $dayIndex = 0; $date->lte($data['period']->end_date); $date = $date->addDay(), $dayIndex++) {
            $isThroughToday = $date->lte($today);
            if ($isThroughToday) {
                $cumulativeSpent += (int) $data['dailySpending']->get($date->toDateString(), 0);
            }
            $paceElapsedDays = min($dayIndex + 1, $daysInPeriod);
            $dailyPace[] = ['date' => $date->toDateString(), 'spent' => $isThroughToday ? $cumulativeSpent : null, 'planned' => (int) round($totals['planned'] * $paceElapsedDays / $daysInPeriod)];
        }
        $spendingMix = $data['categoryRows']->filter(fn (array $row): bool => $row['spent'] > 0)
            ->map(fn (array $row): array => ['name' => $row['category']->name, 'amount' => $row['spent']])->values();
        $uncategorised = $data['uncategorisedSpent'];
        if ($uncategorised > 0) {
            $spendingMix->push(['name' => 'Uncategorised', 'amount' => $uncategorised]);
        }

        return ['budget' => $period->budget, 'period' => $data['period'], 'totals' => $totals, 'incomes' => $data['incomes']->sortBy('name')->values(), 'dailyPace' => $dailyPace, 'spendingMix' => $spendingMix->all(), 'canEdit' => $canEdit, 'role' => $period->budget->memberRole($user), 'url' => $url('overview'), 'planUrl' => $url('plan'), 'daysLeft' => $daysLeft, 'elapsedDays' => $elapsedDays, 'periodDays' => $periodDays, 'isPast' => $isPast, 'isFuture' => $isFuture, 'forecastSpent' => $isPast ? $totals['spent'] : (int) round($totals['spent'] * $periodDays / max(1, $elapsedDays)), 'elapsedPercent' => $isPast ? 100 : (int) round($elapsedDays / $periodDays * 100), 'spentPercent' => $totals['planned'] > 0 ? round($totals['spent'] / $totals['planned'] * 100, 1) : null, 'dailyRoom' => $daysLeft > 0 ? intdiv(max(0, $totals['after_commitments']), $daysLeft) : 0, 'dueSoonCents' => $dueSoon->sum('amount'), 'dueSoonCount' => $dueSoon->count(), 'sevenDayEvents' => $sevenDayEvents, 'sevenDayIncomeCents' => $sevenDayIncome->sum('amount'), 'overdueCount' => $overdue->count(), 'payments' => $payments->filter(fn (array $payment): bool => $payment['date']->lte($today->addDays(6)))->sortBy('date')->take(3)->values(), 'rows' => $highlightRows, 'topSpentRows' => $spendingMix->sortByDesc('amount')->take(6)->values(), 'grouped' => $data['groups']->isNotEmpty(), 'insights' => array_slice($insights, 0, 2), 'risk' => $risk];
    }
}
