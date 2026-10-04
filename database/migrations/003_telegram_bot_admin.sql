-- ATD Panel Telegram Bot Admin layer.
-- Additive only: provider/provisioning tables remain unchanged.

CREATE TABLE IF NOT EXISTS telegram_bot_admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_id TEXT UNIQUE NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    enabled INTEGER NOT NULL DEFAULT 1,
    created_by TEXT,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS telegram_admin_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER,
    admin_telegram_id TEXT NOT NULL,
    action TEXT NOT NULL,
    target_user_id INTEGER,
    target_service_id INTEGER,
    provider_key TEXT,
    result TEXT NOT NULL DEFAULT 'success',
    details_json TEXT NOT NULL DEFAULT '{}',
    created_at INTEGER NOT NULL,
    FOREIGN KEY(admin_id) REFERENCES telegram_bot_admins(id) ON DELETE SET NULL,
    FOREIGN KEY(target_user_id) REFERENCES telegram_users(id) ON DELETE SET NULL,
    FOREIGN KEY(target_service_id) REFERENCES service_subscriptions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS telegram_admin_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    admin_telegram_id TEXT NOT NULL,
    action TEXT NOT NULL,
    payload_json TEXT NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'pending',
    result_json TEXT NOT NULL DEFAULT '{}',
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_telegram_admin_audit_created_at
    ON telegram_admin_audit(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_telegram_admin_audit_admin
    ON telegram_admin_audit(admin_telegram_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_telegram_admin_actions_admin_status
    ON telegram_admin_actions(admin_telegram_id, status, created_at DESC);
