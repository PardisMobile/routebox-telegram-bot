<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;
use RuntimeException;

final class IBSngService
{
    public function __construct(private readonly PDO $db)
    {
        IBSngSchema::ensure($db);
    }

    public function server(int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM ibsng_servers WHERE id=?');
        $q->execute([$id]);
        $server = $q->fetch(PDO::FETCH_ASSOC);
        if (!$server) throw new RuntimeException('IBSng server not found.');
        return $server;
    }

    public function client(int $id): IBSngClient
    {
        $server = $this->server($id);
        return new IBSngClient((string)$server['host'], dec((string)$server['admin_user_enc']), dec((string)$server['admin_pass_enc']), (int)$server['port']);
    }

    public function test(int $id): array
    {
        $this->client($id)->testConnection();
        $now = time();
        $this->db->prepare('UPDATE ibsng_servers SET last_test_at=?,last_error=NULL,updated_at=? WHERE id=?')->execute([$now, $now, $id]);
        return ['ok' => true];
    }

    public function addGroup(int $serverId, string $planName, string $groupName): int
    {
        $this->server($serverId);
        $planName = trim($planName);
        $groupName = trim($groupName);
        if ($planName === '' || $groupName === '') throw new RuntimeException('نام پلن و نام گروه IBSng الزامی است.');

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('INSERT INTO ibsng_groups(ibsng_server_id,group_name,group_id,group_info_json,enabled,synced_at,plan_name) VALUES(?,?,?,?,1,?,?)');
            $st->execute([$serverId, $groupName, null, '{}', time(), $planName]);
            $groupId = (int)$this->db->lastInsertId();
            $this->syncServicePlan($serverId, $groupName, $planName, true, $groupId);
            $this->db->commit();
            return $groupId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function updateGroup(int $id, string $planName, string $groupName): void
    {
        $planName = trim($planName);
        $groupName = trim($groupName);
        if ($planName === '' || $groupName === '') throw new RuntimeException('نام پلن و نام گروه IBSng الزامی است.');

        $q = $this->db->prepare('SELECT * FROM ibsng_groups WHERE id=?');
        $q->execute([$id]);
        $group = $q->fetch(PDO::FETCH_ASSOC);
        if (!$group) throw new RuntimeException('گروه IBSng پیدا نشد.');

        $oldName = (string)$group['group_name'];
        $serverId = (int)$group['ibsng_server_id'];
        $plan = $this->db->prepare('SELECT id FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?');
        $plan->execute(['ibsng', $serverId, $oldName]);
        $servicePlanId = (int)$plan->fetchColumn();

        if ($oldName !== $groupName) {
            $used = $this->db->prepare('SELECT COUNT(*) FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=? AND id<>?');
            $used->execute(['ibsng', $serverId, $groupName, $servicePlanId]);
            if ((int)$used->fetchColumn() > 0) throw new RuntimeException('این گروه قبلاً برای همین سرور تعریف شده است.');
        }

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('UPDATE ibsng_groups SET plan_name=?,group_name=?,synced_at=? WHERE id=?');
            $st->execute([$planName, $groupName, time(), $id]);

            if ($servicePlanId > 0) {
                $this->db->prepare('UPDATE service_plans SET provider_plan_key=?,display_name_fa=?,display_name_en=?,updated_at=? WHERE id=?')
                    ->execute([$groupName, $planName, $planName, time(), $servicePlanId]);
            } else {
                $this->syncServicePlan($serverId, $groupName, $planName, !empty($group['enabled']), $id);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function toggleGroup(int $id): void
    {
        $q = $this->db->prepare('SELECT * FROM ibsng_groups WHERE id=?');
        $q->execute([$id]);
        $group = $q->fetch(PDO::FETCH_ASSOC);
        if (!$group) throw new RuntimeException('گروه IBSng پیدا نشد.');

        $enabled = empty($group['enabled']) ? 1 : 0;
        $this->db->prepare('UPDATE ibsng_groups SET enabled=?,synced_at=? WHERE id=?')->execute([$enabled, time(), $id]);
        $this->db->prepare('UPDATE service_plans SET enabled=?,updated_at=? WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?')
            ->execute([$enabled, time(), 'ibsng', (int)$group['ibsng_server_id'], (string)$group['group_name']]);
    }

    public function deleteGroup(int $id): void
    {
        $q = $this->db->prepare('SELECT * FROM ibsng_groups WHERE id=?');
        $q->execute([$id]);
        $group = $q->fetch(PDO::FETCH_ASSOC);
        if (!$group) throw new RuntimeException('گروه IBSng پیدا نشد.');

        $used = $this->db->prepare('SELECT COUNT(*) FROM service_subscriptions WHERE provider_key=? AND provider_server_id=? AND plan_id IN (SELECT id FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?)');
        $used->execute(['ibsng', (int)$group['ibsng_server_id'], 'ibsng', (int)$group['ibsng_server_id'], (string)$group['group_name']]);
        if ((int)$used->fetchColumn() > 0) throw new RuntimeException('این گروه در اشتراک‌های فعال/قبلی استفاده شده و قابل حذف نیست.');

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?')
                ->execute(['ibsng', (int)$group['ibsng_server_id'], (string)$group['group_name']]);
            $this->db->prepare('DELETE FROM ibsng_groups WHERE id=?')->execute([$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteServer(int $id): void
    {
        $this->server($id);
        $plans = $this->db->prepare('SELECT COUNT(*) FROM service_plans WHERE provider_key=? AND provider_server_id=?');
        $plans->execute(['ibsng', $id]);
        if ((int)$plans->fetchColumn() > 0) throw new RuntimeException('این سرور در پلن‌های IBSng استفاده می‌شود؛ ابتدا پلن‌های مرتبط را حذف یا منتقل کنید.');

        $subscriptions = $this->db->prepare('SELECT COUNT(*) FROM service_subscriptions WHERE provider_key=? AND provider_server_id=?');
        $subscriptions->execute(['ibsng', $id]);
        if ((int)$subscriptions->fetchColumn() > 0) throw new RuntimeException('این سرور در اشتراک‌های IBSng استفاده می‌شود و فعلاً قابل حذف نیست.');

        $this->db->prepare('DELETE FROM ibsng_groups WHERE ibsng_server_id=?')->execute([$id]);
        $this->db->prepare('DELETE FROM ibsng_servers WHERE id=?')->execute([$id]);
    }

    private function syncServicePlan(int $serverId, string $groupName, string $planName, bool $enabled, int $sortOrder): int
    {
        $category = (int)$this->db->query("SELECT id FROM service_categories WHERE service_key='ibsng'")->fetchColumn();
        if ($category < 1) throw new RuntimeException('IBSng category is missing.');

        $existing = $this->db->prepare('SELECT id FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?');
        $existing->execute(['ibsng', $serverId, $groupName]);
        $id = (int)$existing->fetchColumn();
        $now = time();

        if ($id > 0) {
            $this->db->prepare('UPDATE service_plans SET display_name_fa=?,display_name_en=?,enabled=?,sort_order=?,updated_at=? WHERE id=?')
                ->execute([$planName, $planName, $enabled ? 1 : 0, $sortOrder, $now, $id]);
            return $id;
        }

        $st = $this->db->prepare('INSERT INTO service_plans(category_id,provider_key,provider_server_id,provider_plan_key,display_name_fa,display_name_en,price_minor,duration_days,quota_gb,enabled,sort_order,metadata_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$category, 'ibsng', $serverId, $groupName, $planName, $planName, 0, 0, 0, $enabled ? 1 : 0, $sortOrder, '{}', $now, $now]);
        return (int)$this->db->lastInsertId();
    }

    public function createAccount(int $serverId, string $ispName, string $groupName, string $username, string $password, int $credit = 0): array
    {
        $c = $this->client($serverId);
        $created = $c->createUser($ispName, $groupName, $credit);
        $ids = $created['user_ids'] ?? $created['users'] ?? $created;
        $userId = is_array($ids) ? reset($ids) : $ids;
        if ($userId === false || $userId === null || $userId === '') throw new RuntimeException('IBSng did not return a user id.');
        $c->setUserCredentials((string)$userId, $username, $password);
        return ['user_id' => (string)$userId, 'username' => $username, 'password' => $password, 'group' => $groupName];
    }

    public function userInfo(int $serverId, string $username): array
    {
        return $this->client($serverId)->getUserInfoByUsername($username);
    }

    public function renew(int $serverId, string $userId, string $comment = ''): mixed
    {
        return $this->client($serverId)->renewUser($userId, $comment);
    }

    public function changeGroup(int $serverId, string $userId, string $group): mixed
    {
        return $this->client($serverId)->changeUserGroup($userId, $group);
    }

    public function savePlan(array $data): int
    {
        return $this->syncServicePlan(
            (int)$data['server_id'],
            trim((string)$data['group_name']),
            trim((string)$data['name_fa']),
            true,
            (int)($data['sort_order'] ?? 0)
        );
    }
}
