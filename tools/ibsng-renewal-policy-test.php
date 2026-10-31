<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Integrations/IBSng/IBSngClient.php';


$cases = [
    [
        'name' => 'expired account resets baseline and zero gift remains zero',
        'input' => [true, 999, 30, 0],
        'expected' => ['relative_expiration' => 30, 'reset_first_login' => true],
    ],
    [
        'name' => 'active account preserves remaining days and adds plan plus gift',
        'input' => [false, 5, 30, 2],
        'expected' => ['relative_expiration' => 37, 'reset_first_login' => false],
    ],
    [
        'name' => 'active account with zero gift receives plan validity only',
        'input' => [false, 0, 30, 0],
        'expected' => ['relative_expiration' => 30, 'reset_first_login' => false],
    ],
];

foreach ($cases as $case) {
    $actual = \RouteBox\Integrations\IBSng\IBSngRenewal::calculateRelativeExpiration(...$case['input']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, $case['name'] . " failed: " . json_encode($actual) . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $case['name'] . PHP_EOL;
}


$now = strtotime('2027-01-01 00:00:00 UTC');
$notStarted = \RouteBox\Integrations\IBSng\IBSngClient::normalizeRenewalState(
    '101',
    null,
    '30 Days',
    null,
    $now
);
if ($notStarted['expired'] !== false || $notStarted['remaining_days'] !== 30 || $notStarted['first_login'] !== null) {
    fwrite(STDERR, "Not-yet-started IBSng account state was not preserved." . PHP_EOL);
    exit(1);
}
echo "PASS: not-yet-started account keeps relative duration without resetting First Login" . PHP_EOL;

$active = \RouteBox\Integrations\IBSng\IBSngClient::normalizeRenewalState(
    '102',
    '2026-12-30 00:00:00',
    '30 Days',
    '2027-01-03 00:00:00 UTC',
    $now
);
if ($active['expired'] !== false || $active['remaining_days'] !== 2) {
    fwrite(STDERR, "Active IBSng account remaining days were calculated incorrectly." . PHP_EOL);
    exit(1);
}
echo "PASS: active account remaining days are derived from nearest expiry" . PHP_EOL;

$expired = \RouteBox\Integrations\IBSng\IBSngClient::normalizeRenewalState(
    '103',
    '2026-12-01 00:00:00',
    '30 Days',
    '2026-12-31 23:59:59 UTC',
    $now
);
if ($expired['expired'] !== true || $expired['remaining_days'] !== 0) {
    fwrite(STDERR, "Expired IBSng account was not detected." . PHP_EOL);
    exit(1);
}
echo "PASS: expired account state is detected" . PHP_EOL;

try {
    \RouteBox\Integrations\IBSng\IBSngRenewal::calculateRelativeExpiration(false, -1, 30, 0);
    fwrite(STDERR, "Invalid negative remaining days were accepted." . PHP_EOL);
    exit(1);
} catch (RuntimeException $e) {
    echo "PASS: invalid renewal duration values are rejected" . PHP_EOL;
}
