<?php

declare(strict_types=1);

// ATD Panel compatibility layer. Keep provider implementations untouched.
require_once __DIR__ . '/../src/Admin/ATDUICompatibility.php';
\RouteBox\Admin\ATDUICompatibility::start();

$__atdRouteboxAlias = ((string)($_GET['section'] ?? 'dashboard')) === 'routebox';
if ($__atdRouteboxAlias) {
    // The original RouteBox section is still internally named `servers`.
    // The public UI URL is now `section=routebox` without changing provider logic.
    $_GET['section'] = 'servers';
    $_POST['section'] = $_POST['section'] ?? 'servers';
    header_register_callback(static function (): void {
        header_remove('Location');
        header('Location: /?section=routebox');
    });
}

require __DIR__ . '/index.legacy.php';
