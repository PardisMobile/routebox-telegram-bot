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

$navItems = [
    'ibsng' => '<a class="' . ($section === 'ibsng' ? 'active' : '') . '"' . ($section === 'ibsng' ? ' aria-current="page"' : '') . ' href="/?section=ibsng"><span class="nav-icon">' . IBSngSection::navIcon() . '</span><span>' . IBSngSection::navLabel($lang) . '</span></a>',
    'mikrotik' => '<a class="' . ($section === 'mikrotik' ? 'active' : '') . '"' . ($section === 'mikrotik' ? ' aria-current="page"' : '') . ' href="/?section=mikrotik"><span class="nav-icon">' . MikroTikSection::navIcon() . '</span><span>' . MikroTikSection::navLabel($lang) . '</span></a>',
];
foreach ($navItems as $key => $nav) {
    if (strpos($html, 'href="/?section=' . $key . '"') === false) {
        $html = preg_replace('~(<nav\b[^>]*\bclass="[^"]*\bnav\b[^"]*"[^>]*>)(.*?)</nav>~is', '$1$2' . $nav . '</nav>', $html, 1) ?? $html;
    }
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
    $subtitle = $lang === 'fa' ? 'مدیریت RouterOS، WireGuard، کاربران و پلن‌ها' : 'Manage RouterOS, WireGuard peers and plans';
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
