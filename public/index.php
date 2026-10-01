<?php
declare(strict_types=1);

/*
 * RouteBox Admin front controller.
 * IBSng is an integration module, not a standalone web page.
 * Its UI is rendered only inside the existing index.core.php shell.
 */

$section = (string)($_GET['section'] ?? 'dashboard');

if ($section === 'ibsng') {
    require __DIR__ . '/../src/bootstrap.php';
    require_admin();
    require_once __DIR__ . '/../src/Integrations/IBSng/IBSngModule.php';

    // Keep language selection identical to the main admin panel.
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['fa', 'en'], true)) {
        $_SESSION['panel_lang'] = $_GET['lang'];
    }
    $lang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';

    $ibsng = \RouteBox\Integrations\IBSng\IBSngModule::admin(db());

    // IBSng POST actions are handled by the integration controller. The
    // controller itself emits no HTML; the redirect returns to this shell.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $ibsng->handle($_POST, 'POST');
        header('Location: /?section=ibsng');
        exit;
    }

    $data = $ibsng->viewData();
    $csrf = csrf_token();

    // Ask the existing RouteBox Admin renderer for its normal shell.
    // Temporarily render the dashboard only; the dashboard body is replaced
    // below with the IBSng module content while preserving sidebar/theme/etc.
    $originalGet = $_GET;
    $_GET['section'] = 'dashboard';
    ob_start();
    require __DIR__ . '/index.core.php';
    $html = (string)ob_get_clean();
    $_GET = $originalGet;

    $T = $lang === 'fa' ? [
        'title' => 'مدیریت IBSng',
        'sub' => 'اتصال و مدیریت IBSng A1.24 از طریق Web Panel',
        'add_title' => '➕ افزودن سرور IBSng',
        'add_hint' => 'قبل از ذخیره، اتصال Web Panel تست می‌شود و گروه‌ها همان لحظه Sync می‌شوند.',
        'name' => 'نام سرور',
        'host' => 'IP / Host',
        'port' => 'پورت Web Panel',
        'isp' => 'ISP Name',
        'user' => 'Admin Username',
        'pass' => 'Admin Password',
        'add' => 'تست اتصال و ذخیره',
        'servers' => '🖥 سرورهای IBSng',
        'servers_hint' => 'نام، Host، پورت و ISP قابل ویرایش هستند. برای امنیت، Username و Password ذخیره‌شده هرگز نمایش داده نمی‌شوند.',
        'none' => 'هنوز هیچ سرور IBSng اضافه نشده است.',
        'ok' => '● اتصال موفق',
        'err' => '● خطای اتصال',
        'untested' => '● تست نشده',
        'credentials' => 'دسترسی‌ها',
        'stored' => 'Credentialها رمزنگاری‌شده نگهداری می‌شوند.',
        'groups' => 'گروه‌های Sync شده',
        'no_groups' => 'گروهی Sync نشده است.',
        'test' => 'تست اتصال',
        'sync' => 'Sync گروه‌ها',
        'edit' => '✏️ ویرایش اطلاعات سرور',
        'new_user' => 'Admin Username جدید',
        'new_pass' => 'Admin Password جدید',
        'unchanged' => 'خالی = بدون تغییر',
        'username_hidden' => 'Username فعلی نمایش داده نمی‌شود.',
        'password_hidden' => 'رمز فعلی هرگز نمایش داده نمی‌شود.',
        'save' => 'تست و ذخیره تغییرات',
        'back' => '← بازگشت به داشبورد',
    ] : [
        'title' => 'IBSng Management',
        'sub' => 'Connect and manage IBSng A1.24 through the Web Panel',
        'add_title' => '➕ Add IBSng Server',
        'add_hint' => 'The Web Panel connection is tested before saving and groups are synced immediately.',
        'name' => 'Server name',
        'host' => 'IP / Host',
        'port' => 'Web Panel port',
        'isp' => 'ISP Name',
        'user' => 'Admin Username',
        'pass' => 'Admin Password',
        'add' => 'Test connection & save',
        'servers' => '🖥 IBSng Servers',
        'servers_hint' => 'Server name, Host, port and ISP are editable. Stored username and password are never displayed.',
        'none' => 'No IBSng servers have been added yet.',
        'ok' => '● Connected',
        'err' => '● Connection error',
        'untested' => '● Not tested',
        'credentials' => 'Credentials',
        'stored' => 'Credentials are stored encrypted.',
        'groups' => 'Synced groups',
        'no_groups' => 'No groups synced.',
        'test' => 'Test connection',
        'sync' => 'Sync groups',
        'edit' => '✏️ Edit server information',
        'new_user' => 'New Admin Username',
        'new_pass' => 'New Admin Password',
        'unchanged' => 'blank = unchanged',
        'username_hidden' => 'Current username is never displayed.',
        'password_hidden' => 'Current password is never displayed.',
        'save' => 'Test & save changes',
        'back' => '← Back to Dashboard',
    ];

    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dir = $lang === 'fa' ? 'rtl' : 'ltr';

    $flash = '';
    if (!empty($data['flash'])) {
        $flash = '<div class="flash ' . (!empty($data['error']) ? 'err' : 'ok') . '">' . $e((string)$data['flash']) . '</div>';
    }

    $cards = '';
    foreach ($data['servers'] as $s) {
        $id = (int)$s['id'];
        $groups = is_array($s['groups'] ?? null) ? $s['groups'] : [];
        $status = !empty($s['last_test_at']) && empty($s['last_error'])
            ? '<span class="status"><span class="dot" style="background:var(--green)"></span>' . $T['ok'] . '</span>'
            : (!empty($s['last_error'])
                ? '<span class="status"><span class="dot" style="background:var(--red)"></span>' . $T['err'] . '</span>'
                : '<span class="status"><span class="dot" style="background:var(--muted)"></span>' . $T['untested'] . '</span>');

        $groupHtml = '';
        if ($groups) {
            foreach ($groups as $g) {
                $groupHtml .= '<span class="tag">' . $e((string)$g['group_name']) . '</span>';
            }
        } else {
            $groupHtml = '<div class="empty">' . $T['no_groups'] . '</div>';
        }

        $lastError = !empty($s['last_error'])
            ? '<div class="flash err" style="margin-top:15px;margin-bottom:0">' . $e((string)$s['last_error']) . '</div>'
            : '';

        $cards .= '<article class="server">'
            . '<div class="server-head"><div><div class="server-title">' . $e((string)$s['name']) . ' ' . $status . '</div>'
            . '<div class="server-url">' . $e((string)$s['host']) . ' : ' . $e((string)$s['port']) . ' · ISP: ' . $e((string)$s['isp_name']) . '</div></div></div>'
            . '<div class="server-grid">'
            . '<div><div class="help">' . $T['credentials'] . '</div><div class="security-note">Admin Username: <strong>••••••••</strong><br>Password: <strong>••••••••</strong><br>' . $T['stored'] . '</div></div>'
            . '<div><div class="help">' . $T['groups'] . ' (' . count($groups) . ')</div><div class="group-list">' . $groupHtml . '</div></div>'
            . '</div>' . $lastError
            . '<div class="btnrow">'
            . '<form method="post"><input type="hidden" name="csrf_token" value="' . $e($csrf) . '"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-secondary" type="submit">↻ ' . $T['test'] . '</button></form>'
            . '<form method="post"><input type="hidden" name="csrf_token" value="' . $e($csrf) . '"><input type="hidden" name="action" value="sync_groups"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-secondary" type="submit">↻ ' . $T['sync'] . '</button></form>'
            . '</div>'
            . '<details class="ibsng-edit"><summary>' . $T['edit'] . '</summary>'
            . '<form method="post"><input type="hidden" name="csrf_token" value="' . $e($csrf) . '"><input type="hidden" name="action" value="update_server"><input type="hidden" name="id" value="' . $id . '"><div class="grid">'
            . '<div class="field"><label>' . $T['name'] . '</label><input name="name" required maxlength="80" value="' . $e((string)$s['name']) . '"></div>'
            . '<div class="field"><label>' . $T['host'] . '</label><input name="host" required value="' . $e((string)$s['host']) . '"></div>'
            . '<div class="field"><label>' . $T['port'] . '</label><select name="port"><option value="80"' . ((int)$s['port'] === 80 ? ' selected' : '') . '>80 — HTTP</option><option value="443"' . ((int)$s['port'] === 443 ? ' selected' : '') . '>443 — HTTPS</option></select></div>'
            . '<div class="field"><label>' . $T['isp'] . '</label><input name="isp_name" value="' . $e((string)$s['isp_name']) . '"></div>'
            . '<div class="field"><label>' . $T['new_user'] . '</label><input name="username" autocomplete="off" placeholder="' . $e($T['unchanged']) . '"><div class="help">' . $T['username_hidden'] . '</div></div>'
            . '<div class="field"><label>' . $T['new_pass'] . '</label><input type="password" name="password" autocomplete="new-password" placeholder="' . $e($T['unchanged']) . '"><div class="help">' . $T['password_hidden'] . '</div></div>'
            . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">' . $T['save'] . '</button></div></form></details>'
            . '</article>';
    }

    if ($cards === '') {
        $cards = '<div class="empty">' . $T['none'] . '</div>';
    }

    $ibsngBody = '<header class="topbar">'
        . '<div><div class="eyebrow">ROUTEBOX SERVICE MODULE</div><h1>' . $T['title'] . '</h1><p>' . $T['sub'] . '</p></div>'
        . '<div class="top-actions"><span class="chip keep"><span class="dot"></span>' . ($lang === 'fa' ? 'فعال' : 'Active') . '</span><span class="chip keep">IBSng A1.24</span><button class="icon-button" id="themeBtn" type="button" aria-label="' . ($lang === 'fa' ? 'تغییر تم' : 'Toggle theme') . '"><svg viewBox="0 0 24 24"><path d="M12 3v2m0 14v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M3 12h2m14 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/><circle cx="12" cy="12" r="4"/></svg></button></div>'
        . '</header>'
        . $flash
        . '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>' . $T['add_title'] . '</h2><p>' . $T['add_hint'] . '</p></div></div><a class="btn btn-secondary" href="/">' . $T['back'] . '</a></div>'
        . '<form method="post"><input type="hidden" name="csrf_token" value="' . $e($csrf) . '"><input type="hidden" name="action" value="add_server"><div class="grid">'
        . '<div class="field"><label>' . $T['name'] . '</label><input name="name" required maxlength="80" placeholder="IBSng Main"></div>'
        . '<div class="field"><label>' . $T['host'] . '</label><input name="host" required placeholder="185.18.x.x"></div>'
        . '<div class="field"><label>' . $T['port'] . '</label><select name="port"><option value="80">80 — HTTP</option><option value="443">443 — HTTPS</option></select></div>'
        . '<div class="field"><label>' . $T['isp'] . '</label><input name="isp_name" value="Main" required></div>'
        . '<div class="field"><label>' . $T['user'] . '</label><input name="username" required autocomplete="off"></div>'
        . '<div class="field"><label>' . $T['pass'] . '</label><input type="password" name="password" required autocomplete="new-password"></div>'
        . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">' . $T['add'] . '</button></div></form></section>'
        . '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">▤</div><div><h2>' . $T['servers'] . '</h2><p>' . $T['servers_hint'] . '</p></div></div></div><div class="server-list">' . $cards . '</div></section>';

    // Replace only the dashboard content inside the existing shell. Sidebar,
    // global CSS, theme switcher, language selector and footer remain exactly
    // the same as every other RouteBox section.
    $html = preg_replace('~<header class="topbar">.*?(?=<div class="footer">)~s', $ibsngBody . "\n", $html, 1) ?? $html;

    // Add IBSng to the existing sidebar and mark it active. There is no second
    // sidebar and no standalone IBSng page anymore.
    $ibsngNav = '<a class="active" href="/?section=ibsng" aria-current="page"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg></span><span>IBSng</span></a>';
    if (strpos($html, 'href="/?section=ibsng"') === false) {
        $html = preg_replace('~(</nav>)~', $ibsngNav . '$1', $html, 1) ?? $html;
    }

    echo $html;
    exit;
}

// All normal RouteBox sections use the original admin renderer.
ob_start();
require __DIR__ . '/index.core.php';
$html = (string)ob_get_clean();

if (strpos($html, 'href="/?section=ibsng"') === false) {
    $ibsngLink = <<<'HTML'
    <a class="ibsng-nav" href="/?section=ibsng" aria-label="IBSng">
      <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg></span><span>IBSng</span>
    </a>
HTML;
    $html = str_replace('</nav>', $ibsngLink . "\n  </nav>", $html, 1);
}

echo $html;
