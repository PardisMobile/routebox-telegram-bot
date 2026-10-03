<?php

declare(strict_types=1);

namespace RouteBox\Admin\Providers;

/**
 * Stable extension contract for provider-specific Admin Panel modules.
 *
 * A provider owns its server/settings UI, provider plans, and provider guide.
 * The shared ATD Panel shell supplies navigation, layout, security and
 * reusable plan/user/payment components.
 */
interface ProviderAdminInterface
{
    public function key(): string;

    public function label(string $lang = 'fa'): string;

    /** Return the provider navigation slug used by the Admin Panel. */
    public function section(): string;

    /** Render provider server/configuration management. */
    public function renderServers(string $lang, string $csrfToken): string;

    /** Render the shared provider-scoped plan management screen. */
    public function renderPlans(string $lang, string $csrfToken): string;

    /** Render provider-specific connection/setup documentation. */
    public function renderGuide(string $lang): string;
}
