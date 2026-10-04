<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

/**
 * Renders the IBSng section inside the existing RouteBox Admin shell.
 */
final class IBSngSection
{
    public static function handlePost(IBSngAdmin $admin): void
    {
        $admin->handle($_POST, 'POST');
        header('Location: /?section=ibsng');
        exit;
    }

    public static function render(IBSngAdmin $admin, string $lang, string $csrf): string
    {
        $data = $admin->viewData();
        $fa = $lang === 'fa';
        $T = $fa ? [
            'title' => 'مدیریت IBSng',
            'sub' => 'مدیریت اتصال، سرورها و پلن‌های IBSng از داخل پنل RouteBox',
            'add_title' => 'افزودن سرور IBSng',
            'add_hint' => 'اتصال Web Panel قبل از ذخیره تست می‌شود؛ گروه‌ها را به‌صورت دستی تعریف کنید.',
            'name' => 'نام سرور', 'host' => 'IP / Host', 'port' => 'پورت Web Panel',
            'isp' => 'ISP Name', 'user' => 'Admin Username', 'pass' => 'Admin Password',
            'add' => 'تست اتصال و ذخیره', 'servers' => 'سرورهای IBSng',
            'servers_hint' => 'اطلاعات اتصال، سرورها و نگاشت پلن به گروه IBSng را مدیریت کنید.',
            'none' => 'هنوز سرور IBSng اضافه نشده است.',
            'ok' => 'متصل', 'err' => 'خطا', 'untested' => 'تست نشده',
            'groups' => 'پلن‌های IBSng', 'no_groups' => 'هنوز پلنی تعریف نشده است.',
            'test' => 'تست اتصال', 'test_user' => 'تست ساخت یوزر', 'delete_server' => 'حذف سرور', 'edit' => 'ویرایش سرور',
            'new_user' => 'Admin Username جدید', 'new_pass' => 'Admin Password جدید',
            'unchanged' => 'خالی = بدون تغییر', 'save' => 'تست و ذخیره تغییرات',
            'stored' => 'اطلاعات ورود به‌صورت امن ذخیره می‌شوند.',
            'plan_name' => 'نام پلن', 'group_name' => 'نام گروه IBSng',
            'add_group' => 'افزودن پلن / گروه', 'save_group' => 'ذخیره', 'delete_group' => 'حذف',
            'group_hint' => 'مثلاً پلن «یک ماهه» با گروه IBSng به نام p-1.',
        ] : [
            'title' => 'IBSng Management',
            'sub' => 'Manage IBSng connectivity, servers and plans from RouteBox Admin',
            'add_title' => 'Add IBSng Server',
            'add_hint' => 'The Web Panel connection is tested before saving; IBSng groups are entered manually.',
            'name' => 'Server name', 'host' => 'IP / Host', 'port' => 'Web Panel port',
            'isp' => 'ISP Name', 'user' => 'Admin Username', 'pass' => 'Admin Password',
            'add' => 'Test connection & save', 'servers' => 'IBSng Servers',
            'servers_hint' => 'Manage connection settings, servers and manual plan-to-group mappings.',
            'none' => 'No IBSng servers have been added yet.',
            'ok' => 'Connected', 'err' => 'Error', 'untested' => 'Not tested',
            'groups' => 'IBSng Plans', 'no_groups' => 'No plans have been defined yet.',
            'test' => 'Test connection', 'test_user' => 'Test Create User', 'delete_server' => 'Delete Server', 'edit' => 'Edit server',
            'new_user' => 'New Admin Username', 'new_pass' => 'New Admin Password',
            'unchanged' => 'blank = unchanged', 'save' => 'Test & save changes',
            'stored' => 'Credentials are stored securely.',
            'plan_name' => 'Plan name', 'group_name' => 'IBSng group name',
            'add_group' => 'Add plan / group', 'save_group' => 'Save', 'delete_group' => 'Delete',
            'group_hint' => 'For example, plan “One month” mapped to IBSng group p-1.',
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
                $gid = (int)($g['id'] ?? 0);
                $planName = (string)($g['plan_name'] ?? '');
                $groupName = (string)($g['group_name'] ?? '');
                $groupHtml .= '<div style="display:grid;grid-template-columns:minmax(120px,1fr) minmax(120px,1fr) auto;gap:8px;align-items:center;margin-top:8px">'
                    . '<div><div class="help">' . $h($T['plan_name']) . '</div><strong>' . $h($planName) . '</strong></div>'
                    . '<div><div class="help">' . $h($T['group_name']) . '</div><code>' . $h($groupName) . '</code></div>'
                    . '<div style="display:flex;gap:6px;justify-content:flex-end">'
                    . '<details><summary class="btn btn-secondary atd-ibsng-small-action">✎ ' . $h($fa ? 'ویرایش' : 'Edit') . '</summary>'
                    . '<form method="post" style="margin-top:8px;min-width:300px;background:var(--panel);padding:12px;border-radius:12px">'
                    . '<input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="update_group"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $gid . '">'
                    . '<div class="field"><label>' . $h($T['plan_name']) . '</label><input name="plan_name" required value="' . $h($planName) . '"></div>'
                    . '<div class="field"><label>' . $h($T['group_name']) . '</label><input name="group_name" required value="' . $h($groupName) . '"></div>'
                    . '<div class="form-actions"><button class="btn btn-primary" type="submit">✓ ' . $h($T['save_group']) . '</button></div>'
                    . '</form></details>'
                    . '<form method="post" onsubmit="return confirm(' . htmlspecialchars(json_encode($fa ? 'این پلن و نگاشت گروه حذف شود؟' : 'Delete this plan and group mapping?', JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . ')">'
                    . '<input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $gid . '">'
                    . '<button class="btn btn-secondary atd-ibsng-small-action atd-ibsng-danger" type="submit">× ' . $h($T['delete_group']) . '</button></form>'
                    . '</div></div>';
            }
            if ($groupHtml === '') {
                $groupHtml = '<div class="help" style="margin-top:10px">' . $h($T['no_groups']) . '</div>';
            }

            $error = !empty($s['last_error'])
                ? '<div class="flash" style="margin-top:12px">' . $h((string)$s['last_error']) . '</div>'
                : '';

            $cards .= '<article class="card">'
                . '<div class="section-head"><div class="section-title"><div class="section-icon">▤</div><div><h2>' . $h((string)($s['name'] ?? '')) . ' ' . $status . '</h2><p dir="ltr">' . $h((string)($s['host'] ?? '')) . ' : ' . $h((string)($s['port'] ?? '')) . ' · ' . $h((string)($s['isp_name'] ?? '')) . '</p></div></div></div>'
                . '<div class="grid">'
                . '<div><div class="help">Credentials</div><p class="help">••••••••<br>' . $T['stored'] . '</p></div>'
                . '<div><div class="help">' . $h($T['groups']) . ' (' . count($groups) . ')</div>' . $groupHtml . '</div>'
                . '</div>' . $error
                . '<div class="form-actions atd-ibsng-server-actions">'
                . '<form method="post"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-secondary atd-ibsng-action" type="submit">↻ ' . $h($T['test']) . '</button></form>'
                . '<form method="post" onsubmit="return confirm(' . htmlspecialchars(json_encode($fa ? 'یک یوزر تستی روی این سرور IBSng ساخته شود؟' : 'Create a test user on this IBSng server?', JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . ')"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="test_create_user"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-primary atd-ibsng-action" type="submit">✓ ' . $h($T['test_user']) . '</button></form>'
                . '<form method="post" onsubmit="return confirm(' . htmlspecialchars(json_encode($fa ? 'سرور IBSng حذف شود؟' : 'Delete this IBSng server?', JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . ')"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="delete_server"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><button class="btn btn-danger atd-ibsng-action" type="submit">× ' . $h($T['delete_server']) . '</button></form>'
                . '</div>'
                . '<details class="atd-ibsng-edit" style="margin-top:14px"><summary class="btn btn-secondary atd-ibsng-action">✎ ' . $h($T['edit']) . '</summary>'
                . '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="update_server"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="id" value="' . $id . '"><div class="grid">'
                . '<div class="field"><label>' . $h($T['name']) . '</label><input name="name" required value="' . $h((string)($s['name'] ?? '')) . '"></div>'
                . '<div class="field"><label>' . $h($T['host']) . '</label><input name="host" required value="' . $h((string)($s['host'] ?? '')) . '"></div>'
                . '<div class="field"><label>' . $h($T['port']) . '</label><select name="port"><option value="80"' . ((int)($s['port'] ?? 80) === 80 ? ' selected' : '') . '>80 — HTTP</option><option value="443"' . ((int)($s['port'] ?? 80) === 443 ? ' selected' : '') . '>443 — HTTPS</option></select></div>'
                . '<div class="field"><label>' . $h($T['isp']) . '</label><input name="isp_name" value="' . $h((string)($s['isp_name'] ?? '')) . '"></div>'
                . '<div class="field"><label>' . $h($T['new_user']) . '</label><input name="username" autocomplete="off" placeholder="' . $h($T['unchanged']) . '"></div>'
                . '<div class="field"><label>' . $h($T['new_pass']) . '</label><input type="password" name="password" autocomplete="new-password" placeholder="' . $h($T['unchanged']) . '"></div>'
                . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">✓ ' . $h($T['save']) . '</button></div></form></details>'
                . '<details class="atd-ibsng-edit" style="margin-top:14px"><summary class="btn btn-secondary atd-ibsng-action">+ ' . $h($T['add_group']) . '</summary>'
                . '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="add_group"><input type="hidden" name="section" value="ibsng"><input type="hidden" name="server_id" value="' . $id . '"><div class="grid">'
                . '<div class="field"><label>' . $h($T['plan_name']) . '</label><input name="plan_name" required placeholder="' . $h($fa ? 'یک ماهه' : 'One month') . '"></div>'
                . '<div class="field"><label>' . $h($T['group_name']) . '</label><input name="group_name" required placeholder="Enter IBSng group name"></div>'
                . '</div><p class="help">' . $h($T['group_hint']) . '</p><div class="form-actions"><button class="btn btn-primary" type="submit">+ ' . $h($T['add_group']) . '</button></div></form></details>'
                . '</article>';
        }
        if ($cards === '') {
            $cards = '<div class="empty">' . $h($T['none']) . '</div>';
        }

        $addServer = '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>' . $h($T['add_title']) . '</h2><p>' . $h($T['add_hint']) . '</p></div></div></div>'
            . '<form method="post"><input type="hidden" name="csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="action" value="add_server"><input type="hidden" name="section" value="ibsng"><div class="grid">'
            . '<div class="field"><label>' . $h($T['name']) . '</label><input name="name" required maxlength="80"></div>'
            . '<div class="field"><label>' . $h($T['host']) . '</label><input name="host" required placeholder="185.18.x.x"></div>'
            . '<div class="field"><label>' . $h($T['port']) . '</label><select name="port"><option value="80">80 — HTTP</option><option value="443">443 — HTTPS</option></select></div>'
            . '<div class="field"><label>' . $h($T['isp']) . '</label><input name="isp_name" value="Main" required></div>'
            . '<div class="field"><label>' . $h($T['user']) . '</label><input name="username" required autocomplete="off"></div>'
            . '<div class="field"><label>' . $h($T['pass']) . '</label><input type="password" name="password" required autocomplete="new-password"></div>'
            . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">+ ' . $h($T['add']) . '</button></div></form></section>';

        $serverList = '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">▤</div><div><h2>' . $h($T['servers']) . '</h2><p>' . $h($T['servers_hint']) . '</p></div></div></div>' . $cards . '</section>';

        return $flash . $serverList . $addServer
            . '<style>.atd-ibsng-server-actions{display:flex;gap:8px;flex-wrap:wrap}.atd-ibsng-action{min-height:40px!important;padding:10px 14px!important;border-radius:11px!important;font-weight:750!important;cursor:pointer;list-style:none!important}.atd-ibsng-action::-webkit-details-marker{display:none}.atd-ibsng-edit>summary{display:inline-flex}.atd-ibsng-small-action{min-height:36px!important;padding:8px 11px!important;border-radius:10px!important;font-size:12px!important;cursor:pointer;list-style:none!important}.atd-ibsng-small-action::-webkit-details-marker{display:none}.atd-ibsng-danger{color:var(--red)!important;border-color:rgba(255,80,80,.35)!important}@media(max-width:700px){.atd-ibsng-server-actions{justify-content:flex-start}}</style>';
    }

    public static function navLabel(string $lang): string
    {
        return 'IBSng';
    }

    public static function navIcon(): string
    {
        return '<svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg>';
    }
}
