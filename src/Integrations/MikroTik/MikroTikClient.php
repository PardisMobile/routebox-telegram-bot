<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use RuntimeException;

/**
 * RouterOS API foundation client.
 *
 * This layer intentionally keeps MikroTik communication isolated from the
 * Telegram worker and provisioning layer.
 *
 * Future implementations can add RouterOS API/REST transport without changing
 * the service provider contract.
 */
final class MikroTikClient
{
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 8728,
        private readonly bool $tls = false,
        private readonly int $timeout = 10,
    ) {
        if ($this->host === '' || $this->username === '') {
            throw new RuntimeException('MikroTik connection settings are incomplete.');
        }
    }

    public function testConnection(): array
    {
        return [
            'status' => 'pending',
            'message' => 'RouterOS API transport foundation created. Connection adapter will be added without changing provider architecture.',
        ];
    }

    public function routerInfo(): array
    {
        return [];
    }

    public function wireguardInterfaces(): array
    {
        return [];
    }
}
