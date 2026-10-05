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

// Register after the existing TrialAdminPanel callback so this final
// presentation pass can deterministically repair only the Trial UI.
if (function_exists('db')) {
    require_once __DIR__ . '/../src/Admin/TrialUIEnhancer.php';
    \RouteBox\Admin\TrialUIEnhancer::boot(db());
}
