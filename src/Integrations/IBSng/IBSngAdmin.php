<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;
use RuntimeException;

/**
 * Controller for the IBSng section inside the RouteBox Admin panel.
 * Credentials are encrypted and never returned to the view.
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
        if ($method !== 'POST') {
            return;
        }
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
                $client->testConnection();

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

                $_SESSION['ibsng_flash'] = '✓ اتصال IBSng موفق بود و سرور ذخیره شد.';
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
                if ($name === '' || $host === '') {
                    throw new RuntimeException('نام و Host سرور الزامی است.');
                }
                if (!in_array($port, [80, 443], true)) {
                    throw new RuntimeException('پورت IBSng باید 80 یا 443 باشد.');
                }

                $effectiveUser = $username !== '' ? $username : dec((string)$server['admin_user_enc']);
                $effectivePass = $password !== '' ? $password : dec((string)$server['admin_pass_enc']);
                $client = new IBSngClient($host, $effectiveUser, $effectivePass, $port);
                $client->testConnection();

                $userEnc = $username !== '' ? enc($username) : (string)$server['admin_user_enc'];
                $passEnc = $password !== '' ? enc($password) : (string)$server['admin_pass_enc'];
                $now = time();
                $st = $this->db->prepare(
                    'UPDATE ibsng_servers SET name=?,host=?,port=?,admin_user_enc=?,admin_pass_enc=?,isp_name=?,verify_tls=?,last_test_at=?,last_error=NULL,updated_at=? WHERE id=?'
                );
                $st->execute([
                    $name,
                    $host,
                    $port,
                    $userEnc,
                    $passEnc,
                    $ispName !== '' ? $ispName : (string)$server['isp_name'],
                    $port === 443 ? 1 : 0,
                    $now,
                    $now,
                    $id,
                ]);

                $_SESSION['ibsng_flash'] = '✓ اطلاعات سرور و اتصال با موفقیت به‌روزرسانی شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'test_server') {
                $id = (int)($post['id'] ?? 0);
                $this->service->test($id);
                $_SESSION['ibsng_flash'] = '✓ اتصال موفق است. IBSng A1.24 Web Panel احراز هویت شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'test_create_user') {
                $serverId = (int)($post['id'] ?? 0);
                $server = $this->service->server($serverId);
                $q = $this->db->prepare('SELECT group_name FROM ibsng_groups WHERE ibsng_server_id=? AND enabled=1 ORDER BY id LIMIT 1');
                $q->execute([$serverId]);
                $groupName = trim((string)$q->fetchColumn());
                if ($groupName === '') {
                    throw new RuntimeException('ابتدا حداقل یک گروه IBSng فعال برای این سرور تعریف کنید.');
                }

                $testUsername = 'rbtest_' . date('ymdHis');
                $testPassword = 'Rb!' . bin2hex(random_bytes(6));
                $result = $this->service->createAccount(
                    $serverId,
                    (string)($server['isp_name'] ?? 'Main'),
                    $groupName,
                    $testUsername,
                    $testPassword,
                    0
                );

                $_SESSION['ibsng_flash'] = '✓ Test user created: ' . (string)$result['username'] . ' / ' . (string)$result['password'] . ' · Group: ' . $groupName;
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'delete_server') {
                $this->service->deleteServer((int)($post['id'] ?? 0));
                $_SESSION['ibsng_flash'] = '✓ سرور IBSng حذف شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'add_group') {
                $this->service->addGroup(
                    (int)($post['server_id'] ?? 0),
                    (string)($post['plan_name'] ?? ''),
                    (string)($post['group_name'] ?? '')
                );
                $_SESSION['ibsng_flash'] = '✓ پلن و گروه IBSng اضافه شد و برای ربات فعال شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'update_group') {
                $this->service->updateGroup(
                    (int)($post['id'] ?? 0),
                    (string)($post['plan_name'] ?? ''),
                    (string)($post['group_name'] ?? '')
                );
                $_SESSION['ibsng_flash'] = '✓ پلن و نام گروه IBSng به‌روزرسانی شد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'toggle_group') {
                $this->service->toggleGroup((int)($post['id'] ?? 0));
                $_SESSION['ibsng_flash'] = '✓ وضعیت نمایش پلن در ربات تغییر کرد.';
                $_SESSION['ibsng_error'] = false;
            } elseif ($action === 'delete_group') {
                $this->service->deleteGroup((int)($post['id'] ?? 0));
                $_SESSION['ibsng_flash'] = '✓ گروه IBSng و پلن ربات حذف شد.';
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
            $q = $this->db->prepare('SELECT id,plan_name,group_name,group_id,enabled,synced_at FROM ibsng_groups WHERE ibsng_server_id=? ORDER BY id DESC');
            $q->execute([(int)$row['id']]);
            $row['groups'] = $q->fetchAll(PDO::FETCH_ASSOC);
            $servers[] = $row;
        }

        $flash = (string)($_SESSION['ibsng_flash'] ?? '');
        $error = (bool)($_SESSION['ibsng_error'] ?? false);
        unset($_SESSION['ibsng_flash'], $_SESSION['ibsng_error']);

        return ['servers' => $servers, 'flash' => $flash, 'error' => $error];
    }
}
