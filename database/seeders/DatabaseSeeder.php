<?php

namespace Database\Seeders;

use App\Domain\Budgeting\Enums\BudgetGroup;
use App\Domain\Budgeting\Enums\BudgetPeriodState;
use App\Domain\Budgeting\Models\BudgetAllocation;
use App\Domain\Budgeting\Models\BudgetPeriod;
use App\Domain\Budgeting\Models\BudgetSubcategory;
use App\Domain\Debts\Models\Debt;
use App\Domain\Debts\Models\DebtPayment;
use App\Domain\Identity\Enums\Capability;
use App\Domain\Identity\Models\AuditEvent;
use App\Domain\Identity\Models\Household;
use App\Domain\Identity\Models\HouseholdMembership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\NetWorth\Enums\NetWorthAccountType;
use App\Domain\NetWorth\Models\NetWorthAccount;
use App\Domain\NetWorth\Models\NetWorthSnapshot;
use App\Domain\Settings\Models\InstallationSetting;
use App\Domain\Subscriptions\Enums\RecurrenceUnit;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Transactions\Enums\TransactionType;
use App\Domain\Transactions\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Representative data is never seeded in production.');

            return;
        }

        DB::transaction(function (): void {
            $household = Household::factory()->create(['name' => 'Moola Demo Household']);
            $administrator = User::factory()->administrator()->create([
                'name' => 'Demo Administrator',
                'email' => 'admin@moola.test',
                'email_normalized' => 'admin@moola.test',
            ]);

            HouseholdMembership::query()->create([
                'household_id' => $household->id,
                'user_id' => $administrator->id,
                'joined_at' => now(),
            ]);

            foreach (Capability::cases() as $capability) {
                Permission::query()->create([
                    'household_id' => $household->id,
                    'user_id' => $administrator->id,
                    'capability' => $capability,
                ]);
            }

            InstallationSetting::query()->forceCreate([
                'id' => 1,
                'household_id' => $household->id,
                'currency_code' => 'ZAR',
                'locale' => 'en_ZA',
                'timezone' => 'Africa/Johannesburg',
                'budget_start_day' => 25,
                'backup_retention_count' => 10,
            ]);

            $rent = $this->subcategory($household, BudgetGroup::Essentials, 'Rent', 0, false);
            $groceries = $this->subcategory($household, BudgetGroup::Essentials, 'Groceries', 1, true);
            $funMoney = $this->subcategory($household, BudgetGroup::GuiltFreeSpend, 'Fun Money', 0, false);
            $emergencyFund = $this->subcategory($household, BudgetGroup::Future, 'Emergency Fund', 0, true);

            $period = BudgetPeriod::query()->create([
                'household_id' => $household->id,
                'starts_on' => '2026-08-25',
                'ends_on' => '2026-09-24',
                'expected_income_minor' => 2_500_000,
                'state' => BudgetPeriodState::Finalized,
                'finalized_at' => now(),
            ]);

            foreach ([
                [$rent, 1_000_000, 0],
                [$groceries, 500_000, 12_500],
                [$funMoney, 300_000, 0],
                [$emergencyFund, 700_000, 25_000],
            ] as [$subcategory, $allocation, $openingRollover]) {
                BudgetAllocation::query()->create([
                    'budget_period_id' => $period->id,
                    'budget_subcategory_id' => $subcategory->id,
                    'allocation_minor' => $allocation,
                    'opening_rollover_minor' => $openingRollover,
                ]);
            }

            $this->transaction($household, $administrator, null, TransactionType::Income, 2_500_000, 'Salary', '2026-08-25');
            $this->transaction($household, $administrator, $groceries, TransactionType::Expense, 125_050, 'Monthly groceries', '2026-08-27');
            $this->transaction($household, $administrator, $funMoney, TransactionType::Expense, 45_000, 'Dinner out', '2026-09-02');

            $debt = Debt::query()->create([
                'household_id' => $household->id,
                'name' => 'Credit card',
                'current_principal_minor' => 850_000,
                'apr_basis_points' => 2_150,
                'minimum_payment_minor' => 50_000,
                'planned_payment_minor' => 75_000,
                'opened_on' => '2025-04-12',
            ]);

            DebtPayment::query()->create([
                'debt_id' => $debt->id,
                'recorded_by_user_id' => $administrator->id,
                'amount_minor' => 75_000,
                'balance_after_minor' => 850_000,
                'paid_on' => '2026-08-30',
                'note' => 'Representative manual payment',
            ]);

            $savings = $this->netWorthAccount($household, 'Savings', NetWorthAccountType::Asset, 1_250_000);
            $creditCard = $this->netWorthAccount($household, 'Credit card', NetWorthAccountType::Liability, 850_000);

            foreach ([
                [$savings, 1_100_000, '2026-08-31'],
                [$savings, 1_250_000, '2026-09-13'],
                [$creditCard, 925_000, '2026-08-31'],
                [$creditCard, 850_000, '2026-09-13'],
            ] as [$account, $balance, $recordedOn]) {
                NetWorthSnapshot::query()->create([
                    'net_worth_account_id' => $account->id,
                    'recorded_by_user_id' => $administrator->id,
                    'balance_minor' => $balance,
                    'recorded_on' => $recordedOn,
                ]);
            }

            Subscription::query()->create([
                'household_id' => $household->id,
                'budget_subcategory_id' => $funMoney->id,
                'name' => 'Streaming service',
                'amount_minor' => 10_900,
                'recurrence_unit' => RecurrenceUnit::Month,
                'recurrence_interval' => 1,
                'anchor_day' => 30,
                'occurs_on_last_day' => false,
                'next_charge_on' => '2026-09-30',
                'is_active' => true,
            ]);

            AuditEvent::query()->create([
                'household_id' => $household->id,
                'actor_user_id' => $administrator->id,
                'event_type' => 'representative_data.seeded',
                'subject_type' => Household::class,
                'subject_id' => $household->id,
                'metadata' => ['source' => 'DatabaseSeeder'],
                'occurred_at' => now(),
            ]);
        });
    }

    private function subcategory(
        Household $household,
        BudgetGroup $group,
        string $label,
        int $position,
        bool $rolloverEnabled,
    ): BudgetSubcategory {
        return BudgetSubcategory::query()->create([
            'household_id' => $household->id,
            'main_group_identifier' => $group,
            'label' => $label,
            'position' => $position,
            'rollover_enabled' => $rolloverEnabled,
        ]);
    }

    private function transaction(
        Household $household,
        User $user,
        ?BudgetSubcategory $subcategory,
        TransactionType $type,
        int $amountMinor,
        string $description,
        string $transactedOn,
    ): void {
        Transaction::query()->create([
            'household_id' => $household->id,
            'entered_by_user_id' => $user->id,
            'budget_subcategory_id' => $subcategory?->id,
            'type' => $type,
            'amount_minor' => $amountMinor,
            'transacted_on' => $transactedOn,
            'description' => $description,
        ]);
    }

    private function netWorthAccount(
        Household $household,
        string $name,
        NetWorthAccountType $type,
        int $balanceMinor,
    ): NetWorthAccount {
        return NetWorthAccount::query()->create([
            'household_id' => $household->id,
            'name' => $name,
            'type' => $type,
            'current_balance_minor' => $balanceMinor,
        ]);
    }
}
