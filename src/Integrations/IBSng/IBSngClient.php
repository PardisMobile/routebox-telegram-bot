<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/**
 * IBSng A1.24 Free Edition client.
 *
 * A1.24 exposes its Core XML-RPC service locally; this adapter intentionally
 * uses the Apache Web Panel so the IBSng server needs no database access and
 * no exposed 1235/1237 port.
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
        $this->baseUrl = $scheme . '://' . $this->host . $portSuffix . '/' . trim($this->basePath, '/');
        $cookie = tempnam(sys_get_temp_dir(), 'routebox_ibsng_');
        if ($cookie === false) throw new RuntimeException('Could not create temporary IBSng session storage.');
        $this->cookieFile = $cookie;
    }

    public static function defaultPort(): int { return 80; }

    public function __destruct()
    {
        if (isset($this->cookieFile) && is_file($this->cookieFile)) @unlink($this->cookieFile);
    }

    private function request(string $path, array $post = []): array
    {
        $ch = curl_init($this->baseUrl . '/' . ltrim($path, '/'));
        if ($ch === false) throw new RuntimeException('Could not initialize IBSng HTTP connection.');
        $headers = ['Accept: text/html,application/xhtml+xml'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
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
        $body = curl_exec($ch); $error = curl_error($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $url = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); curl_close($ch);
        if ($body === false) throw new RuntimeException('IBSng connection failed: ' . ($error ?: 'unknown cURL error'));
        if ($code < 200 || $code >= 400) throw new RuntimeException('IBSng Web Panel HTTP error: ' . $code);
        return ['body' => (string)$body, 'url' => $url, 'code' => $code];
    }

    private function ensureLogin(): void
    {
        if ($this->loggedIn) return;
        $response = $this->request('/admin/', ['username' => $this->username, 'password' => $this->password]);
        $body = $response['body'];
        if (stripos($body, 'name="username"') !== false && stripos($body, 'name="password"') !== false) {
            throw new RuntimeException('IBSng authentication failed: Admin username/password were rejected.');
        }
        $this->loggedIn = true;
    }

    private function textFromHtml(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function firstMatch(string $pattern, string $html): ?string
    {
        return preg_match($pattern, $html, $m) === 1 ? trim(html_entity_decode((string)($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null;
    }

    public function testConnection(): void { $this->ensureLogin(); $this->request('/admin/admin_index.php'); }

    public function listGroups(): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/group/group_list.php')['body'];
        $groups = [];
        // A1.24 renders Group List links to group_info.php?group_name=...
        $patterns = [
            '/(?:href|action)=["\'][^"\']*group_info\.php\?[^"\']*group_name=([^"\'&]+)/i',
            '/group_info\.php\?group_name=([^"\'&]+)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $encoded) {
                    $name = urldecode(html_entity_decode((string)$encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($name !== '') $groups[$name] = $name;
                }
            }
            if ($groups) break;
        }
        // Fallback for installations whose Group List prints the name as plain table text.
        if (!$groups) {
            if (preg_match_all('/<a[^>]+href=["\'][^"\']*group_info\.php[^"\']*["\'][^>]*>\s*([^<]+?)\s*<\/a>/is', $html, $matches)) {
                foreach ($matches[1] as $name) { $name = trim(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8')); if ($name !== '') $groups[$name] = $name; }
            }
        }
        return array_values($groups);
    }

    public function getGroupInfo(string $groupName): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/group/group_info.php?group_name=' . rawurlencode($groupName))['body'];
        $groupId = $this->firstMatch('/(?:name|id)=["\'](?:group_id|groupId)["\'][^>]*?(?:value|data-value)=["\']([^"\']+)["\']/i', $html);
        if ($groupId === null) $groupId = $this->firstMatch('/group_id\s*[=:]\s*["\']?([0-9]+)/i', $html);
        return ['group_name' => $groupName, 'group_id' => $groupId, 'html' => $html];
    }

    public function getUserInfoByUsername(string $username): array { return $this->getUserInfoByUsername($username); }

    public function getUserInfoByNormalUsername(string $username): array { return $this->getUserInfoByUsername($username); }

    public function getUserInfoByUsernameLegacy(string $username): array
    {
        $this->ensureLogin();
        $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username))['body'];
        if (stripos($html, 'Admin Login') !== false && stripos($html, 'name="password"') !== false) {
            $this->loggedIn = false; $this->ensureLogin(); $html = $this->request('/admin/user/user_info.php?normal_username=' . rawurlencode($username))['body'];
        }
        $plain = $this->textFromHtml($html);
        if (stripos($plain, 'User Information') === false && stripos($plain, 'User Informations') === false) throw new RuntimeException('IBSng user was not found or the Web Panel returned an unexpected page.');
        $result = ['username'=>$username,'html'=>$html,'text'=>$plain];
        if (($uid=$this->firstMatch('/name=["\']user_id["\'][^>]*value=["\']([^"\']+)["\']/i',$html))!==null) $result['user_id']=$uid;
        return $result;
    }

    public function createUser(string $ispName, string $groupName, int $credit = 0): array
    {
        $this->ensureLogin();
        $body = $this->request('/admin/user/add_new_users.php', ['count'=>1,'credit'=>$credit,'owner_name'=>$this->username,'group_name'=>$groupName,'credit_comment'=>''])['body'];
        $userId = $this->firstMatch('/name=["\']user_id["\'][^>]*value=["\']([^"\']+)["\']/i',$body);
        if ($userId === null) throw new RuntimeException('IBSng user creation did not return a user id. The group may be invalid or the Admin lacks permission.');
        return ['user_ids'=>[$userId],'user_id'=>$userId];
    }

    public function setUserCredentials(string|int $userId, string $username, string $password): mixed
    {
        $this->ensureLogin();
        if ($username === '' || $password === '') throw new RuntimeException('IBSng username and password cannot be empty.');
        $body = $this->request('/admin/plugins/edit.php', ['update'=>'1','edit_tpl_cs'=>'normal_username','target'=>'user','target_id'=>(string)$userId,'user_id'=>(string)$userId,'has_normal_username'=>'t','current_normal_username'=>'','normal_username'=>$username,'password'=>$password,'normal_save_user_add'=>'t','attr_update_method_0'=>'normalAttrs'])['body'];
        return ['updated'=>true,'html'=>$body];
    }

    public function getUserInfoByUsername(string $username): array
    {
        return $this->getUserInfoByUsernameLegacy($username);
    }

    public function renewUser(string|int $userId, string $comment = ''): mixed { throw new RuntimeException('IBSng renewal is not enabled in this first Web Panel adapter yet.'); }
    public function changeUserGroup(string|int $userId, string $groupName): mixed { throw new RuntimeException('IBSng group changes are not enabled in this first Web Panel adapter yet.'); }
}
