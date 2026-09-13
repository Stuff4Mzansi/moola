<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE debts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 120),
                current_principal_minor INTEGER NOT NULL CHECK (current_principal_minor >= 0),
                apr_basis_points INTEGER NOT NULL DEFAULT 0 CHECK (apr_basis_points BETWEEN 0 AND 100000),
                minimum_payment_minor INTEGER NOT NULL DEFAULT 0 CHECK (minimum_payment_minor >= 0),
                planned_payment_minor INTEGER NOT NULL DEFAULT 0 CHECK (planned_payment_minor >= 0),
                opened_on TEXT,
                is_archived INTEGER NOT NULL DEFAULT 0 CHECK (is_archived IN (0, 1)),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX debts_household_active_index ON debts (household_id, is_archived, name)');

        DB::statement(<<<'SQL'
            CREATE TABLE debt_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                debt_id INTEGER NOT NULL,
                recorded_by_user_id INTEGER NOT NULL,
                amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
                balance_after_minor INTEGER CHECK (balance_after_minor IS NULL OR balance_after_minor >= 0),
                paid_on TEXT NOT NULL,
                note TEXT CHECK (note IS NULL OR length(note) <= 500),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (debt_id) REFERENCES debts (id) ON DELETE RESTRICT,
                FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX debt_payments_debt_date_index ON debt_payments (debt_id, paid_on DESC, id DESC)');

        DB::statement(<<<'SQL'
            CREATE TABLE net_worth_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 120),
                type TEXT NOT NULL CHECK (type IN ('asset', 'liability')),
                current_balance_minor INTEGER NOT NULL DEFAULT 0 CHECK (current_balance_minor >= 0),
                is_archived INTEGER NOT NULL DEFAULT 0 CHECK (is_archived IN (0, 1)),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX net_worth_accounts_household_type_index ON net_worth_accounts (household_id, type, is_archived)');

        DB::statement(<<<'SQL'
            CREATE TABLE net_worth_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                net_worth_account_id INTEGER NOT NULL,
                recorded_by_user_id INTEGER NOT NULL,
                balance_minor INTEGER NOT NULL CHECK (balance_minor >= 0),
                recorded_on TEXT NOT NULL,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (net_worth_account_id, recorded_on),
                FOREIGN KEY (net_worth_account_id) REFERENCES net_worth_accounts (id) ON DELETE RESTRICT,
                FOREIGN KEY (recorded_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX net_worth_snapshots_account_date_index ON net_worth_snapshots (net_worth_account_id, recorded_on DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('net_worth_snapshots');
        Schema::dropIfExists('net_worth_accounts');
        Schema::dropIfExists('debt_payments');
        Schema::dropIfExists('debts');
    }
};
