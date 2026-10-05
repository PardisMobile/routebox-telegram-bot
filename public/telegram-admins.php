<?php

declare(strict_types=1);

/* Backend action endpoint retained for compatibility; the Admin UI lives at ?section=bot-admin. */
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
    header('Location: /?section=bot-admin');
    exit;
}

header('Location: /?section=bot-admin');
exit;
