<?php

declare(strict_types=1);

/* ATD Panel front controller. Existing RouteBox, IBSng and MikroTik shells remain intact. */
$requestedSection = (string)($_GET['section'] ?? 'dashboard');
$section = $requestedSection;
$isProviderPlans = $requestedSection === 'provider-plans';
$isAtdExtra = in_array($requestedSection, ['users','user-details','payment-settings','provider-guide'], true);

if (in_array($requestedSection, ['ibsng', 'mikrotik'], true) && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') ) {
    require __DIR__ . '/../src/bootstrap.php';
    require_admin();
    if ($requestedSection === 'ibsng') {
        require_once __DIR__ . '/../src/Integrations/IBSng/IBSngModule.php';
        $module = \RouteBox\Integrations\IBSng\IBSngModule::admin(db());
    } else {
        require_once __DIR__ . '/../src/Integrations/MikroTik/MikroTikModule.php';
        $module = \RouteBox\Integrations\MikroTik\MikroTikModule::admin(db());
    }
    $module->handle($_POST, 'POST');
    header('Location: /?section=' . rawurlencode($requestedSection));
    exit;
}

if ($isProviderPlans && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    require __DIR__ . '/../src/bootstrap.php';
    require_admin();
    require_once __DIR__ . '/../src/Admin/Plans/PlanPolicy.php';
    require_once __DIR__ . '/../src/Admin/Plans/PlanRepository.php';
    require_once __DIR__ . '/../src/Admin/Plans/ProviderPlansSection.php';
    verify_csrf();
    try {
        \RouteBox\Admin\Plans\ProviderPlansSection::handle(db(), $_POST);
        $_SESSION['flash'] = '✓ Plan saved.';
    } catch (Throwable $e) {
        $_SESSION['flash'] = '❌ ' . $e->getMessage();
        log_event('error', 'Provider plan action failed: ' . $e->getMessage());
    }
    $provider = trim((string)($_POST['provider_key'] ?? $_GET['provider'] ?? 'routebox'));
    header('Location: /?section=provider-plans&provider=' . rawurlencode($provider));
    exit;
}

if ($isAtdExtra && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    require __DIR__ . '/../src/bootstrap.php';
    require_admin();
    require_once __DIR__ . '/../src/Admin/ATDPanelSections.php';
    verify_csrf();
    try {
        $return = \RouteBox\Admin\ATDPanelSections::handle(db(), $_POST);
        $_SESSION['flash'] = '✓ Saved.';
    } catch (Throwable $e) {
        $_SESSION['flash'] = '❌ ' . $e->getMessage();
        log_event('error', 'ATD admin action failed: ' . $e->getMessage());
        $return = $requestedSection;
    }
    header('Location: /?section=' . $return);
    exit;
}

/* Every section uses the exact existing Admin shell; special sections replace only the body. */
$specialSections = ['ibsng', 'mikrotik', 'provider-plans', 'users', 'user-details', 'payment-settings', 'provider-guide'];
if (!in_array($requestedSection, $specialSections, true)) {
    ob_start();
    require __DIR__ . '/index.core.php';
    $html = (string)ob_get_clean();
    $section = $requestedSection;
} else {
    $originalGet = $_GET;
    $_GET['section'] = 'dashboard';
    ob_start();
    require __DIR__ . '/index.core.php';
    $html = (string)ob_get_clean();
    $_GET = $originalGet;
    $section = $requestedSection;
}

require_once __DIR__ . '/../src/Integrations/IBSng/IBSngModule.php';
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngSection.php';
require_once __DIR__ . '/../src/Integrations/MikroTik/MikroTikModule.php';
require_once __DIR__ . '/../src/Integrations/MikroTik/MikroTikSection.php';
require_once __DIR__ . '/../src/Admin/ATDPanelSections.php';

use RouteBox\Integrations\IBSng\IBSngModule;
use RouteBox\Integrations\IBSng\IBSngSection;
use RouteBox\Integrations\MikroTik\MikroTikModule;
use RouteBox\Integrations\MikroTik\MikroTikSection;

$lang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';
require_once __DIR__ . '/../src/Admin/ATDStats.php';

/* Preserve the original SVG sidebar icon set from the RouteBox shell. */
$navIcon = static function (string $key): string {
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/></svg>',
        'servers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>',
        'bot' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3m-5 2h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Z"/><path d="M8 13h.01M16 13h.01M9 17h6"/></svg>',
        'ibsng' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg>',
        'mikrotik' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 17v2M17 17v2"/><circle cx="8" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="12" r="1"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c.6-4 2.5-6 6-6s5.4 2 6 6"/><path d="M16 6.5a3 3 0 0 1 0 5.8M17 14c2.2.7 3.5 2.3 4 6"/></svg>',
        'payment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></svg>',
        'security' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 20 6v6c0 5-3.2 8-8 9-4.8-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'updates' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/></svg>',
    ];
    return $icons[$key] ?? $icons['dashboard'];
};
$label = static function (string $fa, string $en) use ($lang): string {
    return htmlspecialchars($lang === 'fa' ? $fa : $en, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$active = static function (string $key) use ($section): string {
    return $section === $key ? ' active' : '';
};
$planActive = static function (string $provider) use ($section): string {
    if ($section !== 'provider-plans') return '';
    return trim((string)($_GET['provider'] ?? 'routebox')) === $provider ? ' active' : '';
};
$guideActive = static function (string $provider) use ($section): string {
    if ($section !== 'provider-guide') return '';
    return trim((string)($_GET['provider'] ?? 'routebox')) === $provider ? ' active' : '';
};

$nav = '<nav class="nav atd-nav" aria-label="' . $label('ناوبری پنل', 'Panel navigation') . '">';
$nav .= '<a class="atd-nav-item' . $active('dashboard') . '" href="/?section=dashboard"><span class="nav-icon">' . $navIcon('dashboard') . '</span><span>' . $label('داشبورد', 'Dashboard') . '</span></a>';
$nav .= '<div class="atd-nav-group' . ($section === 'bot' || $section === 'bot-guides' ? ' expanded' : '') . '">';
$nav .= '<a class="atd-nav-item provider-item' . ($section === 'bot' ? ' active' : '') . '" href="/?section=bot"><span class="nav-icon">' . $navIcon('bot') . '</span><span>' . $label('ربات تلگرام', 'Telegram Bot') . '</span><span class="nav-chevron">›</span></a>';
$nav .= '<div class="atd-nav-sub"><a class="atd-nav-subitem' . ($section === 'bot-guides' ? ' active' : '') . '" href="/?section=bot-guides"><span class="sub-dot">•</span><span>' . $label('راهنمای استفاده', 'Usage Guides') . '</span></a></div></div>';

$providers = [
    'routebox' => ['section'=>'servers','fa'=>'Routebox Servers','en'=>'Routebox Servers','icon'=>'servers'],
    'ibsng' => ['section'=>'ibsng','fa'=>'IBSng Servers','en'=>'IBSng Servers','icon'=>'ibsng'],
    'mikrotik_wireguard' => ['section'=>'mikrotik','fa'=>'MikroTik WireGuard','en'=>'MikroTik WireGuard','icon'=>'mikrotik'],
];
foreach ($providers as $providerKey => $provider) {
    $providerUrl = '/?section=' . rawurlencode($provider['section']);
    $planUrl = '/?section=provider-plans&provider=' . rawurlencode($providerKey);
    $guideUrl = '/?section=provider-guide&provider=' . rawurlencode($providerKey);
    $providerIsActive = $section === $provider['section'] || ($section === 'provider-plans' && trim((string)($_GET['provider'] ?? 'routebox')) === $providerKey) || ($section === 'provider-guide' && trim((string)($_GET['provider'] ?? 'routebox')) === $providerKey);
    $nav .= '<div class="atd-nav-group' . ($providerIsActive ? ' expanded' : '') . '">';
    $nav .= '<a class="atd-nav-item provider-item' . ($section === $provider['section'] ? ' active' : '') . '" href="' . $providerUrl . '"><span class="nav-icon">' . $navIcon($provider['icon']) . '</span><span>' . $label($provider['fa'], $provider['en']) . '</span><span class="nav-chevron">›</span></a>';
    $nav .= '<div class="atd-nav-sub"><a class="atd-nav-subitem' . $planActive($providerKey) . '" href="' . $planUrl . '"><span class="sub-dot">•</span><span>' . $label('Plans','Plans') . '</span></a><a class="atd-nav-subitem' . $guideActive($providerKey) . '" href="' . $guideUrl . '"><span class="sub-dot">•</span><span>' . $label('راهنمای Provider','Provider Guide') . '</span></a></div></div>';
}
$nav .= '<a class="atd-nav-item' . ($section === 'users' || $section === 'user-details' ? ' active' : '') . '" href="/?section=users"><span class="nav-icon">' . $navIcon('users') . '</span><span>' . $label('کاربران', 'Users') . '</span></a>';
$nav .= '<a class="atd-nav-item' . $active('payment-settings') . '" href="/?section=payment-settings"><span class="nav-icon">' . $navIcon('payment') . '</span><span>' . $label('تنظیمات پرداخت', 'Payment Settings') . '</span></a>';
$nav .= '<a class="atd-nav-item' . $active('security') . '" href="/?section=security"><span class="nav-icon">' . $navIcon('security') . '</span><span>' . $label('امنیت', 'Security') . '</span></a>';
$nav .= '<a class="atd-nav-item' . $active('updates') . '" href="/?section=updates"><span class="nav-icon">' . $navIcon('updates') . '</span><span>' . $label('به‌روزرسانی', 'Updates') . '</span></a>';
$nav .= '</nav>';

$html = preg_replace('~<nav\b[^>]*>.*?</nav>~is', $nav, $html, 1) ?? $html;

$style = <<<'CSS'
<style id="atd-panel-nav-style">
/* Keep the original RouteBox sidebar sizing. ATD only adds hierarchy/sub-navigation. */
.atd-nav-item,.atd-nav-subitem{box-sizing:border-box;text-decoration:none;transition:background .16s ease,transform .16s ease,box-shadow .16s ease}
.atd-nav-item:hover{background:rgba(127,127,127,.09);transform:translateX(-1px)}
.atd-nav-item.active{background:rgba(127,127,127,.13);box-shadow:inset 3px 0 0 currentColor}
.atd-nav-item .nav-icon{display:inline-flex;align-items:center;justify-content:center}
.atd-nav-item .nav-icon svg{display:block;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.atd-nav-item>span:nth-child(2){flex:1;min-width:0;white-space:nowrap;overflow:visible;text-overflow:clip}
.provider-item .nav-chevron{margin-inline-start:auto;opacity:.55;line-height:1;transition:transform .16s ease}
.atd-nav-group.expanded .nav-chevron{transform:rotate(90deg);opacity:.8}
.atd-nav-sub{display:none;margin:0 0 2px 34px;padding-left:9px;border-left:1px solid rgba(127,127,127,.22)}
.atd-nav-group.expanded .atd-nav-sub{display:block}
.atd-nav-subitem{display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:8px;color:inherit;font-size:.9em;font-weight:600;opacity:.78}
.atd-nav-subitem:hover{background:rgba(127,127,127,.08);opacity:1}
.atd-nav-subitem.active{background:rgba(127,127,127,.11);opacity:1}
.atd-nav-subitem .sub-dot{opacity:.55;font-size:15px}
</style>
CSS;
if (stripos($html, '</head>') !== false) {
    $html = preg_replace('~</head>~i', $style . '</head>', $html, 1) ?? $html;
}

$html = str_replace('RouteBox Admin', 'ATD Panel', $html);
$html = str_replace('Telegram Bot Control Center', 'Multi-Service Control Center', $html);

if ($section !== 'ibsng' && $section !== 'mikrotik' && $section !== 'provider-plans' && !$isAtdExtra && $section !== 'bot-guides') {
    echo $html;
    exit;
}

if ($section === 'provider-plans') {
    require_once __DIR__ . '/../src/Admin/Plans/PlanPolicy.php';
    require_once __DIR__ . '/../src/Admin/Plans/PlanRepository.php';
    require_once __DIR__ . '/../src/Admin/Plans/ProviderPlansSection.php';
    $provider = trim((string)($_GET['provider'] ?? 'routebox'));
    if (!in_array($provider, ['routebox', 'ibsng', 'mikrotik_wireguard'], true)) $provider = 'routebox';
    $body = \RouteBox\Admin\Plans\ProviderPlansSection::render(db(), $provider, $lang, csrf_token());
    $title = $lang === 'fa' ? 'مدیریت پلن‌ها' : 'Plan Management';
    $subtitle = $lang === 'fa' ? 'مدیریت یکپارچه پلن‌های Provider' : 'Shared provider-scoped plan management';
} elseif ($section === 'provider-guide') {
    $provider = trim((string)($_GET['provider'] ?? 'routebox'));
    if (!in_array($provider, ['routebox', 'ibsng', 'mikrotik_wireguard'], true)) $provider = 'routebox';
    $body = \RouteBox\Admin\ATDPanelSections::renderProviderGuide(db(), $provider, $lang, csrf_token());
    $title = $lang === 'fa' ? 'راهنمای Provider' : 'Provider Guide';
    $subtitle = $lang === 'fa' ? 'راهنمای اختصاصی مدیریت و اتصال Provider' : 'Provider-specific administration and connection guide';
} elseif ($section === 'users') {
    $body = \RouteBox\Admin\ATDPanelSections::renderUsers(db(), $lang, csrf_token());
    $title = $lang === 'fa' ? 'کاربران' : 'Users';
    $subtitle = $lang === 'fa' ? 'مدیریت کاربران ربات و سرویس‌های آن‌ها' : 'Manage Telegram bot users and their services';
} elseif ($section === 'user-details') {
    $body = \RouteBox\Admin\ATDPanelSections::renderUserDetails(db(), (int)($_GET['id'] ?? 0), $lang, csrf_token());
    $title = $lang === 'fa' ? 'جزئیات کاربر' : 'User Details';
    $subtitle = $lang === 'fa' ? 'سرویس‌ها و اشتراک‌های کاربر' : 'User services and subscriptions';
} elseif ($section === 'payment-settings') {
    $body = \RouteBox\Admin\ATDPanelSections::renderPayments(db(), $lang, csrf_token());
    $title = $lang === 'fa' ? 'تنظیمات پرداخت' : 'Payment Settings';
    $subtitle = $lang === 'fa' ? 'زیرساخت مشترک پرداخت برای همه Providerها' : 'Shared payment foundation for all providers';
} elseif ($section === 'bot-guides') {
    $body = \RouteBox\Admin\ATDPanelSections::renderBot(db(), $lang, csrf_token());
    $title = $lang === 'fa' ? 'راهنمای ربات' : 'Telegram Bot Usage Guides';
    $subtitle = $lang === 'fa' ? 'راهنمای عمومی و راهنمای اتصال هر سرویس' : 'General and per-service connection guides';
} elseif ($section === 'ibsng') {
    $admin = IBSngModule::admin(db());
    $body = IBSngSection::render($admin, $lang, csrf_token());
    $title = $lang === 'fa' ? 'مدیریت IBSng' : 'IBSng Management';
    $subtitle = $lang === 'fa' ? 'مدیریت اتصال و تنظیمات IBSng از داخل پنل' : 'Manage IBSng connectivity and settings from the panel';
} else {
    $admin = MikroTikModule::admin(db());
    $body = MikroTikSection::render($admin, $lang, csrf_token());
    $title = 'MikroTik WireGuard';
    $subtitle = $lang === 'fa' ? 'مدیریت RouterOS، WireGuard، کاربران و پلن‌ها' : 'Manage RouterOS, WireGuard, peers and plans';
}

$titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$subtitleEsc = htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = preg_replace('~(<header class="topbar">.*?<div><div class="eyebrow">).*?(</div><h1>).*?(</h1><p>).*?(</p>)~s', '$1ATD PANEL$2' . $titleEsc . '$3' . $subtitleEsc . '$4', $html, 1) ?? $html;

$headerEnd = strpos($html, '</header>');
$footerStart = strpos($html, '<div class="footer">', $headerEnd === false ? 0 : $headerEnd);
if ($headerEnd !== false && $footerStart !== false && $footerStart > $headerEnd) {
    $contentStart = $headerEnd + strlen('</header>');
    $html = substr($html, 0, $contentStart) . "\n\n" . $body . "\n\n" . substr($html, $footerStart);
}

$html = preg_replace_callback('~<a\b([^>]*)href="/\?section=dashboard"([^>]*)>~i', static function (array $m): string {
    $attrs = $m[1] . $m[2];
    $attrs = preg_replace('/\s+class="active"/i', '', $attrs) ?? $attrs;
    $attrs = preg_replace('/\s+aria-current="page"/i', '', $attrs) ?? $attrs;
    return '<a' . $attrs . ' href="/?section=dashboard">';
}, $html, 1) ?? $html;

echo $html;
