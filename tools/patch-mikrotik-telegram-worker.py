from pathlib import Path

p = Path('worker.php')
s = p.read_text(encoding='utf-8')
if 'function mikrotikServiceActions(' in s:
    print('MikroTik Telegram flow already present')
    raise SystemExit(0)

marker = """    } else {
        throw new RuntimeException('ارائه‌دهنده این سرویس هنوز برای ربات فعال نشده است.');
    }
    tg($token, 'sendMessage'"""
branch = """    } elseif (($r['provider'] ?? '') === 'mikrotik_wireguard') {
        $expiry = $r['expires_at'] ? date('Y-m-d H:i', (int)$r['expires_at']) : '—';
        $text = $l === 'fa'
            ? "✅ سرویس MikroTik WireGuard فعال شد.\\n\\n📦 پلن: {$r['plan_name_fa']}\\n🖥️ سرور: {$r['server_name']}\\n👤 نام کاربری: {$r['username']}\\n🌐 IP: {$r['assigned_ip']}\\n⏱️ اعتبار تا: {$expiry}\\n\\n📋 برای دریافت Config یا QR از «سرویس‌های من» استفاده کنید."
            : "✅ MikroTik WireGuard service activated.\\n\\n📦 Plan: {$r['plan_name_en']}\\n🖥️ Server: {$r['server_name']}\\n👤 Username: {$r['username']}\\n🌐 IP: {$r['assigned_ip']}\\n⏱️ Valid until: {$expiry}\\n\\n📋 Use My services to get your Config or QR.";
    } else {
        throw new RuntimeException('ارائه‌دهنده این سرویس هنوز برای ربات فعال نشده است.');
    }
    tg($token, 'sendMessage'"""
if marker not in s:
    raise SystemExit('purchase marker not found')
s = s.replace(marker, branch, 1)

funcs = r'''function mikrotikServiceActions(string $token, $chat, int $uid, int $id): void
{
    $l = langFor($uid);
    $q = db()->prepare('SELECT s.id,s.username,s.expires_at,s.status,p.display_name_fa,p.display_name_en,r.name server_name,s.metadata_json FROM service_subscriptions s JOIN service_plans p ON p.id=s.plan_id JOIN mikrotik_servers r ON r.id=s.provider_server_id WHERE s.id=? AND s.telegram_user_id=? AND s.provider_key="mikrotik_wireguard" AND s.status="active" AND (s.expires_at IS NULL OR s.expires_at>?)');
    $q->execute([$id, $uid, time()]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r) throw new RuntimeException('سرویس MikroTik WireGuard پیدا نشد یا منقضی شده است.');
    $meta = json_decode((string)$r['metadata_json'], true);
    if (!is_array($meta)) $meta = [];
    $ip = (string)($meta['assigned_ip'] ?? '—');
    $plan = $l === 'fa' ? (string)$r['display_name_fa'] : (string)$r['display_name_en'];
    $expiry = $r['expires_at'] ? date('Y-m-d H:i', (int)$r['expires_at']) : '—';
    $text = $l === 'fa'
        ? "🟢 سرویس MikroTik WireGuard\n\n📦 پلن: {$plan}\n🖥️ سرور: {$r['server_name']}\n👤 نام کاربری: {$r['username']}\n🌐 IP: {$ip}\n⏱️ اعتبار تا: {$expiry}"
        : "🟢 MikroTik WireGuard service\n\n📦 Plan: {$plan}\n🖥️ Server: {$r['server_name']}\n👤 Username: {$r['username']}\n🌐 IP: {$ip}\n⏱️ Valid until: {$expiry}";
    $k = [
        [['text' => $l === 'fa' ? '📄 دریافت Config' : '📄 Get Config', 'callback_data' => 'mkconfig:' . $id], ['text' => $l === 'fa' ? '📷 دریافت QR' : '📷 Get QR', 'callback_data' => 'mkqr:' . $id]],
        [['text' => $l === 'fa' ? '📋 سرویس‌ها' : '📋 Services', 'callback_data' => 'services']],
    ];
    tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
}

function sendMikroTikConfig(string $token, $chat, int $uid, int $id): void
{
    $q = db()->prepare('SELECT s.*,r.name server_name FROM service_subscriptions s JOIN mikrotik_servers r ON r.id=s.provider_server_id WHERE s.id=? AND s.telegram_user_id=? AND s.provider_key="mikrotik_wireguard" AND s.status="active" AND (s.expires_at IS NULL OR s.expires_at>?)');
    $q->execute([$id, $uid, time()]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r) throw new RuntimeException('سرویس MikroTik WireGuard پیدا نشد یا منقضی شده است.');
    $meta = json_decode((string)$r['metadata_json'], true);
    if (!is_array($meta)) $meta = [];
    $conf = trim((string)($meta['config'] ?? ''));
    if ($conf === '') throw new RuntimeException('Config سرویس MikroTik در دیتابیس موجود نیست.');
    $tmp = tempnam(sys_get_temp_dir(), 'mkconf');
    file_put_contents($tmp, $conf);
    try {
        tg($token, 'sendDocument', ['chat_id' => $chat, 'document' => new CURLFile($tmp, 'text/plain', 'MikroTik-WireGuard-' . $r['server_name'] . '.conf'), 'caption' => '📄 ' . $r['server_name'] . ' — WireGuard']);
    } finally { @unlink($tmp); }
}

function sendMikroTikQr(string $token, $chat, int $uid, int $id): void
{
    $q = db()->prepare('SELECT s.*,r.name server_name FROM service_subscriptions s JOIN mikrotik_servers r ON r.id=s.provider_server_id WHERE s.id=? AND s.telegram_user_id=? AND s.provider_key="mikrotik_wireguard" AND s.status="active" AND (s.expires_at IS NULL OR s.expires_at>?)');
    $q->execute([$id, $uid, time()]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r) throw new RuntimeException('سرویس MikroTik WireGuard پیدا نشد یا منقضی شده است.');
    $meta = json_decode((string)$r['metadata_json'], true);
    if (!is_array($meta)) $meta = [];
    $conf = trim((string)($meta['config'] ?? ''));
    if ($conf === '') throw new RuntimeException('Config سرویس MikroTik در دیتابیس موجود نیست.');
    $bin = trim((string)shell_exec('command -v qrencode 2>/dev/null'));
    if ($bin === '') throw new RuntimeException('qrencode نصب نیست.');
    $tmp = tempnam(sys_get_temp_dir(), 'mkqr');
    @unlink($tmp);
    $proc = proc_open([$bin, '-l', 'L', '-m', '2', '-s', '8', '-o', $tmp], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('QR generation failed.');
    fwrite($pipes[0], $conf); fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 || !is_file($tmp)) throw new RuntimeException('QR generation failed.');
    try {
        tg($token, 'sendPhoto', ['chat_id' => $chat, 'photo' => new CURLFile($tmp, 'image/png', 'MikroTik-WireGuard-QR.png'), 'caption' => '📷 ' . $r['server_name'] . ' — WireGuard']);
    } finally { @unlink($tmp); }
}

'''
marker = "function sendConfig(string $token, $chat, int $uid, int $id): void\n{"
if marker not in s:
    raise SystemExit('config marker not found')
s = s.replace(marker, funcs + marker, 1)

marker = """    $ibs = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$routebox && !$ibs) {"""
repl = """    $ibs = $q->fetchAll(PDO::FETCH_ASSOC);
    $q = db()->prepare('SELECT s.id,s.username,s.expires_at,s.status,p.display_name_fa,p.display_name_en,r.name server_name,s.metadata_json FROM service_subscriptions s JOIN service_plans p ON p.id=s.plan_id JOIN mikrotik_servers r ON r.id=s.provider_server_id WHERE s.telegram_user_id=? AND s.provider_key=\"mikrotik_wireguard\" AND s.status=\"active\" AND (s.expires_at IS NULL OR s.expires_at>?) ORDER BY s.created_at DESC,s.id DESC');
    $q->execute([$uid, $now]);
    $mikrotik = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$routebox && !$ibs && !$mikrotik) {"""
if marker not in s:
    raise SystemExit('services query marker not found')
s = s.replace(marker, repl, 1)

ibs_loop = """    foreach ($ibs as $i => $r) {
        $plan = $l === 'fa' ? $r['display_name_fa'] : $r['display_name_en'];
        $expiry = $r['expires_at'] ? date('Y-m-d H:i', (int)$r['expires_at']) : '—';
        $text .= '🔵 IBSng — ' . $plan . ' — ' . $r['username'] . ' — ' . $expiry . \"\\n\";
        $k[] = [['text' => '⚙️ IBSng · ' . ($i + 1), 'callback_data' => 'ibsservice:' . $r['id']]];
    }
"""
mk_loop = ibs_loop + """    foreach ($mikrotik as $i => $r) {
        $plan = $l === 'fa' ? $r['display_name_fa'] : $r['display_name_en'];
        $meta = json_decode((string)$r['metadata_json'], true);
        if (!is_array($meta)) $meta = [];
        $ip = (string)($meta['assigned_ip'] ?? '—');
        $expiry = $r['expires_at'] ? date('Y-m-d H:i', (int)$r['expires_at']) : '—';
        $text .= '🟢 MikroTik — ' . $r['server_name'] . ' — ' . $ip . ' — ' . $expiry . \"\\n\";
        $k[] = [['text' => '⚙️ MikroTik · ' . ($i + 1), 'callback_data' => 'mikrotikservice:' . $r['id']]];
    }
"""
if ibs_loop not in s:
    raise SystemExit('services loop marker not found')
s = s.replace(ibs_loop, mk_loop, 1)

marker = """                } elseif (str_starts_with($a, 'ibsservice:')) {
                    ibsngServiceActions($token, $chat, $uid, (int)substr($a, 11));
                } elseif (str_starts_with($a, 'config:')) {"""
repl = """                } elseif (str_starts_with($a, 'ibsservice:')) {
                    ibsngServiceActions($token, $chat, $uid, (int)substr($a, 11));
                } elseif (str_starts_with($a, 'mikrotikservice:')) {
                    mikrotikServiceActions($token, $chat, $uid, (int)substr($a, 16));
                } elseif (str_starts_with($a, 'mkconfig:')) {
                    sendMikroTikConfig($token, $chat, $uid, (int)substr($a, 9));
                } elseif (str_starts_with($a, 'mkqr:')) {
                    sendMikroTikQr($token, $chat, $uid, (int)substr($a, 5));
                } elseif (str_starts_with($a, 'config:')) {"""
if marker not in s:
    raise SystemExit('dispatch marker not found')
s = s.replace(marker, repl, 1)

p.write_text(s, encoding='utf-8')
print('worker.php patched successfully')
