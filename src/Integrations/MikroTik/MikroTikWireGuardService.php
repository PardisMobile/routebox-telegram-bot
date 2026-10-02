<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

/**
 * Provider service layer for MikroTik WireGuard accounts.
 *
 * A WireGuard peer is modeled as a service account, matching the existing
 * RouteBox/IBSng subscription architecture.
 */
final class MikroTikWireGuardService
{
    public function __construct(private readonly MikroTikClient $client) {}

    public function createPeer(array $context): array
    {
        return [
            'status' => 'pending',
            'message' => 'Peer allocation will use RouterOS WireGuard pools in the next implementation phase.',
        ];
    }

    public function getPeer(string $username): array
    {
        return [];
    }

    public function disablePeer(string $username): void
    {
    }
}
