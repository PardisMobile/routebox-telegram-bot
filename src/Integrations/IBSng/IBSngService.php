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
        if (!$server) {
            throw new RuntimeException('IBSng server not found.');
        }
        return $server;
    }

    public function client(int $id): IBSngClient
    {
        $server = $this->server($id);
        return new IBSngClient(
            (string)$server['host'],
            dec((string)$server['admin_user_enc']),
            dec((string)$server['admin_pass_enc']),
            (int)$server['port']
        );
    }

    public function test(int $id): array
    {
        $this->client($id)->testConnection();
        $now = time();
        $this->db->prepare('UPDATE ibsng_servers SET last_test_at=?,last_error=NULL,updated_at=? WHERE id=?')
            ->execute([$now, $now, $id]);
        return ['ok' => true];
    }

    public function clearServerError(int $id): void
    {
        $this->db->prepare('UPDATE ibsng_servers SET last_error=NULL WHERE id=?')->execute([$id]);
    }

    public function addGroup(int $serverId, string $planName, string $groupName): int
    {
        $this->server($serverId);
        $planName = trim($planName);
        $groupName = trim($groupName);
        if ($planName === '' || $groupName === '') {
            throw new RuntimeException('نام پلن و نام گروه IBSng الزامی است.');
        }

        $st = $this->db->prepare(
            'INSERT INTO ibsng_groups(ibsng_server_id,group_name,group_id,group_info_json,enabled,synced_at,plan_name) VALUES(?,?,?,?,1,?,?)'
        );
        $st->execute([$serverId, $groupName, null, '{}', time(), $planName]);
        return (int)$this->db->lastInsertId();
    }

    public function updateGroup(int $id, string $planName, string $groupName): void
    {
        $planName = trim($planName);
        $groupName = trim($groupName);
        if ($planName === '' || $groupName === '') {
            throw new RuntimeException('نام پلن و نام گروه IBSng الزامی است.');
        }

        $q = $this->db->prepare('SELECT * FROM ibsng_groups WHERE id=?');
        $q->execute([$id]);
        $group = $q->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            throw new RuntimeException('گروه IBSng پیدا نشد.');
        }

        $oldName = (string)$group['group_name'];
        $serverId = (int)$group['ibsng_server_id'];
        if ($oldName !== $groupName) {
            $used = $this->db->prepare('SELECT COUNT(*) FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?');
            $used->execute(['ibsng', $serverId, $oldName]);
            if ((int)$used->fetchColumn() > 0) {
                throw new RuntimeException('این گروه در یک پلن استفاده شده است؛ ابتدا پلن را ویرایش کنید.');
            }
        }

        $st = $this->db->prepare('UPDATE ibsng_groups SET plan_name=?,group_name=?,updated_at=? WHERE id=?');
        // updated_at did not exist in the original table; use synced_at as the
        // last manual-change marker to keep the schema backward compatible.
        $st = $this->db->prepare('UPDATE ibsng_groups SET plan_name=?,group_name=?,synced_at=? WHERE id=?');
        $st->execute([$planName, $groupName, time(), $id]);
    }

    public function deleteGroup(int $id): void
    {
        $q = $this->db->prepare('SELECT * FROM ibsng_groups WHERE id=?');
        $q->execute([$id]);
        $group = $q->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            throw new RuntimeException('گروه IBSng پیدا نشد.');
        }

        $used = $this->db->prepare('SELECT COUNT(*) FROM service_plans WHERE provider_key=? AND provider_server_id=? AND provider_plan_key=?');
        $used->execute(['ibsng', (int)$group['ibsng_server_id'], (string)$group['group_name']]);
        if ((int)$used->fetchColumn() > 0) {
            throw new RuntimeException('این گروه در یک پلن استفاده شده و قابل حذف نیست.');
        }

        $this->db->prepare('DELETE FROM ibsng_groups WHERE id=?')->execute([$id]);
    }

    public function deleteServer(int $id): void
    {
        $this->server($id);

        $plans = $this->db->prepare('SELECT COUNT(*) FROM service_plans WHERE provider_key=? AND provider_server_id=?');
        $plans->execute(['ibsng', $id]);
        if ((int)$plans->fetchColumn() > 0) {
            throw new RuntimeException('این سرور در پلن‌های IBSng استفاده می‌شود؛ ابتدا پلن‌های مرتبط را حذف یا منتقل کنید.');
        }

        $subscriptions = $this->db->prepare('SELECT COUNT(*) FROM service_subscriptions WHERE provider_key=? AND provider_server_id=?');
        $subscriptions->execute(['ibsng', $id]);
        if ((int)$subscriptions->fetchColumn() > 0) {
            throw new RuntimeException('این سرور در اشتراک‌های IBSng استفاده می‌شود و فعلاً قابل حذف نیست.');
        }

        $this->db->prepare('DELETE FROM ibsng_groups WHERE ibsng_server_id=?')->execute([$id]);
        $this->db->prepare('DELETE FROM ibsng_servers WHERE id=?')->execute([$id]);
    }

    public function createAccount(int $serverId, string $ispName, string $groupName, string $username, string $password, int $credit = 0): array
    {
        $c = $this->client($serverId);
        $created = $c->createUser($ispName, $groupName, $credit);
        $ids = $created['user_ids'] ?? $created['users'] ?? $created;
        $userId = is_array($ids) ? reset($ids) : $ids;
        if ($userId === false || $userId === null || $userId === '') {
            throw new RuntimeException('IBSng did not return a user id.');
        }
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
        $category = (int)$this->db->query("SELECT id FROM service_categories WHERE service_key='ibsng'")->fetchColumn();
        if (!$category) {
            throw new RuntimeException('IBSng category is missing.');
        }

        $serverId = (int)$data['server_id'];
        $groupName = trim((string)$data['group_name']);
        $check = $this->db->prepare('SELECT COUNT(*) FROM ibsng_groups WHERE ibsng_server_id=? AND group_name=? AND enabled=1');
        $check->execute([$serverId, $groupName]);
        if ((int)$check->fetchColumn() < 1) {
            throw new RuntimeException('گروه IBSng انتخاب‌شده در پنل RouteBox تعریف نشده است.');
        }

        $now = time();
        $st = $this->db->prepare('INSERT INTO service_plans(category_id,provider_key,provider_server_id,provider_plan_key,display_name_fa,display_name_en,price_minor,duration_days,quota_gb,enabled,sort_order,metadata_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $category,
            'ibsng',
            $serverId,
            $groupName,
            (string)$data['name_fa'],
            (string)$data['name_en'],
            (int)$data['price'],
            (int)$data['days'],
            (float)$data['quota'],
            1,
            (int)$data['sort_order'],
            '{}',
            $now,
            $now,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
