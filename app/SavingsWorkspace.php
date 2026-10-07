<?php

namespace App;

use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SavingsWorkspace
{
    /** @return array{rows: Collection, saved: int, target: int, monthly: int, completed: int, behind: int, forecast: Collection, forecastMonths: int, contributions: Collection, trends: Collection} */
    public function build(User $user): array
    {
        $today = CarbonImmutable::today();
        $goals = SavingsGoal::query()->where('user_id', $user->id)->with(['contributions' => fn (HasMany $query): HasMany => $query->with('account')->whereDate('date', '<=', $today)->orderByDesc('date')->orderByDesc('id'), 'budget', 'account'])->orderBy('name')->get();
        $rows = $goals->map(function (SavingsGoal $goal) use ($today): array {
            $saved = $goal->opening_cents + $goal->contributions->sum('amount_cents');
            $remaining = max(0, $goal->target_cents - $saved);
            $thisMonth = $goal->contributions->filter(fn (SavingsContribution $entry): bool => $entry->date->gte($today->startOfMonth()))->sum('amount_cents');
            $monthly = $goal->monthly_cents;
            $needed = null;
            $expected = $goal->opening_cents;
            if ($goal->target_date !== null) {
                $months = max(1, ($goal->target_date->year - $today->year) * 12 + $goal->target_date->month - $today->month + 1);
                $needed = (int) ceil($remaining / $months);
                $originalMonths = max(1, ($goal->target_date->year - $goal->start_date->year) * 12 + $goal->target_date->month - $goal->start_date->month + 1);
                $monthly = $monthly ?: (int) ceil(max(0, $goal->target_cents - $goal->opening_cents) / $originalMonths);
                $totalDays = max(1, (int) $goal->start_date->diffInDays($goal->target_date));
                $elapsed = max(0, min($totalDays, (int) $goal->start_date->diffInDays($today)));
                $expected += (int) ceil(max(0, $goal->target_cents - $goal->opening_cents) * $elapsed / $totalDays);
            }
            $status = $remaining === 0 ? 'Complete' : ($goal->target_date?->lt($today) ? 'Past target date' : ($goal->target_date !== null ? ($saved >= $expected ? 'On track' : 'Needs a boost') : 'Building savings'));
            $currentMonthForecast = min($remaining, max(0, $monthly - $thisMonth));
            $forecastMonths = $remaining === 0 ? 0 : ($monthly > 0 ? 1 + (int) ceil(max(0, $remaining - $currentMonthForecast) / $monthly) : null);
            $forecastDate = $remaining === 0
                ? $today
                : ($forecastMonths === null ? null : $today->startOfMonth()->addMonths($forecastMonths - 1)->endOfMonth());

            return ['goal' => $goal, 'saved' => $saved, 'remaining' => $remaining, 'monthly' => $remaining > 0 ? $monthly : 0, 'needed' => $needed, 'thisMonth' => $thisMonth, 'monthRemaining' => $currentMonthForecast, 'shortfall' => max(0, $expected - $saved), 'progress' => min(100, (int) floor($saved * 100 / $goal->target_cents)), 'status' => $status, 'forecastMonths' => $forecastMonths, 'forecastDate' => $forecastDate];
        });
        $forecastMonths = max(1, (int) $rows->max('forecastMonths'));
        $forecast = collect(range(0, $forecastMonths))->map(function (int $month) use ($rows, $today): array {
            $saved = $rows->sum(function (array $row) use ($month): int {
                $goal = $row['goal'];
                $projected = $row['saved'];
                if ($month > 0 && $row['remaining'] > 0) {
                    $projected += $row['monthRemaining'] + max(0, $month - 1) * $row['monthly'];
                }

                return min($goal->target_cents, $projected);
            });

            return ['date' => $month === 0 ? $today->toDateString() : $today->startOfMonth()->addMonths($month - 1)->endOfMonth()->toDateString(), 'saved' => $saved];
        });
        $contributions = $goals->flatMap(fn (SavingsGoal $goal): Collection => $goal->contributions->map(fn (SavingsContribution $entry): array => ['entry' => $entry, 'goal' => $goal]))->sortByDesc(fn (array $row): string => $row['entry']->date->toDateString().'|'.str_pad((string) $row['entry']->id, 20, '0', STR_PAD_LEFT))->values();
        $trends = collect(range(5, 0))->map(function (int $offset) use ($today, $contributions): array {
            $month = $today->startOfMonth()->subMonths($offset);

            return ['label' => $month->format('M'), 'date' => $month->format('M Y'), 'amount' => $contributions->filter(fn (array $row): bool => $row['entry']->date->format('Y-m') === $month->format('Y-m'))->sum(fn (array $row): int => $row['entry']->amount_cents)];
        });

        return ['rows' => $rows, 'saved' => $rows->sum('saved'), 'target' => $rows->sum(fn (array $row): int => $row['goal']->target_cents), 'monthly' => $rows->sum('monthly'), 'completed' => $rows->where('status', 'Complete')->count(), 'behind' => $rows->whereIn('status', ['Needs a boost', 'Past target date'])->count(), 'forecast' => $forecast, 'forecastMonths' => $forecastMonths, 'contributions' => $contributions, 'trends' => $trends];
    }
}
