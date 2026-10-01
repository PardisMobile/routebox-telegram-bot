<?php

declare(strict_types=1);

/*
 * RouteBox Admin front controller.
 * Existing RouteBox sections continue to be rendered by index.core.php.
 * IBSng is only an additional section and uses the exact same shell.
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

/* Render the real RouteBox shell first, then replace only its content area. */
$originalGet = $_GET;
$_GET['section'] = 'dashboard';
ob_start();
require __DIR__ . '/index.core.php';
$html = (string)ob_get_clean();
$_GET = $originalGet;

function replaceStatsContent(string $html, string $body): string
{
    $start = strpos($html, '<div class="stats">');
    if ($start === false) return $html;

    $pos = $start;
    $depth = 0;
    $length = strlen($html);
    while ($pos < $length) {
        $nextOpen = stripos($html, '<div', $pos);
        $nextClose = stripos($html, '</div>', $pos);
        if ($nextClose === false) return $html;

        if ($nextOpen !== false && $nextOpen < $nextClose) {
            $depth++;
            $gt = strpos($html, '>', $nextOpen);
            if ($gt === false) return $html;
            $pos = $gt + 1;
        } else {
            $depth--;
            $pos = $nextClose + 6;
            if ($depth === 0) {
                $footer = preg_match('~<(?:div|footer)\s+class="footer"~i', $html, $m, PREG_OFFSET_CAPTURE, $pos)
                    ? $m[0][1]
                    : strpos($html, '</main>', $pos);
                if ($footer === false) return $html;
                return substr($html, 0, $pos) . $body . substr($html, $footer);
            }
        }
    }
    return $html;
}

$body = IBSngSection::render($ibsng, $lang, csrf_token());
$html = replaceStatsContent($html, $body);

$title = $lang === 'fa' ? 'مدیریت IBSng' : 'IBSng Management';
$subtitle = $lang === 'fa'
    ? 'مدیریت اتصال و تنظیمات IBSng از داخل پنل RouteBox'
    : 'Manage IBSng connectivity and settings from RouteBox Admin';
$titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$subtitleEsc = htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$html = preg_replace(
    '~(<header class="topbar">.*?<h1>).*?(</h1>.*?<p>).*?(</p>)~s',
    '$1' . $titleEsc . '$2' . $subtitleEsc . '$3',
    $html,
    1
) ?? $html;

/* Add IBSng to the existing RouteBox sidebar and make it the active item. */
$html = preg_replace(
    '~(<a class=")active(" aria-current="page" href="/\?section=dashboard")~',
    '$1$2',
    $html,
    1
) ?? $html;

$ibsngNav = '<a class="active" aria-current="page" href="/?section=ibsng">'
    . '<span class="nav-icon">' . IBSngSection::navIcon() . '</span><span>'
    . IBSngSection::navLabel($lang) . '</span></a>';
if (strpos($html, 'href="/?section=ibsng"') === false) {
    $html = str_replace('</nav>', $ibsngNav . '</nav>', $html, $count);
}

echo $html;
