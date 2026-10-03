<?php

declare(strict_types=1);

/*
 * Provider-only ATD stats row.
 *
 * Dashboard, Telegram Bot, RouteBox Servers, Security and Updates already
 * render their canonical stats from public/index.core.php. This file is only
 * used for the special IBSng and MikroTik pages, which reuse the captured
 * dashboard shell and need provider-scoped cards injected into it.
 */
if (!isset($html) || !is_string($html)) {
    return;
}

$atdSection = (string)($section ?? ($_GET['section'] ?? ''));
if (!in_array($atdSection, ['ibsng', 'mikrotik'], true)) {
    return;
}

$atdDb = db();
$atdLang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';
$atdFa = $atdLang === 'fa';
$atdLabel = static fn(string $fa, string $en): string => htmlspecialchars(
    $atdFa ? $fa : $en,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
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
    $errno = 0;
    $errstr = '';
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
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_USERAGENT => 'RouteBox-ATD-Panel',
        ]);
        $value = trim((string)curl_exec($ch));
        curl_close($ch);
        return preg_match('/^[A-Za-z]{2}$/', $value) ? strtoupper($value) : '';
    } catch (Throwable) {
        return '';
    }
};
$atdEscape = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$atdCsrf = function_exists('csrf_token') ? (string)csrf_token() : '';

$provider = $atdSection === 'ibsng' ? 'ibsng' : 'mikrotik_wireguard';
$table = $atdSection === 'ibsng' ? 'ibsng_servers' : 'mikrotik_servers';
$rows = $atdTableExists($atdDb, $table)
    ? $atdDb->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
    : [];

$serverCount = count($rows);
$userCount = $atdDistinctSubscriptions($atdDb, $provider);
$planCount = $atdProviderPlans($atdDb, $provider);
$status = null;

if ($rows) {
    $server = $rows[0];
    $host = (string)($server['host'] ?? '');
    $port = $atdSection === 'ibsng' ? (int)($server['port'] ?? 80) : (int)($server['api_port'] ?? 443);
    $tls = $atdSection === 'ibsng' ? $port === 443 : !empty($server['tls_mode']);
    $endpoint = $atdEndpoint($host, $port, $tls);
    $ping = $atdPing($host, $port);
    $country = $atdCountry($endpoint);
    $connected = (int)($server['enabled'] ?? 0) === 1 && trim((string)($server['last_error'] ?? '')) === '';
    $status = [
        'host' => $host,
        'connected' => $connected,
        'ping' => $ping,
        'country' => $country,
        'id' => (int)($server['id'] ?? 0),
    ];
}

$stats = '<div class="stats atd-stats">';
$stats .= '<div class="stat"><div class="stat-top"><span>' . $atdLabel('سرورها', 'Servers') . '</span><span class="stat-icon">▦</span></div><b>' . number_format($serverCount) . '</b></div>';
$stats .= '<div class="stat"><div class="stat-top"><span>' . $atdLabel('کاربران', 'Users') . '</span><span class="stat-icon">♙</span></div><b>' . number_format($userCount) . '</b></div>';
$stats .= '<div class="stat"><div class="stat-top"><span>' . $atdLabel('پلن‌ها', 'Plans') . '</span><span class="stat-icon">≋</span></div><b>' . number_format($planCount) . '</b></div>';

if ($status === null) {
    $stats .= '<div class="stat"><div class="stat-top"><span>' . $atdLabel('وضعیت سرور', 'Server Status') . '</span><span class="stat-icon">●</span></div><b style="font-size:15px;color:var(--muted)">' . $atdLabel('سروری ثبت نشده', 'No server configured') . '</b></div>';
} else {
    $statusText = $status['connected']
        ? '<span class="state-ok">● ' . $atdLabel('متصل', 'Connected') . '</span>'
        : '<span class="state-bad">● ' . $atdLabel('قطع', 'Offline') . '</span>';
    $hostHtml = $atdEscape((string)$status['host']);
    $pingHtml = $status['ping'] !== null ? $atdEscape((string)$status['ping']) . ' ms' : '—';
    $flag = $atdFlag((string)$status['country']);
    $stats .= '<div class="stat"><div class="stat-top"><span>' . $atdLabel('وضعیت سرور', 'Server Status') . '</span><span class="stat-icon">↔</span></div>'
        . '<b style="font-size:16px">' . $statusText . '</b>'
        . '<div style="margin-top:7px;font-size:11px;color:var(--muted);direction:ltr;text-align:left">' . $hostHtml . '</div>'
        . '<div style="margin-top:4px;font-size:11px;color:var(--muted)">⚡ ' . $pingHtml . ' · ' . $flag . '</div>'
        . '<div class="form-actions" style="margin-top:9px"><form method="post">'
        . '<input type="hidden" name="csrf_token" value="' . $atdEscape($atdCsrf) . '">'
        . '<input type="hidden" name="action" value="test_server">'
        . '<input type="hidden" name="section" value="' . $atdSection . '">'
        . '<input type="hidden" name="id" value="' . (int)$status['id'] . '">'
        . '<button class="btn btn-secondary" type="submit">↻ ' . $atdLabel('رفرش / تست', 'Refresh / Check') . '</button>'
        . '</form></div></div>';
}
$stats .= '</div>';

/* The special provider pages capture the dashboard shell first. Remove its
 * dashboard stats row, then insert the provider-scoped row exactly once. */
$removeStats = static function (string $source): string {
    while (($start = strpos($source, '<div class="stats')) !== false) {
        $depth = 0;
        $pos = $start;
        $len = strlen($source);
        $end = null;
        while ($pos < $len) {
            $nextOpen = strpos($source, '<div', $pos);
            $nextClose = strpos($source, '</div>', $pos);
            if ($nextClose === false) break;
            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + 4;
            } else {
                $depth--;
                $pos = $nextClose + 6;
                if ($depth === 0) {
                    $end = $pos;
                    break;
                }
            }
        }
        if ($end === null) break;
        $source = substr($source, 0, $start) . substr($source, $end);
    }
    return $source;
};

$html = $removeStats($html);
$anchor = strpos($html, '</header>');
if ($anchor !== false) {
    $anchor += 9;
    $html = substr($html, 0, $anchor) . $stats . substr($html, $anchor);
} else {
    $main = strpos($html, '<main');
    $gt = $main === false ? false : strpos($html, '>', $main);
    if ($gt !== false) $html = substr($html, 0, $gt + 1) . $stats . substr($html, $gt + 1);
}
