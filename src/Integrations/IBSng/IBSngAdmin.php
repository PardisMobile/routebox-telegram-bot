<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;
use RuntimeException;

/**
 * Controller for the standalone IBSng admin module.
 * Credentials are always stored encrypted and are never returned to the view.
 */
final class IBSngAdmin
{
    private IBSngService $service;

    public function __construct(private readonly PDO $db)
    {
        $this->service = new IBSngService($db);
    }

    public function handle(array $post, string $method): void
    {
        if ($method !== 'POST') return;
        verify_csrf();

        $action = trim((string)($post['action'] ?? ''));
        try {
            if ($action === 'add_server') {
                $name = trim((string)($post['name'] ?? ''));
                $host = trim((string)($post['host'] ?? ''));
                $username = trim((string)($post['username'] ?? ''));
                $password = (string)($post['password'] ?? '');
                $port = (int)($post['port'] ?? 80);
                $ispName = trim((string)($post['isp_name'] ?? 'Main')) ?: 'Main';

                if ($name === '' || $host === '' || $username === '' || $password === '') {
                    throw new RuntimeException('تمام اطلاعات اتصال IBSng الزامی است.');
                }
                if (!in_array($port, [80, 443], true)) {
                    throw new RuntimeException('پورت IBSng باید 80 یا 443 باشد.');
                }

                $client = new IBSngClient($host, $username, $password, $port);
                $groups = $client->listGroups();

                $now = time();
                $st = $this->db->prepare(
                    'INSERT INTO ibsng_servers(name,host,port,admin_user_enc,admin_pass_enc,isp_name,verify_tls,enabled,last_test_at,last_error,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,1,?,NULL,?,?)'
                );
                $st->execute([
                    $name,
                    $host,
                    $port,
                    enc($username),
                    enc($password),
                    $ispName,
                    $port === 443 ? 1 : 0,
                    $now,
                    $now,
                    $now,
                ]);
                $serverId = (int)$this->db->lastInsertId();

                $this->syncGroupsForServer($serverId, $groups, $client);
                $_SESSION['ibsng_flash'] = '✓ اتصال IBSng موفق بود و سرور ذخیره شد. ' . count($groups) . ' گروه شناسایی شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'update_server') {
                $id = (int)($post['id'] ?? 0);
                $server = $this->service->server($id);
                $name = trim((string)($post['name'] ?? ''));
                $host = trim((string)($post['host'] ?? ''));
                $port = (int)($post['port'] ?? 80);
                $username = trim((string)($post['username'] ?? ''));
                $password = (string)($post['password'] ?? '');
                $ispName = trim((string)($post['isp_name'] ?? ''));
                if ($name === '' || $host === '') throw new RuntimeException('نام و Host سرور الزامی است.');
                if (!in_array($port, [80, 443], true)) throw new RuntimeException('پورت IBSng باید 80 یا 443 باشد.');

                $effectiveUser = $username !== '' ? $username : dec((string)$server['admin_user_enc']);
                $effectivePass = $password !== '' ? $password : dec((string)$server['admin_pass_enc']);
                $client = new IBSngClient($host, $effectiveUser, $effectivePass, $port);
                $groups = $client->listGroups();

                $userEnc = $username !== '' ? enc($username) : (string)$server['admin_user_enc'];
                $passEnc = $password !== '' ? enc($password) : (string)$server['admin_pass_enc'];
                $now = time();
                $st = $this->db->prepare(
                    'UPDATE ibsng_servers SET name=?,host=?,port=?,admin_user_enc=?,admin_pass_enc=?,isp_name=?,verify_tls=?,last_test_at=?,last_error=NULL,updated_at=? WHERE id=?'
                );
                $st->execute([$name,$host,$port,$userEnc,$passEnc,$ispName !== '' ? $ispName : (string)$server['isp_name'],$port === 443 ? 1 : 0,$now,$now,$id]);
                $this->syncGroupsForServer($id, $groups, $client);

                $_SESSION['ibsng_flash'] = '✓ اطلاعات سرور و اتصال با موفقیت به‌روزرسانی شد. ' . count($groups) . ' گروه شناسایی شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'test_server') {
                $id = (int)($post['id'] ?? 0);
                $result = $this->service->test($id);
                $_SESSION['ibsng_flash'] = '✓ اتصال موفق است. ' . count($result['groups']) . ' گروه پیدا شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'sync_groups') {
                $id = (int)($post['id'] ?? 0);
                $count = $this->service->syncGroups($id);
                $_SESSION['ibsng_flash'] = '✓ همگام‌سازی گروه‌ها انجام شد: ' . $count . ' گروه.';
                $_SESSION['ibsng_error'] = false;
            } else {
                throw new RuntimeException('عملیات IBSng ناشناخته است.');
            }
        } catch (\Throwable $e) {
            $_SESSION['ibsng_flash'] = '✕ ' . $e->getMessage();
            $_SESSION['ibsng_error'] = true;
        }
    }

    public function viewData(): array
    {
        $servers = [];
        $rows = $this->db->query('SELECT id,name,host,port,isp_name,enabled,last_test_at,last_error,created_at,updated_at FROM ibsng_servers ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $q = $this->db->prepare('SELECT group_name,group_id,enabled,synced_at FROM ibsng_groups WHERE ibsng_server_id=? ORDER BY group_name COLLATE NOCASE');
            $q->execute([(int)$row['id']]);
            $row['groups'] = $q->fetchAll(PDO::FETCH_ASSOC);
            $servers[] = $row;
        }

        $flash = (string)($_SESSION['ibsng_flash'] ?? '');
        $error = (bool)($_SESSION['ibsng_error'] ?? false);
        unset($_SESSION['ibsng_flash'], $_SESSION['ibsng_error']);

        return ['servers' => $servers, 'flash' => $flash, 'error' => $error];
    }

    private function syncGroupsForServer(int $serverId, array $groups, IBSngClient $client): void
    {
        $now = time();
        $st = $this->db->prepare(
            'INSERT INTO ibsng_groups(ibsng_server_id,group_name,group_id,group_info_json,enabled,synced_at)
             VALUES(?,?,?,?,1,?)
             ON CONFLICT(ibsng_server_id,group_name) DO UPDATE SET group_id=excluded.group_id,group_info_json=excluded.group_info_json,synced_at=excluded.synced_at'
        );
        foreach ($groups as $name) {
            $name = (string)$name;
            $info = $client->getGroupInfo($name);
            $groupId = isset($info['group_id']) && is_numeric($info['group_id']) ? (int)$info['group_id'] : null;
            $st->execute([$serverId, $name, $groupId, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now]);
        }
    }
}
