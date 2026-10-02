<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use PDO;

/** MikroTik-specific schema extension for the shared service catalog. */
final class MikroTikSchema
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

    public static function migrate(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS mikrotik_servers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            host TEXT NOT NULL,
            api_port INTEGER NOT NULL DEFAULT 443,
            username TEXT NOT NULL,
            password_enc TEXT NOT NULL DEFAULT '',
            tls_mode INTEGER NOT NULL DEFAULT 1,
            vpn_endpoint TEXT NOT NULL DEFAULT '',
            vpn_port INTEGER NOT NULL DEFAULT 51820,
            interface_name TEXT NOT NULL DEFAULT '',
            pool_name TEXT NOT NULL DEFAULT '',
            dns_servers TEXT NOT NULL DEFAULT '',
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL DEFAULT 0,
            last_test_at INTEGER DEFAULT NULL,
            last_error TEXT DEFAULT NULL
        )");

        self::ensureColumn($db, 'mikrotik_servers', 'vpn_endpoint', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_servers', 'vpn_port', 'INTEGER NOT NULL DEFAULT 51820');
        self::ensureColumn($db, 'mikrotik_servers', 'interface_name', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_servers', 'pool_name', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_servers', 'dns_servers', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_servers', 'updated_at', 'INTEGER NOT NULL DEFAULT 0');
        self::ensureColumn($db, 'mikrotik_servers', 'last_test_at', 'INTEGER DEFAULT NULL');
        self::ensureColumn($db, 'mikrotik_servers', 'last_error', 'TEXT DEFAULT NULL');

        $db->exec("CREATE TABLE IF NOT EXISTS mikrotik_wireguard_peers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            server_id INTEGER NOT NULL,
            subscription_id INTEGER DEFAULT NULL,
            routeros_id TEXT DEFAULT '',
            username TEXT NOT NULL,
            public_key TEXT DEFAULT '',
            private_key_enc TEXT DEFAULT '',
            assigned_ip TEXT DEFAULT '',
            interface_name TEXT DEFAULT '',
            status TEXT NOT NULL DEFAULT 'active',
            expires_at INTEGER DEFAULT NULL,
            quota INTEGER DEFAULT NULL,
            rx_bytes INTEGER NOT NULL DEFAULT 0,
            tx_bytes INTEGER NOT NULL DEFAULT 0,
            upload_limit TEXT DEFAULT '',
            download_limit TEXT DEFAULT '',
            queue_id TEXT DEFAULT '',
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            UNIQUE(server_id, username)
        )");

        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'subscription_id', 'INTEGER DEFAULT NULL');
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'routeros_id', "TEXT DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'private_key_enc', "TEXT DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'interface_name', "TEXT DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'upload_limit', "TEXT DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'download_limit', "TEXT DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'queue_id', "TEXT DEFAULT ''");
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'created_at', 'INTEGER NOT NULL DEFAULT 0');
        self::ensureColumn($db, 'mikrotik_wireguard_peers', 'updated_at', 'INTEGER NOT NULL DEFAULT 0');

        $now = time();
        $db->exec("INSERT OR IGNORE INTO service_categories(service_key,name_fa,name_en,icon,provider_key,enabled,sort_order,created_at,updated_at)
            VALUES('mikrotik_wireguard','MikroTik WireGuard','MikroTik WireGuard','🟢','mikrotik_wireguard',1,30,$now,$now)");
    }
}
