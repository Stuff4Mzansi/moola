<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                budget_subcategory_id INTEGER,
                name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 120),
                amount_minor INTEGER NOT NULL CHECK (amount_minor > 0),
                recurrence_unit TEXT NOT NULL CHECK (recurrence_unit IN ('day', 'week', 'month', 'year')),
                recurrence_interval INTEGER NOT NULL DEFAULT 1 CHECK (recurrence_interval BETWEEN 1 AND 999),
                anchor_day INTEGER CHECK (anchor_day IS NULL OR anchor_day BETWEEN 1 AND 31),
                occurs_on_last_day INTEGER NOT NULL DEFAULT 0 CHECK (occurs_on_last_day IN (0, 1)),
                next_charge_on TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
                FOREIGN KEY (budget_subcategory_id) REFERENCES budget_subcategories (id) ON DELETE SET NULL
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX subscriptions_upcoming_index ON subscriptions (household_id, is_active, next_charge_on)');

        DB::statement(<<<'SQL'
            CREATE TABLE audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER,
                actor_user_id INTEGER,
                event_type TEXT NOT NULL CHECK (length(trim(event_type)) BETWEEN 1 AND 120),
                subject_type TEXT CHECK (subject_type IS NULL OR length(subject_type) <= 120),
                subject_id INTEGER,
                metadata TEXT CHECK (metadata IS NULL OR json_valid(metadata)),
                occurred_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE SET NULL,
                FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
            ) STRICT
        SQL);

        DB::statement('CREATE INDEX audit_events_household_time_index ON audit_events (household_id, occurred_at DESC, id DESC)');
        DB::statement('CREATE INDEX audit_events_actor_time_index ON audit_events (actor_user_id, occurred_at DESC)');
        DB::statement('CREATE INDEX audit_events_subject_index ON audit_events (subject_type, subject_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('subscriptions');
    }
};
