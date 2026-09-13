<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE households (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                singleton_key INTEGER NOT NULL DEFAULT 1 UNIQUE CHECK (singleton_key = 1),
                name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 120),
                created_at TEXT,
                updated_at TEXT
            ) STRICT
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 120),
                email TEXT NOT NULL CHECK (length(trim(email)) BETWEEN 3 AND 254),
                email_normalized TEXT NOT NULL UNIQUE CHECK (
                    email_normalized = lower(trim(email_normalized))
                    AND length(email_normalized) BETWEEN 3 AND 254
                ),
                password TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1 CHECK (is_enabled IN (0, 1)),
                must_change_password INTEGER NOT NULL DEFAULT 1 CHECK (must_change_password IN (0, 1)),
                is_administrator INTEGER NOT NULL DEFAULT 0 CHECK (is_administrator IN (0, 1)),
                remember_token TEXT,
                created_at TEXT,
                updated_at TEXT
            ) STRICT
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE household_memberships (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                joined_at TEXT NOT NULL,
                removed_at TEXT,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (household_id, user_id),
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                capability TEXT NOT NULL CHECK (capability IN (
                    'budget.view', 'budget.manage',
                    'transactions.view', 'transactions.manage',
                    'debts.view', 'debts.manage',
                    'networth.view', 'networth.manage',
                    'subscriptions.view', 'subscriptions.manage',
                    'users.manage', 'settings.manage'
                )),
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (household_id, user_id, capability),
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE installation_settings (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                household_id INTEGER NOT NULL UNIQUE,
                currency_code TEXT NOT NULL CHECK (
                    length(currency_code) = 3 AND currency_code = upper(currency_code)
                ),
                locale TEXT NOT NULL CHECK (length(trim(locale)) BETWEEN 2 AND 16),
                timezone TEXT NOT NULL CHECK (length(trim(timezone)) BETWEEN 1 AND 64),
                budget_start_day INTEGER NOT NULL CHECK (budget_start_day BETWEEN 1 AND 31),
                backup_retention_count INTEGER NOT NULL DEFAULT 10 CHECK (backup_retention_count BETWEEN 1 AND 365),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT
            ) STRICT
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER,
                ip_address TEXT,
                user_agent TEXT,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX sessions_user_id_index ON sessions (user_id)');
        DB::statement('CREATE INDEX sessions_last_activity_index ON sessions (last_activity)');
        DB::statement('CREATE INDEX household_memberships_current_index ON household_memberships (household_id, user_id, removed_at)');
        DB::statement('CREATE INDEX permissions_lookup_index ON permissions (household_id, user_id, capability)');

        DB::statement(<<<'SQL'
            CREATE TABLE cache (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                expiration INTEGER NOT NULL
            ) STRICT
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE cache_locks (
                key TEXT PRIMARY KEY,
                owner TEXT NOT NULL,
                expiration INTEGER NOT NULL
            ) STRICT
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('installation_settings');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('household_memberships');
        Schema::dropIfExists('users');
        Schema::dropIfExists('households');
    }
};
