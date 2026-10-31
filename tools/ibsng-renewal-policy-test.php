<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Integrations/IBSng/IBSngRenewal.php';


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
    $actual = \\RouteBox\\Integrations\\IBSng\\IBSngRenewal::calculateRelativeExpiration(...$case['input']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, $case['name'] . " failed: " . json_encode($actual) . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $case['name'] . PHP_EOL;
}

try {
    IBSngRenewal::calculateRelativeExpiration(false, -1, 30, 0);
    fwrite(STDERR, "Invalid negative remaining days were accepted." . PHP_EOL);
    exit(1);
} catch (RuntimeException $e) {
    echo "PASS: invalid renewal duration values are rejected" . PHP_EOL;
}
