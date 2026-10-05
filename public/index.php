<?php

declare(strict_types=1);

// ATD Panel compatibility layer. Keep provider implementations untouched.
require_once __DIR__ . '/../src/Admin/ATDUICompatibility.php';
\RouteBox\Admin\ATDUICompatibility::start();

// Presentation-only cleanup for the unified ?section=users page.
// The global ATD Panel header already renders the Users title, so the
// users section must not render a second section header or Back button.
ob_start(static function (string $html): string {
    if ((string)($_GET['section'] ?? '') === 'users') {
        $html = str_replace(
            '</head>',
            '<style id="atd-users-header-cleanup">.atd-user-page > .section-head{display:none!important}</style></head>',
            $html
        );
    }
    return $html;
});

$__atdRouteboxAlias = ((string)($_GET['section'] ?? 'dashboard')) === 'routebox';
if ($__atdRouteboxAlias) {
    // The original RouteBox section is still internally named `servers`.
    // The public UI URL is now `section=routebox` without changing provider logic.
    $_GET['section'] = 'servers';
    $_POST['section'] = $_POST['section'] ?? 'servers';
    header_register_callback(static function (): void {
        $hasLocation = false;
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) { $hasLocation = true; break; }
        }
        if ($hasLocation) {
            header_remove('Location');
            header('Location: /?section=routebox');
        }
    });
}

require __DIR__ . '/index.legacy.php';
