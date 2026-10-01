<?php

declare(strict_types=1);

/*
 * RouteBox Admin front controller.
 * IBSng is an additional section rendered inside the exact same RouteBox shell.
 * There is intentionally no standalone public/ibsng.php page.
 */

$section = (string)($_GET['section'] ?? 'dashboard');

if ($section !== 'ibsng') {
    require __DIR__ . '/index.core.php';
    exit;
}

require __DIR__ . '/../src/bootstrap.php';
require_admin();
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngModule.php';
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngSection.php';

use RouteBox\Integrations\IBSng\IBSngModule;
use RouteBox\Integrations\IBSng\IBSngSection;

$lang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';
$ibsng = IBSngModule::admin(db());

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ibsng->handle($_POST, 'POST');
    header('Location: /?section=ibsng');
    exit;
}

/* Build the exact existing RouteBox shell using the normal core page. */
$originalGet = $_GET;
$_GET['section'] = 'dashboard';
ob_start();
require __DIR__ . '/index.core.php';
$html = (string)ob_get_clean();
$_GET = $originalGet;

/** Replace the dashboard main-content block while preserving the shell. */
function replaceDashboardContent(string $html, string $body): string
{
    $stats = strpos($html, '<div class="stats">');
    if ($stats === false) return $html;

    $footer = preg_match('~<(?:div|footer)\s+class="footer"~i', $html, $m, PREG_OFFSET_CAPTURE, $stats)
        ? (int)$m[0][1]
        : null;
    if ($footer === null) return $html;

    return substr($html, 0, $stats) . $body . substr($html, $footer);
}

$body = IBSngSection::render($ibsng, $lang, csrf_token());
$html = replaceDashboardContent($html, $body);

/* Replace only the page heading inside the existing RouteBox topbar. */
$title = $lang === 'fa' ? 'مدیریت IBSng' : 'IBSng Management';
$subtitle = $lang === 'fa'
    ? 'مدیریت اتصال و تنظیمات IBSng از داخل پنل RouteBox'
    : 'Manage IBSng connectivity and settings from RouteBox Admin';
$titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$subtitleEsc = htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = preg_replace(
    '~(<div class="topbar">.*?<h1>).*?(</h1>.*?<p>).*?(</p>)~s',
    '$1' . $titleEsc . '$2' . $subtitleEsc . '$3',
    $html,
    1
) ?? $html;

/* Insert IBSng into the exact same sidebar navigation element used by RouteBox. */
$ibsngNav = '<a class="active" aria-current="page" href="/?section=ibsng">'
    . '<span class="nav-icon">' . IBSngSection::navIcon() . '</span><span>'
    . IBSngSection::navLabel($lang) . '</span></a>';

$navMarker = '<nav class="nav" aria-label="Main navigation">';
if (strpos($html, 'href="/?section=ibsng"') === false) {
    $html = str_replace($navMarker, $navMarker . $ibsngNav, $html, $navCount);
}

/* Make Dashboard non-active on the IBSng page. */
$html = preg_replace(
    '~(<a class=")active("[^>]*href="/\?section=dashboard")~',
    '$1$2',
    $html,
    1
) ?? $html;

echo $html;
