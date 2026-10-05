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
            . '@media(max-width:760px){.atd-bot-control-section .bot-overview-grid{grid-template-columns:1fr}}'
            . '</style>';
    }

    public static function renderCustomer(string $lang): string
    {
        $fa = self::fa($lang);
        $body = '<div class="bot-overview-grid">'
            . '<div class="bot-overview-card"><h3>👤 ' . ($fa ? 'Customer Bot' : 'Customer Bot') . '</h3>'
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
            . '<ul>'
            . '<li>RouteBox / WireGuard</li><li>IBSng</li><li>MikroTik WireGuard</li>'
            . '</ul><div class="actions"><a class="btn btn-secondary" href="/?section=bot-guides">' . ($fa ? 'مدیریت Service Guides' : 'Manage Service Guides') . '</a></div></div>'
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
        try {
            $adminCount = (int)$db->query('SELECT COUNT(*) FROM telegram_bot_admins')->fetchColumn();
            $enabledCount = (int)$db->query('SELECT COUNT(*) FROM telegram_bot_admins WHERE enabled=1')->fetchColumn();
        } catch (\Throwable) {
            // The existing AdminBot schema is created by its own orchestration path.
        }

        $body = '<div class="bot-overview-grid">'
            . '<div class="bot-overview-card"><h3>🛡️ ' . ($fa ? 'Telegram Bot Admin' : 'Telegram Bot Admin') . '</h3>'
            . '<p>' . ($fa ? 'سیستم مستقل Authorization برای Adminهای Telegram؛ مستقل از IBSng owner / owner_name.' : 'Independent Telegram Admin authorization, separate from IBSng owner / owner_name.') . '</p>'
            . '<ul>'
            . '<li>' . ($fa ? 'Numeric Telegram ID' : 'Numeric Telegram ID') . '</li>'
            . '<li>' . ($fa ? 'چند Admin همزمان' : 'Multiple Admins') . '</li>'
            . '<li>' . ($fa ? 'Role-ready: Owner / Admin / Support / Finance / Operator' : 'Role-ready: Owner / Admin / Support / Finance / Operator') . '</li>'
            . '<li>' . ($fa ? 'Audit Log برای عملیات حساس' : 'Audit Log for sensitive actions') . '</li>'
            . '</ul><div class="actions"><a class="btn btn-primary" href="/telegram-admins.php">' . ($fa ? 'مدیریت Adminها' : 'Manage Admins') . '</a></div></div>'
            . '<div class="bot-overview-card"><h3>📊 ' . ($fa ? 'وضعیت Adminها' : 'Admin Status') . '</h3>'
            . '<p>' . ($fa ? 'اطلاعات از همان جدول Authorization فعلی خوانده می‌شود.' : 'Read-only status from the existing Telegram Admin authorization table.') . '</p>'
            . '<ul><li>' . ($fa ? 'تعداد Admin: ' : 'Admins: ') . '<strong>' . $adminCount . '</strong></li><li>' . ($fa ? 'فعال: ' : 'Enabled: ') . '<strong>' . $enabledCount . '</strong></li></ul>'
            . '<div class="actions"><a class="btn btn-secondary" href="/telegram-admins.php">' . ($fa ? 'باز کردن مدیریت Admin' : 'Open Admin Management') . '</a></div></div>'
            . '</div>'
            . '<div class="bot-flow"><strong>' . ($fa ? 'Admin Flow' : 'Admin Flow') . '</strong><code>Telegram Admin ID → Authorization → Admin Menu → Users / Services / Orders / Payments / Audit → Existing Application / Provider Layer</code></div>'
            . '<div class="notice">' . ($fa ? 'Provisioning سرویس Admin از همان ServiceProvisioner و Provider integration موجود استفاده می‌کند. این صفحه هیچ implementation جدیدی برای Providerها ایجاد نمی‌کند.' : 'Admin service provisioning continues through the existing ServiceProvisioner and Provider integrations. This page adds no parallel Provider implementation.') . '</div>';

        return self::shell($fa ? 'Telegram Bot Admin' : 'Telegram Bot Admin', $fa ? 'مرکز مدیریت قابلیت‌های Admin ربات روی معماری فعلی.' : 'Management overview for the existing Telegram Bot Admin architecture.', $body);
    }
}
