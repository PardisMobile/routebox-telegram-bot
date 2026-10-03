<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();
require_once __DIR__ . '/../src/Admin/ATDWorker.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /?section=bot', true, 303);
    exit;
}

try {
    verify_csrf();
    $result = \RouteBox\Admin\ATDWorker::reload();
    $_SESSION['flash'] = ($result['ok'] ?? false)
        ? '✓ Worker reloaded successfully.'
        : '❌ ' . (string)($result['message'] ?? 'Worker reload failed.');
} catch (Throwable $e) {
    $_SESSION['flash'] = '❌ Worker reload failed: ' . $e->getMessage();
}

header('Location: /?section=bot', true, 303);
exit;
