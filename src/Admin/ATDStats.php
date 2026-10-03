<?php
declare(strict_types=1);

namespace RouteBox\Admin;

use PDO;

final class ATDStats
{
    private static function tableExists(PDO $db, string $table): bool
    {
        $st = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    private static function countRows(PDO $db, string $table): int
    {
        if (!self::tableExists($db, $table)) return 0;
        return (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private static function scalar(PDO $db, string $sql, array $params = []): int
    {
        try {
            $st = $db->prepare($sql);
            $st->execute($params);
            return (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function providerStats(PDO $db, string $provider): array
    {
        $serverTable = [
            'routebox' => 'routebox_servers',
            'ibsng' => 'ibsng_servers',
            'mikrotik_wireguard' => 'mikrotik_servers',
        ][$provider] ?? null;

        $servers = $serverTable ? self::countRows($db, $serverTable) : 0;
        $users = self::tableExists($db, 'service_subscriptions')
            ? self::scalar($db, "SELECT COUNT(DISTINCT telegram_user_id) FROM service_subscriptions WHERE provider_key=? AND status != 'deleted'", [$provider])
            : 0;
        $plans = self::tableExists($db, 'service_plans')
            ? self::scalar($db, 'SELECT COUNT(*) FROM service_plans WHERE provider_key=?', [$provider])
            : 0;

        return [$servers, $users, $plans];
    }

    private static function globalStats(PDO $db): array
    {
        $servers = self::countRows($db, 'routebox_servers')
            + self::countRows($db, 'ibsng_servers')
            + self::countRows($db, 'mikrotik_servers');
        $users = self::countRows($db, 'telegram_users');
        $plans = self::tableExists($db, 'service_plans')
            ? self::countRows($db, 'service_plans')
            : self::countRows($db, 'plans');
        return [$servers, $users, $plans];
    }

    public static function render(PDO $db, string $context, string $version, string $lang): string
    {
        $provider = in_array($context, ['routebox', 'ibsng', 'mikrotik_wireguard'], true) ? $context : null;
        [$servers, $users, $plans] = $provider !== null
            ? self::providerStats($db, $provider)
            : self::globalStats($db);

        $labels = ['servers'=>'Servers','users'=>'Users','plans'=>'Plans','version'=>'Version'];
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cards = [
            [$labels['servers'], number_format($servers), '<path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/>'],
            [$labels['users'], number_format($users), '<path d="M16 20c0-3-1.8-5-5-5s-5 2-5 5"/><circle cx="11" cy="8" r="3"/><path d="M17 11a3 3 0 0 0 0-6M18 20c-.3-2.2-1.2-3.8-2.7-4.7"/>'],
            [$labels['plans'], number_format($plans), '<path d="m12 3 8 4-8 4-8-4 8-4Zm-8 9 8 4 8-4M4 17l8 4 8-4"/>'],
            [$labels['version'], 'v' . $version, '<path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/>'],
        ];

        $html = '<div class="stats atd-stats">';
        foreach ($cards as [$label, $value, $icon]) {
            $size = $label === $labels['version'] ? ' style="font-size:18px"' : '';
            $html .= '<div class="stat"><div class="stat-top"><span>' . $esc($label) . '</span><span class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</svg></span></div><b' . $size . '>' . $esc($value) . '</b></div>';
        }
        return $html . '</div>\n';
    }
}
