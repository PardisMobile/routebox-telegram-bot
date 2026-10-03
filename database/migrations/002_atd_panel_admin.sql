-- ATD Panel admin layer. Additive only: existing provider/provisioning tables remain unchanged.

CREATE TABLE IF NOT EXISTS telegram_service_guides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER,
    title_fa TEXT NOT NULL,
    title_en TEXT NOT NULL,
    body_fa TEXT NOT NULL DEFAULT '',
    body_en TEXT NOT NULL DEFAULT '',
    enabled INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(category_id),
    FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS provider_admin_guides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_key TEXT UNIQUE NOT NULL,
    title_fa TEXT NOT NULL,
    title_en TEXT NOT NULL,
    body_fa TEXT NOT NULL DEFAULT '',
    body_en TEXT NOT NULL DEFAULT '',
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

INSERT OR IGNORE INTO telegram_service_guides(category_id,title_fa,title_en,body_fa,body_en,enabled,sort_order,created_at,updated_at)
SELECT NULL,'راهنمای عمومی','General Guide',
       COALESCE((SELECT value FROM settings WHERE key='guide_fa'),''),
       COALESCE((SELECT value FROM settings WHERE key='guide_en'),''),1,0,strftime('%s','now'),strftime('%s','now');

INSERT OR IGNORE INTO telegram_service_guides(category_id,title_fa,title_en,body_fa,body_en,enabled,sort_order,created_at,updated_at)
SELECT id,name_fa,name_en,'','',1,10+sort_order,strftime('%s','now'),strftime('%s','now') FROM service_categories;

INSERT OR IGNORE INTO provider_admin_guides(provider_key,title_fa,title_en,body_fa,body_en,enabled,created_at,updated_at) VALUES
('routebox','راهنمای RouteBox','RouteBox Admin Guide','راهنمای اتصال و مدیریت RouteBox را اینجا بنویسید.','Add the RouteBox server/API/TLS setup guide here.',1,strftime('%s','now'),strftime('%s','now')),
('ibsng','راهنمای IBSng','IBSng Admin Guide','راهنمای اتصال API، گروه‌ها و پیش‌نیازهای Provisioning را اینجا بنویسید.','Add the IBSng API, group mapping and provisioning prerequisites here.',1,strftime('%s','now'),strftime('%s','now')),
('mikrotik_wireguard','راهنمای MikroTik WireGuard','MikroTik WireGuard Admin Guide','راهنمای RouterOS REST، WireGuard interface، Pool و Listen Port را اینجا بنویسید.','Add the RouterOS REST, WireGuard interface, pool and listen-port setup guide here.',1,strftime('%s','now'),strftime('%s','now'));

INSERT OR IGNORE INTO payment_providers(provider_key,display_name,enabled,config_json,sort_order,created_at,updated_at) VALUES
('zarinpal','ZarinPal',0,'{}',10,strftime('%s','now'),strftime('%s','now')),
('crypto','Crypto Gateway',0,'{}',20,strftime('%s','now'),strftime('%s','now'));

INSERT OR IGNORE INTO settings(key,value) VALUES('payment_currency','IRR');
INSERT OR IGNORE INTO settings(key,value) VALUES('payment_callback_url','');
