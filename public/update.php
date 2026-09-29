<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();

const GITHUB_VERSION_URL = 'https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/VERSION';
const UPDATE_LOG = __DIR__ . '/../storage/logs/admin-update.log';

function localVersion(): string
{
    $file = __DIR__ . '/../VERSION';
    return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
}

function remoteVersion(): ?string
{
    $ch = curl_init(GITHUB_VERSION_URL);
    if ($ch === false) return null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'RouteBox-Telegram-Bot-Updater',
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) return null;
    $version = trim((string) $raw);
    return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?$/', $version) ? $version : null;
}

function updateLogTail(): string
{
    if (!is_file(UPDATE_LOG)) return '';
    $lines = @file(UPDATE_LOG, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) return '';
    return implode("\n", array_slice($lines, -35));
}

$local = localVersion();
$remote = remoteVersion();
$message = '';
$started = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action !== 'start_update') throw new RuntimeException('Invalid update request.');
        if ($remote !== null && version_compare($remote, $local, '<=')) {
            throw new RuntimeException('The server is already on the latest available version.');
        }
        $sudo = '/usr/bin/sudo -n /usr/local/sbin/routebox-telegram-bot-update';
        $command = 'nohup ' . $sudo . ' >/dev/null 2>&1 &';
        @exec($command);
        log_event('warning', 'Administrator started a self-update from the web panel.');
        $started = true;
        $message = '🚀 Update started. The web panel will briefly disconnect while the services are restarted.';
    } catch (Throwable $e) {
        $message = '❌ ' . $e->getMessage();
    }
}

$upToDate = $remote !== null && version_compare($remote, $local, '<=');
$canUpdate = $remote !== null && version_compare($remote, $local, '>');
$log = updateLogTail();
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>به‌روزرسانی RouteBox Telegram Bot</title>
<style>
:root{color-scheme:dark;--bg:#070b14;--text:#e5e7eb;--muted:#94a3b8;--card:#111827;--card2:#0d1422;--border:#263247;--blue:#2563eb;--ok:#34d399;--warn:#f59e0b;--danger:#fb7185}body{margin:0;background:radial-gradient(circle at top,#13203a 0,var(--bg) 42%);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif}.wrap{max-width:760px;margin:auto;padding:30px 18px 60px}.card{background:linear-gradient(145deg,var(--card),var(--card2));border:1px solid var(--border);border-radius:22px;padding:24px;margin:16px 0;box-shadow:0 18px 45px rgba(0,0,0,.2)}h1{margin-top:0}.version{display:grid;grid-template-columns:1fr 1fr;gap:12px}.box{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:16px}.label{color:var(--muted);font-size:13px}.value{font-size:21px;font-weight:800;margin-top:6px;direction:ltr;text-align:left}.ok{color:var(--ok)}.warn{color:var(--warn)}button{border:0;border-radius:11px;padding:12px 17px;background:var(--blue);color:#fff;font-weight:800;cursor:pointer}button[disabled]{opacity:.5;cursor:not-allowed}.msg{padding:13px 15px;border-radius:12px;background:#172554;border:1px solid #1d4ed8;margin:15px 0}.muted{color:var(--muted);font-size:13px;line-height:1.8}pre{white-space:pre-wrap;direction:ltr;text-align:left;background:#050914;border:1px solid var(--border);border-radius:12px;padding:14px;max-height:320px;overflow:auto;font-size:12px}a{color:#60a5fa;text-decoration:none}.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}@media(max-width:600px){.version{grid-template-columns:1fr}}
</style>
</head>
<body><div class="wrap">
<div class="card">
<h1>🚀 به‌روزرسانی سیستم</h1>
<p class="muted">این بخش نسخه فعلی را با GitHub مقایسه می‌کند. هنگام به‌روزرسانی، سرویس Bot و Admin Panel به‌صورت کنترل‌شده متوقف می‌شوند، کد جدید دریافت می‌شود و در پایان هر دو سرویس دوباره اجرا می‌شوند.</p>
<div class="version"><div class="box"><div class="label">نسخه نصب‌شده</div><div class="value">v<?=h($local)?></div></div><div class="box"><div class="label">آخرین نسخه GitHub</div><div class="value"><?= $remote ? 'v'.h($remote) : 'نامشخص' ?></div></div></div>
<?php if($message): ?><div class="msg"><?=h($message)?></div><?php endif; ?>
<?php if($upToDate): ?><p class="ok">✅ نسخه فعلی به‌روز است.</p><?php elseif($canUpdate && !$started): ?><p class="warn">🆕 نسخه جدید موجود است.</p><?php elseif($remote===null): ?><p class="muted">⚠️ فعلاً نتوانستم نسخه GitHub را بررسی کنم. اتصال اینترنت سرور یا دسترسی به GitHub را بررسی کنید.</p><?php endif; ?>
<?php if(!$started): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="start_update"><button type="submit" <?=(!$canUpdate?'disabled':'')?>>⬆️ دریافت و نصب نسخه جدید</button></form><?php endif; ?>
<p class="muted">⚠️ در زمان آپدیت ممکن است پنل برای چند ثانیه قطع شود. سرویس RouteBox و Nginx/Apache موجود توسط این updater تغییر داده نمی‌شوند.</p>
</div>
<?php if($log): ?><div class="card"><h2>📋 گزارش آخرین به‌روزرسانی</h2><pre><?=h($log)?></pre></div><?php endif; ?>
<div class="card"><div class="actions"><a href="/">← بازگشت به داشبورد</a><a href="/account.php">🔐 امنیت پنل</a></div></div>
<footer class="muted" style="text-align:center;margin-top:22px">© 2026 <a href="https://t.me/+918807085399" target="_blank" rel="noopener">Amir Taheri</a> · RouteBox Telegram Bot</footer>
</div></body></html>
