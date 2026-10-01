<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;

final class IBSngSchema
{
    private static function ensureColumn(PDO $db, string $table, string $column, string $definition): void
    {
        $columns = $db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $row) {
            if ((string)($row['name'] ?? '') === $column) {
                return;
            }
        }
        $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    public static function ensure(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS service_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, service_key TEXT UNIQUE NOT NULL, name_fa TEXT NOT NULL, name_en TEXT NOT NULL, icon TEXT NOT NULL DEFAULT '', provider_key TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS ibsng_servers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, host TEXT NOT NULL, port INTEGER NOT NULL DEFAULT 80, admin_user_enc TEXT NOT NULL, admin_pass_enc TEXT NOT NULL, isp_name TEXT NOT NULL DEFAULT 'Main', verify_tls INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1, last_test_at INTEGER, last_error TEXT, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS ibsng_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, ibsng_server_id INTEGER NOT NULL, group_name TEXT NOT NULL, group_id INTEGER, group_info_json TEXT NOT NULL DEFAULT '{}', enabled INTEGER NOT NULL DEFAULT 1, synced_at INTEGER NOT NULL, UNIQUE(ibsng_server_id, group_name), FOREIGN KEY(ibsng_server_id) REFERENCES ibsng_servers(id) ON DELETE CASCADE)");
        $db->exec("CREATE TABLE IF NOT EXISTS service_plans (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NOT NULL, provider_key TEXT NOT NULL, provider_server_id INTEGER, provider_plan_key TEXT, display_name_fa TEXT NOT NULL, display_name_en TEXT NOT NULL, price_minor INTEGER NOT NULL DEFAULT 0, duration_days INTEGER NOT NULL DEFAULT 0, quota_gb REAL NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, metadata_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE CASCADE)");
        $db->exec("CREATE TABLE IF NOT EXISTS service_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, telegram_user_id INTEGER NOT NULL, category_id INTEGER NOT NULL, plan_id INTEGER NOT NULL, provider_key TEXT NOT NULL, provider_server_id INTEGER, provider_reference TEXT, username TEXT, password_enc TEXT, status TEXT NOT NULL DEFAULT 'pending', starts_at INTEGER, expires_at INTEGER, metadata_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, FOREIGN KEY(telegram_user_id) REFERENCES telegram_users(id) ON DELETE CASCADE, FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE RESTRICT, FOREIGN KEY(plan_id) REFERENCES service_plans(id) ON DELETE RESTRICT)");
        $db->exec("CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, telegram_user_id INTEGER NOT NULL, category_id INTEGER NOT NULL, plan_id INTEGER NOT NULL, subtotal_minor INTEGER NOT NULL DEFAULT 0, discount_minor INTEGER NOT NULL DEFAULT 0, total_minor INTEGER NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'IRR', coupon_code TEXT, status TEXT NOT NULL DEFAULT 'pending', payment_provider TEXT, payment_authority TEXT, transaction_id TEXT, paid_at INTEGER, metadata_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, FOREIGN KEY(telegram_user_id) REFERENCES telegram_users(id) ON DELETE CASCADE, FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE RESTRICT, FOREIGN KEY(plan_id) REFERENCES service_plans(id) ON DELETE RESTRICT)");
        $db->exec("CREATE TABLE IF NOT EXISTS payment_providers (id INTEGER PRIMARY KEY AUTOINCREMENT, provider_key TEXT UNIQUE NOT NULL, display_name TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, config_json TEXT NOT NULL DEFAULT '{}', sort_order INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS coupons (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE NOT NULL, discount_type TEXT NOT NULL, discount_value INTEGER NOT NULL, max_uses INTEGER NOT NULL DEFAULT 0, used_count INTEGER NOT NULL DEFAULT 0, per_user_limit INTEGER NOT NULL DEFAULT 1, min_amount_minor INTEGER NOT NULL DEFAULT 0, starts_at INTEGER, expires_at INTEGER, enabled INTEGER NOT NULL DEFAULT 1, metadata_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS coupon_redemptions (id INTEGER PRIMARY KEY AUTOINCREMENT, coupon_id INTEGER NOT NULL, telegram_user_id INTEGER NOT NULL, order_id INTEGER NOT NULL, discount_minor INTEGER NOT NULL, created_at INTEGER NOT NULL, UNIQUE(coupon_id, telegram_user_id, order_id), FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE, FOREIGN KEY(telegram_user_id) REFERENCES telegram_users(id) ON DELETE CASCADE, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE)");

        // Manual IBSng plan/group mapping. Existing installations are upgraded
        // in-place without dropping their current groups.
        self::ensureColumn($db, 'ibsng_groups', 'plan_name', "TEXT NOT NULL DEFAULT ''");
        $db->exec("UPDATE ibsng_groups SET plan_name=group_name WHERE plan_name='' OR plan_name IS NULL");

        $now = time();
        $st = $db->prepare("INSERT OR IGNORE INTO service_categories(service_key,name_fa,name_en,icon,provider_key,enabled,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,1,?,?,?)");
        $st->execute(['routebox','WireGuard','WireGuard','🟣','routebox',10,$now,$now]);
        $st->execute(['ibsng','OpenVPN / Cisco / L2TP','OpenVPN / Cisco / L2TP','🔵','ibsng',20,$now,$now]);

        // Every manually defined IBSng group is also a generic service plan so
        // the Telegram bot can discover any number of IBSng plans dynamically.
        // Duration/quota stay zero here: IBSng itself owns those rules through
        // the real group; price is zero until a payment gateway is configured.
        $cat = (int)$db->query("SELECT id FROM service_categories WHERE service_key='ibsng'")->fetchColumn();
        if ($cat > 0) {
            $groups = $db->query("SELECT g.* FROM ibsng_groups g JOIN ibsng_servers s ON s.id=g.ibsng_server_id WHERE s.enabled=1")->fetchAll(PDO::FETCH_ASSOC);
            $insert = $db->prepare(
                "INSERT INTO service_plans(category_id,provider_key,provider_server_id,provider_plan_key,display_name_fa,display_name_en,price_minor,duration_days,quota_gb,enabled,sort_order,metadata_json,created_at,updated_at)
                 SELECT ?, 'ibsng', ?, ?, ?, ?, 0, 0, 0, ?, ?, '{}', ?, ?
                 WHERE NOT EXISTS (SELECT 1 FROM service_plans WHERE provider_key='ibsng' AND provider_server_id=? AND provider_plan_key=?)"
            );
            foreach ($groups as $group) {
                $planName = trim((string)$group['plan_name']);
                if ($planName === '') {
                    $planName = (string)$group['group_name'];
                }
                $enabled = !empty($group['enabled']) ? 1 : 0;
                $insert->execute([
                    $cat,
                    (int)$group['ibsng_server_id'],
                    (string)$group['group_name'],
                    $planName,
                    $planName,
                    $enabled,
                    (int)$group['id'],
                    $now,
                    $now,
                    (int)$group['ibsng_server_id'],
                    (string)$group['group_name'],
                ]);
            }
        }
    }
}
