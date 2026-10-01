<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/**
 * IBSng A1.24 Free Edition Web Panel adapter.
 *
 * This adapter deliberately uses the actual A1.24 Apache Web Panel entry
 * points. RouteBox does not expose or require IBSng's internal XML-RPC port.
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
        $this->baseUrl = $scheme . '://' . $this->host . '/' . trim($this->basePath, '/');

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

    private function request(string $path, array $post = [], bool $followLocation = true): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize IBSng HTTP connection.');
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml',
            'Cache-Control: no-cache',
            'Accept-Encoding: identity',
            'Connection: close',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'RouteBox-IBSng-A1.24/1.0',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        ];

        if ($post !== []) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($post, '', '&');
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);

        /*
         * IBSng A1.24 on the legacy Apache/PHP stack can send the complete
         * response and then leave the HTTP connection open. Preserve the body
         * already received before libcurl reports CURLE_OPERATION_TIMEDOUT.
         */
        if (
            $errno === CURLE_OPERATION_TIMEDOUT
            && $code >= 200
            && $code < 400
            && $responseBody !== false
        ) {
            $responseBody = (string)$responseBody;
        }

        if ($responseBody === false) {
            throw new RuntimeException(
                'IBSng connection failed: ' . ($error !== '' ? $error : 'unknown cURL error')
            );
        }

        if ($code < 200 || $code >= 400) {
            throw new RuntimeException(
                'IBSng Web Panel HTTP error: ' . $code . ' (' . $effectiveUrl . ')'
            );
        }

        return [
            'body' => (string)$responseBody,
            'url' => $effectiveUrl,
            'code' => $code,
        ];
    }

    /**
     * Login without following IBSng's redirect.
     *
     * IBSng A1.24 returns a quick 302 to admin_index.php after a successful
     * login, but the redirected admin page can keep the legacy HTTP connection
     * open for a long time. Following that redirect makes the connection test
     * appear hung even though authentication already succeeded.
     */
    private function loginFast(): void
    {
        $response = $this->request('/admin/', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);

        if ($response['code'] < 300 || $response['code'] >= 400) {
            $body = $response['body'];
            $hasLoginForm = stripos($body, 'name="username"') !== false
                && stripos($body, 'name="password"') !== false;
            if ($hasLoginForm) {
                throw new RuntimeException('IBSng authentication failed: Admin username/password were rejected.');
            }
            throw new RuntimeException('IBSng authentication failed: unexpected login response.');
        }

        $this->loggedIn = true;
    }

    private function ensureLogin(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $this->loginFast();
    }

    private function textFromHtml(string $html): string
    {
        return trim(preg_replace(
            '/\s+/u',
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
        /*
         * Fast connectivity/authentication test only. Do NOT request Group List
         * or any other slow IBSng page here. The successful A1.24 login itself
         * is the health check.
         */
        $this->loginFast();
    }

    public function listGroups(): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/group/group_list.php')['body'];
        $groups = [];

        if (preg_match_all(
            '/group_info\.php\?group_name=([^"&<>\s]+)/i',
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
        $html = $this->request('/admin/group/group_info.php?group_name=' . rawurlencode($groupName))['body'];

        $groupId = $this->firstMatch(
            "/name=[\"']group_id[\"'][^>]*value=[\"']([^\"']+)[\"']/i",
            $html
        );
        if ($groupId === null) {
            $groupId = $this->firstMatch(
                '/(?:Group\s+ID|group_id)\s*(?:<[^>]+>\s*)+([0-9]+)/i',
                $html
            );
        }

        return ['group_name' => $groupName, 'group_id' => $groupId, 'html' => $html];
    }

    public function getUserInfoByUsername(string $username): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username))['body'];

        $plain = $this->textFromHtml($html);
        $loginPage = stripos($html, 'name="username"') !== false
            && stripos($html, 'name="password"') !== false;
        if ($loginPage) {
            $this->loggedIn = false;
            $this->ensureLogin();
            $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username))['body'];
            $plain = $this->textFromHtml($html);
        }

        if (stripos($plain, 'User Information') === false
            && stripos($plain, 'User Informations') === false
            && stripos($plain, 'Username') === false
            && stripos($plain, 'Normal Username') === false) {
            throw new RuntimeException('IBSng user was not found or the Web Panel returned an unexpected page.');
        }

        $result = ['username' => $username, 'html' => $html, 'text' => $plain];
        if (($uid = $this->firstMatch(
            "/name=[\"']user_id[\"'][^>]*value=[\"']([^\"']+)[\"']/i",
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

        $userId = $this->firstMatch(
            "/(?:name=[\"']user_id[\"']|[?&]user_id=)[^>]*?(?:value=[\"']|[=])([^\"'&<]+)/i",
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
