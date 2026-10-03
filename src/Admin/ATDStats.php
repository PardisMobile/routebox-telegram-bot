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

    private static function providerTable(string $provider): ?string
    {
        return ['routebox'=>'routebox_servers','ibsng'=>'ibsng_servers','mikrotik_wireguard'=>'mikrotik_servers'][$provider] ?? null;
    }

    private static function providerStats(PDO $db, string $provider): array
    {
        $table = self::providerTable($provider);
        $servers = $table ? self::countRows($db, $table) : 0;
        $users = self::tableExists($db, 'service_subscriptions') ? self::scalar($db, "SELECT COUNT(DISTINCT telegram_user_id) FROM service_subscriptions WHERE provider_key=? AND status != 'deleted'", [$provider]) : 0;
        $plans = self::tableExists($db, 'service_plans') ? self::scalar($db, 'SELECT COUNT(*) FROM service_plans WHERE provider_key=?', [$provider]) : 0;
        $online = $table && self::tableExists($db, $table) ? self::scalar($db, 'SELECT COUNT(*) FROM ' . $table . ' WHERE enabled = 1') : 0;
        return [$servers, $users, $plans, $online];
    }

    private static function globalStats(PDO $db): array
    {
        return [
            self::countRows($db, 'routebox_servers') + self::countRows($db, 'ibsng_servers') + self::countRows($db, 'mikrotik_servers'),
            self::countRows($db, 'telegram_users'),
            self::tableExists($db, 'service_plans') ? self::countRows($db, 'service_plans') : self::countRows($db, 'plans'),
        ];
    }

    private static function snapshot(PDO $db, string $provider): array
    {
        $table = self::providerTable($provider);
        if ($table === null) return [];
        try {
            $row = $db->query('SELECT * FROM ' . $table . ' ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return [];
            $host = trim((string)($row['host'] ?? $row['ip'] ?? $row['address'] ?? ''));
            $port = (int)($row['api_port'] ?? $row['port'] ?? 0);
            if ($provider === 'routebox') {
                $url = trim((string)($row['base_url'] ?? $host));
                $parsed = parse_url($url);
                $host = is_array($parsed) && !empty($parsed['host']) ? (string)$parsed['host'] : $host;
                $port = (int)($parsed['port'] ?? (($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80));
            } elseif ($port <= 0) {
                $port = $provider === 'mikrotik_wireguard' ? 443 : 80;
            }
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            $ping = null;
            $connected = false;
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $start = microtime(true);
                $errno = 0; $errstr = '';
                $socket = @fsockopen($ip, $port, $errno, $errstr, 1.2);
                $ping = round((microtime(true) - $start) * 1000, 1);
                $connected = is_resource($socket);
                if ($connected) fclose($socket);
            }
            $country = '';
            if ($provider === 'routebox' && self::tableExists($db, 'server_meta')) {
                $st = $db->prepare('SELECT country_code,ping_ms FROM server_meta WHERE server_id=? LIMIT 1');
                $st->execute([(int)($row['id'] ?? 0)]);
                $meta = $st->fetch(PDO::FETCH_ASSOC);
                $country = strtoupper(trim((string)($meta['country_code'] ?? '')));
                if ($ping === null && isset($meta['ping_ms']) && $meta['ping_ms'] !== null) $ping = (float)$meta['ping_ms'];
            }
            $flag = '🌐';
            if (preg_match('/^[A-Z]{2}$/', $country) && function_exists('mb_chr')) $flag = mb_chr(127397 + ord($country[0])) . mb_chr(127397 + ord($country[1]));
            return ['id'=>(int)($row['id'] ?? 0),'name'=>(string)($row['name'] ?? ''),'ip'=>(string)$ip,'flag'=>$flag,'ping'=>$ping,'connected'=>$connected || (!empty($row['last_test_at']) && empty($row['last_error']))];
        } catch (\Throwable $e) { return []; }
    }

    public static function statusCard(PDO $db, string $provider, callable $esc): string
    {
        $s = self::snapshot($db, $provider);
        $csrf = function_exists('csrf_token') ? csrf_token() : '';
        $id = (int)($s['id'] ?? 0);
        $connected = !empty($s['connected']);
        $status = $connected ? 'Connected' : 'Offline';
        $ip = (string)($s['ip'] ?? '—');
        $ping = isset($s['ping']) && $s['ping'] !== null ? number_format((float)$s['ping'], 1) . ' ms' : '—';
        $flag = (string)($s['flag'] ?? '🌐');
        $name = (string)($s['name'] ?? '—');
        $color = $connected ? 'var(--green)' : 'var(--red)';
        $section = $provider === 'routebox' ? 'servers' : ($provider === 'ibsng' ? 'ibsng' : 'mikrotik');
        $action = '<form method="post" action="/" style="margin:0"><input type="hidden" name="csrf_token" value="'.$esc($csrf).'">'
            . '<input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="'.$esc($section).'">'
            . '<input type="hidden" name="id" value="'.$id.'"><button class="atd-status-refresh" type="submit" title="Refresh">↻</button></form>';
        return '<div class="atd-provider-status"><div class="atd-status-head"><span>Server Status</span>'.$action.'</div>'
            . '<div class="atd-status-main"><span class="atd-flag">'.$esc($flag).'</span><div><strong>'.$esc($name).'</strong><div class="atd-status-state"><i style="background:'.$color.'"></i>'.$esc($status).'</div></div></div>'
            . '<div class="atd-status-grid"><div><span>Server IP</span><b dir="ltr">'.$esc($ip).'</b></div><div><span>Ping</span><b dir="ltr">'.$esc($ping).'</b></div></div></div>';
    }

    public static function render(PDO $db, string $context, string $version, string $lang): string
    {
        $context = $context === 'servers' ? 'routebox' : $context;
        $provider = in_array($context, ['routebox','ibsng','mikrotik_wireguard'], true) ? $context : null;
        if ($provider !== null) [$servers,$users,$plans] = array_slice(self::providerStats($db,$provider),0,3); else [$servers,$users,$plans] = self::globalStats($db);
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $serverIcon='<path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/>';
        $userIcon='<path d="M16 20c0-3-1.8-5-5-5s-5 2-5 5"/><circle cx="11" cy="8" r="3"/><path d="M17 11a3 3 0 0 0 0-6M18 20c-.3-2.2-1.2-3.8-2.7-4.7"/>';
        $planIcon='<path d="m12 3 8 4-8 4-8-4 8-4Zm-8 9 8 4 8-4M4 17l8 4 8-4"/>';
        $refreshIcon='<path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/>';
        $cards='<div class="stat"><div class="stat-top"><span>Servers</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$serverIcon.'</svg></span></div><b>'.number_format($servers).'</b></div>'
            .'<div class="stat"><div class="stat-top"><span>Users</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$userIcon.'</svg></span></div><b>'.number_format($users).'</b></div>'
            .'<div class="stat"><div class="stat-top"><span>Plans</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$planIcon.'</svg></span></div><b>'.number_format($plans).'</b></div>';
        if ($provider === null) {
            $cards.='<div class="stat"><div class="stat-top"><span>Version</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$refreshIcon.'</svg></span></div><b style="font-size:18px">v'.$esc($version).'</b></div>';
        } else {
            $cards.='<div class="stat atd-status-stat">'.self::statusCard($db,$provider,$esc).'</div>';
        }
        return '<div class="stats atd-stats">'.$cards.'</div><style>.atd-status-stat{min-width:0}.atd-provider-status{margin-top:4px}.atd-status-head{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:11px;font-weight:700}.atd-status-refresh{width:30px;height:30px;border:1px solid var(--line);border-radius:9px;background:transparent;color:var(--text);cursor:pointer;font-size:18px}.atd-status-main{display:flex;align-items:center;gap:10px;margin-top:8px}.atd-flag{font-size:29px;min-width:38px;text-align:center}.atd-status-main strong{display:block;font-size:13px;max-width:145px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.atd-status-state{font-size:11px;color:var(--muted);margin-top:4px}.atd-status-state i{display:inline-block;width:7px;height:7px;border-radius:50%;margin-inline-end:5px}.atd-status-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.atd-status-grid span{display:block;color:var(--muted);font-size:10px}.atd-status-grid b{display:block;margin-top:3px;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}</style>\n';
    }
}
