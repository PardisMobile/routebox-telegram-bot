<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

/**
 * Admin service layer for MikroTik WireGuard management.
 *
 * Keeps MikroTik UI operations isolated from the provider and provisioning
 * layers, following the same separation used by IBSng.
 */
final class MikroTikAdmin
{
    public function testConnection(array $server): array
    {
        $client = new MikroTikClient(
            (string)($server['host'] ?? ''),
            (string)($server['username'] ?? ''),
            (string)($server['password'] ?? ''),
            (int)($server['api_port'] ?? 8728),
            !empty($server['tls'])
        );

        return $client->testConnection();
    }
}
