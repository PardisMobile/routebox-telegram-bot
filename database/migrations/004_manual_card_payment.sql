-- Manual/card-to-card payment workflow. Additive only; existing provider tables are untouched.

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL UNIQUE,
    method TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'awaiting_receipt',
    amount_minor INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'IRR',
    reviewed_by TEXT,
    reviewed_at INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    metadata_json TEXT NOT NULL DEFAULT '{}',
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payment_receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INTEGER NOT NULL,
    order_id INTEGER NOT NULL,
    telegram_user_id INTEGER NOT NULL,
    telegram_file_id TEXT NOT NULL,
    telegram_file_unique_id TEXT,
    file_type TEXT NOT NULL,
    mime_type TEXT,
    caption TEXT,
    status TEXT NOT NULL DEFAULT 'submitted',
    created_at INTEGER NOT NULL,
    FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY(telegram_user_id) REFERENCES telegram_users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_payment_receipts_payment ON payment_receipts(payment_id,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_payment_receipts_user ON payment_receipts(telegram_user_id,created_at DESC);

INSERT OR IGNORE INTO settings(key,value) VALUES('manual_payment_enabled','0');
INSERT OR IGNORE INTO settings(key,value) VALUES('manual_payment_card_number','');
INSERT OR IGNORE INTO settings(key,value) VALUES('manual_payment_card_holder','');
INSERT OR IGNORE INTO settings(key,value) VALUES('manual_payment_bank','');
INSERT OR IGNORE INTO settings(key,value) VALUES('manual_payment_instructions','');
