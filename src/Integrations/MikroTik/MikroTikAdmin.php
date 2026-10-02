<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use PDO;
use RuntimeException;

/** Controller for the MikroTik section inside the existing RouteBox Admin shell. */
final class MikroTikAdmin
{
    public function __construct(private readonly PDO $db)
    {
        MikroTikSchema::migrate($db);
    }

    public function handle(array $post, string $method): void
    {
        if ($method !== 'POST') {
            return;
        }
        verify_csrf();
        $action = trim((string)($post['action'] ?? ''));
        try {
            switch ($action) {
                case 'add_server':
                    $data = $this->serverInput($post);
                    $client = $this->clientFromData($data);
                    $result = $client->testConnection();
                    $interfaces = $result['wireguard_interfaces'] ?? [];
                    if (!$interfaces) {
                        throw new RuntimeException('RouterOS is reachable, but no WireGuard interface was found.');
                    }
                    $now = time();
                    $this->db->prepare(
                        'INSERT INTO mikrotik_servers(name,host,api_port,username,password_enc,tls_mode,vpn_endpoint,vpn_port,interface_name,pool_name,dns_servers,enabled,created_at,updated_at,last_test_at,last_error)
                         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL)'
                    )->execute([
                        $data['name'], $data['host'], $data['api_port'], $data['username'], enc($data['password']), $data['tls_mode'],
                        $data['vpn_endpoint'], $data['vpn_port'], $data['interface_name'] ?: (string)($interfaces[0]['name'] ?? ''),
                        $data['pool_name'], $data['dns_servers'] ?: (string)($result['dns']['servers'] ?? ''), 1, $now, $now, $now,
                    ]);
                    $_SESSION['mikrotik_flash'] = '✓ MikroTik connection succeeded and the server was saved.';
                    $_SESSION['mikrotik_error'] = false;
                    break;

                case 'update_server':
                    $id = (int)($post['id'] ?? 0);
                    $old = $this->server($id);
                    if (!$old) throw new RuntimeException('MikroTik server was not found.');
                    $data = $this->serverInput($post, $old);
                    $client = $this->clientFromData($data);
                    $result = $client->testConnection();
                    $interfaces = $result['wireguard_interfaces'] ?? [];
                    if (!$interfaces) throw new RuntimeException('No WireGuard interface was found on this RouterOS device.');
                    $this->db->prepare(
                        'UPDATE mikrotik_servers SET name=?,host=?,api_port=?,username=?,password_enc=?,tls_mode=?,vpn_endpoint=?,vpn_port=?,interface_name=?,pool_name=?,dns_servers=?,last_test_at=?,last_error=NULL,updated_at=? WHERE id=?'
                    )->execute([
                        $data['name'], $data['host'], $data['api_port'], $data['username'], enc($data['password']), $data['tls_mode'],
                        $data['vpn_endpoint'], $data['vpn_port'], $data['interface_name'], $data['pool_name'], $data['dns_servers'], time(), time(), $id,
                    ]);
                    $_SESSION['mikrotik_flash'] = '✓ MikroTik server settings were updated and tested.';
                    $_SESSION['mikrotik_error'] = false;
                    break;

                case 'test_server':
                    $server = $this->server((int)($post['id'] ?? 0));
                    if (!$server) throw new RuntimeException('MikroTik server was not found.');
                    $client = $this->client($server);
                    $result = $client->testConnection();
                    $this->db->prepare('UPDATE mikrotik_servers SET last_test_at=?,last_error=NULL,updated_at=? WHERE id=?')->execute([time(),time(),$server['id']]);
                    $_SESSION['mikrotik_flash'] = '✓ RouterOS ' . (string)($result['router']['version'] ?? 'unknown') . ' connected; WireGuard interfaces: ' . count($result['wireguard_interfaces'] ?? []);
                    $_SESSION['mikrotik_error'] = false;
                    break;

                case 'delete_server':
                    $id = (int)($post['id'] ?? 0);
                    $this->db->prepare('DELETE FROM mikrotik_servers WHERE id=?')->execute([$id]);
                    $_SESSION['mikrotik_flash'] = '✓ MikroTik server removed.';
                    $_SESSION['mikrotik_error'] = false;
                    break;

                case 'add_plan':
                    $serverId = (int)($post['server_id'] ?? 0);
                    $server = $this->server($serverId);
                    if (!$server) throw new RuntimeException('Select a valid MikroTik server.');
                    $categoryId = (int)$this->db->query("SELECT id FROM service_categories WHERE service_key='mikrotik_wireguard'")->fetchColumn();
                    if ($categoryId < 1) throw new RuntimeException('MikroTik service category is unavailable.');
                    $nameFa = trim((string)($post['display_name_fa'] ?? ''));
                    $nameEn = trim((string)($post['display_name_en'] ?? ''));
                    if ($nameFa === '' || $nameEn === '') throw new RuntimeException('Plan names are required.');
                    $metadata = json_encode([
                        'upload_limit' => trim((string)($post['upload_limit'] ?? '')),
                        'download_limit' => trim((string)($post['download_limit'] ?? '')),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $this->db->prepare(
                        'INSERT INTO service_plans(category_id,provider_key,provider_server_id,provider_plan_key,display_name_fa,display_name_en,price_minor,duration_days,quota_gb,enabled,sort_order,metadata_json,created_at,updated_at)
                         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $categoryId, 'mikrotik_wireguard', $serverId, 'mt:' . bin2hex(random_bytes(5)), $nameFa, $nameEn,
                        max(0, (int)($post['price_minor'] ?? 0)), max(0, (int)($post['duration_days'] ?? 0)), max(0, (float)($post['quota_gb'] ?? 0)), 1,
                        max(0, (int)($post['sort_order'] ?? 0)), $metadata, time(), time(),
                    ]);
                    $_SESSION['mikrotik_flash'] = '✓ MikroTik WireGuard plan created.';
                    $_SESSION['mikrotik_error'] = false;
                    break;

                case 'delete_plan':
                    $this->db->prepare("UPDATE service_plans SET enabled=0,updated_at=? WHERE id=? AND provider_key='mikrotik_wireguard'")->execute([time(),(int)($post['id'] ?? 0)]);
                    $_SESSION['mikrotik_flash'] = '✓ MikroTik plan disabled.';
                    $_SESSION['mikrotik_error'] = false;
                    break;

                case 'disable_peer':
                case 'enable_peer':
                    $peer = $this->peer((int)($post['id'] ?? 0));
                    if (!$peer) throw new RuntimeException('WireGuard peer was not found.');
                    $service = $this->service($peer);
                    if ($action === 'disable_peer') $service->disablePeer((string)$peer['routeros_id']); else $service->enablePeer((string)$peer['routeros_id']);
                    $status = $action === 'disable_peer' ? 'disabled' : 'active';
                    $this->db->prepare('UPDATE mikrotik_wireguard_peers SET status=?,updated_at=? WHERE id=?')->execute([$status,time(),$peer['id']]);
                    $_SESSION['mikrotik_flash'] = $status === 'active' ? '✓ Peer enabled.' : '✓ Peer disabled.';
                    $_SESSION['mikrotik_error'] = false;
                    break;

                default:
                    throw new RuntimeException('Unknown MikroTik operation.');
            }
        } catch (\Throwable $e) {
            $_SESSION['mikrotik_flash'] = '✕ ' . $e->getMessage();
            $_SESSION['mikrotik_error'] = true;
        }
    }

    public function viewData(): array
    {
        $servers = $this->db->query('SELECT * FROM mikrotik_servers ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $plans = $this->db->query("SELECT p.*,s.name server_name FROM service_plans p LEFT JOIN mikrotik_servers s ON s.id=p.provider_server_id WHERE p.provider_key='mikrotik_wireguard' ORDER BY p.sort_order,p.id")->fetchAll(PDO::FETCH_ASSOC);
        $peers = $this->db->query('SELECT p.*,s.name server_name FROM mikrotik_wireguard_peers p LEFT JOIN mikrotik_servers s ON s.id=p.server_id ORDER BY p.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
        $flash = (string)($_SESSION['mikrotik_flash'] ?? '');
        $error = (bool)($_SESSION['mikrotik_error'] ?? false);
        unset($_SESSION['mikrotik_flash'], $_SESSION['mikrotik_error']);
        foreach ($servers as &$server) {
            $server['interfaces'] = [];
            $server['pools'] = [];
            $server['dns'] = '';
            try {
                $client = $this->client($server);
                $server['interfaces'] = $client->wireguardInterfaces();
                $server['pools'] = $client->ipPools();
                $server['dns'] = (string)($client->dnsSettings()['servers'] ?? '');
            } catch (\Throwable $e) {
                $server['discovery_error'] = $e->getMessage();
            }
        }
        unset($server);
        return compact('servers','plans','peers','flash','error');
    }

    private function serverInput(array $post, ?array $old = null): array
    {
        $password = (string)($post['password'] ?? '');
        if ($password === '' && $old) $password = dec((string)$old['password_enc']);
        $tls = !empty($post['tls_mode']) ? 1 : 0;
        $apiPort = (int)($post['api_port'] ?? ($tls ? 443 : 80));
        $vpnPort = (int)($post['vpn_port'] ?? 51820);
        if ($apiPort < 1 || $apiPort > 65535 || $vpnPort < 1 || $vpnPort > 65535) throw new RuntimeException('API/VPN port is invalid.');
        return [
            'name' => trim((string)($post['name'] ?? '')),
            'host' => trim((string)($post['host'] ?? '')),
            'api_port' => $apiPort,
            'username' => trim((string)($post['username'] ?? '')),
            'password' => $password,
            'tls_mode' => $tls,
            'vpn_endpoint' => trim((string)($post['vpn_endpoint'] ?? '')),
            'vpn_port' => $vpnPort,
            'interface_name' => trim((string)($post['interface_name'] ?? '')),
            'pool_name' => trim((string)($post['pool_name'] ?? '')),
            'dns_servers' => trim((string)($post['dns_servers'] ?? '')),
        ];
    }

    private function clientFromData(array $data): MikroTikClient
    {
        if ($data['name'] === '' || $data['host'] === '' || $data['username'] === '' || $data['password'] === '') throw new RuntimeException('Server name, host, username and password are required.');
        return new MikroTikClient($data['host'],$data['username'],$data['password'],$data['api_port'],(bool)$data['tls_mode']);
    }

    private function server(int $id): ?array
    {
        $q = $this->db->prepare('SELECT * FROM mikrotik_servers WHERE id=?'); $q->execute([$id]); $row=$q->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }

    private function client(array $server): MikroTikClient
    {
        return new MikroTikClient((string)$server['host'],(string)$server['username'],dec((string)$server['password_enc']),(int)$server['api_port'],!empty($server['tls_mode']));
    }

    private function peer(int $id): ?array
    {
        $q=$this->db->prepare('SELECT * FROM mikrotik_wireguard_peers WHERE id=?'); $q->execute([$id]); $row=$q->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }

    private function service(array $peer): MikroTikWireGuardService
    {
        $server=$this->server((int)$peer['server_id']); if(!$server) throw new RuntimeException('MikroTik server was not found.');
        return new MikroTikWireGuardService($this->client($server));
    }
}
