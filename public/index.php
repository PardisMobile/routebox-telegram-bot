<?php

declare(strict_types=1);

/* ATD Panel front controller. Existing RouteBox, IBSng and MikroTik shells remain intact. */
$requestedSection = (string)($_GET['section'] ?? 'dashboard');
$section = $requestedSection;
$isProviderPlans = $requestedSection === 'provider-plans';

if (in_array($requestedSection, ['ibsng', 'mikrotik'], true) && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
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

/* Every section uses the exact existing Admin shell; special sections replace only the body. */
if (!in_array($requestedSection, ['ibsng', 'mikrotik', 'provider-plans'], true)) {
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

use RouteBox\Integrations\IBSng\IBSngModule;
use RouteBox\Integrations\IBSng\IBSngSection;
use RouteBox\Integrations\MikroTik\MikroTikModule;
use RouteBox\Integrations\MikroTik\MikroTikSection;

$lang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';

/* Keep the original Admin SVG icon set. ATD only changes navigation hierarchy and layout. */
$navIcon = static function (string $key): string {
    $icons = [
        'dashboard'=>'<svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/></svg>',
        'servers'=>'<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>',
        'bot'=>'<svg viewBox="0 0 24 24"><path d="M12 3v3m-5 2h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Z"/><path d="M8 13h.01M16 13h.01M9 17h6"/></svg>',
        'plans'=>'<svg viewBox="0 0 24 24"><path d="m12 3 8 4-8 4-8-4 8-4Zm-8 9 8 4 8-4M4 17l8 4 8-4"/></svg>',
        'security'=>'<svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 5-3.2 8-8 9-4.8-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'updates'=>'<svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/></svg>',
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

/* Provider-first navigation. Provider pages own their Plans as a child route. */
$nav = '<nav class="nav atd-nav" aria-label="' . $label('ناوبری پنل', 'Panel navigation') . '">';
$nav .= '<a class="atd-nav-item' . $active('dashboard') . '" href="/?section=dashboard"><span class="nav-icon">' . $navIcon('dashboard') . '</span><span>' . $label('داشبورد', 'Dashboard') . '</span></a>';
$nav .= '<a class="atd-nav-item' . $active('bot') . '" href="/?section=bot"><span class="nav-icon">' . $navIcon('bot') . '</span><span>' . $label('ربات تلگرام', 'Telegram Bot') . '</span></a>';

$providers = [
    'routebox' => ['section'=>'servers','fa'=>'Routebox Servers','en'=>'Routebox Servers','icon'=>'servers'],
    'ibsng' => ['section'=>'ibsng','fa'=>'IBSng Servers','en'=>'IBSng Servers','icon'=>'servers'],
    'mikrotik_wireguard' => ['section'=>'mikrotik','fa'=>'MikroTik WireGuard','en'=>'MikroTik WireGuard','icon'=>'servers'],
];
foreach ($providers as $providerKey => $provider) {
    $providerUrl = '/?section=' . rawurlencode($provider['section']);
    $planUrl = '/?section=provider-plans&provider=' . rawurlencode($providerKey);
    $providerIsActive = $section === $provider['section'] || ($section === 'provider-plans' && trim((string)($_GET['provider'] ?? 'routebox')) === $providerKey);
    $nav .= '<div class="atd-nav-group' . ($providerIsActive ? ' expanded' : '') . '">';
    $nav .= '<a class="atd-nav-item provider-item' . ($section === $provider['section'] ? ' active' : '') . '" href="' . $providerUrl . '"><span class="nav-icon">' . $navIcon($provider['icon']) . '</span><span>' . $label($provider['fa'], $provider['en']) . '</span><span class="nav-chevron">›</span></a>';
    $nav .= '<div class="atd-nav-sub"><a class="atd-nav-subitem' . $planActive($providerKey) . '" href="' . $planUrl . '"><span class="nav-icon sub-icon">' . $navIcon('plans') . '</span><span>' . $label('Plans','Plans') . '</span></a></div></div>';
}
$nav .= '<a class="atd-nav-item' . $active('security') . '" href="/?section=security"><span class="nav-icon">' . $navIcon('security') . '</span><span>' . $label('امنیت', 'Security') . '</span></a>';
$nav .= '<a class="atd-nav-item' . $active('updates') . '" href="/?section=updates"><span class="nav-icon">' . $navIcon('updates') . '</span><span>' . $label('به‌روزرسانی', 'Updates') . '</span></a>';
$nav .= '</nav>';

/* Replace only navigation markup; all existing page bodies and integrations stay untouched. */
$html = preg_replace('~<nav\b[^>]*>.*?</nav>~is', $nav, $html, 1) ?? $html;

$style = <<<'CSS'
<style id="atd-panel-nav-style">
/* ATD sidebar: preserve the original visual language and SVG icons, only improve hierarchy/width. */
.app{grid-template-columns:300px minmax(0,1fr)}
.atd-nav{display:flex;flex-direction:column;gap:5px;padding:8px 6px}
.atd-nav-item,.atd-nav-subitem{box-sizing:border-box;text-decoration:none;transition:background .16s ease,transform .16s ease,box-shadow .16s ease}
.atd-nav-item{display:flex;align-items:center;gap:11px;min-height:44px;padding:10px 12px;border-radius:12px;color:inherit;font-weight:650}
.atd-nav-item:hover{background:rgba(127,127,127,.09);transform:translateX(-1px)}
.atd-nav-item.active{background:rgba(127,127,127,.13);box-shadow:inset 3px 0 0 currentColor}
.atd-nav-item .nav-icon{width:22px;min-width:22px;height:20px;display:grid;place-items:center;text-align:center;font-size:17px;line-height:1}
.atd-nav-item .nav-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.atd-nav-item>span:nth-child(2){flex:1;min-width:0;white-space:nowrap;overflow:visible;text-overflow:clip}
.provider-item .nav-chevron{margin-inline-start:auto;opacity:.55;font-size:21px;line-height:1;transition:transform .16s ease}
.atd-nav-group.expanded .nav-chevron{transform:rotate(90deg);opacity:.8}
.atd-nav-sub{display:none;margin:1px 0 4px 45px;padding-left:9px;border-left:1px solid rgba(127,127,127,.22)}
.atd-nav-group.expanded .atd-nav-sub{display:block}
.atd-nav-subitem{display:flex;align-items:center;gap:8px;min-height:38px;padding:8px 10px;border-radius:9px;color:inherit;font-size:.92em;font-weight:600;opacity:.78}
.atd-nav-subitem:hover{background:rgba(127,127,127,.08);opacity:1}
.atd-nav-subitem.active{background:rgba(127,127,127,.11);opacity:1}
.atd-nav-subitem .sub-icon{width:19px;min-width:19px;height:19px}
.atd-nav-subitem .sub-icon svg{width:17px;height:17px}
/* Keep a usable compact sidebar on narrow screens. */
@media (max-width:1050px){.app{grid-template-columns:270px minmax(0,1fr)}}
</style>
CSS;
if (stripos($html, '</head>') !== false) {
    $html = preg_replace('~</head>~i', $style . '</head>', $html, 1) ?? $html;
}

if ($section !== 'ibsng' && $section !== 'mikrotik' && $section !== 'provider-plans') {
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
