<?php

declare(strict_types=1);

namespace RouteBox\Admin;

final class ProviderGuideSection
{
    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function markdown(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $parts = preg_split('/(```[a-zA-Z0-9_-]*\n.*?```)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^```([a-zA-Z0-9_-]*)\n(.*?)```$/s', $part, $m)) {
                $lang = self::esc($m[1] !== '' ? $m[1] : 'text');
                $html .= '<pre class="provider-guide-code"><code class="language-'.$lang.'">'.self::esc(rtrim($m[2])).'</code></pre>';
                continue;
            }
            $lines = explode("\n", $part);
            $inList = false;
            foreach ($lines as $line) {
                $trim = trim($line);
                if ($trim === '') {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    continue;
                }
                if (preg_match('/^###\s+(.+)$/', $trim, $m)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h4>'.self::inline($m[1]).'</h4>'; continue;
                }
                if (preg_match('/^##\s+(.+)$/', $trim, $m)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h3>'.self::inline($m[1]).'</h3>'; continue;
                }
                if (preg_match('/^#\s+(.+)$/', $trim, $m)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h2>'.self::inline($m[1]).'</h2>'; continue;
                }
                if (preg_match('/^[-*]\s+(.+)$/', $trim, $m)) {
                    if (!$inList) { $html .= '<ul>'; $inList = true; }
                    $html .= '<li>'.self::inline($m[1]).'</li>'; continue;
                }
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<p>'.self::inline($trim).'</p>';
            }
            if ($inList) $html .= '</ul>';
        }
        return $html;
    }

    private static function inline(string $text): string
    {
        $safe = self::esc($text);
        $safe = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safe) ?? $safe;
        $safe = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
        return $safe;
    }

    private static function styles(): string
    {
        return '<style id="provider-guide-docs-style">.provider-guide-docs{display:grid;gap:18px}.provider-guide-intro{padding:16px 18px;border:1px solid var(--line);border-radius:15px;background:var(--card2);color:var(--muted);line-height:1.8}.provider-guide-content{padding:20px;border:1px solid var(--line);border-radius:15px;background:var(--card)}.provider-guide-content h2{margin:0 0 16px;font-size:25px}.provider-guide-content h3{margin:24px 0 10px;font-size:19px}.provider-guide-content h4{margin:18px 0 8px;font-size:16px}.provider-guide-content p{line-height:1.85;margin:8px 0}.provider-guide-content ul{line-height:1.9;padding-inline-start:24px}.provider-guide-content code{padding:2px 6px;border-radius:6px;background:rgba(127,127,127,.12);font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.provider-guide-code{overflow:auto;margin:12px 0;padding:15px;border:1px solid var(--line);border-radius:12px;background:rgba(0,0,0,.08);direction:ltr;text-align:left}.provider-guide-code code{display:block;padding:0;background:none;color:inherit;white-space:pre;font-size:13px;line-height:1.7}.provider-guide-note{font-size:12px;color:var(--muted)}@media(max-width:760px){.provider-guide-content{padding:14px}} </style>';
    }

    public static function render(string $provider, string $lang): string
    {
        $manifestPath = __DIR__ . '/../../docs/providers/manifest.php';
        $manifest = is_file($manifestPath) ? require $manifestPath : [];
        $item = is_array($manifest[$provider] ?? null) ? $manifest[$provider] : null;
        $back = $provider === 'ibsng' ? 'ibsng' : ($provider === 'mikrotik_wireguard' ? 'mikrotik' : 'servers');
        if ($item === null || !is_file((string)($item['file'] ?? ''))) {
            return '<section class="atd-admin-section card"><div class="section-head"><div><div class="eyebrow">ATD PANEL</div><h2>Provider Guide</h2><p>Guide is not configured for this provider.</p></div><a class="btn btn-secondary" href="/?section='.$back.'">← Back</a></div></section>'.self::styles();
        }
        $fa = $lang === 'fa';
        $title = (string)($fa ? $item['title_fa'] : $item['title_en']);
        $markdown = (string)file_get_contents((string)$item['file']);
        $intro = $fa
            ? 'این راهنما بخشی از خود ATD Panel است و توسط سازنده پنل ارائه می‌شود. برای تغییر محتوا، فایل Provider Guide در نسخه بعدی پنل به‌روزرسانی می‌شود.'
            : 'This guide is shipped as part of ATD Panel and is maintained by the panel author. Content changes are delivered through a future panel release.';
        $body = '<div class="provider-guide-docs"><div class="provider-guide-intro">'.self::esc($intro).'<div class="provider-guide-note" style="margin-top:7px">'.self::esc($fa ? 'منبع: docs/providers/'.$provider : 'Source: docs/providers/'.$provider).'</div></div><article class="provider-guide-content">'.self::markdown($markdown).'</article></div>';
        return '<section class="atd-admin-section card"><div class="section-head"><div><div class="eyebrow">ATD PANEL</div><h2>'.self::esc($title).'</h2><p>'.self::esc($fa ? 'راهنمای فنی آماده‌سازی، اتصال و تست Provider.' : 'Technical guide for provider setup, connection and testing.').'</p></div><a class="btn btn-secondary" href="/?section='.$back.'">← '.self::esc($fa ? 'بازگشت' : 'Back').'</a></div>'.$body.'</section>'.self::styles();
    }
}

/* ATD Panel scoped statistics/status layer. It wraps the final HTML so existing
 * RouteBox/IBSng/MikroTik rendering stays untouched. */
function atd_panel_table_exists(\PDO $db, string $table): bool
{
    if (!preg_match('/^[a-z_]+$/', $table)) return false;
    $q = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

function atd_panel_count(\PDO $db, string $table, string $where = '', array $params = []): int
{
    if (!atd_panel_table_exists($db, $table)) return 0;
    $sql = 'SELECT COUNT(*) FROM '.$table.($where !== '' ? ' WHERE '.$where : '');
    $q = $db->prepare($sql);
    $q->execute($params);
    return (int)$q->fetchColumn();
}

function atd_panel_distinct_subscribers(\PDO $db, string $provider): int
{
    if (!atd_panel_table_exists($db, 'service_subscriptions')) return 0;
    $q = $db->prepare("SELECT COUNT(DISTINCT telegram_user_id) FROM service_subscriptions WHERE provider_key=? AND COALESCE(status,'active') NOT IN ('deleted','cancelled')");
    $q->execute([$provider]);
    return (int)$q->fetchColumn();
}

function atd_panel_ping(string $host, int $port): ?float
{
    $host = trim($host);
    if ($host === '' || $port < 1 || $port > 65535) return null;
    $target = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if ($target === '') return null;
    $start = microtime(true); $errno = 0; $errstr = '';
    $socket = @fsockopen($target, $port, $errno, $errstr, 0.9);
    if ($socket === false) return null;
    fclose($socket);
    return round((microtime(true) - $start) * 1000, 1);
}

function atd_panel_geo(string $ip): array
{
    $ip = trim($ip);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return ['', ''];
    $ch = curl_init('https://ipapi.co/'.rawurlencode($ip).'/country/');
    if ($ch === false) return [$ip, ''];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>2,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_USERAGENT=>'ATD-Panel']);
    $country = strtoupper(trim((string)curl_exec($ch))); curl_close($ch);
    return [ $ip, preg_match('/^[A-Z]{2}$/', $country) ? $country : '' ];
}

function atd_panel_public_ip(): array
{
    $ch = curl_init('https://api.ipify.org?format=json');
    if ($ch === false) return ['', ''];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>2,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_USERAGENT=>'ATD-Panel']);
    $raw = curl_exec($ch); curl_close($ch);
    $data = json_decode((string)$raw, true);
    $ip = is_array($data) ? trim((string)($data['ip'] ?? '')) : '';
    return atd_panel_geo($ip);
}

function atd_panel_flag(string $country): string
{
    $country = strtoupper(trim($country));
    if (!preg_match('/^[A-Z]{2}$/', $country) || !function_exists('mb_chr')) return '🌐';
    return mb_chr(127397 + ord($country[0])).mb_chr(127397 + ord($country[1]));
}

function atd_panel_server_rows(\PDO $db, string $provider): array
{
    if ($provider === 'routebox') {
        if (!atd_panel_table_exists($db,'routebox_servers')) return [];
        return $db->query('SELECT id,name,base_url AS host,enabled FROM routebox_servers ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
    }
    if ($provider === 'ibsng') {
        if (!atd_panel_table_exists($db,'ibsng_servers')) return [];
        return $db->query('SELECT id,name,host,port,enabled,last_test_at,last_error FROM ibsng_servers ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
    }
    if (!atd_panel_table_exists($db,'mikrotik_servers')) return [];
    return $db->query('SELECT id,name,host,api_port,enabled,last_test_at,last_error FROM mikrotik_servers ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
}

function atd_panel_provider_stats(\PDO $db, string $provider, bool $refresh = false): array
{
    $rows = atd_panel_server_rows($db, $provider);
    $pings = []; $connected = 0; $firstIp = ''; $firstCountry = '';
    foreach ($rows as $row) {
        $host = (string)($row['host'] ?? ''); $port = (int)($row['port'] ?? ($row['api_port'] ?? 0));
        if ($provider === 'routebox') {
            $parsed = parse_url($host);
            $port = isset($parsed['port']) ? (int)$parsed['port'] : (($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80);
            $host = (string)($parsed['host'] ?? $host);
        }
        $ip = filter_var($host,FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($firstIp === '' && filter_var($ip,FILTER_VALIDATE_IP)) [$firstIp,$firstCountry] = atd_panel_geo($ip);
        $ping = atd_panel_ping($host,$port);
        if ($ping !== null) { $connected++; $pings[]=$ping; }
    }
    $count = count($rows); $avg = $pings ? round(array_sum($pings)/count($pings),1) : null;
    return ['servers'=>$count,'users'=>atd_panel_distinct_subscribers($db,$provider),'plans'=>atd_panel_count($db,'service_plans','provider_key=?',[$provider]),'connected'=>$connected,'ping'=>$avg,'ip'=>$firstIp,'country'=>$firstCountry,'refreshed'=>$refresh];
}

function atd_panel_global_stats(\PDO $db): array
{
    return [
        'servers'=>atd_panel_count($db,'routebox_servers')+atd_panel_count($db,'ibsng_servers')+atd_panel_count($db,'mikrotik_servers'),
        'users'=>atd_panel_count($db,'telegram_users'),
        'plans'=>atd_panel_count($db,'service_plans'),
    ];
}

function atd_panel_stat_card(string $label, string $value, string $icon): string
{
    return '<div class="stat"><div class="stat-top"><span>'.htmlspecialchars($label,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span><span class="stat-icon">'.$icon.'</span></div><b>'.htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</b></div>';
}

function atd_panel_stats_html(\PDO $db, string $section, string $lang): string
{
    $fa = $lang === 'fa';
    $icons = ['servers'=>'<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>','users'=>'<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c.6-4 2.5-6 6-6s5.4 2 6 6"/><path d="M16 6.5a3 3 0 0 1 0 5.8M17 14c2.2.7 3.5 2.3 4 6"/></svg>','plans'=>'<svg viewBox="0 0 24 24"><path d="m12 3 8 4-8 4-8-4 8-4Zm-8 9 8 4 8-4M4 17l8 4 8-4"/></svg>','updates'=>'<svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/></svg>'];
    if ($section === 'dashboard') {
        $s=atd_panel_global_stats($db);
        return '<div class="stats atd-scoped-stats">'.atd_panel_stat_card($fa?'سرورها':'Servers',(string)$s['servers'],$icons['servers']).atd_panel_stat_card($fa?'کاربران':'Users',number_format($s['users']),$icons['users']).atd_panel_stat_card($fa?'پلن‌ها':'Plans',(string)$s['plans'],$icons['plans']).atd_panel_stat_card($fa?'نسخه':'Version',(string)localVersion(),$icons['updates']).'</div>';
    }
    if ($section === 'bot') {
        $s=atd_panel_global_stats($db); [$ip,$country]=atd_panel_public_ip();
        $service='routebox-telegram-bot.service'; $running=trim((string)@shell_exec('systemctl is-active '.escapeshellarg($service).' 2>/dev/null'))==='active';
        if (!$running) { $service='routebox-telegram-bot-dev.service'; $running=trim((string)@shell_exec('systemctl is-active '.escapeshellarg($service).' 2>/dev/null'))==='active'; }
        $status=($running?($fa?'Running':'Running'):($fa?'Stopped':'Stopped')).' · '.($ip!==''?$ip:'—');
        $form='<form method="post" action="/reload-worker.php" style="margin:0"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"><button class="btn btn-secondary" type="submit">↻ '.($fa?'Reload Worker':'Reload Worker').'</button></form>';
        $value=($running?'🟢 ':'🔴 ').$status.' '.atd_panel_flag($country).' '.$form;
        return '<div class="stats atd-scoped-stats atd-bot-stats">'.atd_panel_stat_card($fa?'سرورها':'Servers',(string)$s['servers'],$icons['servers']).atd_panel_stat_card($fa?'کاربران':'Users',number_format($s['users']),$icons['users']).atd_panel_stat_card($fa?'پلن‌ها':'Plans',(string)$s['plans'],$icons['plans']).'<div class="stat"><div class="stat-top"><span>'.($fa?'Bot Server':'Bot Server').'</span><span class="stat-icon">'.$icons['updates'].'</span></div><b class="atd-bot-status">'.$value.'</b></div></div>';
    }
    $provider = $section==='servers'?'routebox':($section==='ibsng'?'ibsng':'mikrotik_wireguard');
    $s=atd_panel_provider_stats($db,$provider,isset($_GET['atd_refresh']));
    $labelServers=$fa?'سرورها':'Servers'; $labelUsers=$fa?'کاربران':'Users'; $labelPlans=$fa?'پلن‌ها':'Plans'; $labelStatus=$fa?'وضعیت سرور':'Server Status';
    $conn=$s['servers']>0 ? $s['connected'].'/'.$s['servers'].' '.($fa?'متصل':'Connected') : ($fa?'بدون سرور':'No servers');
    $details='<div class="atd-provider-status"><div class="atd-status-main">'.($s['connected']>0?'🟢':'🔴').' '.htmlspecialchars($conn,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</div><div class="atd-status-meta">🌐 '.htmlspecialchars($s['ip']?:'—',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').' · ⚡ '.htmlspecialchars($s['ping']!==null?(string)$s['ping'].' ms':'—',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').' · '.atd_panel_flag((string)$s['country']).'</div><a class="btn btn-secondary atd-refresh-link" href="/?section='.rawurlencode($section).'&atd_refresh=1">↻ '.($fa?'Refresh / بررسی':'Refresh / Check').'</a></div>';
    return '<div class="stats atd-scoped-stats">'.atd_panel_stat_card($labelServers,(string)$s['servers'],$icons['servers']).atd_panel_stat_card($labelUsers,number_format($s['users']),$icons['users']).atd_panel_stat_card($labelPlans,(string)$s['plans'],$icons['plans']).'<div class="stat"><div class="stat-top"><span>'.htmlspecialchars($labelStatus,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span><span class="stat-icon">'.$icons['updates'].'</span></div>'.$details.'</div></div>';
}

function atd_panel_buffer(string $buffer): string
{
    $section=(string)($_GET['section']??'dashboard');
    if (!in_array($section,['dashboard','bot','servers','ibsng','mikrotik'],true) || !function_exists('db')) return $buffer;
    try {
        $lang=(string)($_SESSION['panel_lang']??'fa')==='en'?'en':'fa';
        $stats=atd_panel_stats_html(\db(),$section,$lang);
        $class='atd-scope-'.$section;
        $buffer=preg_replace_callback('~<body\b([^>]*)>~i',static function($m)use($class){$attrs=$m[1];if(preg_match('/\bclass="([^"]*)"/i',$attrs,$cm)){$attrs=str_replace($cm[0],'class="'.trim($cm[1].' '.$class).'"',$attrs);}else{$attrs.=' class="'.$class.'"';}return '<body'.$attrs.'>';},$buffer,1)??$buffer;
        $style='<style id="atd-scoped-stats-style">.atd-scope-dashboard .stats,.atd-scope-bot .stats{display:none!important}.atd-scoped-stats{display:grid!important}.atd-provider-status{display:grid;gap:7px;margin-top:8px}.atd-status-main{font-size:15px;font-weight:800}.atd-status-meta{font-size:11px;color:var(--muted);font-weight:600;white-space:nowrap}.atd-provider-status .btn{width:max-content;min-height:34px;padding:7px 10px;font-size:11px}.atd-bot-stats .stat:last-child b{font-size:12px;line-height:1.55}.atd-bot-stats .stat:last-child form{margin-top:8px}.atd-bot-stats .stat:last-child .btn{font-size:11px;min-height:32px;padding:6px 9px}@media(max-width:1050px){.atd-status-meta{white-space:normal}}</style>';
        $buffer=preg_replace('~</head>~i',$style.'</head>',$buffer,1)??$buffer;
        if ($section==='dashboard') {
            $buffer=preg_replace('~(</section>\s*\n\s*)<div class="stats">~s','$1'.$stats.'<div class="stats" style="display:none!important">',$buffer,1)??$buffer;
        } elseif ($section==='bot') {
            $buffer=preg_replace('~(<section class="card" id="bot">)~',$stats.'$1',$buffer,1)??$buffer;
        } else {
            $header=strpos($buffer,'</header>'); $pos=$header===false?0:$header+9; $tail=substr($buffer,$pos); $secPos=strpos($tail,'<section');
            if($secPos!==false){$absolute=$pos+$secPos;$buffer=substr($buffer,0,$absolute).$stats.substr($buffer,$absolute);}
        }
    } catch (\Throwable) {
        /* Never let the dashboard/status enhancement break the existing panel. */
    }
    return $buffer;
}

if (PHP_SAPI !== 'cli' && !defined('ATD_PANEL_STATS_BUFFER')) {
    define('ATD_PANEL_STATS_BUFFER', true);
    ob_start(__NAMESPACE__.'\\atd_panel_buffer');
}
