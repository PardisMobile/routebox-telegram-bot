<?php

declare(strict_types=1);

namespace RouteBox\Integrations;

/**
 * Common contract for provisioning backends.
 *
 * RouteBox/AWG, IBSng, MikroTik and future backends can implement this
 * contract without moving their implementation into the Telegram worker.
 */
interface ServiceProviderInterface
{
    public function key(): string;

    public function testConnection(): void;

    public function listPlans(): array;

    public function createSubscription(array $context, array $plan): array;

    public function renewSubscription(array $context, array $plan): array;

    public function getSubscription(array $context): array;

    public function deleteSubscription(array $context): void;
}
