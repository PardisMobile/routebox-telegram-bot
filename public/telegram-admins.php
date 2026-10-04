<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();

function telegram_admins_schema(): void
{
    $db = db();
    $db->exec("CREATE TABLE IF NOT EXISTS telegram_bot_admins (id INTEGER PRIMARY KEY AUTOINCREMENT, telegram_id TEXT UNIQUE NOT NULL, role TEXT NOT NULL DEFAULT 'admin', enabled INTEGER NOT NULL DEFAULT 1, created_by TEXT, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
}

telegram_admins_schema();
$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $telegramId = trim((string)($_POST['telegram_id'] ?? ''));
            $role = trim((string)($_POST['role'] ?? 'admin'));
            if (!preg_match('/^[0-9]{5,20}$/', $telegramId)) throw new RuntimeException('Telegram Numeric ID is invalid.');
            if (!in_array($role, ['owner', 'admin', 'support', 'finance', 'operator'], true)) $role = 'admin';
            $now = time();
            if ($id > 0) {
                $st = $db->prepare('UPDATE telegram_bot_admins SET telegram_id=?,role=?,enabled=?,updated_at=? WHERE id=?');
                $st->execute([$telegramId, $role, isset($_POST['enabled']) ? 1 : 0, $now, $id]);
            } else {
                $st = $db->prepare('INSERT INTO telegram_bot_admins(telegram_id,role,enabled,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?)');
                $st->execute([$telegramId, $role, isset($_POST['enabled']) ? 1 : 0, (string)($_SESSION['admin'] ?? 'web-admin'), $now, $now]);
            }
            $_SESSION['flash'] = '✓ Telegram Bot Admin saved.';
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $db->prepare('SELECT enabled FROM telegram_bot_admins WHERE id=?');
            $st->execute([$id]);
            $current = $st->fetchColumn();
            if ($current === false) throw new RuntimeException('Admin not found.');
            if ((int)$current === 1 && (int)$db->query('SELECT COUNT(*) FROM telegram_bot_admins WHERE enabled=1')->fetchColumn() <= 1) throw new RuntimeException('At least one Telegram Bot Admin must remain enabled.');
            $db->prepare('UPDATE telegram_bot_admins SET enabled=?,updated_at=? WHERE id=?')->execute([(int)$current ? 0 : 1, time(), $id]);
            $_SESSION['flash'] = '✓ Admin status updated.';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $db->prepare('SELECT enabled FROM telegram_bot_admins WHERE id=?');
            $st->execute([$id]);
            $current = $st->fetchColumn();
            if ($current === false) throw new RuntimeException('Admin not found.');
            if ((int)$current === 1 && (int)$db->query('SELECT COUNT(*) FROM telegram_bot_admins WHERE enabled=1')->fetchColumn() <= 1) throw new RuntimeException('The last enabled Telegram Bot Admin cannot be deleted. Disable or add another Admin first.');
            $db->prepare('DELETE FROM telegram_bot_admins WHERE id=?')->execute([$id]);
            $_SESSION['flash'] = '✓ Admin removed.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = '❌ ' . $e->getMessage();
    }
    header('Location: /telegram-admins.php');
    exit;
}

$rows = $db->query('SELECT * FROM telegram_bot_admins ORDER BY enabled DESC,id ASC')->fetchAll(PDO::FETCH_ASSOC);
$csrf = h(csrf_token());
$flash = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ATD Panel — Telegram Bot Admins</title>
<style>
:root{color-scheme:light dark;--bg:#0f172a;--card:#111c31;--line:#263653;--text:#eef4ff;--muted:#9eabc1;--accent:#4f7cff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:1050px;margin:0 auto;padding:28px 16px 50px}.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px;margin-bottom:16px}.head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px}.eyebrow{color:var(--accent);font-weight:800;font-size:11px;letter-spacing:.08em}.head h1{margin:5px 0;font-size:26px}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:2fr 1fr auto auto;gap:10px;align-items:end}.field{display:flex;flex-direction:column;gap:7px}.field label{color:var(--muted);font-weight:700;font-size:12px}.field input,.field select{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:11px;background:transparent;color:inherit;font:inherit}.check{display:flex;gap:7px;align-items:center;height:42px;color:var(--muted);white-space:nowrap}.btn{border:1px solid var(--line);border-radius:10px;padding:10px 13px;background:transparent;color:inherit;cursor:pointer;font-weight:750}.primary{background:var(--accent);border-color:var(--accent);color:#fff}.danger{color:#ff8b8b}.table{overflow:auto;border:1px solid var(--line);border-radius:14px}.table table{width:100%;border-collapse:collapse}.table th,.table td{padding:12px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}.table th{color:var(--muted);font-size:11px}.table tr:last-child td{border-bottom:0}.actions{display:flex;gap:7px;flex-wrap:wrap}.flash{padding:11px 13px;border:1px solid var(--line);border-radius:12px;margin-bottom:16px}.back{display:inline-block;margin-bottom:14px;color:var(--muted);text-decoration:none}@media(max-width:720px){.grid{grid-template-columns:1fr 1fr}.grid .wide{grid-column:1/-1}.head{flex-direction:column}.table th,.table td{font-size:12px}}
</style>
</head>
<body><main class="wrap">
<a class="back" href="/?section=bot">← ATD Panel / Telegram Bot</a>
<div class="card"><div class="head"><div><div class="eyebrow">ATD PANEL</div><h1>Telegram Bot Admins</h1><div class="muted">Independent Telegram Numeric ID authorization. This is separate from IBSng owner/owner_name.</div></div></div>
<?php if ($flash !== ''): ?><div class="flash"><?=h($flash)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="save"><div class="grid">
<div class="field wide"><label>Telegram Numeric ID</label><input name="telegram_id" inputmode="numeric" pattern="[0-9]{5,20}" required placeholder="123456789"></div>
<div class="field"><label>Role</label><select name="role"><option value="owner">Owner</option><option value="admin" selected>Admin</option><option value="support">Support</option><option value="finance">Finance</option><option value="operator">Operator</option></select></div>
<label class="check"><input type="checkbox" name="enabled" checked> Enabled</label>
<button class="btn primary" type="submit">Add Admin</button>
</div></form></div>
<div class="card"><div class="head"><div><h2>Configured Admins</h2><div class="muted">Disable an Admin without changing historical audit records.</div></div></div>
<div class="table"><table><thead><tr><th>Telegram ID</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><code><?=h((string)$row['telegram_id'])?></code></td><td><?=h(ucfirst((string)$row['role']))?></td><td><?=((int)$row['enabled']===1)?'Enabled':'Disabled'?></td><td><?=date('Y-m-d H:i',(int)$row['created_at'])?></td><td><div class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=((int)$row['id'])?>"><button class="btn" type="submit"><?=((int)$row['enabled']===1)?'Disable':'Enable'?></button></form><form method="post" onsubmit="return confirm('Remove this Telegram Bot Admin?')"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=((int)$row['id'])?>"><button class="btn danger" type="submit">Remove</button></form></div></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5">No Telegram Bot Admins configured yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
</main></body></html>
