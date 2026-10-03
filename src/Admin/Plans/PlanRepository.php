<?php

declare(strict_types=1);

namespace RouteBox\Admin\Plans;

use PDO;

/**
 * Provider-neutral persistence layer for service plans.
 *
 * This class deliberately does not know how a provider provisions a service.
 * It only owns the shared plan fields used by the Admin Panel. Existing
 * provider provisioning code continues to consume the same service_plans
 * records through the existing ServiceCatalog/ServiceProvisioner flow.
 */
final class PlanRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(?string $providerKey = null, bool $includeDisabled = true): array
    {
        $sql = 'SELECT p.*, c.service_key, c.name_fa category_name_fa, c.name_en category_name_en, c.provider_key category_provider_key
                FROM service_plans p
                JOIN service_categories c ON c.id=p.category_id';
        $where = [];
        $params = [];

        if ($providerKey !== null && $providerKey !== '') {
            $where[] = 'p.provider_key=?';
            $params[] = $providerKey;
        }
        if (!$includeDisabled) {
            $where[] = 'p.enabled=1';
            $where[] = 'c.enabled=1';
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY c.sort_order,c.id,p.sort_order,p.id';

        $q = $this->db->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $q = $this->db->prepare(
            'SELECT p.*, c.service_key, c.name_fa category_name_fa, c.name_en category_name_en, c.provider_key category_provider_key
             FROM service_plans p
             JOIN service_categories c ON c.id=p.category_id
             WHERE p.id=?'
        );
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $now = time();
        $q = $this->db->prepare(
            'INSERT INTO service_plans
             (category_id,provider_key,provider_server_id,provider_plan_key,display_name_fa,display_name_en,
              price_minor,duration_days,quota_gb,enabled,sort_order,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $q->execute([
            (int)$data['category_id'],
            (string)$data['provider_key'],
            $data['provider_server_id'] === null ? null : (int)$data['provider_server_id'],
            $data['provider_plan_key'] ?? null,
            (string)$data['display_name_fa'],
            (string)$data['display_name_en'],
            (int)$data['price_minor'],
            (int)$data['duration_days'],
            (float)$data['quota_gb'],
            !empty($data['enabled']) ? 1 : 0,
            (int)($data['sort_order'] ?? 0),
            (string)($data['metadata_json'] ?? '{}'),
            $now,
            $now,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $q = $this->db->prepare(
            'UPDATE service_plans SET
                category_id=?, provider_key=?, provider_server_id=?, provider_plan_key=?,
                display_name_fa=?, display_name_en=?, price_minor=?, duration_days=?, quota_gb=?,
                enabled=?, sort_order=?, metadata_json=?, updated_at=?
             WHERE id=?'
        );
        $q->execute([
            (int)$data['category_id'],
            (string)$data['provider_key'],
            $data['provider_server_id'] === null ? null : (int)$data['provider_server_id'],
            $data['provider_plan_key'] ?? null,
            (string)$data['display_name_fa'],
            (string)$data['display_name_en'],
            (int)$data['price_minor'],
            (int)$data['duration_days'],
            (float)$data['quota_gb'],
            !empty($data['enabled']) ? 1 : 0,
            (int)($data['sort_order'] ?? 0),
            (string)($data['metadata_json'] ?? '{}'),
            time(),
            $id,
        ]);
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $q = $this->db->prepare('UPDATE service_plans SET enabled=?,updated_at=? WHERE id=?');
        $q->execute([$enabled ? 1 : 0, time(), $id]);
    }
}
