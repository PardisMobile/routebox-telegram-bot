<?php

declare(strict_types=1);

namespace RouteBox\Services;

use PDO;
use RuntimeException;
use RouteBox\Integrations\IBSng\IBSngService;

require_once __DIR__ . '/../Integrations/IBSng/IBSngService.php';
require_once __DIR__ . '/../Integrations/IBSng/IBSngSchema.php';

/**
 * Provider-agnostic provisioning entry point for modular Telegram services.
 * The worker only calls provision(); provider-specific logic stays here.
 */
final class ServiceProvisioner
{
    private ServiceCatalog $catalog;

    public function __construct(private readonly PDO $db)
    {
        $this->catalog = new ServiceCatalog($db);
    }

    public function provision(int $telegramUserId, string $telegramId, int $planId): array
    {
        $plan = $this->catalog->plan($planId);
        $provider = (string)$plan['provider_key'];

        return match ($provider) {
            'ibsng' => $this->provisionIbsng($telegramUserId, $telegramId, $plan),
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

        // Keep credentials unique and easy to type. The IBSng group is the
        // source of service/charge rules; initial credit is intentionally 0.
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
        $days = max(0, (int)$plan['duration_days']);
        $expires = $days > 0 ? $now + ($days * 86400) : null;
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
}
