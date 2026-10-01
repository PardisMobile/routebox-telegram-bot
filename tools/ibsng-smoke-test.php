<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Integrations/ServiceProviderInterface.php';
require dirname(__DIR__) . '/src/Integrations/IBSng/IBSngClient.php';
require dirname(__DIR__) . '/src/Integrations/IBSng/IBSngProvider.php';

use RouteBox\Integrations\IBSng\IBSngClient;
use RouteBox\Integrations\IBSng\IBSngProvider;

function usage(): never
{
    fwrite(STDERR, <<<TXT
Usage:
  php tools/ibsng-smoke-test.php HOST ADMIN_USER ADMIN_PASS [PORT]
  php tools/ibsng-smoke-test.php HOST ADMIN_USER ADMIN_PASS get USERNAME [PORT]
  php tools/ibsng-smoke-test.php HOST ADMIN_USER ADMIN_PASS create GROUP [ISP] [CREDIT] [PORT]

The default transport for IBSng A1.24 Free Edition is the Apache Web Panel
on port 80. This test does NOT require or open IBSng ports 1235/1237.

TXT);
    exit(2);
}

if ($argc < 4) {
    usage();
}

$host = $argv[1];
$user = $argv[2];
$pass = $argv[3];
$mode = 'test';
$arg = 4;

if (isset($argv[4]) && in_array(strtolower($argv[4]), ['get', 'create'], true)) {
    $mode = strtolower($argv[4]);
    $arg = 5;
}

if ($mode === 'test') {
    $port = isset($argv[4]) ? (int)$argv[4] : IBSngClient::defaultPort();
} elseif ($mode === 'get') {
    if (!isset($argv[$arg])) usage();
    $targetUsername = $argv[$arg++];
    $port = isset($argv[$arg]) ? (int)$argv[$arg] : IBSngClient::defaultPort();
} else {
    if (!isset($argv[$arg])) usage();
    $group = $argv[$arg++];
    $isp = $argv[$arg] ?? 'Main';
    if (isset($argv[$arg])) $arg++;
    $credit = isset($argv[$arg]) ? (int)$argv[$arg] : 0;
    if (isset($argv[$arg])) $arg++;
    $port = isset($argv[$arg]) ? (int)$argv[$arg] : IBSngClient::defaultPort();
}

try {
    $client = new IBSngClient($host, $user, $pass, $port);
    $provider = new IBSngProvider($client);

    if ($mode === 'test') {
        $provider->testConnection();
        $groups = $client->listGroups();
        echo "OK: IBSng A1.24 Web Panel authenticated.\n";
        echo "Transport: HTTP/Web Panel\n";
        echo "Groups: " . count($groups) . "\n";
        foreach ($groups as $groupName) {
            echo " - {$groupName}\n";
        }
        exit(0);
    }

    if ($mode === 'get') {
        $info = $client->getUserInfoByUsername($targetUsername);
        echo "OK: IBSng user found.\n";
        echo "Username: {$targetUsername}\n";
        if (isset($info['user_id'])) echo "User ID: {$info['user_id']}\n";
        if (isset($info['group_name'])) echo "Group: {$info['group_name']}\n";
        echo "Raw user page was received successfully.\n";
        exit(0);
    }

    $created = $client->createUser($isp, $group, $credit);
    echo "OK: IBSng user created.\n";
    echo "Group: {$group}\n";
    echo "User ID: " . (string)($created['user_id'] ?? $created['user_ids'][0] ?? '') . "\n";
    echo "Note: A1.24 creates the account first; username/password can be assigned in the next provider step.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
