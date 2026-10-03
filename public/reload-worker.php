<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

verify_csrf();

$services = ['routebox-telegram-bot.service', 'routebox-telegram-bot-dev.service'];
$service = '';
foreach ($services as $candidate) {
    $probe = trim((string)@shell_exec('systemctl is-enabled '.escapeshellarg($candidate).' 2>/dev/null'));
    $active = trim((string)@shell_exec('systemctl is-active '.escapeshellarg($candidate).' 2>/dev/null'));
    if ($probe !== '' || $active !== '' || is_file('/etc/systemd/system/'.$candidate)) {
        $service = $candidate;
        break;
    }
}
if ($service === '') $service = $services[0];

$command = 'systemctl restart '.escapeshellarg($service);
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    $output = [];
    $code = 0;
    exec($command.' 2>&1', $output, $code);
} else {
    $output = [];
    $code = 0;
    exec('sudo -n '.$command.' 2>&1', $output, $code);
}

if ($code === 0) {
    $_SESSION['flash'] = '✓ Telegram worker reloaded successfully: '.$service;
} else {
    $_SESSION['flash'] = '❌ Worker reload failed. Check the panel service permissions for: '.$service;
    log_event('error', 'Telegram worker reload failed: '.$service.' :: '.implode(' | ', $output));
}

header('Location: /?section=bot');
exit;
