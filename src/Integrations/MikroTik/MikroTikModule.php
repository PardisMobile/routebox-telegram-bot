<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use PDO;

require_once __DIR__ . '/MikroTikSchema.php';
require_once __DIR__ . '/MikroTikClient.php';
require_once __DIR__ . '/MikroTikWireGuardService.php';
require_once __DIR__ . '/MikroTikWireGuardProvider.php';
require_once __DIR__ . '/MikroTikAdmin.php';
require_once __DIR__ . '/MikroTikSection.php';

final class MikroTikModule
{
    public static function admin(PDO $db): MikroTikAdmin
    {
        MikroTikSchema::migrate($db);
        return new MikroTikAdmin($db);
    }
}
