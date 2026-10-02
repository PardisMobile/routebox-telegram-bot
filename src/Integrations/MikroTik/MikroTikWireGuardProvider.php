<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use RouteBox\Integrations\ServiceProviderInterface;
use RuntimeException;

/**
 * MikroTik WireGuard provider.
 *
 * MikroTik and WireGuard intentionally remain one provider:
 * mikrotik_wireguard.
 */
final class MikroTikWireGuardProvider implements ServiceProviderInterface
{
    public function __construct(private readonly MikroTikClient $client) {}

    public function key(): string
    {
        return 'mikrotik_wireguard';
    }

    public function testConnection(): void
    {
        $this->client->testConnection();
    }

    public function listPlans(): array
    {
        return [];
    }

    public function createSubscription(array $context, array $plan): array
    {
        throw new RuntimeException('MikroTik WireGuard provisioning foundation is not enabled yet.');
    }

    public function renewSubscription(array $context, array $plan): array
    {
        throw new RuntimeException('MikroTik WireGuard renewal is not enabled yet.');
    }

    public function getSubscription(array $context): array
    {
        return [];
    }

    public function deleteSubscription(array $context): void
    {
        throw new RuntimeException('MikroTik WireGuard deletion is not enabled yet.');
    }
}
