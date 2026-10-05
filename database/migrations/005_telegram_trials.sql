-- Provider-aware Telegram Trial configuration and lifecycle ledger.
-- Permanent claim state remains in telegram_trials and is intentionally not deleted by cleanup.
CREATE TABLE IF NOT EXISTS telegram_trial_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL UNIQUE,
    plan_id INTEGER NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS telegram_trial_instances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_user_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    plan_id INTEGER,
    provider_key TEXT NOT NULL,
    provider_reference_json TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'active',
    claimed_at INTEGER NOT NULL,
    expires_at INTEGER,
    cleanup_error TEXT,
    deleted_at INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_trial_instances_expiry ON telegram_trial_instances(status, expires_at);
CREATE INDEX IF NOT EXISTS idx_trial_instances_user ON telegram_trial_instances(telegram_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_trial_instances_provider ON telegram_trial_instances(provider_key, status);
