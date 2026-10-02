<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use PDO;
use RouteBox\Integrations\ServiceProviderInterface;
use RuntimeException;

/** MikroTik + WireGuard are intentionally one provider: mikrotik_wireguard. */
final class MikroTikWireGuardProvider implements ServiceProviderInterface
{
    private MikroTikWireGuardService $service;

    public function __construct(private readonly PDO $db, private readonly MikroTikClient $client)
    {
        $this->service = new MikroTikWireGuardService($client);
    }

    public function key(): string
    {
        return 'mikrotik_wireguard';
    }

    public function testConnection(): void
    {
        $this->client->testConnection();
    }

    public function listPlans(): array
    {
        $q = $this->db->query("SELECT * FROM service_plans WHERE provider_key='mikrotik_wireguard' AND enabled=1 ORDER BY sort_order,id");
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSubscription(array $context, array $plan): array
    {
        $serverId = (int)($plan['provider_server_id'] ?? 0);
        if ($serverId < 1) {
            throw new RuntimeException('MikroTik plan has no server configured.');
        }
        $server = $this->server($serverId);
        if (!$server) {
            throw new RuntimeException('MikroTik server is not active.');
        }

        $telegramId = (string)($context['telegram_id'] ?? $context['telegram_user_id'] ?? '');
        $username = trim((string)($context['username'] ?? ''));
        if ($username === '') {
            $username = 'rb' . preg_replace('/[^0-9]/', '', $telegramId) . '_' . bin2hex(random_bytes(3));
        }
        $username = substr($username, 0, 48);

        $metadata = json_decode((string)($plan['metadata_json'] ?? '{}'), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $expires = (int)($plan['duration_days'] ?? 0) > 0
            ? time() + ((int)$plan['duration_days'] * 86400)
            : null;
        $quota = (float)($plan['quota_gb'] ?? 0) > 0
            ? (int)round((float)$plan['quota_gb'] * 1024 * 1024 * 1024)
            : null;

        $this->db->beginTransaction();
        try {
            $now = time();
            $sub = $this->db->prepare(
                'INSERT INTO service_subscriptions(telegram_user_id,category_id,plan_id,provider_key,provider_server_id,provider_reference,username,password_enc,status,starts_at,expires_at,metadata_json,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $sub->execute([
                (int)($context['telegram_user_id'] ?? 0),
                (int)$plan['category_id'],
                (int)$plan['id'],
                'mikrotik_wireguard',
                $serverId,
                null,
                $username,
                null,
                'pending',
                $now,
                $expires,
                json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now,
                $now,
            ]);
            $subscriptionId = (int)$this->db->lastInsertId();

            $client = new MikroTikClient(
                (string)$server['host'],
                (string)$server['username'],
                dec((string)$server['password_enc']),
                (int)$server['api_port'],
                !empty($server['tls_mode'])
            );
            $service = new MikroTikWireGuardService($client);
            $created = $service->createPeer([
                'interface' => (string)$server['interface_name'],
                'pool' => (string)$server['pool_name'],
                'username' => $username,
                'endpoint' => (string)$server['vpn_endpoint'],
                'endpoint_port' => (int)$server['vpn_port'],
                'dns' => (string)$server['dns_servers'],
                'keepalive' => 25,
            ]);

            $queueId = '';
            $upload = trim((string)($metadata['upload_limit'] ?? ''));
            $download = trim((string)($metadata['download_limit'] ?? ''));
            if ($upload !== '' || $download !== '') {
                $queue = $client->createQueue([
                    'name' => 'rb-' . $subscriptionId,
                    'target' => $created['assigned_ip'] . '/32',
                    'max-limit' => ($upload !== '' ? $upload : '0') . '/' . ($download !== '' ? $download : '0'),
                ]);
                $queueId = (string)($queue['.id'] ?? '');
            }

            $meta = [
                'server_name' => (string)$server['name'],
                'interface' => (string)$created['interface'],
                'assigned_ip' => (string)$created['assigned_ip'],
                'public_key' => (string)$created['public_key'],
                'server_public_key' => (string)$created['server_public_key'],
                'endpoint' => (string)$created['endpoint'],
                'endpoint_port' => (int)$created['endpoint_port'],
                'dns' => (string)$created['dns'],
                'config' => (string)$created['config'],
                'quota_bytes' => $quota,
                'upload_limit' => $upload,
                'download_limit' => $download,
            ];

            $peer = $this->db->prepare(
                'INSERT INTO mikrotik_wireguard_peers(server_id,subscription_id,routeros_id,username,public_key,private_key_enc,assigned_ip,interface_name,status,expires_at,quota,upload_limit,download_limit,queue_id,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $peer->execute([
                $serverId,
                $subscriptionId,
                (string)$created['routeros_id'],
                $username,
                (string)$created['public_key'],
                enc((string)$created['private_key']),
                (string)$created['assigned_ip'],
                (string)$created['interface'],
                'active',
                $expires,
                $quota,
                $upload,
                $download,
                $queueId,
                $now,
                $now,
            ]);
            $peerId = (int)$this->db->lastInsertId();

            $this->db->prepare('UPDATE service_subscriptions SET provider_reference=?,password_enc=?,status=?,metadata_json=?,updated_at=? WHERE id=?')
                ->execute([
                    (string)$created['routeros_id'],
                    enc((string)$created['private_key']),
                    'active',
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    time(),
                    $subscriptionId,
                ]);

            $this->db->commit();
            return [
                'subscription_id' => $subscriptionId,
                'peer_id' => $peerId,
                'provider' => 'mikrotik_wireguard',
                'server_name' => (string)$server['name'],
                'username' => $username,
                'assigned_ip' => (string)$created['assigned_ip'],
                'public_key' => (string)$created['public_key'],
                'password' => (string)$created['private_key'],
                'config' => (string)$created['config'],
                'expires_at' => $expires,
                'quota' => $quota,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function renewSubscription(array $context, array $plan): array
    {
        $subscriptionId = (int)($context['subscription_id'] ?? 0);
        if ($subscriptionId < 1) {
            throw new RuntimeException('MikroTik subscription ID is required for renewal.');
        }
        $q = $this->db->prepare('SELECT * FROM mikrotik_wireguard_peers WHERE subscription_id=?');
        $q->execute([$subscriptionId]);
        $peer = $q->fetch(PDO::FETCH_ASSOC);
        if (!$peer) {
            throw new RuntimeException('MikroTik WireGuard peer was not found.');
        }
        $expires = time() + max(1, (int)$plan['duration_days']) * 86400;
        $this->db->prepare('UPDATE mikrotik_wireguard_peers SET status=\'active\',expires_at=?,updated_at=? WHERE id=?')->execute([$expires,time(),$peer['id']]);
        $this->db->prepare('UPDATE service_subscriptions SET status=\'active\',expires_at=?,updated_at=? WHERE id=?')->execute([$expires,time(),$subscriptionId]);
        $this->serviceForPeer($peer)->enablePeer((string)$peer['routeros_id']);
        return ['subscription_id' => $subscriptionId, 'expires_at' => $expires, 'username' => (string)$peer['username']];
    }

    public function getSubscription(array $context): array
    {
        $subscriptionId = (int)($context['subscription_id'] ?? 0);
        $q = $this->db->prepare('SELECT * FROM mikrotik_wireguard_peers WHERE subscription_id=?');
        $q->execute([$subscriptionId]);
        $peer = $q->fetch(PDO::FETCH_ASSOC);
        return $peer ?: [];
    }

    public function deleteSubscription(array $context): void
    {
        $subscriptionId = (int)($context['subscription_id'] ?? 0);
        $q = $this->db->prepare('SELECT * FROM mikrotik_wireguard_peers WHERE subscription_id=?');
        $q->execute([$subscriptionId]);
        $peer = $q->fetch(PDO::FETCH_ASSOC);
        if (!$peer) {
            return;
        }
        $this->serviceForPeer($peer)->deletePeer((string)$peer['routeros_id']);
        $this->db->prepare('UPDATE mikrotik_wireguard_peers SET status=\'deleted\',updated_at=? WHERE id=?')->execute([time(),$peer['id']]);
        $this->db->prepare('UPDATE service_subscriptions SET status=\'deleted\',updated_at=? WHERE id=?')->execute([time(),$subscriptionId]);
    }

    private function server(int $id): ?array
    {
        $q = $this->db->prepare('SELECT * FROM mikrotik_servers WHERE id=? AND enabled=1');
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function serviceForPeer(array $peer): MikroTikWireGuardService
    {
        $q = $this->db->prepare('SELECT * FROM mikrotik_servers WHERE id=?');
        $q->execute([(int)$peer['server_id']]);
        $server = $q->fetch(PDO::FETCH_ASSOC);
        if (!$server) {
            throw new RuntimeException('MikroTik server was not found.');
        }
        return new MikroTikWireGuardService(new MikroTikClient(
            (string)$server['host'],
            (string)$server['username'],
            dec((string)$server['password_enc']),
            (int)$server['api_port'],
            !empty($server['tls_mode'])
        ));
    }
}
