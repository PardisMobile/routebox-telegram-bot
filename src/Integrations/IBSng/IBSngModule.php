<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;

/**
 * IBSng integration entry point.
 *
 * This file contains no HTML and must never be used as a browser endpoint.
 * All IBSng browser UI is rendered by the main RouteBox Admin shell.
 */
final class IBSngModule
{
    public static function admin(PDO $db): IBSngAdmin
    {
        return new IBSngAdmin($db);
    }
}
