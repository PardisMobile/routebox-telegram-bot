<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();
require __DIR__ . '/../src/RouteBoxClient.php';

function telegramCheckAndPrepare(string $token): void
{
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/getMe');
    if ($ch === false) throw new RuntimeException('Telegram connection is unavailable.');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
    $raw = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new RuntimeException('Telegram connection failed: ' . ($error ?: 'unknown cURL error'));
    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json['ok'])) throw new RuntimeException('Invalid Telegram Bot Token.');

    $delete = curl_init('https://api.telegram.org/bot' . $token . '/deleteWebhook');
    if ($delete === false) throw new RuntimeException('Could not initialize Telegram webhook cleanup.');
    curl_setopt_array($delete, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['drop_pending_updates' => 'false'], CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
    $deleteRaw = curl_exec($delete); $deleteError = curl_error($delete); curl_close($delete);
    if ($deleteRaw === false) throw new RuntimeException('Telegram webhook cleanup failed: ' . ($deleteError ?: 'unknown error'));
    $deleteJson = json_decode($deleteRaw, true);
    if (!is_array($deleteJson) || empty($deleteJson['ok'])) throw new RuntimeException('Telegram webhook cleanup failed.');
}

function validateRouteBoxForAdmin(string $url, string $username, string $password, bool $verifyTls): void
{
    $client = new RouteBoxClient($url, $username, $password, $verifyTls);
    $client->validateIntegration();
    $client->smokeTest('rbt-admin-test');
}

function routeBoxEndpoint(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) throw new RuntimeException('Invalid RouteBox URL.');
    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? (int) $parts['port'] : (($parts['scheme'] ?? 'https') === 'https' ? 443 : 80);
    return [$host, $port];
}

function measureServerPing(string $url): ?float
{
    try {
        [$host, $port] = routeBoxEndpoint($url);
        $ip = gethostbyname($host);
        $target = $ip !== $host ? $ip : $host;
        $started = microtime(true); $errno = 0; $errstr = '';
        $socket = @fsockopen($target, $port, $errno, $errstr, 3.0);
        if ($socket === false) return null;
        $elapsed = (microtime(true) - $started) * 1000;
        fclose($socket);
        return round($elapsed, 1);
    } catch (Throwable) { return null; }
}

function countryFlag(string $code): string
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z]{2}$/', $code) || !function_exists('mb_chr')) return '🌐';
    return mb_chr(127397 + ord($code[0])) . mb_chr(127397 + ord($code[1]));
}

function formatQuota(float $quota): string
{
    if ($quota <= 0) return 'نامحدود';
    return rtrim(rtrim(number_format($quota, 1, '.', ''), '0'), '.') . ' GB';
}

function guessCountryCode(string $url): string
{
    try {
        $host = strtolower(routeBoxEndpoint($url)[0]);
        $parts = explode('.', $host);
        $tld = strtoupper((string) end($parts));
        return preg_match('/^[A-Z]{2}$/', $tld) ? $tld : '';
    } catch (Throwable) { return ''; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_server') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = rtrim(trim((string) ($_POST['url'] ?? '')), '/');
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $verifyTls = isset($_POST['verify_tls']) ? 1 : 0;
            $country = strtoupper(trim((string) ($_POST['country_code'] ?? '')));
            if ($country === '') $country = guessCountryCode($url);
            if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) throw new RuntimeException('Country code must contain exactly 2 letters, e.g. US or DE.');
            if ($name === '' || mb_strlen($name) > 80 || !filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('RouteBox name or Panel URL is invalid.');
            validateRouteBoxForAdmin($url, $username, $password, (bool) $verifyTls);
            db()->prepare('INSERT INTO routebox_servers(name,base_url,user_enc,pass_enc,verify_tls,enabled,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$name, $url, enc($username), enc($password), $verifyTls, 1, time()]);
            $serverId = (int) db()->lastInsertId();
            db()->prepare('INSERT OR REPLACE INTO server_meta(server_id,country_code,ping_ms,ping_checked_at) VALUES(?,?,?,?)')->execute([$serverId, $country, measureServerPing($url), time()]);
            log_event('info', 'RouteBox server added and end-to-end validated: ' . $name);
            $_SESSION['flash'] = '✅ RouteBox added. Session/API and AWG create → config → delete tests passed.';
        } elseif ($action === 'test_server') {
            $stmt = db()->prepare('SELECT * FROM routebox_servers WHERE id = ?');
            $stmt->execute([(int) ($_POST['id'] ?? 0)]); $server = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$server) throw new RuntimeException('RouteBox server not found.');
            validateRouteBoxForAdmin((string) $server['base_url'], dec((string) $server['user_enc']), dec((string) $server['pass_enc']), (bool) $server['verify_tls']);
            $ping = measureServerPing((string) $server['base_url']);
            db()->prepare('INSERT OR IGNORE INTO server_meta(server_id,country_code,ping_ms,ping_checked_at) VALUES(?,?,?,?)')->execute([(int)$server['id'], guessCountryCode((string)$server['base_url']), $ping, time()]);
            db()->prepare('UPDATE server_meta SET ping_ms=?,ping_checked_at=? WHERE server_id=?')->execute([$ping, time(), (int)$server['id']]);
            $_SESSION['flash'] = '🟢 Full RouteBox integration test passed for «' . $server['name'] . '».';
            log_event('info', 'Full RouteBox integration test passed: ' . $server['name']);
        } elseif ($action === 'toggle_server') {
            db()->prepare('UPDATE routebox_servers SET enabled = 1 - enabled WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '🔄 RouteBox status updated.';
        } elseif ($action === 'save_settings') {
            $trialHours = max(1, min(720, (int) ($_POST['trial_hours'] ?? 12)));
            db()->prepare("INSERT INTO settings(key,value) VALUES('trial_hours',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([(string) $trialHours]);
            $token = trim((string) ($_POST['telegram_token'] ?? ''));
            if ($token !== '') {
                telegramCheckAndPrepare($token);
                db()->prepare("INSERT INTO settings(key,value) VALUES('telegram_token',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([enc($token)]);
                log_event('info', 'Telegram bot token validated and saved.');
                $_SESSION['flash'] = '✅ Settings saved and Telegram Bot validated.';
            } else $_SESSION['flash'] = '✅ Trial duration saved.';
        } elseif ($action === 'add_plan') {
            $name = trim((string)($_POST['plan_name'] ?? ''));
            $days = max(1, min(3650, (int)($_POST['duration_days'] ?? 30)));
            $quota = max(0, min(1024, (float)($_POST['quota_gb'] ?? 0)));
            if ($name === '' || mb_strlen($name) > 80) throw new RuntimeException('Plan name is required (max 80 characters).');
            db()->prepare('INSERT INTO plans(name,duration_days,quota_gb,enabled,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([$name,$days,$quota,1,0,time(),time()]);
            $_SESSION['flash'] = '✅ Plan «' . $name . '» added. It is now available as a Telegram button.';
            log_event('info','Telegram plan added: '.$name);
        } elseif ($action === 'update_plan') {
            $id = (int)($_POST['id'] ?? 0); $name = trim((string)($_POST['plan_name'] ?? '')); $days = max(1,min(3650,(int)($_POST['duration_days'] ?? 30))); $quota = max(0,min(1024,(float)($_POST['quota_gb'] ?? 0)));
            if ($id < 1 || $name === '') throw new RuntimeException('Invalid plan.');
            db()->prepare('UPDATE plans SET name=?,duration_days=?,quota_gb=?,updated_at=? WHERE id=?')->execute([$name,$days,$quota,time(),$id]);
            $_SESSION['flash'] = '✅ Plan updated.';
        } elseif ($action === 'toggle_plan') {
            db()->prepare('UPDATE plans SET enabled=1-enabled,updated_at=? WHERE id=?')->execute([time(),(int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '🔄 Plan status updated.';
        } elseif ($action === 'delete_plan') {
            db()->prepare('DELETE FROM plans WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '🗑️ Plan deleted.';
        } else throw new RuntimeException('Invalid request.');
    } catch (Throwable $error) {
        $_SESSION['flash'] = '❌ Error: ' . $error->getMessage();
        log_event('error', 'Admin action failed: ' . $error->getMessage());
    }
    header('Location: /'); exit;
}

$servers = db()->query('SELECT s.*,m.country_code,m.ping_ms,m.ping_checked_at FROM routebox_servers s LEFT JOIN server_meta m ON m.server_id=s.id ORDER BY s.id')->fetchAll(PDO::FETCH_ASSOC);
$users = db()->query('SELECT * FROM telegram_users ORDER BY last_seen DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$plans = db()->query('SELECT * FROM plans ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
$trial = (int) (db()->query("SELECT value FROM settings WHERE key='trial_hours'")->fetchColumn() ?: 12);
$hasToken = (bool) db()->query("SELECT value FROM settings WHERE key='telegram_token'")->fetchColumn();
$flash = (string) ($_SESSION['flash'] ?? ''); unset($_SESSION['flash']);
$version = is_file(__DIR__ . '/../VERSION') ? trim((string) file_get_contents(__DIR__ . '/../VERSION')) : '0.1.0-beta.3';
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RouteBox Telegram Bot</title>
<style>
:root{color-scheme:dark;--bg:#070b14;--text:#e5e7eb;--muted:#94a3b8;--card:#111827;--card2:#0d1422;--border:#263247;--input:#09111f;--blue:#2563eb;--danger:#be123c;--ok:#34d399;--shadow:0 14px 40px rgba(0,0,0,.18)}
:root.light{color-scheme:light;--bg:#f1f5f9;--text:#0f172a;--muted:#64748b;--card:#fff;--card2:#f8fafc;--border:#dbe3ee;--input:#fff;--blue:#2563eb;--danger:#be123c;--ok:#059669;--shadow:0 12px 30px rgba(15,23,42,.08)}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#13203a 0,var(--bg) 38%);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif;transition:.2s}.wrap{max-width:1180px;margin:auto;padding:24px 18px 60px}.hero{display:flex;justify-content:space-between;gap:20px;align-items:center;flex-wrap:wrap}.hero h1{margin:0 0 8px;font-size:30px}.subtitle{color:var(--muted)}.badge{display:inline-block;background:#f59e0b;color:#111827;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:800}.card{background:linear-gradient(145deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:20px;padding:22px;margin:16px 0;box-shadow:var(--shadow)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}label{display:block;color:var(--text);font-size:14px;margin-bottom:4px}input,select{width:100%;padding:12px 13px;margin:6px 0 12px;border-radius:11px;border:1px solid #344156;background:var(--input);color:var(--text);outline:none}input[type=number]{direction:ltr;text-align:left}input[type=checkbox]{width:auto;margin:0 5px 0 0}.ltr{direction:ltr!important;text-align:left!important}button{background:var(--blue);color:#fff;border:0;border-radius:10px;padding:10px 15px;font-weight:700;cursor:pointer}button:hover{filter:brightness(1.08)}.secondary{background:#334155}.danger{background:var(--danger)}.row{display:grid;grid-template-columns:minmax(180px,1fr) auto minmax(250px,auto);gap:12px;align-items:center;border-top:1px solid var(--border);padding:14px 0}.muted{color:var(--muted);font-size:13px}.flash{background:#172554;border:1px solid #1d4ed8;padding:13px 15px;border-radius:12px;margin:16px 0}.ok{color:var(--ok)}.off{color:#fb7185}.feature{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.feature div{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:13px}.feature b{display:block;margin-bottom:4px}a{color:#60a5fa;text-decoration:none}.inline{display:inline}.top-actions{display:flex;gap:8px;align-items:center}.small{font-size:12px;color:var(--muted)}h1,h2,h3,p{text-align:right}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.server-title{display:flex;gap:9px;align-items:center;font-size:16px}.flag{font-size:20px}.ping{font-variant-numeric:tabular-nums;white-space:nowrap}.plan{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:14px;margin:10px 0}.plan-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.plan-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.pill{border:1px solid var(--border);border-radius:999px;padding:4px 9px;font-size:12px;color:var(--muted)}.theme{min-width:44px}.navlink{display:inline-flex;align-items:center;gap:5px}.update-link{background:rgba(37,99,235,.14);border:1px solid rgba(96,165,250,.25);padding:7px 10px;border-radius:10px}@media(max-width:800px){.row{grid-template-columns:1fr}.row>div:last-child{display:flex}.hero h1{font-size:24px}}
</style>
<script>
(function(){const k='rbt-theme';const saved=localStorage.getItem(k);if(saved==='light')document.documentElement.classList.add('light');window.toggleTheme=function(){const light=document.documentElement.classList.toggle('light');localStorage.setItem(k,light?'light':'dark');document.getElementById('themeBtn').textContent=light?'🌙':'☀️';};window.addEventListener('DOMContentLoaded',()=>{const b=document.getElementById('themeBtn');if(b)b.textContent=document.documentElement.classList.contains('light')?'🌙':'☀️';});})();
</script>
</head>
<body><div class="wrap">
<div class="hero"><div><h1>🚀 RouteBox Telegram Bot <span class="badge">BETA 3</span></h1><div class="subtitle">🤖 مدیریت ربات · 🌐 چند RouteBox · 🔐 AmneziaWG</div></div><div class="top-actions"><span class="small">v<?=h($version)?></span><a class="navlink update-link" href="/update.php">⬆️ به‌روزرسانی</a><button id="themeBtn" class="secondary theme" type="button" onclick="toggleTheme()">☀️</button><a class="navlink" href="/account.php">🔐 تغییر رمز</a><form method="post" action="/logout.php" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><button class="secondary" type="submit">خروج</button></form></div></div>
<?php if ($flash !== ''): ?><div class="flash"><?=h($flash)?></div><?php endif; ?>
<div class="card"><h2>⚡ وضعیت سریع</h2><div class="feature"><div><b>🤖 تلگرام</b><span class="<?= $hasToken ? 'ok' : 'off' ?>"><?= $hasToken ? '● تنظیم شده' : '● تنظیم نشده' ?></span></div><div><b>🌐 RouteBox</b><span><?=count($servers)?> سرور</span></div><div><b>👥 کاربران</b><span><?=count($users)?> کاربر اخیر</span></div><div><b>🎁 تست رایگان</b><span><?=h((string)$trial)?> ساعت</span></div><div><b>🛒 پلن‌ها</b><span><?=count($plans)?> پلن</span></div></div></div>
<div class="card"><h2>⚙️ تنظیمات ربات</h2><form method="post"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="grid"><div><label>🤖 Telegram Bot Token</label><input class="ltr" name="telegram_token" type="password" autocomplete="new-password" placeholder="<?= $hasToken ? 'توکن فعلی حفظ می‌شود؛ برای تعویض وارد کنید' : '123456:ABC...' ?>"></div><div><label>🎁 مدت تست رایگان (ساعت)</label><input type="number" name="trial_hours" min="1" max="720" value="<?=h((string)$trial)?>" required></div></div><button>💾 ذخیره و اعتبارسنجی</button></form><p class="muted">توکن قبل از ذخیره با Telegram بررسی می‌شود و webhook قبلی حذف می‌شود تا polling ربات تداخل نداشته باشد.</p></div>
<div class="card"><h2>🛒 پلن‌ها و دکمه‌های ربات</h2><p class="muted">از همین بخش دکمه جدید بساز. هر پلن می‌تواند مدت مشخص و حجم مشخص داشته باشد. حجم صفر یعنی نامحدود. بعد از ذخیره، دکمه در منوی <code>/start</code> ربات نمایش داده می‌شود.</p><form method="post"><input type="hidden" name="action" value="add_plan"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="grid"><div><label>نام پلن</label><input name="plan_name" placeholder="مثلاً ۳۰ روزه ۵۰ گیگ" maxlength="80" required></div><div><label>مدت (روز)</label><input type="number" name="duration_days" min="1" max="3650" value="30" required></div><div><label>حجم (GB)</label><input type="number" name="quota_gb" min="0" max="1024" step="0.1" value="50" required></div></div><button>➕ ساخت دکمه جدید</button></form>
<?php foreach ($plans as $plan): ?><div class="plan"><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="update_plan"><input type="hidden" name="id" value="<?=$plan['id']?>"><div class="plan-head"><strong><?=h((string)$plan['name'])?></strong><span class="<?= $plan['enabled'] ? 'ok' : 'off' ?>"><?= $plan['enabled'] ? '● فعال' : '● غیرفعال' ?></span></div><div class="grid"><div><label>نام</label><input name="plan_name" value="<?=h((string)$plan['name'])?>" maxlength="80" required></div><div><label>روز</label><input type="number" name="duration_days" min="1" max="3650" value="<?=h((string)$plan['duration_days'])?>" required></div><div><label>GB</label><input type="number" name="quota_gb" min="0" max="1024" step="0.1" value="<?=h((string)$plan['quota_gb'])?>" required></div></div><div class="plan-meta"><span class="pill">⏱️ <?=h((string)$plan['duration_days'])?> روز</span><span class="pill">📦 <?=h(formatQuota((float)$plan['quota_gb']))?></span></div><div class="actions"><button>💾 ویرایش</button></form><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_plan"><input type="hidden" name="id" value="<?=$plan['id']?>"><button class="secondary">⏯️ <?= $plan['enabled'] ? 'غیرفعال' : 'فعال' ?></button></form><form method="post" class="inline" onsubmit="return confirm('این پلن حذف شود؟')"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="<?=$plan['id']?>"><button class="danger">🗑️ حذف</button></form></div></div><?php endforeach; ?>
<?php if (!$plans): ?><p class="muted">هنوز پلنی نساخته‌ای. اولین دکمه را از فرم بالا بساز.</p><?php endif; ?></div>
<div class="card"><h2>🌐 RouteBox Servers</h2><form method="post"><input type="hidden" name="action" value="add_server"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="grid"><div><label>نام</label><input name="name" placeholder="🇹🇷 Turkey #1" maxlength="80" required></div><div><label>Panel URL</label><input class="ltr" name="url" placeholder="https://panel.example.com:8443" required></div><div><label>Username <span class="small">(در صورت خاموش بودن احراز هویت خالی)</span></label><input class="ltr" name="username" autocomplete="off" placeholder="admin"></div><div><label>Password <span class="small">(در صورت خاموش بودن احراز هویت خالی)</span></label><input class="ltr" type="password" name="password" autocomplete="new-password"></div><div><label>کد کشور <span class="small">(مثلاً US / DE / TR؛ اگر خالی باشد از دامنه حدس زده می‌شود)</span></label><input class="ltr" name="country_code" maxlength="2" placeholder="US"></div></div><label><input type="checkbox" name="verify_tls" checked> 🔒 Verify TLS certificate</label><p class="muted">هنگام افزودن، API و تست واقعی AWG انجام می‌شود و ping اتصال TCP پنل هم اندازه‌گیری می‌شود.</p><button>➕ افزودن و تست RouteBox</button></form>
<?php foreach ($servers as $server): ?><div class="row"><div><div class="server-title"><span class="flag"><?=h(countryFlag((string)($server['country_code'] ?? '')))?></span><b><?=h((string)$server['name'])?></b></div><div class="muted ltr"><?=h((string)$server['base_url'])?></div></div><div><span class="ping">📡 <?= $server['ping_ms'] !== null ? h(number_format((float)$server['ping_ms'],1)) . ' ms' : '—' ?></span><div class="small"><?= $server['ping_checked_at'] ? 'آخرین تست: '.h(date('Y-m-d H:i',(int)$server['ping_checked_at'])) : 'ping ثبت نشده' ?></div></div><div class="actions"><span class="<?= $server['enabled'] ? 'ok' : 'off' ?>"><?= $server['enabled'] ? '● فعال' : '● غیرفعال' ?></span><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="<?=$server['id']?>"><button class="secondary">🧪 تست + Ping</button></form><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_server"><input type="hidden" name="id" value="<?=$server['id']?>"><button class="secondary">⏯️ <?= $server['enabled'] ? 'غیرفعال' : 'فعال' ?></button></form></div></div><?php endforeach; ?><?php if (!$servers): ?><p class="muted">هنوز RouteBoxای تنظیم نشده است.</p><?php endif; ?></div>
<div class="card"><h2>👥 کاربران تلگرام</h2><?php foreach ($users as $user): ?><div class="row"><span><?=h((string)($user['first_name'] ?: $user['username'] ?: $user['telegram_id']))?></span><span class="muted">@<?=h((string)($user['username'] ?: '—'))?> · <?=date('Y-m-d H:i',(int)$user['last_seen'])?></span><span class="small ltr">ID: <?=h((string)$user['telegram_id'])?></span></div><?php endforeach; ?><?php if (!$users): ?><p class="muted">هنوز کاربری وارد نشده است.</p><?php endif; ?></div>
<div class="card"><h2>🔄 به‌روزرسانی نرم‌افزار</h2><p class="muted">نسخه فعلی: <b>v<?=h($version)?></b> · برای بررسی نسخه جدید و اجرای آپدیت کنترل‌شده، وارد بخش به‌روزرسانی شوید.</p><a class="update-link" href="/update.php">⬆️ بررسی و به‌روزرسانی از GitHub</a></div>
<footer class="small" style="text-align:center;padding:10px 0 20px">🚀 RouteBox Telegram Bot v<?=h($version)?> · ساخته و نگهداری توسط <a href="https://t.me/+918807085399" target="_blank" rel="noopener">Amir Taheri</a> · <a href="/account.php">🔐 تغییر رمز</a></footer>
</div></body></html>
