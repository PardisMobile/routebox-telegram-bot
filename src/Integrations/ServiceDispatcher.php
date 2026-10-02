<?php
declare(strict_types=1);

namespace RouteBox\Integrations;

use RuntimeException;

/** Dispatches service operations without exposing provider implementations to Telegram. */
final class ServiceDispatcher
{
    public function __construct(private readonly ServiceRouter $router) {}

    public function create(string $providerKey, array $context, array $plan): array
    {
        return $this->router->provider($providerKey)->createSubscription($context, $plan);
    }

    public function renew(string $providerKey, array $context, array $plan): array
    {
        return $this->router->provider($providerKey)->renewSubscription($context, $plan);
    }

    public function info(string $providerKey, array $context): array
    {
        return $this->router->provider($providerKey)->getSubscription($context);
    }

    public function delete(string $providerKey, array $context): void
    {
        $this->router->provider($providerKey)->deleteSubscription($context);
    }

    public function test(string $providerKey): void
    {
        $this->router->provider($providerKey)->testConnection();
    }
}
