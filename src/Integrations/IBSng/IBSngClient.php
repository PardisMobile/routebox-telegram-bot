<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/**
 * IBSng A1.24 Free Edition Web Panel adapter.
 *
 * This adapter deliberately uses the documented/actual A1.24 Apache Web Panel
 * entry points. The IBSng source shows that /IBSng/admin/index.php accepts
 * username/password and then creates the normal Admin session; the admin pages
 * themselves call IBSng's internal XML-RPC layer. RouteBox therefore does not
 * expose or require IBSng's internal XML-RPC port on the public network.
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
        if (!in_array($this->port, [80, 443], true)) {
            throw new RuntimeException('IBSng Web Panel port must be 80 or 443.');
        }

        $scheme = $this->port === 443 ? 'https' : 'http';
        $this->baseUrl = $scheme . '://' . $this->host . '/'
            . trim($this->basePath, '/');

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

    private function request(string $path, array $post = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize IBSng HTTP connection.');
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml',
            'Cache-Control: no-cache',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'RouteBox-IBSng-A1.24/1.0',
            CURLOPT_HTTPHEADER => $headers,
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
        $url = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('IBSng connection failed: ' . ($error !== '' ? $error : 'unknown cURL error'));
        }
        if ($code < 200 || $code >= 400) {
            throw new RuntimeException('IBSng Web Panel HTTP error: ' . $code . ' (' . $url . ')');
        }

        return [
            'body' => (string)$body,
            'url' => $url,
            'code' => $code,
        ];
    }

    private function ensureLogin(): void
    {
        if ($this->loggedIn) {
            return;
        }

        // A1.24's real login entry point is /IBSng/admin/index.php.
        // It accepts username/password and redirects successful logins to
        // /IBSng/admin/admin_index.php.
        $response = $this->request('/admin/index.php', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $body = $response['body'];
        $effectiveUrl = strtolower((string)$response['url']);
        $hasLoginForm = stripos($body, 'name="username"') !== false
            && stripos($body, 'name="password"') !== false;
        $looksLikeAdmin = str_contains($effectiveUrl, '/admin/admin_index.php')
            || str_contains($effectiveUrl, '/admin/admin_index');

        if ($hasLoginForm && !$looksLikeAdmin) {
            throw new RuntimeException('IBSng authentication failed: Admin username/password were rejected.');
        }

        if (!$looksLikeAdmin) {
            // Some Apache configurations hide the final URL. Verify the
            // authenticated session explicitly instead of accepting a false
            // positive from a generic HTTP 200 response.
            $check = $this->request('/admin/admin_index.php');
            $checkBody = $check['body'];
            $stillLogin = stripos($checkBody, 'name="username"') !== false
                && stripos($checkBody, 'name="password"') !== false;
            if ($stillLogin) {
                throw new RuntimeException('IBSng authentication failed: the Web Panel did not create an Admin session.');
            }
        }

        $this->loggedIn = true;
    }

    private function textFromHtml(string $html): string
    {
        return trim(preg_replace(
            '/\\s+/u',
            ' ',
            html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? '');
    }

    private function firstMatch(string $pattern, string $html): ?string
    {
        return preg_match($pattern, $html, $m) === 1
            ? trim(html_entity_decode((string)($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
    }

    public function testConnection(): void
    {
        $this->ensureLogin();
        $response = $this->request('/admin/admin_index.php');
        $text = $this->textFromHtml($response['body']);
        if ($text === '') {
            throw new RuntimeException('IBSng Web Panel returned an empty Admin page.');
        }
    }

    public function listGroups(): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/group/group_list.php')['body'];
        $groups = [];

        // A1.24 group_list.tpl renders links exactly as:
        // /IBSng/admin/group/group_info.php?group_name=<url-encoded-name>
        if (preg_match_all(
            '/group_info\\.php\\?group_name=([^"\\'&<>\\s]+)/i',
            $html,
            $matches
        )) {
            foreach ($matches[1] as $encoded) {
                $name = urldecode(html_entity_decode((string)$encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
        $html = $this->request(
            '/admin/group/group_info.php?group_name=' . rawurlencode($groupName)
        )['body'];

        // The A1.24 template exposes Group ID as visible text rather than a
        // hidden field, so support both forms and retain the raw HTML for later
        // expansion of the adapter.
        $groupId = $this->firstMatch(
            '/name=["\\']group_id["\\'][^>]*value=["\\']([^"\\']+)["\\']/i',
            $html
        );
        if ($groupId === null) {
            $groupId = $this->firstMatch(
                '/(?:Group\\s+ID|group_id)\\s*(?:<[^>]+>\\s*)+([0-9]+)/i',
                $html
            );
        }

        return [
            'group_name' => $groupName,
            'group_id' => $groupId,
            'html' => $html,
        ];
    }

    public function getUserInfoByUsername(string $username): array
    {
        $this->ensureLogin();
        $html = $this->request(
            '/admin/user/user_info.php?normal_username=' . rawurlencode($username)
        )['body'];

        $plain = $this->textFromHtml($html);
        $loginPage = stripos($html, 'name="username"') !== false
            && stripos($html, 'name="password"') !== false;
        if ($loginPage) {
            $this->loggedIn = false;
            $this->ensureLogin();
            $html = $this->request(
                '/admin/user/user_info.php?normal_username=' . rawurlencode($username)
            )['body'];
            $plain = $this->textFromHtml($html);
        }

        if (stripos($plain, 'User Information') === false
            && stripos($plain, 'User Informations') === false
            && stripos($plain, 'Username') === false
            && stripos($plain, 'Normal Username') === false) {
            throw new RuntimeException('IBSng user was not found or the Web Panel returned an unexpected page.');
        }

        $result = [
            'username' => $username,
            'html' => $html,
            'text' => $plain,
        ];
        if (($uid = $this->firstMatch(
            '/name=["\\']user_id["\\'][^>]*value=["\\']([^"\\']+)["\\']/i',
            $html
        )) !== null) {
            $result['user_id'] = $uid;
        }

        return $result;
    }

    public function createUser(string $ispName, string $groupName, int $credit = 0): array
    {
        $this->ensureLogin();
        $body = $this->request('/admin/user/add_new_users.php', [
            'count' => 1,
            'credit' => $credit,
            'owner_name' => $this->username,
            'group_name' => $groupName,
            'credit_comment' => '',
        ])['body'];

        // add_new_users.php redirects to plugins/edit.php with the new user_id.
        $userId = $this->firstMatch(
            '/(?:name=["\\']user_id["\\']|[?&]user_id=)[^>]*?(?:value=["\\']|[=])([^"\\'&<]+)/i',
            $body
        );
        if ($userId === null) {
            $userId = $this->firstMatch('/[?&]user_id=([0-9]+)/i', $body);
        }
        if ($userId === null) {
            throw new RuntimeException('IBSng user creation did not return a user id. The group may be invalid or the Admin lacks permission.');
        }

        return ['user_ids' => [$userId], 'user_id' => $userId];
    }

    public function setUserCredentials(string|int $userId, string $username, string $password): array
    {
        $this->ensureLogin();
        if ($username === '' || $password === '') {
            throw new RuntimeException('IBSng username and password cannot be empty.');
        }

        $body = $this->request('/admin/plugins/edit.php', [
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
        ])['body'];

        $text = $this->textFromHtml($body);
        if (stripos($text, 'error') !== false && stripos($text, 'normal username') !== false) {
            throw new RuntimeException('IBSng rejected the username/password update.');
        }

        return ['updated' => true, 'html' => $body];
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
