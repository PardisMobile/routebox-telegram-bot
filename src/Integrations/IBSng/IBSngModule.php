<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;

require_once __DIR__ . '/IBSngSchema.php';
require_once __DIR__ . '/IBSngClient.php';
require_once __DIR__ . '/IBSngService.php';
require_once __DIR__ . '/IBSngAdmin.php';
require_once __DIR__ . '/IBSngSection.php';

/**
 * IBSng integration entry point.
 *
 * This module contains integration/admin logic only. It never emits HTML and
 * must never be used as a browser endpoint. The browser UI is rendered by
 * the main RouteBox Admin shell.
 */
final class IBSngModule
{
    public static function admin(PDO $db): IBSngAdmin
    {
        // Ensure the IBSng integration tables exist before the admin view
        // queries them. This prevents a fresh install from returning HTTP 500.
        IBSngSchema::ensure($db);
        return new IBSngAdmin($db);
    }
}
