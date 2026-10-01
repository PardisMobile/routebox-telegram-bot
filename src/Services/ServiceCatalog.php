<?php

declare(strict_types=1);

namespace RouteBox\Services;

use PDO;
use RouteBox\Integrations\IBSng\IBSngSchema;

require_once __DIR__ . '/../Integrations/IBSng/IBSngSchema.php';

/**
 * Generic catalog for services exposed by the Telegram bot.
 * Providers add categories/plans to the database; the worker does not need
 * to be rewritten just to make a new service category visible.
 */
final class ServiceCatalog
{
    public function __construct(private readonly PDO $db)
    {
        // Keep the catalog schema/data in sync whenever the catalog is used.
        // This also migrates legacy RouteBox plans into the modular catalog.
        IBSngSchema::ensure($db);
    }

    public function categories(string $providerKey = ''): array
    {
        $sql = "SELECT c.*, COUNT(p.id) AS plan_count
                FROM service_categories c
                LEFT JOIN service_plans p ON p.category_id=c.id AND p.enabled=1
                WHERE c.enabled=1";
        $params = [];
        if ($providerKey !== '') {
            $sql .= ' AND c.provider_key=?';
            $params[] = $providerKey;
        }
        $sql .= ' GROUP BY c.id HAVING plan_count > 0 ORDER BY c.sort_order,c.id';
        $q = $this->db->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function category(int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM service_categories WHERE id=? AND enabled=1');
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('سرویس پیدا نشد یا غیرفعال است.');
        }
        return $row;
    }

    public function plans(int $categoryId): array
    {
        $q = $this->db->prepare(
            'SELECT p.*, c.service_key, c.name_fa category_name_fa, c.name_en category_name_en, c.provider_key
             FROM service_plans p
             JOIN service_categories c ON c.id=p.category_id
             WHERE p.category_id=? AND p.enabled=1 AND c.enabled=1
             ORDER BY p.sort_order,p.id'
        );
        $q->execute([$categoryId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function plan(int $id): array
    {
        $q = $this->db->prepare(
            'SELECT p.*, c.service_key, c.name_fa category_name_fa, c.name_en category_name_en, c.provider_key
             FROM service_plans p
             JOIN service_categories c ON c.id=p.category_id
             WHERE p.id=? AND p.enabled=1 AND c.enabled=1'
        );
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('این پلن دیگر فعال نیست.');
        }
        return $row;
    }
}
