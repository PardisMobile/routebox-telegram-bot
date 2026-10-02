<?php

declare(strict_types=1);

namespace RouteBox\Services;

use PDO;
use RuntimeException;
use RouteBox\Integrations\IBSng\IBSngService;
use RouteBox\Integrations\MikroTik\MikroTikClient;
use RouteBox\Integrations\MikroTik\MikroTikSchema;
use RouteBox\Integrations\MikroTik\MikroTikWireGuardProvider;

require_once __DIR__ . '/../RouteBoxClient.php';
require_once __DIR__ . '/../Integrations/IBSng/IBSngClient.php';
require_once __DIR__ . '/../Integrations/IBSng/IBSngService.php';
require_once __DIR__ . '/../Integrations/IBSng/IBSngSchema.php';
require_once __DIR__ . '/../Integrations/MikroTik/MikroTikClient.php';
require_once __DIR__ . '/../Integrations/MikroTik/MikroTikSchema.php';
require_once __DIR__ . '/../Integrations/MikroTik/MikroTikWireGuardService.php';
require_once __DIR__ . '/../Integrations/MikroTik/MikroTikWireGuardProvider.php';

/**
 * Provider-agnostic provisioning entry point for modular Telegram services.
 * Existing RouteBox and IBSng provisioning paths remain unchanged; MikroTik
 * WireGuard is added as another provider selected by service_plans.provider_key.
 */
final class ServiceProvisioner
{
    private ServiceCatalog $catalog;

    public function __construct(private readonly PDO $db)
    {
        $this->catalog = new ServiceCatalog($db);
        MikroTikSchema::migrate($db);
    }

    public function provision(int $telegramUserId, string $telegramId, int $planId): array
    {
        $plan = $this->catalog->plan($planId);
        $provider = (string)$plan['provider_key'];

        return match ($provider) {
            'ibsng' => $this->provisionIbsng($telegramUserId, $telegramId, $plan),
            'routebox' => $this->provisionRoutebox($telegramUserId, $telegramId, $plan),
            'mikrotik_wireguard' => $this->provisionMikroTik($telegramUserId, $telegramId, $plan),
            default => throw new RuntimeException('ارائه‌دهنده این سرویس هنوز برای ربات فعال نشده است.'),
        };
    }

    private function provisionIbsng(int $telegramUserId, string $telegramId, array $plan): array
    {
        $serverId = (int)($plan['provider_server_id'] ?? 0);
        $groupName = trim((string)($plan['provider_plan_key'] ?? ''));
        if ($serverId < 1 || $groupName === '') {
            throw new RuntimeException('تنظیمات سرور یا گروه IBSng این پلن کامل نیست.');
        }

        $q = $this->db->prepare('SELECT * FROM ibsng_servers WHERE id=? AND enabled=1');
        $q->execute([$serverId]);
        $server = $q->fetch(PDO::FETCH_ASSOC);
        if (!$server) {
            throw new RuntimeException('سرور IBSng این پلن فعال نیست.');
        }

        $q = $this->db->prepare('SELECT * FROM ibsng_groups WHERE ibsng_server_id=? AND group_name=? AND enabled=1');
        $q->execute([$serverId, $groupName]);
        $group = $q->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            throw new RuntimeException('گروه IBSng این پلن در RouteBox پیدا نشد.');
        }

        $suffix = strtolower(bin2hex(random_bytes(3)));
        $username = 'rb' . preg_replace('/[^0-9]/', '', $telegramId) . $suffix;
        $username = substr($username, 0, 32);
        $password = bin2hex(random_bytes(6));

        $service = new IBSngService($this->db);
        $created = $service->createAccount(
            $serverId,
            (string)($server['isp_name'] ?? 'Main'),
            $groupName,
            $username,
            $password,
            0
        );

        $now = time();
        $expires = null;
        $meta = [
            'protocols' => ['openvpn', 'cisco', 'l2tp'],
            'group_name' => $groupName,
            'plan_name_fa' => (string)$plan['display_name_fa'],
            'plan_name_en' => (string)$plan['display_name_en'],
        ];

        $st = $this->db->prepare(
            'INSERT INTO service_subscriptions(telegram_user_id,category_id,plan_id,provider_key,provider_server_id,provider_reference,username,password_enc,status,starts_at,expires_at,metadata_json,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $telegramUserId,
            (int)$plan['category_id'],
            (int)$plan['id'],
            'ibsng',
            $serverId,
            (string)$created['user_id'],
            $username,
            enc($password),
            'active',
            $now,
            $expires,
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $now,
            $now,
        ]);

        return [
            'subscription_id' => (int)$this->db->lastInsertId(),
            'provider' => 'ibsng',
            'server_name' => (string)$server['name'],
            'group_name' => $groupName,
            'plan_name_fa' => (string)$plan['display_name_fa'],
            'plan_name_en' => (string)$plan['display_name_en'],
            'username' => $username,
            'password' => $password,
            'user_id' => (string)$created['user_id'],
            'expires_at' => $expires,
        ];
    }

    private function provisionMikroTik(int $telegramUserId, string $telegramId, array $plan): array
    {
        $serverId = (int)($plan['provider_server_id'] ?? 0);
        if ($serverId < 1) {
            throw new RuntimeException('تنظیمات سرور MikroTik این پلن کامل نیست.');
        }
        $q = $this->db->prepare('SELECT * FROM mikrotik_servers WHERE id=? AND enabled=1');
        $q->execute([$serverId]);
        $server = $q->fetch(PDO::FETCH_ASSOC);
        if (!$server) {
            throw new RuntimeException('سرور MikroTik این پلن فعال نیست.');
        }

        $client = new MikroTikClient(
            (string)$server['host'],
            (string)$server['username'],
            dec((string)$server['password_enc']),
            (int)$server['api_port'],
            !empty($server['tls_mode'])
        );
        $provider = new MikroTikWireGuardProvider($this->db, $client);
        return $provider->createSubscription([
            'telegram_user_id' => $telegramUserId,
            'telegram_id' => $telegramId,
        ], $plan);
    }

    private function provisionRoutebox(int $telegramUserId, string $telegramId, array $plan): array
    {
        $meta = json_decode((string)($plan['metadata_json'] ?? '{}'), true);
        $legacyPlanId = is_array($meta) ? (int)($meta['legacy_plan_id'] ?? 0) : 0;
        if ($legacyPlanId < 1) {
            throw new RuntimeException('تنظیمات پلن RouteBox کامل نیست.');
        }

        $q = $this->db->prepare('SELECT * FROM plans WHERE id=? AND enabled=1');
        $q->execute([$legacyPlanId]);
        $legacy = $q->fetch(PDO::FETCH_ASSOC);
        if (!$legacy) {
            throw new RuntimeException('این پلن RouteBox دیگر فعال نیست.');
        }

        $servers = $this->db->query('SELECT * FROM routebox_servers WHERE enabled=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if (!$servers) {
            throw new RuntimeException('هیچ RouteBox فعالی تنظیم نشده است.');
        }

        $seconds = max(1, (int)$legacy['duration_days']) * 86400;
        $quota = null;
        $gb = (float)$legacy['quota_gb'];
        if ($gb > 0) {
            $quota = (int)round($gb * 1024 * 1024 * 1024);
        }

        $expires = time() + $seconds;
        $peer = 'user' . $telegramId . '-' . bin2hex(random_bytes(3));
        $created = [];
        $out = [];

        try {
            foreach ($servers as $server) {
                $client = new \RouteBoxClient(
                    (string)$server['base_url'],
                    dec((string)$server['user_enc']),
                    dec((string)$server['pass_enc']),
                    (bool)$server['verify_tls']
                );
                $createdPeer = $client->createPeer($peer);
                $key = (string)($createdPeer['public_key'] ?? $createdPeer['publicKey'] ?? '');
                if ($key === '') {
                    throw new RuntimeException('RouteBox public key was not returned.');
                }
                $created[] = [$client, $key];
                $client->setExpiry($key, $expires, $quota);
                $conf = $client->config($key);

                $st = $this->db->prepare('INSERT INTO provisions(telegram_user_id,server_id,peer_name,public_key,expires_at,created_at) VALUES(?,?,?,?,?,?)');
                $st->execute([$telegramUserId, $server['id'], $peer, $key, $expires, time()]);

                $out[] = [
                    'provision_id' => (int)$this->db->lastInsertId(),
                    'server' => (string)$server['name'],
                    'conf' => $conf,
                    'expires' => $expires,
                    'plan' => (string)$legacy['name'],
                    'public_key' => $key,
                ];
            }
        } catch (\Throwable $e) {
            foreach ($created as [$client, $key]) {
                try {
                    $client->deletePeer($key);
                } catch (\Throwable $rollbackError) {
                    log_event('error', 'Provision rollback failed: ' . $rollbackError->getMessage());
                }
            }
            throw $e;
        }

        log_event('info', 'Provisioned ' . $telegramId . ' with RouteBox plan ' . $legacy['name']);
        return [
            'provider' => 'routebox',
            'plan_name_fa' => (string)$plan['display_name_fa'],
            'plan_name_en' => (string)$plan['display_name_en'],
            'expires_at' => $expires,
            'items' => $out,
        ];
    }
}
