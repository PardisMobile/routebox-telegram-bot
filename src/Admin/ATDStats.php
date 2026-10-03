<?php
declare(strict_types=1);

/* Canonical ATD stats row. Loaded after index.core.php has been captured. */
if (isset($html) && is_string($html)) {
    $atdDb = db();
    $atdSection = (string)($section ?? ($_GET['section'] ?? 'dashboard'));
    $atdLang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';
    $atdFa = $atdLang === 'fa';
    $atdLabel = static fn(string $fa, string $en): string => htmlspecialchars($atdFa ? $fa : $en, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $atdTableExists = static function (PDO $db, string $table): bool {
        $q = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $q->execute([$table]);
        return $q->fetchColumn() !== false;
    };
    $atdCount = static function (PDO $db, string $table) use ($atdTableExists): int {
        if (!$atdTableExists($db, $table)) return 0;
        return (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    };
    $atdDistinctSubscriptions = static function (PDO $db, string $provider) use ($atdTableExists): int {
        if (!$atdTableExists($db, 'service_subscriptions')) return 0;
        $q = $db->prepare('SELECT COUNT(DISTINCT telegram_user_id) FROM service_subscriptions WHERE provider_key=? AND status != ?');
        $q->execute([$provider, 'deleted']);
        return (int)$q->fetchColumn();
    };
    $atdProviderPlans = static function (PDO $db, string $provider) use ($atdTableExists): int {
        if (!$atdTableExists($db, 'service_plans')) return 0;
        $q = $db->prepare('SELECT COUNT(*) FROM service_plans WHERE provider_key=?');
        $q->execute([$provider]);
        return (int)$q->fetchColumn();
    };
    $atdProviderServers = static function (PDO $db, string $table) use ($atdTableExists): array {
        if (!$atdTableExists($db, $table)) return [];
        return $db->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    };
    $atdEndpoint = static function (string $host, int $port, bool $tls = false): string {
        $host = trim($host);
        if ($host === '') return '';
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $host = '[' . $host . ']';
        return ($tls ? 'https://' : 'http://') . $host . ':' . max(1, $port);
    };
    $atdPing = static function (string $host, int $port): ?float {
        $host = trim($host);
        if ($host === '' || $port < 1 || $port > 65535) return null;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        $target = $ip !== $host ? $ip : $host;
        $start = microtime(true);
        $errno = 0; $errstr = '';
        $s = @fsockopen($target, $port, $errno, $errstr, 2);
        if ($s === false) return null;
        $ms = (microtime(true) - $start) * 1000;
        fclose($s);
        return round($ms, 1);
    };
    $atdFlag = static function (string $code): string {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{2}$/', $code) || !function_exists('mb_chr')) return '🌐';
        return mb_chr(127397 + ord($code[0])) . mb_chr(127397 + ord($code[1]));
    };
    $atdCountry = static function (string $endpoint): string {
        if ($endpoint === '') return '';
        try {
            $p = parse_url($endpoint);
            $host = is_array($p) ? (string)($p['host'] ?? '') : '';
            if ($host === '') return '';
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            if ($ip === '' || ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP))) return '';
            $ch = curl_init('https://ipapi.co/' . rawurlencode($ip) . '/country/');
            if ($ch === false) return '';
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_USERAGENT => 'RouteBox-ATD-Panel']);
            $v = trim((string)curl_exec($ch));
            curl_close($ch);
            return preg_match('/^[A-Za-z]{2}$/', $v) ? strtoupper($v) : '';
        } catch (Throwable) { return ''; }
    };
    $atdCsrf = function_exists('csrf_token') ? (string)csrf_token() : '';
    $atdEscape = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $atdGlobalServers = $atdCount($atdDb, 'routebox_servers') + $atdCount($atdDb, 'ibsng_servers') + $atdCount($atdDb, 'mikrotik_servers');
    $atdGlobalUsers = $atdCount($atdDb, 'telegram_users');
    $atdGlobalPlans = $atdCount($atdDb, 'service_plans');

    $atdServerRows = [];
    $atdServerCount = 0; $atdUserCount = 0; $atdPlanCount = 0;
    $atdStatus = null; $atdRefreshAction = ''; $atdRefreshSection = $atdSection;

    if ($atdSection === 'servers') {
        $atdServerRows = $atdProviderServers($atdDb, 'routebox_servers');
        $atdServerCount = count($atdServerRows);
        $atdUserCount = $atdDistinctSubscriptions($atdDb, 'routebox');
        $atdPlanCount = $atdProviderPlans($atdDb, 'routebox');
        if ($atdServerRows) {
            $s = $atdServerRows[0];
            $meta = null;
            if ($atdTableExists($atdDb, 'server_meta')) {
                $q = $atdDb->prepare('SELECT country_code,ping_ms FROM server_meta WHERE server_id=?');
                $q->execute([(int)$s['id']]); $meta = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $atdStatus = ['name'=>(string)$s['name'],'host'=>(string)$s['base_url'],'connected'=>(int)($s['enabled'] ?? 0) === 1,'ping'=>$meta['ping_ms'] ?? null,'country'=>$meta['country_code'] ?? '','id'=>(int)$s['id']];
            $atdRefreshAction = 'test_server';
        }
    } elseif ($atdSection === 'ibsng' || $atdSection === 'mikrotik') {
        $provider = $atdSection === 'ibsng' ? 'ibsng' : 'mikrotik_wireguard';
        $table = $atdSection === 'ibsng' ? 'ibsng_servers' : 'mikrotik_servers';
        $atdServerRows = $atdProviderServers($atdDb, $table);
        $atdServerCount = count($atdServerRows);
        $atdUserCount = $atdDistinctSubscriptions($atdDb, $provider);
        $atdPlanCount = $atdProviderPlans($atdDb, $provider);
        if ($atdServerRows) {
            $s = $atdServerRows[0];
            $host = (string)($s['host'] ?? '');
            $port = $atdSection === 'ibsng' ? (int)($s['port'] ?? 80) : (int)($s['api_port'] ?? 443);
            $tls = $atdSection === 'ibsng' ? $port === 443 : !empty($s['tls_mode']);
            $endpoint = $atdEndpoint($host, $port, $tls);
            $ping = $atdPing($host, $port);
            $country = $atdCountry($endpoint);
            $connected = (int)($s['enabled'] ?? 0) === 1 && trim((string)($s['last_error'] ?? '')) === '';
            $atdStatus = ['name'=>(string)($s['name'] ?? ''),'host'=>$host,'connected'=>$connected,'ping'=>$ping,'country'=>$country,'id'=>(int)$s['id']];
            $atdRefreshAction = 'test_server';
        }
    }

    if (in_array($atdSection, ['security', 'updates'], true)) {
        $atdServerRows = $atdProviderServers($atdDb, 'routebox_servers');
        $atdServerCount = count($atdServerRows);
        $atdUserCount = $atdGlobalUsers;
        $atdPlanCount = $atdGlobalPlans;
        if ($atdServerRows) {
            $s = $atdServerRows[0];
            $meta = null;
            if ($atdTableExists($atdDb, 'server_meta')) {
                $q = $atdDb->prepare('SELECT country_code,ping_ms FROM server_meta WHERE server_id=?');
                $q->execute([(int)$s['id']]); $meta = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $atdStatus = ['name'=>(string)$s['name'],'host'=>(string)$s['base_url'],'connected'=>(int)($s['enabled'] ?? 0) === 1,'ping'=>$meta['ping_ms'] ?? null,'country'=>$meta['country_code'] ?? '','id'=>(int)$s['id']];
            $atdRefreshAction = 'test_server';
            $atdRefreshSection = 'servers';
        }
    }

    $atdStats = '<div class="stats atd-stats">';
    $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('سرورها','Servers').'</span><span class="stat-icon">▦</span></div><b>'.number_format($atdSection === 'dashboard' || $atdSection === 'bot' ? $atdGlobalServers : $atdServerCount).'</b></div>';
    $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('کاربران','Users').'</span><span class="stat-icon">♙</span></div><b>'.number_format($atdSection === 'dashboard' || $atdSection === 'bot' ? $atdGlobalUsers : $atdUserCount).'</b></div>';
    $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('پلن‌ها','Plans').'</span><span class="stat-icon">≋</span></div><b>'.number_format($atdSection === 'dashboard' || $atdSection === 'bot' ? $atdGlobalPlans : $atdPlanCount).'</b></div>';

    if ($atdSection === 'dashboard') {
        $version = is_file(__DIR__ . '/../../VERSION') ? trim((string)file_get_contents(__DIR__ . '/../../VERSION')) : '0.0.0';
        $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('نسخه','Version').'</span><span class="stat-icon">↻</span></div><b style="font-size:18px">v'.$atdEscape($version).'</b></div>';
    } elseif ($atdSection === 'bot') {
        $ip = trim((string)($_SERVER['SERVER_ADDR'] ?? ''));
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) $ip = trim((string)@shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
        $country = $atdCountry($ip !== '' ? 'http://' . $ip : '');
        $worker = 'unknown';
        foreach (['routebox-telegram-bot.service','routebox-telegram-bot-dev.service'] as $service) {
            $candidate = trim((string)@shell_exec('systemctl is-active ' . escapeshellarg($service) . ' 2>/dev/null'));
            if ($candidate !== '' || is_file('/etc/systemd/system/' . $service)) { $worker = $candidate !== '' ? $candidate : 'unknown'; break; }
        }
        $ok = $worker === 'active';
        $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('سرور ربات','Bot Server').'</span><span class="stat-icon">⌁</span></div><b class="'.($ok?'state-ok':'state-bad'). '" style="font-size:15px">● '.($ok?$atdLabel('Worker فعال','Worker Active'):$atdLabel('Worker متوقف','Worker '.($worker ?: 'unknown'))).'</b><div style="margin-top:7px;font-size:11px;color:var(--muted);direction:ltr;text-align:left">'.$atdEscape($ip !== '' ? $ip : '—').' · '.$atdFlag($country).'</div><div class="form-actions" style="margin-top:9px"><form method="post" action="/reload-worker.php"><input type="hidden" name="csrf_token" value="'.$atdEscape($atdCsrf).'"><button class="btn btn-secondary" type="submit">↻ '.$atdLabel('Reload Worker','Reload Worker').'</button></form></div></div>';
    } else {
        if ($atdStatus === null) {
            $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('وضعیت سرور','Server Status').'</span><span class="stat-icon">●</span></div><b style="font-size:15px;color:var(--muted)">'.$atdLabel('سروری ثبت نشده','No server configured').'</b></div>';
        } else {
            $statusText = $atdStatus['connected'] ? '<span class="state-ok">● '.$atdLabel('متصل','Connected').'</span>' : '<span class="state-bad">● '.$atdLabel('قطع','Offline').'</span>';
            $host = $atdEscape((string)$atdStatus['host']);
            $ping = $atdStatus['ping'] !== null ? $atdEscape((string)$atdStatus['ping']).' ms' : '—';
            $flag = $atdFlag((string)$atdStatus['country']);
            $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('وضعیت سرور','Server Status').'</span><span class="stat-icon">↔</span></div><b style="font-size:16px">'.$statusText.'</b><div style="margin-top:7px;font-size:11px;color:var(--muted);direction:ltr;text-align:left">'.$host.'</div><div style="margin-top:4px;font-size:11px;color:var(--muted)">⚡ '.$ping.' · '.$flag.'</div><div class="form-actions" style="margin-top:9px"><form method="post"><input type="hidden" name="csrf_token" value="'.$atdEscape($atdCsrf).'"><input type="hidden" name="action" value="'.$atdEscape($atdRefreshAction).'"><input type="hidden" name="section" value="'.$atdEscape($atdRefreshSection).'"><input type="hidden" name="id" value="'.(int)$atdStatus['id'].'"><button class="btn btn-secondary" type="submit">↻ '.$atdLabel('رفرش / تست','Refresh / Check').'</button></form></div></div>';
        }
    }
    $atdStats .= '</div>';

    /* Replace every legacy stats block with the canonical row. */
    $removeStats = static function (string $source): string {
        while (($start = strpos($source, '<div class="stats')) !== false) {
            $depth = 0; $pos = $start; $len = strlen($source); $end = null;
            while ($pos < $len) {
                $nextOpen = strpos($source, '<div', $pos);
                $nextClose = strpos($source, '</div>', $pos);
                if ($nextClose === false) break;
                if ($nextOpen !== false && $nextOpen < $nextClose) { $depth++; $pos = $nextOpen + 4; }
                else { $depth--; $pos = $nextClose + 6; if ($depth === 0) { $end = $pos; break; } }
            }
            if ($end === null) break;
            $source = substr($source,0,$start).substr($source,$end);
        }
        return $source;
    };
    $html = $removeStats($html);
    $anchor = strpos($html, '</header>');
    if ($anchor !== false) {
        $anchor += 9;
        $html = substr($html,0,$anchor).$atdStats.substr($html,$anchor);
    } else {
        $main = strpos($html, '<main');
        $gt = $main === false ? false : strpos($html, '>', $main);
        if ($gt !== false) $html = substr($html,0,$gt+1).$atdStats.substr($html,$gt+1);
    }
}
