<?php

declare(strict_types=1);

/*
 * RouteBox Admin front controller.
 * IBSng is an additional section rendered inside the exact same RouteBox shell.
 * There is intentionally no standalone public/ibsng.php page.
 */

$section = (string)($_GET['section'] ?? 'dashboard');

/*
 * Every section must use the existing RouteBox shell. For normal sections we
 * simply pass the request through. For IBSng we render the normal Dashboard
 * shell as a template, then replace only its main content area.
 */
if ($section !== 'ibsng') {
    ob_start();
    require __DIR__ . '/index.core.php';
    $html = (string)ob_get_clean();
} else {
    $originalGet = $_GET;
    $_GET['section'] = 'dashboard';
    ob_start();
    require __DIR__ . '/index.core.php';
    $html = (string)ob_get_clean();
    $_GET = $originalGet;
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
    '~(<header class="topbar">.*?<div><div class="eyebrow">.*?</div><h1>).*?(</h1><p>).*?(</p>)~s',
    '$1' . $titleEsc . '$2' . $subtitleEsc . '$3',
    $html,
    1
) ?? $html;

/* The Dashboard shell is only a template; replace its entire main content. */
$html = preg_replace(
    '~(</header>).*?(<div class="footer">)~s',
    '$1' . $body . '$2',
    $html,
    1
) ?? $html;

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
