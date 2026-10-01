<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

/**
 * Renders the IBSng section inside the existing RouteBox Admin shell.
 * This class never emits a standalone HTML document or browser page.
 */
final class IBSngSection
{
    public static function handlePost(IBSngAdmin $admin): void
    {
        $admin->handle($_POST, 'POST');
        $return = '/?section=ibsng';
        header('Location: ' . $return);
        exit;
    }

    public static function render(IBSngAdmin $admin, string $lang, string $csrf): string
    {
        $data = $admin->viewData();
        $fa = $lang === 'fa';
        $T = $fa ? [
            'title' => 'مدیریت IBSng',
            'sub' => 'مدیریت اتصال و تنظیمات IBSng از داخل پنل RouteBox',
            'add_title' => 'افزودن سرور IBSng',
            'add_hint' => 'اتصال Web Panel قبل از ذخیره تست می‌شود و گروه‌ها قابل Sync هستند.',
            'name' => 'نام سرور', 'host' => 'IP / Host', 'port' => 'پورت Web Panel',
            'isp' => 'ISP Name', 'user' => 'Admin Username', 'pass' => 'Admin Password',
            'add' => 'تست اتصال و ذخیره', 'servers' => 'سرورهای IBSng',
            'servers_hint' => 'اطلاعات اتصال و سرورهای IBSng را از همین بخش مدیریت کنید.',
            'none' => 'هنوز سرور IBSng اضافه نشده است.',
            'ok' => 'متصل', 'err' => 'خطا', 'untested' => 'تست نشده',
            'groups' => 'گروه‌های Sync شده', 'no_groups' => 'گروهی Sync نشده است.',
            'test' => 'تست اتصال', 'sync' => 'Sync گروه‌ها', 'edit' => 'ویرایش سرور',
            'new_user' => 'Admin Username جدید', 'new_pass' => 'Admin Password جدید',
            'unchanged' => 'خالی = بدون تغییر', 'save' => 'تست و ذخیره تغییرات',
            'stored' => 'اطلاعات ورود به‌صورت امن ذخیره می‌شوند.',
        ] : [
            'title' => 'IBSng Management',
            'sub' => 'Manage IBSng connectivity and settings from RouteBox Admin',
            'add_title' => 'Add IBSng Server',
            'add_hint' => 'The Web Panel connection is tested before saving and groups can be synced.',
            'name' => 'Server name', 'host' => 'IP / Host', 'port' => 'Web Panel port',
            'isp' => 'ISP Name', 'user' => 'Admin Username', 'pass' => 'Admin Password',
            'add' => 'Test connection & save', 'servers' => 'IBSng Servers',
            'servers_hint' => 'Manage IBSng servers and connection settings from this section.',
            'none' => 'No IBSng servers have been added yet.',
            'ok' => 'Connected', 'err' => 'Error', 'untested' => 'Not tested',
            'groups' => 'Synced groups', 'no_groups' => 'No groups synced.',
            'test' => 'Test connection', 'sync' => 'Sync groups', 'edit' => 'Edit server',
            'new_user' => 'New Admin Username', 'new_pass' => 'New Admin Password',
            'unchanged' => 'blank = unchanged', 'save' => 'Test & save changes',
            'stored' => 'Credentials are stored securely.',
        ];

        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flash = '';
        if (!empty($data['flash'])) {
            $flash = '<div class="flash ' . (!empty($data['error']) ? 'err' : '') . '">' . $h((string)$data['flash']) . '</div>';
        }

        $cards = '';
        foreach (($data['servers'] ?? []) as $s) {
            $id = (int)($s['id'] ?? 0);
            $groups = is_array($s['groups'] ?? null) ? $s['groups'] : [];
            $status = !empty($s['last_error'])
                ? '<span class="status"><span class="dot" style="background:var(--red)"></span>' . $T['err'] . '</span>'
                : (!empty($s['last_test_at'])
                    ? '<span class="status"><span class="dot" style="background:var(--green)"></span>' . $T['ok'] . '</span>'
                    : '<span class="status"><span class="dot" style="background:var(--muted)"></span>' . $T['untested'] . '</span>');

            $groupHtml = '';
            foreach ($groups as $g) {
                $groupHtml .= '<span class="tag">' . $h((string)($g['group_name'] ?? '')) . '</span>';
            }
            if ($groupHtml === '') $groupHtml = '<span class="help">' . $T['no_groups'] . '</span>';

            $error = !empty($s['last_error'])
                ? '<div class="flash" style="margin-top:12px">' . $h((string)$s['last_error']) . '</div>'
                : '';

            $cards .= '<article class="card">'
                . '<div class="section-head"><div class="section-title"><div class="section-icon">▤</div><div><h2>' . $h((string)($s['name'] ?? '')) . ' ' . $status . '</h2><p dir="ltr">' . $h((string)($s['host'] ?? '')) . ' : ' . $h((string)($s['port'] ?? '')) . ' · ' . $h((string)($s['isp_name'] ?? '')) . '</p></div></div></div>'
                . '<div class="grid">'
                . '<div><div class="help">Credentials</div><p class="help">••••••••<br>' . $T['stored'] . '</p></div>'
                . '<div><div class="help">' . $T['groups'] . ' (' . count($groups) . ')</div><div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:7px">' . $groupHtml . '</div></div>'
                . '</div>' . $error
                . '<div class="form-actions">'
                . '<form method="post"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-secondary" type="submit">↻ ' . $T['test'] . '</button></form>'
                . '<form method="post"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="sync_groups"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-secondary" type="submit">↻ ' . $T['sync'] . '</button></form>'
                . '</div>'
                . '<details style="margin-top:14px"><summary style="cursor:pointer;font-weight:750">' . $T['edit'] . '</summary>'
                . '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="update_server"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><div class="grid">'
                . '<div class="field"><label>' . $T['name'] . '</label><input name="name" required value="' . $h((string)($s['name'] ?? '')) . '"></div>'
                . '<div class="field"><label>' . $T['host'] . '</label><input name="host" required value="' . $h((string)($s['host'] ?? '')) . '"></div>'
                . '<div class="field"><label>' . $T['port'] . '</label><select name="port"><option value="80"' . ((int)($s['port'] ?? 80) === 80 ? ' selected' : '') . '>80 — HTTP</option><option value="443"' . ((int)($s['port'] ?? 80) === 443 ? ' selected' : '') . '>443 — HTTPS</option></select></div>'
                . '<div class="field"><label>' . $T['isp'] . '</label><input name="isp_name" value="' . $h((string)($s['isp_name'] ?? '')) . '"></div>'
                . '<div class="field"><label>' . $T['new_user'] . '</label><input name="username" autocomplete="off" placeholder="' . $h($T['unchanged']) . '"></div>'
                . '<div class="field"><label>' . $T['new_pass'] . '</label><input type="password" name="password" autocomplete="new-password" placeholder="' . $h($T['unchanged']) . '"></div>'
                . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">✓ ' . $T['save'] . '</button></div></form></details>'
                . '</article>';
        }
        if ($cards === '') $cards = '<div class="empty">' . $T['none'] . '</div>';

        return $flash
            . '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>' . $T['add_title'] . '</h2><p>' . $T['add_hint'] . '</p></div></div></div>'
            . '<form method="post"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="add_server"><input type="hidden" name="section" value="ibsng"><div class="grid">'
            . '<div class="field"><label>' . $T['name'] . '</label><input name="name" required maxlength="80"></div>'
            . '<div class="field"><label>' . $T['host'] . '</label><input name="host" required placeholder="185.18.x.x"></div>'
            . '<div class="field"><label>' . $T['port'] . '</label><select name="port"><option value="80">80 — HTTP</option><option value="443">443 — HTTPS</option></select></div>'
            . '<div class="field"><label>' . $T['isp'] . '</label><input name="isp_name" value="Main" required></div>'
            . '<div class="field"><label>' . $T['user'] . '</label><input name="username" required autocomplete="off"></div>'
            . '<div class="field"><label>' . $T['pass'] . '</label><input type="password" name="password" required autocomplete="new-password"></div>'
            . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">+ ' . $T['add'] . '</button></div></form></section>'
            . '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">▤</div><div><h2>' . $T['servers'] . '</h2><p>' . $T['servers_hint'] . '</p></div></div></div>' . $cards . '</section>';
    }

    public static function navLabel(string $lang): string
    {
        return $lang === 'fa' ? 'IBSng' : 'IBSng';
    }

    public static function navIcon(): string
    {
        return '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg>';
    }
}
