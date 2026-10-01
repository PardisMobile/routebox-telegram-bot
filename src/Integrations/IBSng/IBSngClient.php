<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/**
 * IBSng A1.24 Free Edition client.
 *
 * A1.24's XML-RPC core listens on 127.0.0.1:1235 and is intentionally not an
 * external API. The supported external surface available on a normal install
 * is the Apache Web Panel. This client logs into /IBSng/admin/ and performs
 * the same admin pages that the A1.24 source uses; it never touches the
 * IBSng database and never asks the operator to expose port 1235/1237.
 */
final class IBSngClient
{
    private string $baseUrl;
    private string $cookieFile;
    private bool $loggedIn = false;

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 80,
        private readonly int $timeout = 30,
        private readonly string $basePath = '/IBSng',
    ) {
        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('IBSng connection settings are incomplete.');
        }

        $scheme = $this->port === 443 ? 'https' : 'http';
        $portSuffix = in_array($this->port, [80, 443], true) ? '' : ':' . $this->port;
        $path = '/' . trim($this->basePath, '/');
        $this->baseUrl = $scheme . '://' . $this->host . $portSuffix . $path;

        $cookie = tempnam(sys_get_temp_dir(), 'routebox_ibsng_');
        if ($cookie === false) {
            throw new RuntimeException('Could not create temporary IBSng session storage.');
        }
        $this->cookieFile = $cookie;
    }

    public static function defaultPort(): int
    {
        return 80;
    }

    public function __destruct()
    {
        if (isset($this->cookieFile) && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    private function request(string $path, array $post = [], bool $followRedirects = true): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize IBSng HTTP connection.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'RouteBox-IBSng-A1.24/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
        ];

        if ($post !== []) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($post, '', '&');
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('IBSng connection failed: ' . ($error ?: 'unknown cURL error'));
        }
        if ($code < 200 || $code >= 400) {
            throw new RuntimeException('IBSng Web Panel HTTP error: ' . $code);
        }

        return ['body' => (string)$body, 'url' => $effectiveUrl, 'code' => $code];
    }

    private function ensureLogin(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $response = $this->request('/admin/', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $body = $response['body'];
        if (stripos($body, 'name="username"') !== false && stripos($body, 'name="password"') !== false) {
            throw new RuntimeException('IBSng authentication failed: Admin username/password were rejected.');
        }

        $this->loggedIn = true;
    }

    private function textFromHtml(string $html): string
    {
        return trim(preg_replace('/\\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function firstMatch(string $pattern, string $html): ?string
    {
        if (preg_match($pattern, $html, $m) === 1) {
            return trim(html_entity_decode((string)($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return null;
    }

    public function testConnection(): void
    {
        $this->ensureLogin();
        $this->request('/admin/admin_index.php');
    }

    public function listGroups(): array
    {
        $this->ensureLogin();
        $response = $this->request('/admin/group/group_list.php');
        $groups = [];
        if (preg_match_all('/group_info\.php\?group_name=([^"&]+)[^>]*>/', $response['body'], $matches)) {
            foreach ($matches[1] as $encoded) {
                $name = urldecode($encoded);
                if ($name !== '') {
                    $groups[$name] = $name;
                }
            }
        }
        return array_values($groups);
    }

    public function getGroupInfo(string $groupName): array
    {
        $this->ensureLogin();
        $response = $this->request('/admin/group/group_info.php?group_name=' . rawurlencode($groupName));
        return ['group_name' => $groupName, 'html' => $response['body']];
    }

    public function getUserInfoByUsername(string $username): array
    {
        $this->ensureLogin();
        $response = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username));
        $html = $response['body'];

        if (stripos($html, 'Admin Login') !== false && stripos($html, 'name="password"') !== false) {
            $this->loggedIn = false;
            $this->ensureLogin();
            $response = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username));
            $html = $response['body'];
        }

        $plain = $this->textFromHtml($html);
        if (stripos($plain, 'User Information') === false && stripos($plain, 'User Informations') === false) {
            throw new RuntimeException('IBSng user was not found or the Web Panel returned an unexpected page.');
        }

        $result = [
            'username' => $username,
            'html' => $html,
            'text' => $plain,
        ];

        if (($group = $this->firstMatch('/Group.*?<[^>]+>\\s*([^<]+)\\s*<\\/[^>]+>/is', $html)) !== null) {
            $result['group_name'] = $group;
        }
        if (($userId = $this->firstMatch('/name=["\']user_id["\'][^>]*value=["\']([^"\']+)["\']/i', $html)) !== null) {
            $result['user_id'] = $userId;
        }
        return $result;
    }

    public function createUser(string $ispName, string $groupName, int $credit = 0): array
    {
        $this->ensureLogin();

        // A1.24's add_new_users.php directly consumes these POST fields and
        // then redirects to the user editor. This is the same entry point used
        // by the official Web Panel; the Core still performs all validation.
        $response = $this->request('/admin/user/add_new_users.php', [
            'count' => 1,
            'credit' => $credit,
            'owner_name' => $this->username,
            'group_name' => $groupName,
            'credit_comment' => '',
        ]);

        $userId = $this->firstMatch('/name=["\']user_id["\'][^>]*value=["\']([^"\']+)["\']/i', $response['body']);
        if ($userId === null) {
            throw new RuntimeException('IBSng user creation did not return a user id. The group may be invalid or the Admin lacks permission.');
        }

        return ['user_ids' => [$userId], 'user_id' => $userId];
    }

    public function setUserCredentials(string|int $userId, string $username, string $password): mixed
    {
        $this->ensureLogin();
        if ($username === '' || $password === '') {
            throw new RuntimeException('IBSng username and password cannot be empty.');
        }

        // The A1.24 user editor exposes normal username/password fields and
        // uses the normalAttrs update method to call user.updateUserAttrs.
        $response = $this->request('/admin/plugins/edit.php', [
            'update' => '1',
            'edit_tpl_cs' => 'normal_username',
            'target' => 'user',
            'target_id' => (string)$userId,
            'user_id' => (string)$userId,
            'has_normal_username' => 't',
            'current_normal_username' => '',
            'normal_username' => $username,
            'password' => $password,
            'normal_save_user_add' => 't',
            'attr_update_method_0' => 'normalAttrs',
        ]);

        return ['updated' => true, 'html' => $response['body']];
    }

    public function renewUser(string|int $userId, string $comment = ''): mixed
    {
        throw new RuntimeException('IBSng renewal is not enabled in this first Web Panel adapter yet.');
    }

    public function changeUserGroup(string|int $userId, string $groupName): mixed
    {
        throw new RuntimeException('IBSng group changes are not enabled in this first Web Panel adapter yet.');
    }
}
