<?php

declare(strict_types=1);

namespace RouteBox\Services;

use PDO;
use RuntimeException;
use RouteBox\RouteBoxClient;
use RouteBox\Integrations\IBSng\IBSngTrialCleanup;
use RouteBox\Integrations\MikroTik\MikroTikClient;
use RouteBox\Integrations\MikroTik\MikroTikWireGuardProvider;

require_once __DIR__ . '/ServiceProvisioner.php';
require_once __DIR__ . '/../RouteBoxClient.php';
require_once __DIR__ . '/../Integrations/IBSng/IBSngTrialCleanup.php';
require_once __DIR__ . '/../Integrations/MikroTik/MikroTikClient.php';
require_once __DIR__ . '/../Integrations/MikroTik/MikroTikWireGuardProvider.php';

final class TrialService
{
    public static function ensureSchema(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_trial_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL UNIQUE,
            plan_id INTEGER NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE CASCADE,
            FOREIGN KEY(plan_id) REFERENCES service_plans(id) ON DELETE RESTRICT
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_trial_instances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            telegram_user_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            plan_id INTEGER,
            provider_key TEXT NOT NULL,
            provider_reference_json TEXT NOT NULL DEFAULT '{}',
            status TEXT NOT NULL DEFAULT 'active',
            claimed_at INTEGER NOT NULL,
            expires_at INTEGER,
            cleanup_error TEXT,
            deleted_at INTEGER,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            FOREIGN KEY(telegram_user_id) REFERENCES telegram_users(id) ON DELETE CASCADE,
            FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE RESTRICT,
            FOREIGN KEY(plan_id) REFERENCES service_plans(id) ON DELETE SET NULL
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_trial_instances_expiry ON telegram_trial_instances(status,expires_at)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_trial_instances_user ON telegram_trial_instances(telegram_user_id,created_at DESC)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_trial_instances_provider ON telegram_trial_instances(provider_key,status)');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_trial_instances_user_category ON telegram_trial_instances(telegram_user_id,category_id)');
    }

    public static function planForCategory(PDO $db, int $categoryId): ?array
    {
        self::ensureSchema($db);
        $q = $db->prepare('SELECT p.*, c.service_key, c.provider_key AS category_provider, c.name_fa AS category_name_fa, c.name_en AS category_name_en FROM telegram_trial_plans t JOIN service_plans p ON p.id=t.plan_id JOIN service_categories c ON c.id=t.category_id WHERE t.category_id=? AND t.enabled=1 AND p.enabled=1 AND c.enabled=1 AND p.category_id=c.id AND p.provider_key=c.provider_key AND p.duration_days>0 LIMIT 1');
        $q->execute([$categoryId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function categoriesWithPlans(PDO $db): array
    {
        self::ensureSchema($db);
        $cats = $db->query('SELECT id,service_key,provider_key,name_fa,name_en FROM service_categories WHERE enabled=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cats as &$cat) {
            $q = $db->prepare('SELECT id,display_name_fa,display_name_en,duration_days,quota_gb,price_minor,provider_key,enabled FROM service_plans WHERE category_id=? ORDER BY sort_order,id');
            $q->execute([(int)$cat['id']]);
            $cat['plans'] = $q->fetchAll(PDO::FETCH_ASSOC);

            $q = $db->prepare('SELECT plan_id FROM telegram_trial_plans WHERE category_id=? AND enabled=1 LIMIT 1');
            $q->execute([(int)$cat['id']]);
            $value = $q->fetchColumn();
            $cat['trial_plan_id'] = $value === false ? null : (int)$value;
        }
        unset($cat);
        return $cats;
    }

    public static function savePlan(PDO $db, int $categoryId, int $planId, bool $enabled): void
    {
        self::ensureSchema($db);
        if ($planId < 1) {
            if ($enabled) {
                throw new RuntimeException('Trial Plan انتخاب نشده است.');
            }
            $db->prepare('DELETE FROM telegram_trial_plans WHERE category_id=?')->execute([$categoryId]);
            return;
        }

        $q = $db->prepare('SELECT c.provider_key AS category_provider, p.provider_key, p.category_id, p.enabled, p.duration_days FROM service_categories c JOIN service_plans p ON p.id=? WHERE c.id=?');
        $q->execute([$planId, $categoryId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string)$row['category_provider'] !== (string)$row['provider_key'] || (int)$row['category_id'] !== $categoryId) {
            throw new RuntimeException('Trial Plan و Provider/Category مطابقت ندارند.');
        }
        if ($enabled && ((int)$row['enabled'] !== 1 || (int)$row['duration_days'] < 1)) {
            throw new RuntimeException('Trial Plan باید فعال و دارای مدت معتبر باشد.');
        }
        if (!$enabled) {
            $db->prepare('DELETE FROM telegram_trial_plans WHERE category_id=?')->execute([$categoryId]);
            return;
        }

        $now = time();
        $db->prepare('INSERT INTO telegram_trial_plans(category_id,plan_id,enabled,created_at,updated_at) VALUES(?,?,1,?,?) ON CONFLICT(category_id) DO UPDATE SET plan_id=excluded.plan_id,enabled=1,updated_at=excluded.updated_at')->execute([$categoryId, $planId, $now, $now]);
    }

    public static function provision(PDO $db, int $uid, string $telegramId, int $categoryId): array
    {
        self::ensureSchema($db);
        $plan = self::planForCategory($db, $categoryId);
        if (!$plan) {
            throw new RuntimeException('برای این Provider هنوز Trial Plan فعال نشده است.');
        }

        $now = time();
        $claim = $db->prepare('INSERT OR IGNORE INTO telegram_trial_instances(telegram_user_id,category_id,plan_id,provider_key,status,claimed_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)');
        $claim->execute([$uid, $categoryId, (int)$plan['id'], (string)$plan['provider_key'], 'provisioning', $now, $now, $now]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('این حساب قبلاً تست رایگان این Provider را دریافت کرده است.');
        }

        $trialId = (int)$db->lastInsertId();
        try {
            $result = (new ServiceProvisioner($db))->provision($uid, $telegramId, (int)$plan['id']);
            $refs = [
                'subscription_id' => (int)($result['subscription_id'] ?? 0),
                'provision_ids' => array_values(array_map(
                    static fn($item): int => (int)($item['provision_id'] ?? 0),
                    (array)($result['items'] ?? [])
                )),
            ];

            $subscriptionId = (int)$refs['subscription_id'];
            if ($subscriptionId > 0) {
                $q = $db->prepare('SELECT metadata_json FROM service_subscriptions WHERE id=?');
                $q->execute([$subscriptionId]);
                $metadata = json_decode((string)$q->fetchColumn(), true);
                if (!is_array($metadata)) $metadata = [];
                $metadata['is_trial'] = 1;
                $db->prepare('UPDATE service_subscriptions SET metadata_json=?,updated_at=? WHERE id=?')->execute([
                    json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    time(),
                    $subscriptionId,
                ]);
            }

            $expires = (int)($result['expires_at'] ?? 0);
            if ($expires < 1 && !empty($refs['provision_ids'])) {
                $placeholders = implode(',', array_fill(0, count($refs['provision_ids']), '?'));
                $q = $db->prepare("SELECT MAX(expires_at) FROM provisions WHERE id IN ($placeholders)");
                $q->execute($refs['provision_ids']);
                $expires = (int)$q->fetchColumn();
            }

            $db->prepare("UPDATE telegram_trial_instances SET plan_id=?,provider_key=?,provider_reference_json=?,status='active',claimed_at=?,expires_at=?,cleanup_error=NULL,updated_at=? WHERE id=?")->execute([
                (int)$plan['id'],
                (string)$plan['provider_key'],
                json_encode($refs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now,
                $expires > 0 ? $expires : null,
                time(),
                $trialId,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $db->prepare('DELETE FROM telegram_trial_instances WHERE id=? AND status=?')->execute([$trialId, 'provisioning']);
            throw $e;
        }
    }

    public static function recordLegacyRoutebox(PDO $db, int $uid, int $categoryId, array $result): void
    {
        self::ensureSchema($db);
        $ids = [];
        foreach ($result as $item) {
            $id = (int)($item['provision_id'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        if (!$ids) return;

        $q = $db->prepare('SELECT expires_at FROM provisions WHERE id=?');
        $q->execute([$ids[0]]);
        $expires = (int)$q->fetchColumn();
        $now = time();
        $db->prepare('INSERT OR IGNORE INTO telegram_trial_instances(telegram_user_id,category_id,plan_id,provider_key,provider_reference_json,status,claimed_at,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([
            $uid,
            $categoryId,
            null,
            'routebox',
            json_encode(['provision_ids' => $ids], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'active',
            $now,
            $expires > 0 ? $expires : null,
            $now,
            $now,
        ]);
    }

    public static function listTrials(PDO $db, int $limit = 100): array
    {
        self::ensureSchema($db);
        $limit = max(1, min(500, $limit));
        $sql = "SELECT t.*,u.telegram_id,u.username,u.first_name,c.name_fa,c.name_en,p.display_name_fa,p.display_name_en
                FROM telegram_trial_instances t
                JOIN telegram_users u ON u.id=t.telegram_user_id
                JOIN service_categories c ON c.id=t.category_id
                LEFT JOIN service_plans p ON p.id=t.plan_id
                ORDER BY CASE
                    WHEN t.status='active' AND t.expires_at IS NOT NULL AND t.expires_at<=strftime('%s','now') THEN 0
                    WHEN t.status='expired' THEN 1
                    ELSE 2
                END,t.created_at DESC LIMIT {$limit}";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteExpired(PDO $db, int $trialId): array
    {
        self::ensureSchema($db);
        $q = $db->prepare('SELECT * FROM telegram_trial_instances WHERE id=? LIMIT 1');
        $q->execute([$trialId]);
        $trial = $q->fetch(PDO::FETCH_ASSOC);
        if (!$trial) throw new RuntimeException('Trial پیدا نشد.');
        if ((string)$trial['status'] === 'deleted') return ['status' => 'deleted'];
        if ($trial['expires_at'] === null || (int)$trial['expires_at'] > time()) {
            throw new RuntimeException('این Trial هنوز منقضی نشده است.');
        }

        $refs = json_decode((string)$trial['provider_reference_json'], true);
        if (!is_array($refs)) $refs = [];
        $provider = (string)$trial['provider_key'];

        try {
            if ($provider === 'routebox') {
                foreach ((array)($refs['provision_ids'] ?? []) as $provisionId) {
                    $provisionId = (int)$provisionId;
                    $s = $db->prepare('SELECT p.*,r.base_url,r.user_enc,r.pass_enc,r.verify_tls FROM provisions p JOIN routebox_servers r ON r.id=p.server_id WHERE p.id=? AND p.telegram_user_id=?');
                    $s->execute([$provisionId, (int)$trial['telegram_user_id']]);
                    $row = $s->fetch(PDO::FETCH_ASSOC);
                    if (!$row) continue;
                    $client = new RouteBoxClient((string)$row['base_url'], dec((string)$row['user_enc']), dec((string)$row['pass_enc']), (bool)$row['verify_tls']);
                    $client->deletePeer((string)$row['public_key']);
                    $db->prepare('DELETE FROM provisions WHERE id=?')->execute([$provisionId]);
                }
            } elseif ($provider === 'mikrotik_wireguard') {
                $subscriptionId = (int)($refs['subscription_id'] ?? 0);
                if ($subscriptionId < 1) throw new RuntimeException('MikroTik Trial subscription reference is missing.');
                $q = $db->prepare("SELECT * FROM service_subscriptions WHERE id=? AND provider_key='mikrotik_wireguard'");
                $q->execute([$subscriptionId]);
                $subscription = $q->fetch(PDO::FETCH_ASSOC);
                if ($subscription) {
                    $q = $db->prepare('SELECT * FROM mikrotik_servers WHERE id=?');
                    $q->execute([(int)$subscription['provider_server_id']]);
                    $server = $q->fetch(PDO::FETCH_ASSOC);
                    if (!$server) throw new RuntimeException('MikroTik server not found.');
                    $client = new MikroTikClient((string)$server['host'], (string)$server['username'], dec((string)$server['password_enc']), (int)$server['api_port'], !empty($server['tls_mode']));
                    (new MikroTikWireGuardProvider($db, $client))->deleteSubscription(['subscription_id' => $subscriptionId]);
                    $db->prepare('DELETE FROM service_subscriptions WHERE id=?')->execute([$subscriptionId]);
                }
            } elseif ($provider === 'ibsng') {
                $subscriptionId = (int)($refs['subscription_id'] ?? 0);
                if ($subscriptionId < 1) throw new RuntimeException('IBSng Trial subscription reference is missing.');
                $q = $db->prepare("SELECT * FROM service_subscriptions WHERE id=? AND provider_key='ibsng'");
                $q->execute([$subscriptionId]);
                $subscription = $q->fetch(PDO::FETCH_ASSOC);
                if (!$subscription) throw new RuntimeException('IBSng Trial subscription not found.');
                $q = $db->prepare('SELECT * FROM ibsng_servers WHERE id=?');
                $q->execute([(int)$subscription['provider_server_id']]);
                $server = $q->fetch(PDO::FETCH_ASSOC);
                if (!$server) throw new RuntimeException('IBSng server not found.');
                IBSngTrialCleanup::deleteUser($server, (string)$subscription['provider_reference']);
                $db->prepare('DELETE FROM service_subscriptions WHERE id=?')->execute([$subscriptionId]);
            } else {
                throw new RuntimeException('Provider Trial cleanup is not supported.');
            }

            $db->prepare("UPDATE telegram_trial_instances SET status='deleted',deleted_at=?,cleanup_error=NULL,updated_at=? WHERE id=?")->execute([time(), time(), $trialId]);
            return ['status' => 'deleted'];
        } catch (\Throwable $e) {
            $db->prepare("UPDATE telegram_trial_instances SET status='expired',cleanup_error=?,updated_at=? WHERE id=?")->execute([$e->getMessage(), time(), $trialId]);
            throw $e;
        }
    }
}
