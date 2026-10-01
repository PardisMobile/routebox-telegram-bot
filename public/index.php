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

/*
 * Build the real RouteBox shell using the existing core page.
 * We render Dashboard only as the shell source; its content is replaced below.
 */
$originalGet = $_GET;
$_GET['section'] = 'dashboard';
ob_start();
require __DIR__ . '/index.core.php';
$html = (string)ob_get_clean();
$_GET = $originalGet;

/** Find the matching closing div for a known opening <div>. */
function matchingDivEnd(string $html, int $start): ?int
{
    $open = stripos($html, '<div', $start);
    if ($open !== $start) return null;

    $depth = 0;
    $pos = $start;
    $length = strlen($html);

    while ($pos < $length) {
        $nextOpen = stripos($html, '<div', $pos);
        $nextClose = stripos($html, '</div>', $pos);
        if ($nextClose === false) return null;

        if ($nextOpen !== false && $nextOpen < $nextClose) {
            $depth++;
            $gt = strpos($html, '>', $nextOpen);
            if ($gt === false) return null;
            $pos = $gt + 1;
        } else {
            $depth--;
            $closeEnd = $nextClose + 6;
            if ($depth === 0) return $closeEnd;
            $pos = $closeEnd;
        }
    }

    return null;
}

/** Replace the dashboard content area while preserving the RouteBox shell. */
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

/* Add IBSng to the same sidebar navigation used by every other section. */
$nav = '<a class="active" aria-current="page" href="/?section=ibsng">'
    . '<span class="nav-icon">' . IBSngSection::navIcon() . '</span><span>'
    . IBSngSection::navLabel($lang) . '</span></a>';

if (strpos($html, 'href="/?section=ibsng"') === false) {
    $html = preg_replace('~(<nav[^>]*class="[^"]*nav[^"]*"[^>]*>)~i', '$1' . $nav, $html, 1) ?? $html;
}

/* Make Dashboard non-active on the IBSng page. */
$html = preg_replace(
    '~(<a class=")active("[^>]*href="/\?section=dashboard")~',
    '$1$2',
    $html,
    1
) ?? $html;

echo $html;
