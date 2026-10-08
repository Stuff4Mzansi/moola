<?php

namespace Database\Seeders;

use App\BillingFrequency;
use App\BudgetWorkspace;
use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetGroup;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentDashboardSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new RuntimeException('Development dashboard data can only be seeded in local, development, or testing environments.');
        }

        DB::transaction(function (): void {
            $owner = $this->owner();
            $today = CarbonImmutable::today();
            $currentStart = $today->startOfMonth();
            $currentEnd = $today->endOfMonth();

            $this->seedSteadyBudget($owner, $currentStart, $currentEnd, $today);
            $this->seedOverspentBudget($owner, $currentStart, $currentEnd, $today);
            $this->seedNoIncomeBudget($owner, $currentStart, $currentEnd);
            $this->seedPeriodExplorer($owner, $today);

            $this->command?->info("Development dashboard data is ready for {$owner->email}.");
        });
    }

    private function owner(): User
    {
        $owner = User::query()->where('role', UserRole::SuperAdmin->value)->first();
        if ($owner !== null) {
            return $owner;
        }

        $owner = User::factory()->superAdmin()->create([
            'name' => 'Development Demo',
            'email' => 'demo@example.test',
            'password' => 'password',
        ]);

        $this->command?->warn('Created demo sign-in: demo@example.test (password: password). Development only.');

        return $owner;
    }

    private function budget(User $owner, string $name, CarbonImmutable $anchorDate): Budget
    {
        $budget = Budget::query()->where('user_id', $owner->id)->where('name', $name)->first();
        if ($budget === null) {
            $budget = Budget::factory()->create(['user_id' => $owner->id, 'name' => $name]);
        }

        $budget->forceFill([
            'scope' => 'personal',
            'include_subscriptions' => false,
            'repeat_cycle' => 'monthly',
            'anchor_date' => $anchorDate->toDateString(),
        ])->save();

        return $budget;
    }

    private function period(Budget $budget, CarbonImmutable $start, CarbonImmutable $end): BudgetPeriod
    {
        $period = $budget->periods()
            ->whereDate('start_date', $start->toDateString())
            ->whereDate('end_date', $end->toDateString())
            ->first();

        if ($period === null) {
            $period = $budget->periods()->create([
                'name' => $start->format('M Y'),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        } else {
            $period->update(['name' => $start->format('M Y')]);
        }

        return $period;
    }

    private function group(BudgetPeriod $period, string $name, int $percentage): BudgetGroup
    {
        $group = $period->groups()->firstOrNew(['name' => $name]);
        $group->forceFill(['percentage_basis_points' => $percentage])->save();

        return $group;
    }

    private function category(BudgetPeriod $period, string $name, ?int $allocation, ?BudgetGroup $group = null, string $kind = 'custom'): BudgetCategory
    {
        $category = $period->categories()->firstOrNew(['name' => $name]);
        $category->forceFill([
            'allocated_cents' => $allocation,
            'kind' => $kind,
            'budget_group_id' => $group?->id,
        ])->save();

        return $category;
    }

    private function income(BudgetPeriod $period, string $name, int $expected, int $received, ?string $expectedDate, ?string $receivedDate): void
    {
        $income = $period->incomes()->firstOrNew(['name' => $name]);
        $income->forceFill([
            'expected_cents' => $expected,
            'received_cents' => $received,
            'expected_date' => $expectedDate,
            'received_date' => $receivedDate,
        ])->save();
    }

    private function transaction(BudgetPeriod $period, BudgetCategory $category, string $description, int $amount, CarbonImmutable $date): void
    {
        $transaction = $period->transactions()->firstOrNew(['description' => $description]);
        $transaction->forceFill([
            'budget_category_id' => $category->id,
            'amount_cents' => $amount,
            'date' => $date->toDateString(),
        ])->save();
    }

    private function recurringExpense(Budget $budget, string $name, string $category, int $amount, CarbonImmutable $start): void
    {
        $expense = $budget->recurringExpenses()->firstOrNew(['name' => $name]);
        $expense->forceFill([
            'category_name' => $category,
            'amount_cents' => $amount,
            'billing_frequency' => BillingFrequency::Monthly,
            'start_date' => $start->toDateString(),
            'end_date' => null,
            'is_active' => true,
        ])->save();
    }

    private function seedSteadyBudget(User $owner, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today): void
    {
        $budget = $this->budget($owner, 'Demo - Steady pace', $start);
        $period = $this->period($budget, $start, $end);
        $needs = $this->group($period, 'Needs', 7500);
        $wants = $this->group($period, 'Wants', 1500);
        $savings = $this->group($period, 'Savings', 1000);
        $home = $this->category($period, 'Home', 450000, $needs);
        $food = $this->category($period, 'Food', 300000, $needs);
        $transport = $this->category($period, 'Transport', 120000, $needs);
        $lifestyle = $this->category($period, 'Lifestyle', 90000, $wants);
        $other = $this->category($period, 'Other', 60000, $wants, 'other');
        $this->category($period, 'Savings', 180000, $savings);
        $this->category($period, 'Subscriptions', null, null, 'subscriptions');

        $receivedDate = $start->addDays(4)->min($today)->toDateString();
        $this->income($period, 'Monthly salary', 1200000, 900000, $today->max($start->addDays(10))->min($end)->toDateString(), $receivedDate);
        $this->income($period, 'Freelance work', 200000, 0, $today->max($start)->min($end)->toDateString(), null);

        $this->transaction($period, $home, 'Demo rent payment', 160000, $start->addDays(1)->min($today));
        $this->transaction($period, $food, 'Demo weekly groceries', 80000, $start->addDays(2)->min($today));
        $this->transaction($period, $transport, 'Demo travel card', 35000, $start->addDays(4)->min($today));
        $this->transaction($period, $lifestyle, 'Demo family outing', 30000, $today);
        $this->transaction($period, $other, 'Demo small purchase', 12500, $today);

        $this->recurringExpense($budget, 'Demo - Internet bill', 'Home', 65000, $today->addDays(4)->min($end));
        app(BudgetWorkspace::class)->sync($period, true);
    }

    private function seedOverspentBudget(User $owner, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today): void
    {
        $budget = $this->budget($owner, 'Demo - Over budget', $start);
        $period = $this->period($budget, $start, $end);
        $needs = $this->group($period, 'Essentials', 5000);
        $food = $this->category($period, 'Food', 250000, $needs);
        $transport = $this->category($period, 'Transport', 100000, $needs);
        $this->category($period, 'Other', 0, null, 'other');
        $this->income($period, 'Expected pay', 500000, 300000, $today->toDateString(), $today->toDateString());
        $this->transaction($period, $food, 'Demo extra groceries', 420000, $start->addDays(2));
        $this->transaction($period, $transport, 'Demo car repair', 180000, $start->addDays(3));
        $this->recurringExpense($budget, 'Demo - Upcoming car payment', 'Transport', 225000, $today->addDays(5)->min($end));
        app(BudgetWorkspace::class)->sync($period, true);
    }

    private function seedNoIncomeBudget(User $owner, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $budget = $this->budget($owner, 'Demo - Income not set', $start);
        $period = $this->period($budget, $start, $end);
        $needs = $this->group($period, 'Essentials', 7000);
        $this->category($period, 'Home', 700000, $needs);
        $this->category($period, 'Food', 250000, $needs);
        $this->category($period, 'Other', 0, null, 'other');
    }

    private function seedPeriodExplorer(User $owner, CarbonImmutable $today): void
    {
        $budget = $this->budget($owner, 'Demo - Period explorer', $today->startOfMonth());
        $periodDates = [
            [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), 'Completed'],
            [$today->startOfMonth(), $today->endOfMonth(), 'Current'],
            [$today->addMonthNoOverflow()->startOfMonth(), $today->addMonthNoOverflow()->endOfMonth(), 'Upcoming'],
        ];

        foreach ($periodDates as [$start, $end, $state]) {
            $period = $this->period($budget, $start, $end);
            $needs = $this->group($period, 'Essentials', 6000);
            $home = $this->category($period, 'Home', 500000, $needs);
            $food = $this->category($period, 'Food', 250000, $needs);
            $this->category($period, 'Other', 50000, null, 'other');

            if ($state === 'Completed') {
                $this->income($period, 'Monthly salary', 1000000, 1000000, $start->addDays(1)->toDateString(), $start->addDays(1)->toDateString());
                $this->transaction($period, $home, 'Demo completed rent', 500000, $start->addDays(2));
                $this->transaction($period, $food, 'Demo completed groceries', 180000, $start->addDays(10));
            } elseif ($state === 'Upcoming') {
                $this->income($period, 'Expected monthly salary', 1000000, 0, $start->addDays(1)->toDateString(), null);
            } else {
                $receivedDate = $start->addDays(1)->min($today)->toDateString();
                $this->income($period, 'Monthly salary', 1000000, 550000, $today->max($start->addDays(10))->min($end)->toDateString(), $receivedDate);
                $this->transaction($period, $home, 'Demo current rent', 500000, $start->addDays(2)->min($today));
            }
        }
    }
}
