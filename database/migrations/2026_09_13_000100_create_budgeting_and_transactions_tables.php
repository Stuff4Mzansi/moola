<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE budget_subcategories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                main_group_identifier TEXT NOT NULL CHECK (main_group_identifier IN (
                    'essentials', 'guilt_free_spend', 'future'
                )),
                label TEXT NOT NULL CHECK (length(trim(label)) BETWEEN 1 AND 80),
                position INTEGER NOT NULL DEFAULT 0 CHECK (position >= 0),
                is_archived INTEGER NOT NULL DEFAULT 0 CHECK (is_archived IN (0, 1)),
                rollover_enabled INTEGER NOT NULL DEFAULT 0 CHECK (rollover_enabled IN (0, 1)),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX budget_subcategories_household_group_index ON budget_subcategories (household_id, main_group_identifier, is_archived, position)');

        DB::statement(<<<'SQL'
            CREATE TABLE budget_periods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                starts_on TEXT NOT NULL,
                ends_on TEXT NOT NULL CHECK (starts_on <= ends_on),
                expected_income_minor INTEGER NOT NULL DEFAULT 0 CHECK (expected_income_minor >= 0),
                state TEXT NOT NULL DEFAULT 'draft' CHECK (state IN ('draft', 'finalized', 'closed')),
                finalized_at TEXT,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (household_id, starts_on),
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX budget_periods_household_dates_index ON budget_periods (household_id, starts_on, ends_on)');
        DB::statement('CREATE INDEX budget_periods_household_state_index ON budget_periods (household_id, state)');

        DB::statement(<<<'SQL'
            CREATE TABLE budget_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                budget_period_id INTEGER NOT NULL,
                budget_subcategory_id INTEGER NOT NULL,
                allocation_minor INTEGER NOT NULL DEFAULT 0 CHECK (allocation_minor >= 0),
                opening_rollover_minor INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (budget_period_id, budget_subcategory_id),
                FOREIGN KEY (budget_period_id) REFERENCES budget_periods (id) ON DELETE RESTRICT,
                FOREIGN KEY (budget_subcategory_id) REFERENCES budget_subcategories (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX budget_allocations_subcategory_period_index ON budget_allocations (budget_subcategory_id, budget_period_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                entered_by_user_id INTEGER NOT NULL,
                budget_subcategory_id INTEGER,
                type TEXT NOT NULL CHECK (type IN ('income', 'expense')),
                amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
                transacted_on TEXT NOT NULL,
                description TEXT NOT NULL CHECK (length(trim(description)) BETWEEN 1 AND 255),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
                FOREIGN KEY (entered_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                FOREIGN KEY (budget_subcategory_id) REFERENCES budget_subcategories (id) ON DELETE SET NULL
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX transactions_household_date_index ON transactions (household_id, transacted_on DESC, id DESC)');
        DB::statement('CREATE INDEX transactions_budget_lookup_index ON transactions (household_id, budget_subcategory_id, type, transacted_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budget_periods');
        Schema::dropIfExists('budget_subcategories');
    }
};
