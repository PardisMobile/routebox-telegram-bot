<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use PDO;

/**
 * MikroTik-specific schema extension.
 *
 * Provider-specific tables are isolated while subscriptions continue to use
 * the shared service architecture.
 */
final class MikroTikSchema
{
    public static function migrate(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS mikrotik_servers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            host TEXT NOT NULL,
            api_port INTEGER NOT NULL DEFAULT 8728,
            username TEXT NOT NULL,
            password_enc TEXT NOT NULL DEFAULT '',
            tls_mode INTEGER NOT NULL DEFAULT 0,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS mikrotik_wireguard_peers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            server_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            public_key TEXT DEFAULT '',
            assigned_ip TEXT DEFAULT '',
            status TEXT NOT NULL DEFAULT 'active',
            expires_at INTEGER DEFAULT NULL,
            quota INTEGER DEFAULT NULL,
            rx_bytes INTEGER NOT NULL DEFAULT 0,
            tx_bytes INTEGER NOT NULL DEFAULT 0
        )");
    }
}
