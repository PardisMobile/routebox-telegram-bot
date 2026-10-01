<?php

declare(strict_types=1);

/*
 * RouteBox Admin front controller.
 * IBSng is an additional section rendered inside the exact same RouteBox shell.
 * There is intentionally no standalone public/ibsng.php page.
 */

$requestedSection = (string)($_GET['section'] ?? 'dashboard');
$section = $requestedSection;

/*
 * Every section must use the existing RouteBox shell. For normal sections we
 * simply pass the request through. For IBSng we render the normal Dashboard
 * shell as a template, then replace only its main content area.
 *
 * IMPORTANT: index.core.php uses the variable name $section itself. Because
 * PHP includes execute in the current scope, its value would otherwise leak
 * back here as "dashboard" and IBSng would never reach its renderer.
 */
if ($requestedSection !== 'ibsng') {
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

use RouteBox\Integrations\IBSng\IBSngModule;
use RouteBox\Integrations\IBSng\IBSngSection;

$lang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';

/* IBSng is a permanent item in the same RouteBox navigation. */
$ibsngActive = $section === 'ibsng';
$ibsngNav = '<a class="' . ($ibsngActive ? 'active' : '') . '"'
    . ($ibsngActive ? ' aria-current="page"' : '')
    . ' href="/?section=ibsng">'
    . '<span class="nav-icon">' . IBSngSection::navIcon() . '</span><span>'
    . IBSngSection::navLabel($lang) . '</span></a>';

if (strpos($html, 'href="/?section=ibsng"') === false) {
    $html = preg_replace(
        '~(<nav\b[^>]*\bclass="[^"]*\bnav\b[^"]*"[^>]*>)(.*?)</nav>~is',
        '$1$2' . $ibsngNav . '</nav>',
        $html,
        1
    ) ?? $html;
}

if ($section !== 'ibsng') {
    echo $html;
    exit;
}

/*
 * Build the IBSng admin object after index.core.php has loaded bootstrap and
 * authenticated the admin. IBSngModule is logic-only; it never emits a page.
 */
$ibsng = IBSngModule::admin(db());

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ibsng->handle($_POST, 'POST');
    header('Location: /?section=ibsng');
    exit;
}

$body = IBSngSection::render($ibsng, $lang, csrf_token());

$title = $lang === 'fa' ? 'مدیریت IBSng' : 'IBSng Management';
$subtitle = $lang === 'fa'
    ? 'مدیریت اتصال و تنظیمات IBSng از داخل پنل RouteBox'
    : 'Manage IBSng connectivity and settings from RouteBox Admin';
$titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$subtitleEsc = htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/* Make the existing RouteBox topbar describe the active IBSng section. */
$html = preg_replace(
    '~(<header class="topbar">.*?<div><div class="eyebrow">).*?(</div><h1>).*?(</h1><p>).*?(</p>)~s',
    '$1ROUTEBOX TELEGRAM BOT$2' . $titleEsc . '$3' . $subtitleEsc . '$4',
    $html,
    1
) ?? $html;

/*
 * The shell is rendered by index.core.php, but its main content is generated
 * for Dashboard. Replace only everything after the topbar and before the
 * existing footer. Use exact string positions instead of a broad regex so a
 * small markup change in another section cannot make this silently fail.
 */
$headerEnd = strpos($html, '</header>');
$footerStart = strpos($html, '<div class="footer">', $headerEnd === false ? 0 : $headerEnd);
if ($headerEnd !== false && $footerStart !== false && $footerStart > $headerEnd) {
    $contentStart = $headerEnd + strlen('</header>');
    $html = substr($html, 0, $contentStart) . "\n\n" . $body . "\n\n" . substr($html, $footerStart);
}

/* The template was generated as Dashboard; IBSng must be the only active item. */
$html = preg_replace_callback(
    '~<a\b([^>]*)href="/\?section=dashboard"([^>]*)>~i',
    static function (array $m): string {
        $attrs = $m[1] . $m[2];
        $attrs = preg_replace('/\s+class="active"/i', '', $attrs) ?? $attrs;
        $attrs = preg_replace('/\s+aria-current="page"/i', '', $attrs) ?? $attrs;
        return '<a' . $attrs . ' href="/?section=dashboard">';
    },
    $html,
    1
) ?? $html;

echo $html;
