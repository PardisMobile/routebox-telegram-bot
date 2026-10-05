<?php

declare(strict_types=1);

namespace RouteBox\Admin;

use PDO;

/**
 * Presentation-only Telegram Bot control-center views.
 * Existing Bot/Worker/Provider behavior remains outside this class.
 */
final class TelegramBotSections
{
    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function fa(string $lang): bool
    {
        return $lang === 'fa';
    }

    private static function shell(string $title, string $subtitle, string $body, string $back = 'bot'): string
    {
        return '<section class="atd-admin-section card atd-bot-control-section">'
            . '<div class="section-head"><div><div class="eyebrow">ATD PANEL · TELEGRAM BOT</div><h2>' . self::esc($title) . '</h2><p>' . self::esc($subtitle) . '</p></div>'
            . '<a class="btn btn-secondary" href="/?section=' . rawurlencode($back) . '">← Back</a></div>'
            . $body
            . self::styles()
            . '</section>';
    }

    private static function styles(): string
    {
        return '<style id="atd-telegram-bot-sections">'
            . '.atd-bot-control-section .bot-overview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}'
            . '.atd-bot-control-section .bot-overview-card{border:1px solid var(--line);border-radius:16px;padding:18px;background:var(--card2)}'
            . '.atd-bot-control-section .bot-overview-card h3{margin:0 0 7px;font-size:18px}'
            . '.atd-bot-control-section .bot-overview-card p{margin:0;color:var(--muted);line-height:1.7}'
            . '.atd-bot-control-section .bot-overview-card ul{margin:14px 0 0;padding-inline-start:20px;line-height:1.9}'
            . '.atd-bot-control-section .bot-overview-card .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}'
            . '.atd-bot-control-section .bot-flow{margin-top:16px;border:1px dashed var(--line);border-radius:15px;padding:16px;background:var(--card)}'
            . '.atd-bot-control-section .bot-flow strong{display:block;margin-bottom:8px}'
            . '.atd-bot-control-section .bot-flow code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;line-height:1.8;color:var(--muted)}'
            . '.atd-bot-control-section .notice{margin-top:16px;padding:12px 14px;border-radius:12px;background:var(--atd-accent-soft);border:1px solid color-mix(in srgb,var(--atd-accent) 20%,var(--line));line-height:1.7}'
            . '.atd-bot-control-section .admin-table{overflow:auto;border:1px solid var(--line);border-radius:14px;margin-top:16px}'
            . '.atd-bot-control-section table{width:100%;border-collapse:collapse}'
            . '.atd-bot-control-section th,.atd-bot-control-section td{padding:12px 13px;border-bottom:1px solid var(--line);text-align:start;white-space:nowrap}'
            . '.atd-bot-control-section th{color:var(--muted);font-size:12px}'
            . '.atd-bot-control-section tr:last-child td{border-bottom:0}'
            . '.atd-bot-control-section .admin-form{display:grid;grid-template-columns:minmax(0,2fr) minmax(130px,1fr) auto auto;gap:10px;align-items:end;margin-top:14px}'
            . '.atd-bot-control-section .admin-field{display:flex;flex-direction:column;gap:7px}'
            . '.atd-bot-control-section .admin-field label{font-size:12px;font-weight:700;color:var(--muted)}'
            . '.atd-bot-control-section .admin-field input,.atd-bot-control-section .admin-field select{width:100%;box-sizing:border-box;padding:10px 11px;border:1px solid var(--line);border-radius:11px;background:var(--card);color:inherit;font:inherit}'
            . '.atd-bot-control-section .admin-check{height:40px;display:flex;gap:7px;align-items:center;color:var(--muted);white-space:nowrap}'
            . '@media(max-width:760px){.atd-bot-control-section .bot-overview-grid{grid-template-columns:1fr}.atd-bot-control-section .admin-form{grid-template-columns:1fr}.atd-bot-control-section .admin-check{height:auto}}'
            . '</style>';
    }

    public static function renderCustomer(string $lang): string
    {
        $fa = self::fa($lang);
        $body = '<div class="bot-overview-grid">'
            . '<div class="bot-overview-card"><h3>👤 Customer Bot</h3>'
            . '<p>' . ($fa ? 'رابط کاربری Telegram برای مشتریان؛ روی Bot و Worker فعلی ATD Panel.' : 'The customer-facing Telegram interface running on the existing ATD Panel Bot and Worker.') . '</p>'
            . '<ul>'
            . '<li>' . ($fa ? 'انتخاب Provider و Plan از Catalog فعلی' : 'Provider and Plan selection from the existing catalog') . '</li>'
            . '<li>' . ($fa ? 'مشاهده سرویس‌های کاربر' : 'View the user’s services') . '</li>'
            . '<li>' . ($fa ? 'دریافت Config / QR / Credentials بر اساس Provider' : 'Receive Config / QR / Credentials according to the Provider') . '</li>'
            . '<li>' . ($fa ? 'راهنمای عمومی و راهنمای مخصوص سرویس' : 'General guide and service-specific guide') . '</li>'
            . '</ul><div class="actions">'
            . '<a class="btn btn-primary" href="/?section=bot">' . ($fa ? 'تنظیمات Bot' : 'Bot Settings') . '</a>'
            . '<a class="btn btn-secondary" href="/?section=bot-guides">' . ($fa ? 'راهنمای Bot' : 'Bot Usage Guides') . '</a>'
            . '</div></div>'
            . '<div class="bot-overview-card"><h3>🧩 ' . ($fa ? 'Service Experience' : 'Service Experience') . '</h3>'
            . '<p>' . ($fa ? 'راهنمای هر سرویس از General Guide جداست و باید متناسب با سرویس خریداری‌شده نمایش داده شود.' : 'Service guides remain separate from the General Guide and are intended to follow the purchased service.') . '</p>'
            . '<ul><li>RouteBox / WireGuard</li><li>IBSng</li><li>MikroTik WireGuard</li></ul>'
            . '<div class="actions"><a class="btn btn-secondary" href="/?section=bot-guides">' . ($fa ? 'مدیریت Service Guides' : 'Manage Service Guides') . '</a></div></div>'
            . '</div>'
            . '<div class="bot-flow"><strong>' . ($fa ? 'Customer Flow' : 'Customer Flow') . '</strong><code>Telegram → Register → Choose Service → Choose Plan → Order / Payment → Provisioning → Credentials / Config → My Services / Renew</code></div>'
            . '<div class="notice">' . ($fa ? 'این صفحه فقط نمای مدیریتی است؛ Provisioning، Worker و Providerهای موجود از اینجا بازنویسی یا duplicate نمی‌شوند.' : 'This is an administrative overview only. Existing provisioning, Worker behavior and Provider integrations are not duplicated or rewritten here.') . '</div>';

        return self::shell($fa ? 'بات کاربر عادی' : 'Customer Bot', $fa ? 'نمای کلی تجربه کاربر Telegram بدون تغییر در Bot Core.' : 'Customer-facing Telegram experience without changing the existing Bot Core.', $body);
    }

    public static function renderAdmin(PDO $db, string $lang): string
    {
        $fa = self::fa($lang);
        $adminCount = 0;
        $enabledCount = 0;
        $rows = [];
        try {
            $adminCount = (int)$db->query('SELECT COUNT(*) FROM telegram_bot_admins')->fetchColumn();
            $enabledCount = (int)$db->query('SELECT COUNT(*) FROM telegram_bot_admins WHERE enabled=1')->fetchColumn();
            $rows = $db->query('SELECT id,telegram_id,role,enabled,created_at FROM telegram_bot_admins ORDER BY enabled DESC,id ASC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            // Existing AdminBot schema is created by its own orchestration path.
        }

        $csrf = '';
        if (function_exists('csrf_token')) {
            $csrf = (string)csrf_token();
        }
        $escCsrf = self::esc($csrf);
        $body = '<div class="bot-overview-grid">'
            . '<div class="bot-overview-card"><h3>🛡️ ' . ($fa ? 'Telegram Bot Admin' : 'Telegram Bot Admin') . '</h3>'
            . '<p>' . ($fa ? 'Authorization مستقل بر اساس Telegram Numeric ID؛ مستقل از IBSng owner / owner_name.' : 'Independent Telegram Numeric ID authorization, separate from IBSng owner / owner_name.') . '</p>'
            . '<ul><li>Numeric Telegram ID</li><li>' . ($fa ? 'چند Admin همزمان' : 'Multiple Admins') . '</li><li>' . ($fa ? 'Role-ready' : 'Role-ready') . ': Owner / Admin / Support / Finance / Operator</li><li>' . ($fa ? 'Audit Log برای عملیات حساس' : 'Audit Log for sensitive actions') . '</li></ul></div>'
            . '<div class="bot-overview-card"><h3>📊 ' . ($fa ? 'وضعیت Adminها' : 'Admin Status') . '</h3>'
            . '<ul><li>' . ($fa ? 'تعداد Admin: ' : 'Admins: ') . '<strong>' . $adminCount . '</strong></li><li>' . ($fa ? 'فعال: ' : 'Enabled: ') . '<strong>' . $enabledCount . '</strong></li></ul>'
            . '<p>' . ($fa ? 'مدیریت از همین صفحه انجام می‌شود و UI مستقل دیگری برای Adminها وجود ندارد.' : 'Administration is managed inside this section; there is no separate Admin page UI.') . '</p></div>'
            . '</div>';

        $body .= '<div class="bot-overview-card" style="margin-top:14px"><h3>' . ($fa ? 'افزودن Admin' : 'Add Telegram Admin') . '</h3>'
            . '<form class="admin-form" method="post" action="/telegram-admins.php">'
            . '<input type="hidden" name="csrf_token" value="' . $escCsrf . '"><input type="hidden" name="action" value="save">'
            . '<div class="admin-field"><label>' . ($fa ? 'Telegram Numeric ID' : 'Telegram Numeric ID') . '</label><input name="telegram_id" inputmode="numeric" pattern="[0-9]{5,20}" required placeholder="123456789"></div>'
            . '<div class="admin-field"><label>' . ($fa ? 'نقش' : 'Role') . '</label><select name="role"><option value="owner">Owner</option><option value="admin" selected>Admin</option><option value="support">Support</option><option value="finance">Finance</option><option value="operator">Operator</option></select></div>'
            . '<label class="admin-check"><input type="checkbox" name="enabled" checked> ' . ($fa ? 'فعال' : 'Enabled') . '</label>'
            . '<button class="btn btn-primary" type="submit">' . ($fa ? 'افزودن Admin' : 'Add Admin') . '</button>'
            . '</form></div>';

        $body .= '<div class="bot-overview-card" style="margin-top:14px"><h3>' . ($fa ? 'Adminهای ثبت‌شده' : 'Configured Admins') . '</h3>'
            . '<div class="admin-table"><table><thead><tr><th>Telegram ID</th><th>Role</th><th>' . ($fa ? 'وضعیت' : 'Status') . '</th><th>' . ($fa ? 'تاریخ ایجاد' : 'Created') . '</th><th>' . ($fa ? 'عملیات' : 'Actions') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $enabled = (int)$row['enabled'] === 1;
            $body .= '<tr><td><code>' . self::esc((string)$row['telegram_id']) . '</code></td><td>' . self::esc(ucfirst((string)$row['role'])) . '</td><td>' . ($enabled ? ($fa ? 'فعال' : 'Enabled') : ($fa ? 'غیرفعال' : 'Disabled')) . '</td><td>' . date('Y-m-d H:i', (int)$row['created_at']) . '</td><td><div class="actions">'
                . '<form method="post" action="/telegram-admins.php"><input type="hidden" name="csrf_token" value="' . $escCsrf . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . $id . '"><button class="btn" type="submit">' . ($enabled ? ($fa ? 'غیرفعال' : 'Disable') : ($fa ? 'فعال' : 'Enable')) . '</button></form>'
                . '<form method="post" action="/telegram-admins.php" onsubmit="return confirm(\'' . ($fa ? 'این Admin حذف شود؟' : 'Remove this Telegram Bot Admin?') . '\')"><input type="hidden" name="csrf_token" value="' . $escCsrf . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $id . '"><button class="btn" type="submit">' . ($fa ? 'حذف' : 'Remove') . '</button></form>'
                . '</div></td></tr>';
        }
        if (!$rows) {
            $body .= '<tr><td colspan="5">' . ($fa ? 'هنوز Adminی ثبت نشده است.' : 'No Telegram Bot Admins configured yet.') . '</td></tr>';
        }
        $body .= '</tbody></table></div></div>'
            . '<div class="bot-flow"><strong>' . ($fa ? 'Admin Flow' : 'Admin Flow') . '</strong><code>Telegram Admin ID → Authorization → Admin Menu → Users / Services / Orders / Payments / Audit → Existing Application / Provider Layer</code></div>'
            . '<div class="notice">' . ($fa ? 'Provisioning سرویس Admin از همان ServiceProvisioner و Provider integration موجود استفاده می‌کند. این بخش هیچ implementation جدیدی برای Providerها ایجاد نمی‌کند.' : 'Admin service provisioning continues through the existing ServiceProvisioner and Provider integrations. This section adds no parallel Provider implementation.') . '</div>';

        return self::shell($fa ? 'Telegram Bot Admin' : 'Telegram Bot Admin', $fa ? 'مدیریت Adminهای Telegram داخل همان Unified UI پنل.' : 'Telegram Admin management inside the existing unified ATD Panel UI.', $body);
    }
}
