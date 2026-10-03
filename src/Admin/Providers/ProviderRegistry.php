<?php

declare(strict_types=1);

namespace RouteBox\Admin\Providers;

/**
 * Provider registry used by the ATD Panel shell.
 *
 * Registration is intentionally explicit: existing RouteBox, IBSng and
 * MikroTik modules are not changed until their adapters are migrated to this
 * contract. New providers such as V2Ray can then be registered without
 * changing the shared navigation/plan architecture.
 */
final class ProviderRegistry
{
    /** @var array<string, ProviderAdminInterface> */
    private array $providers = [];

    public function register(ProviderAdminInterface $provider): void
    {
        $key = trim($provider->key());
        if ($key === '') {
            throw new \InvalidArgumentException('Provider key cannot be empty.');
        }
        $this->providers[$key] = $provider;
    }

    /** @return array<string, ProviderAdminInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $key): ?ProviderAdminInterface
    {
        return $this->providers[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }
}
