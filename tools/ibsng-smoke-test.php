<?php
declare(strict_types=1);

// The provider implements the shared integration contract, so the contract
// must be loaded before PHP evaluates IBSngProvider. Keep the smoke test
// self-contained; it does not require the full application bootstrap.
require dirname(__DIR__) . '/src/Integrations/ServiceProviderInterface.php';
require dirname(__DIR__) . '/src/Integrations/IBSng/IBSngClient.php';
require dirname(__DIR__) . '/src/Integrations/IBSng/IBSngProvider.php';

use RouteBox\Integrations\IBSng\IBSngClient;
use RouteBox\Integrations\IBSng\IBSngProvider;

if ($argc < 4) {
    fwrite(STDERR, "Usage: php tools/ibsng-smoke-test.php HOST ADMIN_USER ADMIN_PASS [PORT]\n");
    exit(2);
}

$host = $argv[1];
$user = $argv[2];
$pass = $argv[3];
$port = isset($argv[4]) ? (int)$argv[4] : IBSngClient::defaultPort();

try {
    $client = new IBSngClient($host, $user, $pass, $port);
    $provider = new IBSngProvider($client);

    // Read-only smoke test: authentication + group listing only.
    $provider->testConnection();
    $groups = $client->listGroups();

    echo "OK: IBSng connection authenticated.\n";
    echo "Groups: " . count($groups) . "\n";
    foreach ($groups as $group) {
        echo " - {$group}\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
