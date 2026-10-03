<?php

declare(strict_types=1);

namespace RouteBox\Admin\Plans;

/**
 * Shared rules for plans shown/managed by ATD Panel.
 *
 * This policy is intentionally independent from any provider. A zero-priced
 * plan is treated as a non-paid/admin-only plan until an explicit product
 * policy says otherwise; it must not accidentally enter a normal paid flow.
 */
final class PlanPolicy
{
    public static function requiresPayment(array $plan): bool
    {
        return (int)($plan['price_minor'] ?? 0) > 0;
    }

    public static function isFree(array $plan): bool
    {
        return (int)($plan['price_minor'] ?? 0) === 0;
    }

    public static function validatePrice(int $priceMinor): void
    {
        if ($priceMinor < 0) {
            throw new \InvalidArgumentException('Plan price cannot be negative.');
        }
    }
}
