<?php
declare(strict_types=1);

namespace RouteBox\Integrations;

use RuntimeException;

/** Provider-neutral facade. Telegram/UI code should depend on this layer. */
final class ServiceRouter
{
    /** @var array<string,ServiceProviderInterface> */
    private array $providers = [];

    public function register(ServiceProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function provider(string $key): ServiceProviderInterface
    {
        if (!isset($this->providers[$key])) {
            throw new RuntimeException('Service provider is not registered: '.$key);
        }
        return $this->providers[$key];
    }

    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
