<?php

declare(strict_types=1);

namespace RouteBox\Admin;

use PDO;

require_once __DIR__ . '/ATDWorker.php';

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
        $users = self::tableExists($db, 'service_subscriptions')
            ? self::scalar($db, "SELECT COUNT(DISTINCT telegram_user_id) FROM service_subscriptions WHERE provider_key=? AND status != 'deleted'", [$provider]) : 0;
        $plans = self::tableExists($db, 'service_plans')
            ? self::scalar($db, 'SELECT COUNT(*) FROM service_plans WHERE provider_key=?', [$provider]) : 0;
        $online = $table && self::tableExists($db, $table)
            ? self::scalar($db, 'SELECT COUNT(*) FROM ' . $table . ' WHERE enabled = 1') : 0;
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

    private static function systemInfo(): array
    {
        $cpu = null;
        $readCpu = static function (): ?array {
            $line = @file('/proc/stat', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$line) return null;
            foreach ($line as $row) {
                if (strpos($row, 'cpu ') !== 0) continue;
                $parts = preg_split('/\s+/', trim($row));
                if (count($parts) < 5) return null;
                $values = array_map('intval', array_slice($parts, 1));
                $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
                $total = array_sum($values);
                return [$total, $idle];
            }
            return null;
        };
        $a = $readCpu();
        if ($a !== null) {
            usleep(100000);
            $b = $readCpu();
            if ($b !== null) {
                $total = $b[0] - $a[0];
                $idle = $b[1] - $a[1];
                if ($total > 0) $cpu = max(0, min(100, round((1 - ($idle / $total)) * 100, 1)));
            }
        }

        $memTotal = 0;
        $memAvailable = 0;
        $mem = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($mem ?: [] as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)\s+kB$/', $line, $m)) $memTotal = (int)$m[1] * 1024;
            elseif (preg_match('/^MemAvailable:\s+(\d+)\s+kB$/', $line, $m)) $memAvailable = (int)$m[1] * 1024;
        }
        $memUsed = max(0, $memTotal - $memAvailable);
        $memPct = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : null;

        $diskTotal = @disk_total_space('/');
        $diskFree = @disk_free_space('/');
        $diskUsed = ($diskTotal !== false && $diskFree !== false) ? max(0, $diskTotal - $diskFree) : 0;
        $diskPct = ($diskTotal !== false && $diskTotal > 0) ? round(($diskUsed / $diskTotal) * 100, 1) : null;

        $formatBytes = static function (int $bytes): string {
            if ($bytes <= 0) return '—';
            $gb = $bytes / 1073741824;
            if ($gb >= 10) return number_format($gb, 0) . ' GB';
            return number_format($gb, 1) . ' GB';
        };

        return [
            'cpu' => $cpu,
            'cores' => (int)@shell_exec('/usr/bin/nproc 2>/dev/null') ?: (int)@preg_match_all('/^processor\s*:/m', (string)@file_get_contents('/proc/cpuinfo')),
            'ram_used' => $formatBytes($memUsed),
            'ram_total' => $formatBytes($memTotal),
            'ram_pct' => $memPct,
            'disk_used' => $formatBytes($diskUsed),
            'disk_total' => $formatBytes((int)$diskTotal),
            'disk_pct' => $diskPct,
        ];
    }

    private static function countryCodeFromRow(array $row): string
    {
        foreach (['country_code','countryCode','geo_country_code','location_country_code'] as $key) {
            $value = strtoupper(trim((string)($row[$key] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $value)) return $value;
        }
        $map = [
            'United States'=>'US','United States of America'=>'US','USA'=>'US','US'=>'US',
            'United Kingdom'=>'GB','UK'=>'GB','Germany'=>'DE','Turkey'=>'TR','Netherlands'=>'NL',
            'France'=>'FR','Canada'=>'CA','Poland'=>'PL','Finland'=>'FI','Sweden'=>'SE',
            'Singapore'=>'SG','Japan'=>'JP','India'=>'IN','Iran'=>'IR','UAE'=>'AE','United Arab Emirates'=>'AE',
            'South Korea'=>'KR','Korea'=>'KR','KR'=>'KR',
        ];
        foreach (['country','country_name','location','name','title','isp_name'] as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if (preg_match('/^[A-Za-z]{2}$/', $value)) return strtoupper($value);
            foreach ($map as $name => $code) {
                if (strcasecmp($value, $name) === 0 || stripos($value, $name) !== false) return $code;
            }
        }
        return '';
    }

    private static function countryFromHost(string $host, int $port): string
    {
        try {
            $scheme = $port === 443 ? 'https' : 'http';
            $url = $scheme . '://' . trim($host, '/') . ':' . $port;
            if (function_exists('detectCountryCode')) {
                $code = strtoupper(trim((string)\detectCountryCode($url)));
                if (preg_match('/^[A-Z]{2}$/', $code)) return $code;
            }
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) return '';
            $ch = curl_init('https://ipapi.co/' . rawurlencode($ip) . '/country/');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_CONNECTTIMEOUT=>2, CURLOPT_USERAGENT=>'RouteBox-Telegram-Bot']);
            $code = strtoupper(trim((string)curl_exec($ch)));
            curl_close($ch);
            return preg_match('/^[A-Z]{2}$/', $code) ? $code : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function flagForCode(string $country): string
    {
        if (!preg_match('/^[A-Z]{2}$/', $country) || !function_exists('mb_chr')) return '🌐';
        return mb_chr(127397 + ord($country[0])) . mb_chr(127397 + ord($country[1]));
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

            $country = self::countryCodeFromRow($row);
            if ($provider === 'routebox' && self::tableExists($db, 'server_meta')) {
                $st = $db->prepare('SELECT country_code,ping_ms FROM server_meta WHERE server_id=? LIMIT 1');
                $st->execute([(int)($row['id'] ?? 0)]);
                $meta = $st->fetch(PDO::FETCH_ASSOC);
                $country = $country ?: strtoupper(trim((string)($meta['country_code'] ?? '')));
                if ($ping === null && isset($meta['ping_ms']) && $meta['ping_ms'] !== null) $ping = (float)$meta['ping_ms'];
            }
            if ($country === '' && $provider !== 'routebox' && $host !== '') $country = self::countryFromHost($host, $port);

            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') $name = $host !== '' ? $host : 'Server';
            return [
                'id'=>(int)($row['id'] ?? 0),
                'name'=>$name,
                'ip'=>(string)$ip,
                'flag'=>self::flagForCode($country),
                'ping'=>$ping,
                'connected'=>$connected || (!empty($row['last_test_at']) && empty($row['last_error'])),
            ];
        } catch (\Throwable $e) {
            return [];
        }
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
        $actionUrl = '/?section=' . rawurlencode($section);
        $action = '<form method="post" action="'.$esc($actionUrl).'" style="margin:0">'
            . '<input type="hidden" name="csrf_token" value="'.$esc($csrf).'">'
            . '<input type="hidden" name="action" value="test_server">'
            . '<input type="hidden" name="section" value="'.$esc($section).'">'
            . '<input type="hidden" name="id" value="'.$id.'">'
            . '<button class="atd-status-refresh" type="submit" title="Refresh">↻</button></form>';
        return '<div class="atd-provider-status"><div class="atd-status-head"><span>Server Status</span>'.$action.'</div>'
            . '<div class="atd-status-main"><span class="atd-flag">'.$esc($flag).'</span><div><strong>'.$esc($name).'</strong><div class="atd-status-state"><i style="background:'.$color.'"></i>'.$esc($status).'</div></div></div>'
            . '<div class="atd-status-grid"><div><span>Server IP</span><b dir="ltr">'.$esc($ip).'</b></div><div><span>Ping</span><b dir="ltr">'.$esc($ping).'</b></div></div></div>';
    }

    public static function botCard(PDO $db, string $version, callable $esc): string
    {
        $status = ATDWorker::status();
        $info = ATDWorker::serverInfo();
        $flag = ATDWorker::flag((string)$info['country']);
        $state = !empty($status['running']) ? 'Running' : 'Stopped';
        $color = !empty($status['running']) ? 'var(--green)' : 'var(--red)';
        $ping = (string)$status['service'];
        $action = '<form method="post" action="/reload-worker.php" style="margin:0">'
            . '<input type="hidden" name="csrf_token" value="'.$esc(csrf_token()).'">'
            . '<button class="atd-status-refresh" type="submit" title="Reload Worker">↻</button></form>';
        return '<div class="atd-provider-status"><div class="atd-status-head"><span>Bot Server</span>'.$action.'</div>'
            . '<div class="atd-status-main"><span class="atd-flag">'.$esc($flag).'</span><div><strong>Telegram Worker</strong><div class="atd-status-state"><i style="background:'.$color.'"></i>'.$esc($state).'</div></div></div>'
            . '<div class="atd-status-grid"><div><span>Server IP</span><b dir="ltr">'.$esc((string)$info['ip']).'</b></div><div><span>Ping</span><b dir="ltr">'.$esc($ping).'</b></div></div></div>';
    }

    private static function dashboardSystemCard(string $version, callable $esc): string
    {
        $s = self::systemInfo();
        $cpu = $s['cpu'] !== null ? number_format((float)$s['cpu'], 1) . '%' : '—';
        $ramPct = $s['ram_pct'] !== null ? number_format((float)$s['ram_pct'], 1) : null;
        $diskPct = $s['disk_pct'] !== null ? number_format((float)$s['disk_pct'], 1) : null;
        $cpuPct = $s['cpu'] !== null ? number_format((float)$s['cpu'], 1) : null;
        $bar = static function (?string $pct): string {
            $value = $pct === null ? 0 : max(0, min(100, (float)$pct));
            return '<span class="atd-resource-bar"><i style="width:'.$value.'%"></i></span>';
        };
        return '<div class="stat atd-system-stat">'
            . '<div class="stat-top"><span>Version</span><span class="stat-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 20h8M12 17v3"/></svg></span></div>'
            . '<b class="atd-version-value">v'.$esc($version).'</b>'
            . '<div class="atd-resource-grid">'
            . '<div><span>CPU</span><strong>'.$esc($cpu).'</strong>'.$bar($cpuPct).'<small>'.number_format((int)$s['cores']).' Cores</small></div>'
            . '<div><span>RAM</span><strong>'.$esc((string)$s['ram_used']).'</strong>'.$bar($ramPct).'<small>'.$esc((string)$s['ram_total']).' Total</small></div>'
            . '<div><span>Disk</span><strong>'.$esc((string)$s['disk_used']).'</strong>'.$bar($diskPct).'<small>'.$esc((string)$s['disk_total']).' Total</small></div>'
            . '</div></div>';
    }

    public static function render(PDO $db, string $context, string $version, string $lang): string
    {
        $context = $context === 'servers' ? 'routebox' : $context;
        $provider = in_array($context, ['routebox','ibsng','mikrotik_wireguard'], true) ? $context : null;
        if ($provider !== null) [$servers,$users,$plans] = array_slice(self::providerStats($db,$provider),0,3);
        else [$servers,$users,$plans] = self::globalStats($db);

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $serverIcon='<path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/>';
        $userIcon='<path d="M16 20c0-3-1.8-5-5-5s-5 2-5 5"/><circle cx="11" cy="8" r="3"/><path d="M17 11a3 3 0 0 0 0-6M18 20c-.3-2.2-1.2-3.8-2.7-4.7"/>';
        $planIcon='<path d="m12 3 8 4-8 4-8-4 8-4Zm-8 9 8 4 8-4M4 17l8 4 8-4"/>';
        $cards='<div class="stat"><div class="stat-top"><span>Servers</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$serverIcon.'</svg></span></div><b>'.number_format($servers).'</b></div>'
            .'<div class="stat"><div class="stat-top"><span>Users</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$userIcon.'</svg></span></div><b>'.number_format($users).'</b></div>'
            .'<div class="stat"><div class="stat-top"><span>Plans</span><span class="stat-icon"><svg viewBox="0 0 24 24">'.$planIcon.'</svg></span></div><b>'.number_format($plans).'</b></div>';
        if ($context === 'bot') {
            $cards.='<div class="stat atd-status-stat">'.self::botCard($db,$version,$esc).'</div>';
        } elseif ($context === 'dashboard') {
            $cards.=self::dashboardSystemCard($version,$esc);
        } else {
            $cards.='<div class="stat"><div class="stat-top"><span>Version</span><span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/></svg></span></div><b style="font-size:18px">v'.$esc($version).'</b></div>';
        }
        return '<div class="stats atd-stats">'.$cards.'</div><style>.atd-status-stat{min-width:0}.atd-provider-status{margin-top:4px}.atd-status-head{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:11px;font-weight:700}.atd-status-refresh{width:30px;height:30px;border:1px solid var(--line);border-radius:9px;background:transparent;color:var(--text);cursor:pointer;font-size:18px}.atd-status-main{display:flex;align-items:center;gap:10px;margin-top:8px}.atd-flag{font-size:29px;min-width:38px;text-align:center}.atd-status-main strong{display:block;font-size:13px;max-width:145px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.atd-status-state{font-size:11px;color:var(--muted);margin-top:4px}.atd-status-state i{display:inline-block;width:7px;height:7px;border-radius:50%;margin-inline-end:5px}.atd-status-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.atd-status-grid span{display:block;color:var(--muted);font-size:10px}.atd-status-grid b{display:block;margin-top:3px;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.atd-system-stat{min-width:0}.atd-version-value{font-size:17px!important;line-height:1.15}.atd-resource-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}.atd-resource-grid>div{min-width:0}.atd-resource-grid span{display:block;color:var(--muted);font-size:9px;font-weight:700}.atd-resource-grid strong{display:block;margin-top:3px;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.atd-resource-grid small{display:block;margin-top:3px;color:var(--muted);font-size:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.atd-resource-bar{display:block;height:4px;margin-top:5px;border-radius:99px;background:rgba(127,127,127,.16);overflow:hidden}.atd-resource-bar i{display:block;height:100%;border-radius:inherit;background:currentColor;opacity:.8}@media(max-width:700px){.atd-resource-grid{gap:5px}.atd-resource-grid strong{font-size:10px}.atd-resource-grid small{font-size:7px}}</style>\n';
    }
}
