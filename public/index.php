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

// Keep provider/server names compact in Persian mode. The legacy localization
// helper translates these labels into longer Persian phrases; ATD keeps the
// provider names in English to preserve the compact desktop sidebar baseline.
echo <<<'HTML'
<script>
(function(){
    const restoreProviderLabels = function(){
        const labels = {
            'سرورهای RouteBox': 'RouteBox Servers',
            'سرورهای Routebox': 'Routebox Servers',
            'سرورهای IBSng': 'IBSng Servers',
            'سرورهای MikroTik WireGuard': 'MikroTik WireGuard'
        };
        document.querySelectorAll('.sidebar .nav span').forEach(function(el){
            const text = (el.textContent || '').trim();
            if (labels[text]) el.textContent = labels[text];
        });
    };
    restoreProviderLabels();
})();
</script>
HTML;
