<?php

namespace Tests\Feature;

use App\Domain\Budgeting\Enums\BudgetGroup;
use App\Domain\Identity\Models\Household;
use App\Domain\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_v1_domain_tables_exist(): void
    {
        foreach ([
            'households',
            'users',
            'household_memberships',
            'permissions',
            'installation_settings',
            'budget_subcategories',
            'budget_periods',
            'budget_allocations',
            'transactions',
            'debts',
            'debt_payments',
            'net_worth_accounts',
            'net_worth_snapshots',
            'subscriptions',
            'audit_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_sqlite_foreign_keys_and_query_indexes_are_enabled(): void
    {
        $foreignKeys = DB::selectOne('PRAGMA foreign_keys');
        $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'index'"))
            ->pluck('name');

        $this->assertSame(1, $foreignKeys->foreign_keys);
        $this->assertContains('transactions_household_date_index', $indexes);
        $this->assertContains('subscriptions_upcoming_index', $indexes);
        $this->assertContains('audit_events_household_time_index', $indexes);
    }

    public function test_sqlite_connection_policy_is_resource_conscious_and_local(): void
    {
        $connection = config('database.connections.sqlite');

        $this->assertTrue($connection['foreign_key_constraints']);
        $this->assertSame(5000, $connection['busy_timeout']);
        $this->assertSame('WAL', $connection['journal_mode']);
        $this->assertSame('NORMAL', $connection['synchronous']);
        $this->assertSame('IMMEDIATE', $connection['transaction_mode']);
    }

    public function test_money_and_interest_rate_columns_use_sqlite_integers(): void
    {
        foreach ([
            'budget_periods' => ['expected_income_minor'],
            'budget_allocations' => ['allocation_minor', 'opening_rollover_minor'],
            'transactions' => ['amount_minor'],
            'debts' => ['current_principal_minor', 'apr_basis_points', 'minimum_payment_minor', 'planned_payment_minor'],
            'debt_payments' => ['amount_minor', 'balance_after_minor'],
            'net_worth_accounts' => ['current_balance_minor'],
            'net_worth_snapshots' => ['balance_minor'],
            'subscriptions' => ['amount_minor'],
        ] as $table => $columns) {
            $types = collect(DB::select("PRAGMA table_info('{$table}')"))
                ->pluck('type', 'name');

            foreach ($columns as $column) {
                $this->assertSame('INTEGER', $types[$column], "{$table}.{$column} must be INTEGER");
            }
        }
    }

    public function test_normalized_email_is_unique_and_canonical(): void
    {
        User::factory()->create([
            'email' => 'Person@Example.test',
            'email_normalized' => 'person@example.test',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->create([
            'email' => 'person@example.test',
            'email_normalized' => 'person@example.test',
        ]);
    }

    public function test_invalid_budget_group_is_rejected_by_sqlite(): void
    {
        $household = Household::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('budget_subcategories')->insert([
            'household_id' => $household->id,
            'main_group_identifier' => 'business_expenses',
            'label' => 'Invalid',
            'position' => 0,
            'is_archived' => false,
            'rollover_enabled' => false,
        ]);
    }

    public function test_non_positive_transaction_amount_is_rejected_by_sqlite(): void
    {
        $household = Household::factory()->create();
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'household_id' => $household->id,
            'entered_by_user_id' => $user->id,
            'type' => 'expense',
            'amount_minor' => 0,
            'transacted_on' => '2026-09-13',
            'description' => 'Invalid amount',
        ]);
    }

    public function test_representative_seed_data_covers_every_financial_domain(): void
    {
        $this->seed(DatabaseSeeder::class);

        $groups = DB::table('budget_subcategories')
            ->distinct()
            ->orderBy('main_group_identifier')
            ->pluck('main_group_identifier')
            ->all();

        $this->assertEqualsCanonicalizing(
            array_map(static fn (BudgetGroup $group): string => $group->value, BudgetGroup::cases()),
            $groups,
        );
        $this->assertSame(2_500_000, DB::table('budget_allocations')->sum('allocation_minor'));
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseCount('debts', 1);
        $this->assertDatabaseCount('debt_payments', 1);
        $this->assertDatabaseCount('net_worth_accounts', 2);
        $this->assertDatabaseCount('net_worth_snapshots', 4);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }
}
