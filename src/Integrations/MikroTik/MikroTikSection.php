<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

/**
 * MikroTik Admin Panel section foundation.
 *
 * Uses the existing RouteBox Admin shell conventions. Full rendering and
 * routing integration will be wired through the existing section registry.
 */
final class MikroTikSection
{
    public static function title(): string
    {
        return 'MikroTik WireGuard';
    }

    public static function render(): string
    {
        return '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">▤</div><div><h2>MikroTik WireGuard</h2><p>RouterOS API and WireGuard provider management</p></div></div></div><div class="help">MikroTik WireGuard admin foundation is ready.</div></section>';
    }
}
