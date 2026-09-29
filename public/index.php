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
    if ($raw === false) throw new RuntimeException('Telegram connection failed: ' . ($error ?: 'unknown error'));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_server') {
            $name = trim((string) ($_POST['name'] ?? '')); $url = rtrim(trim((string) ($_POST['url'] ?? '')), '/');
            $username = trim((string) ($_POST['username'] ?? '')); $password = (string) ($_POST['password'] ?? '');
            $verifyTls = isset($_POST['verify_tls']) ? 1 : 0;
            if ($name === '' || mb_strlen($name) > 80 || !filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('RouteBox name or Panel URL is invalid.');
            validateRouteBoxForAdmin($url, $username, $password, (bool) $verifyTls);
            db()->prepare('INSERT INTO routebox_servers(name,base_url,user_enc,pass_enc,verify_tls,enabled,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$name, $url, enc($username), enc($password), $verifyTls, 1, time()]);
            log_event('info', 'RouteBox server added and end-to-end validated: ' . $name); $_SESSION['flash'] = '✅ RouteBox added. Session/API and AWG create → config → delete tests passed.';
        } elseif ($action === 'test_server') {
            $stmt = db()->prepare('SELECT * FROM routebox_servers WHERE id = ?'); $stmt->execute([(int) ($_POST['id'] ?? 0)]); $server = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$server) throw new RuntimeException('RouteBox server not found.');
            validateRouteBoxForAdmin((string) $server['base_url'], dec((string) $server['user_enc']), dec((string) $server['pass_enc']), (bool) $server['verify_tls']);
            $_SESSION['flash'] = '🟢 Full RouteBox integration test passed for «' . $server['name'] . '».'; log_event('info', 'Full RouteBox integration test passed: ' . $server['name']);
        } elseif ($action === 'toggle_server') {
            db()->prepare('UPDATE routebox_servers SET enabled = 1 - enabled WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]); $_SESSION['flash'] = '🔄 RouteBox status updated.';
        } elseif ($action === 'save_settings') {
            $trialHours = max(1, min(720, (int) ($_POST['trial_hours'] ?? 12)));
            db()->prepare("INSERT INTO settings(key,value) VALUES('trial_hours',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([(string) $trialHours]);
            $token = trim((string) ($_POST['telegram_token'] ?? ''));
            if ($token !== '') {
                telegramCheckAndPrepare($token); db()->prepare("INSERT INTO settings(key,value) VALUES('telegram_token',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([enc($token)]);
                log_event('info', 'Telegram bot token validated and saved.'); $_SESSION['flash'] = '✅ Settings saved and Telegram Bot validated.';
            } else $_SESSION['flash'] = '✅ Trial duration saved.';
        } else throw new RuntimeException('Invalid request.');
    } catch (Throwable $error) { $_SESSION['flash'] = '❌ Error: ' . $error->getMessage(); log_event('error', 'Admin action failed: ' . $error->getMessage()); }
    header('Location: /'); exit;
}

$servers = db()->query('SELECT * FROM routebox_servers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$users = db()->query('SELECT * FROM telegram_users ORDER BY last_seen DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$trial = (int) (db()->query("SELECT value FROM settings WHERE key='trial_hours'")->fetchColumn() ?: 12);
$hasToken = (bool) db()->query("SELECT value FROM settings WHERE key='telegram_token'")->fetchColumn();
$flash = (string) ($_SESSION['flash'] ?? ''); unset($_SESSION['flash']);
$version = is_file(__DIR__ . '/../VERSION') ? trim((string) file_get_contents(__DIR__ . '/../VERSION')) : '0.1.0-beta.1';
?><!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RouteBox Telegram Bot</title>
<style>
:root{color-scheme:dark}body{margin:0;background:#070b14;color:#e5e7eb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif}.wrap{max-width:1120px;margin:auto;padding:28px 18px 60px}.hero{display:flex;justify-content:space-between;gap:20px;align-items:center;flex-wrap:wrap}.hero h1{margin:0 0 8px;font-size:30px}.subtitle{color:#94a3b8}.badge{display:inline-block;background:#f59e0b;color:#111827;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:800}.card{background:linear-gradient(145deg,#111827,#0d1422);border:1px solid #263247;border-radius:20px;padding:22px;margin:16px 0;box-shadow:0 10px 35px rgba(0,0,0,.18)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}label{display:block;color:#cbd5e1;font-size:14px;margin-bottom:4px}input{box-sizing:border-box;width:100%;padding:12px 13px;margin:6px 0 12px;border-radius:11px;border:1px solid #344156;background:#09111f;color:#fff;outline:none}input[type=number]{direction:ltr;text-align:left}input[type=checkbox]{width:auto} .ltr{direction:ltr!important;text-align:left!important}button{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:10px 15px;font-weight:700;cursor:pointer}button:hover{filter:brightness(1.08)}.secondary{background:#334155}.row{display:flex;gap:14px;justify-content:space-between;align-items:center;border-top:1px solid #243044;padding:14px 0;flex-wrap:wrap}.muted{color:#94a3b8;font-size:13px}.flash{background:#172554;border:1px solid #1d4ed8;padding:13px 15px;border-radius:12px;margin:16px 0}.ok{color:#34d399}.off{color:#fb7185}.feature{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.feature div{background:#0b1220;border:1px solid #202c40;border-radius:14px;padding:13px}.feature b{display:block;margin-bottom:4px}a{color:#93c5fd;text-decoration:none}.inline{display:inline}.top-actions{display:flex;gap:10px;align-items:center}.small{font-size:12px;color:#64748b}h1,h2,h3,p{text-align:left}.row>div:last-child{display:flex;gap:8px;align-items:center}
</style>
</head>
<body>
<div class="wrap">
<div class="hero"><div><h1>🚀 RouteBox Telegram Bot <span class="badge">BETA</span></h1><div class="subtitle">🤖 Bot management · 🌐 Multi-RouteBox · 🔐 AmneziaWG</div></div><div class="top-actions"><span class="small">v<?=h($version)?></span><form method="post" action="/logout.php" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><button class="secondary" type="submit">Sign out</button></form></div></div>
<?php if ($flash !== ''): ?><div class="flash"><?=h($flash)?></div><?php endif; ?>
<div class="card"><h2>⚡ Quick Status</h2><div class="feature"><div><b>🤖 Telegram</b><span class="<?= $hasToken ? 'ok' : 'off' ?>"><?= $hasToken ? '● Configured' : '● Not configured' ?></span></div><div><b>🌐 RouteBox</b><span><?=count($servers)?> server(s)</span></div><div><b>👥 Users</b><span><?=count($users)?> recent user(s)</span></div><div><b>🎁 Trial</b><span><?=h((string)$trial)?> hours</span></div></div></div>
<div class="card"><h2>⚙️ Bot Settings</h2><form method="post"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="grid"><div><label>🤖 Telegram Bot Token</label><input class="ltr" name="telegram_token" type="password" autocomplete="new-password" placeholder="<?= $hasToken ? 'Existing token is kept; enter a new one to replace it' : '123456:ABC...' ?>"></div><div><label>🎁 Trial duration (hours)</label><input type="number" name="trial_hours" min="1" max="720" value="<?=h((string)$trial)?>" required></div></div><button>💾 Save & Validate</button></form><p class="muted">The token is validated with Telegram before storage. Any existing webhook is removed so the polling worker does not conflict with it.</p></div>
<div class="card"><h2>🌐 RouteBox Servers</h2><form method="post"><input type="hidden" name="action" value="add_server"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="grid"><div><label>Name</label><input name="name" placeholder="Turkey #1" maxlength="80" required></div><div><label>Panel URL</label><input class="ltr" name="url" placeholder="https://panel.example.com:8443" required></div><div><label>Username <span class="small">(leave empty if auth is disabled)</span></label><input class="ltr" name="username" autocomplete="off" placeholder="admin"></div><div><label>Password <span class="small">(leave empty if auth is disabled)</span></label><input class="ltr" type="password" name="password" autocomplete="new-password"></div></div><label><input type="checkbox" name="verify_tls" checked> 🔒 Verify TLS certificate</label><p class="muted">Saving a server performs the same read-only API checks and temporary AWG create → config → delete smoke test used by the installer. The server is saved only after those tests pass.</p><button>➕ Add & Test RouteBox</button></form>
<?php foreach ($servers as $server): ?><div class="row"><div><b><?=h((string)$server['name'])?></b><div class="muted ltr"><?=h((string)$server['base_url'])?></div></div><div class="<?= $server['enabled'] ? 'ok' : 'off' ?>"><?= $server['enabled'] ? '● Enabled' : '● Disabled' ?></div><div><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="<?=$server['id']?>"><button class="secondary">🧪 Full integration test</button></form><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_server"><input type="hidden" name="id" value="<?=$server['id']?>"><button class="secondary">⏯️ <?= $server['enabled'] ? 'Disable' : 'Enable' ?></button></form></div></div><?php endforeach; ?><?php if (!$servers): ?><p class="muted">No RouteBox servers configured yet.</p><?php endif; ?></div>
<div class="card"><h2>👥 Telegram Users</h2><?php foreach ($users as $user): ?><div class="row"><span><?=h((string)($user['first_name'] ?: $user['username'] ?: $user['telegram_id']))?></span><span class="muted">@<?=h((string)($user['username'] ?: '—'))?> · <?=date('Y-m-d H:i',(int)$user['last_seen'])?></span></div><?php endforeach; ?><?php if (!$users): ?><p class="muted">No users yet.</p><?php endif; ?></div>
<p class="small">🧪 RouteBox Telegram Bot <?=h($version)?> · Beta · Use HTTPS and restrict admin-panel access before public deployment.</p>
</div></body></html>
