<?php
declare(strict_types=1);

if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'index.php') {
    return;
}

require_once __DIR__ . '/RouteBoxClient.php';

function rbt_setting(string $key, string $default = ''): string
{
    $q = db()->prepare('SELECT value FROM settings WHERE key=?');
    $q->execute([$key]);
    $value = $q->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function rbt_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')
        ->execute([$key, $value]);
}

function rbt_backups(): array
{
    $dir = '/var/backups/routebox-telegram-bot';
    $out = [];

    foreach (glob($dir . '/routebox-telegram-bot-v*.tar.gz') ?: [] as $file) {
        $name = basename($file);
        if (preg_match('/^routebox-telegram-bot-v([0-9A-Za-z.-]+)(-pre-update)?-(\d{8})-(\d{6})\.tar\.gz$/', $name, $m)) {
            $out[] = [
                'name' => $name,
                'version' => $m[1],
                'ts' => $m[3] . $m[4],
                'size' => (int)(filesize($file) ?: 0),
            ];
        }
    }

    usort($out, static fn(array $a, array $b): int => strcmp($b['ts'], $a['ts']));
    return array_slice($out, 0, 10);
}

function rbt_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i ? 1 : 0) . ' ' . $units[$i];
}

function rbt_go(string $section = 'tools'): never
{
    header('Location: /?section=' . rawurlencode($section));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['admin']) && isset($_POST['extra_action'])) {
    verify_csrf();

    try {
        $action = (string)$_POST['extra_action'];

        if ($action === 'guide') {
            foreach (['guide_fa', 'guide_en', 'android_url', 'ios_url', 'windows_url', 'macos_url'] as $key) {
                rbt_set($key, trim((string)($_POST[$key] ?? '')));
            }
            $_SESSION['flash'] = '✓ Guide settings saved.';
            rbt_go();
        }

        if ($action === 'server') {
            $id = (int)$_POST['server_id'];
            $q = db()->prepare('SELECT * FROM routebox_servers WHERE id=?');
            $q->execute([$id]);
            $server = $q->fetch(PDO::FETCH_ASSOC);
            if (!$server) {
                throw new RuntimeException('RouteBox not found.');
            }

            $name = trim((string)$_POST['name']);
            $url = rtrim(trim((string)$_POST['url']), '/');
            $user = trim((string)$_POST['username']);
            $pass = (string)$_POST['password'];
            $verify = isset($_POST['verify_tls']) ? 1 : 0;
            if ($pass === '') {
                $pass = dec($server['pass_enc']);
            }

            $client = new RouteBoxClient($url, $user, $pass, (bool)$verify);
            $client->validateIntegration();
            $client->smokeTest('rbt-admin-test');

            db()->prepare('UPDATE routebox_servers SET name=?,base_url=?,user_enc=?,pass_enc=?,verify_tls=? WHERE id=?')
                ->execute([$name, $url, enc($user), enc($pass), $verify, $id]);

            $_SESSION['flash'] = '✓ RouteBox updated and tested.';
            rbt_go('servers');
        }

        if ($action === 'test') {
            $q = db()->prepare('SELECT * FROM routebox_servers WHERE id=?');
            $q->execute([(int)$_POST['server_id']]);
            $server = $q->fetch(PDO::FETCH_ASSOC);
            if (!$server) {
                throw new RuntimeException('RouteBox not found.');
            }

            $client = new RouteBoxClient(
                $server['base_url'],
                dec($server['user_enc']),
                dec($server['pass_enc']),
                (bool)$server['verify_tls']
            );
            $client->validateIntegration();
            $client->smokeTest('rbt-admin-test');
            $_SESSION['flash'] = '✓ RouteBox + AWG test passed.';
            rbt_go('servers');
        }

        if ($action === 'backup') {
            $output = shell_exec('sudo -n /usr/local/sbin/routebox-telegram-bot-backup 2>&1');
            if (!preg_match('/\/var\/backups\/routebox-telegram-bot\/routebox-telegram-bot-v[^\s]+\.tar\.gz/', $output ?? '')) {
                throw new RuntimeException(trim((string)$output) ?: 'Backup failed.');
            }
            $_SESSION['flash'] = '✓ Backup created.';
            rbt_go();
        }

        if ($action === 'delete') {
            $name = basename((string)$_POST['backup']);
            if (!preg_match('/^routebox-telegram-bot-v[0-9A-Za-z.-]+(?:-pre-update)?-\d{8}-\d{6}\.tar\.gz$/', $name)) {
                throw new RuntimeException('Invalid backup.');
            }
            shell_exec('sudo -n /usr/local/sbin/routebox-telegram-bot-backup delete ' . escapeshellarg($name) . ' 2>&1');
            $_SESSION['flash'] = '✓ Backup deleted.';
            rbt_go();
        }

        if ($action === 'restore') {
            $name = basename((string)$_POST['backup']);
            if (!preg_match('/^routebox-telegram-bot-v[0-9A-Za-z.-]+(?:-pre-update)?-\d{8}-\d{6}\.tar\.gz$/', $name)) {
                throw new RuntimeException('Invalid backup.');
            }
            shell_exec('nohup sudo -n /usr/local/sbin/routebox-telegram-bot-restore ' . escapeshellarg($name) . ' >/tmp/routebox-restore.log 2>&1 &');
            $_SESSION['flash'] = '✓ Restore started; panel may disconnect.';
            rbt_go();
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = '❌ ' . $e->getMessage();
        rbt_go();
    }
}

ob_start(static function (string $html): string {
    $section = (string)($_GET['section'] ?? '');
    $csrf = h(csrf_token());
    $servers = db()->query('SELECT * FROM routebox_servers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

    $rows = '';
    foreach (rbt_backups() as $backup) {
        $name = h($backup['name']);
        $ts = $backup['ts'];
        $date = h(
            substr($ts, 0, 4) . '-' . substr($ts, 4, 2) . '-' . substr($ts, 6, 2) . ' ' .
            substr($ts, 8, 2) . ':' . substr($ts, 10, 2) . ':' . substr($ts, 12, 2)
        );

        $rows .= '<tr>'
            . '<td><code>' . $name . '</code></td>'
            . '<td>' . h($backup['version']) . '</td>'
            . '<td>' . $date . '</td>'
            . '<td>' . rbt_bytes($backup['size']) . '</td>'
            . '<td class="rbt-backup-actions">'
            . '<a class="btn btn-secondary" href="/backup-download.php?name=' . rawurlencode($backup['name']) . '">Download</a> '
            . '<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="extra_action" value="restore"><input type="hidden" name="backup" value="' . $name . '"><button class="btn btn-secondary" type="submit" onclick="return confirm(\'Restore this backup?\')">Restore</button></form> '
            . '<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="extra_action" value="delete"><input type="hidden" name="backup" value="' . $name . '"><button class="btn btn-secondary" type="submit" onclick="return confirm(\'Delete this backup?\')">Delete</button></form>'
            . '</td></tr>';
    }
    $rows = $rows ?: '<tr><td colspan="5">No backups found.</td></tr>';

    $guide = '<section class="rbt-extra-page card">'
        . '<div class="section-head"><div class="section-title"><div class="section-icon">?</div><div><h2>Usage guide</h2><p>Editable links and Persian/English guide.</p></div></div></div>'
        . '<form method="post"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="extra_action" value="guide"><div class="grid">';

    foreach ([
        ['android_url', 'Android'],
        ['ios_url', 'iPhone / iPad'],
        ['windows_url', 'Windows'],
        ['macos_url', 'macOS'],
    ] as [$key, $label]) {
        $guide .= '<div class="field"><label>' . $label . ' URL</label><input name="' . $key . '" value="' . h(rbt_setting($key)) . '"></div>';
    }

    $guide .= '<div class="field"><label>راهنمای فارسی</label><textarea name="guide_fa" rows="7">' . h(rbt_setting('guide_fa')) . '</textarea></div>'
        . '<div class="field"><label>English guide</label><textarea name="guide_en" rows="7">' . h(rbt_setting('guide_en')) . '</textarea></div>'
        . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">Save guide</button></div></form></section>';

    $serverSettings = '<section class="rbt-extra-page card">'
        . '<div class="section-head"><div class="section-title"><div class="section-icon">⚙</div><div><h2>RouteBox settings</h2><p>Edit Name, URL, Username, Password and Verify TLS. Save &amp; Test also runs the connection + AWG smoke test.</p></div></div></div>';

    foreach ($servers as $server) {
        $id = (int)$server['id'];
        $serverSettings .= '<form method="post" class="rbt-server-form">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="extra_action" value="server">'
            . '<input type="hidden" name="server_id" value="' . $id . '">'
            . '<div class="grid">'
            . '<div class="field"><label>Name</label><input name="name" value="' . h($server['name']) . '" required></div>'
            . '<div class="field"><label>URL</label><input name="url" value="' . h($server['base_url']) . '" required></div>'
            . '<div class="field"><label>Username</label><input name="username" value="' . h(dec($server['user_enc'])) . '" required></div>'
            . '<div class="field"><label>Password</label><input name="password" type="password" placeholder="unchanged if empty" autocomplete="new-password"></div>'
            . '</div>'
            . '<label class="switch"><input type="checkbox" name="verify_tls" ' . ((bool)$server['verify_tls'] ? 'checked' : '') . '><span>Verify TLS</span></label>'
            . '<div class="form-actions rbt-server-actions"><button class="btn btn-primary" type="submit">Save &amp; Test</button></div>'
            . '</form>'
            . '<form method="post" class="rbt-test-form">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="extra_action" value="test">'
            . '<input type="hidden" name="server_id" value="' . $id . '">'
            . '<div class="form-actions"><button class="btn btn-secondary" type="submit">Test connection</button></div>'
            . '</form>';
    }

    $serverSettings .= '</section>';

    $tools = '<section class="rbt-extra-page card">'
        . '<div class="section-head"><div class="section-title"><div class="section-icon">▣</div><div><h2>Backup &amp; Restore</h2><p>Manual backups, pre-update snapshots, restore, download and delete.</p></div></div>'
        . '<form method="post"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="extra_action" value="backup"><button class="btn btn-primary" type="submit">Create backup</button></form></div>'
        . '<div style="overflow:auto"><table style="width:100%"><thead><tr><th>File</th><th>Version</th><th>Date / time</th><th>Size</th><th>Actions</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<p class="help">Only the newest 10 backups are retained. Backup files live outside the public web root.</p></section>'
        . $guide
        . $serverSettings;

    $toolsIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.2 5.2L4 17v3h3l5.5-5.5a4 4 0 0 0 5.2-5.2l-2.1 2.1-2.1-2.1 2.1-2.1Z"/></svg>';
    $toolsNav = '<a href="/?section=tools" class="' . ($section === 'tools' ? 'active' : '') . '" aria-current="' . ($section === 'tools' ? 'page' : 'false') . '">' . $toolsIcon . '<span>Tools</span></a>';

    $done = false;
    if (preg_match('/(<a[^>]+href=["\']\/?\?section=updates["\'][^>]*>.*?<\/a>)/is', $html, $match, PREG_OFFSET_CAPTURE)) {
        $position = $match[0][1] + strlen($match[0][0]);
        $html = substr($html, 0, $position) . $toolsNav . substr($html, $position);
        $done = true;
    }
    if (!$done) {
        $html = preg_replace('/<\/nav>/i', $toolsNav . '</nav>', $html, 1) ?? $html;
    }

    if ($section === 'tools') {
        $page = '<style>'
            . 'body.rbt-extra-mode .main>*{display:none!important}'
            . 'body.rbt-extra-mode .main>.rbt-extra-page{display:block!important}'
            . '.rbt-server-form{margin-top:15px;padding:18px;border:1px solid var(--line);border-radius:14px}'
            . '.rbt-server-actions{margin-top:14px}'
            . '.rbt-test-form{margin-top:0}'
            . '.rbt-test-form .form-actions{margin-top:8px}'
            . '.rbt-backup-actions{white-space:nowrap}'
            . '@media(max-width:760px){.rbt-backup-actions{white-space:normal}.rbt-backup-actions .btn{margin:2px 0}}'
            . '</style>' . $tools;

        $html = preg_replace('/<main[^>]*>.*?<\/main>/is', '<main class="main rbt-extra-main">' . $page . '</main>', $html, 1) ?? $html;
        $html = preg_replace('/<body([^>]*)>/i', '<body$1 class="rbt-extra-mode">', $html, 1) ?? $html;
    }

    return $html;
});
