<?php
declare(strict_types=1);

namespace RouteBox\Integrations;

use PDO;

/** Read-only catalog facade used by Telegram/Admin UI. */
final class ServiceCatalog
{
    public function __construct(private readonly PDO $db) {}

    public function categories(bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM service_categories'.($enabledOnly ? ' WHERE enabled=1' : '').' ORDER BY sort_order,id';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function plans(int $categoryId, bool $enabledOnly = true): array
    {
        $sql = 'SELECT * FROM service_plans WHERE category_id=?'.($enabledOnly ? ' AND enabled=1' : '').' ORDER BY sort_order,id';
        $q = $this->db->prepare($sql);
        $q->execute([$categoryId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
