<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/**
 * IBSng JSON-RPC Admin API client.
 *
 * IBSng does not accept Admin credentials on every request directly. The
 * documented flow is login.login -> session id -> subsequent calls with
 * auth_session. Keep that protocol detail isolated here so the rest of the
 * RouteBox application never needs to know about it.
 */
final class IBSngClient
{
    private ?string $session = null;
    private ?string $authRemoteAddr = null;

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 1237,
        private readonly int $timeout = 30,
        ?string $authRemoteAddr = null,
    ) {
        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('IBSng connection settings are incomplete.');
        }
        $this->authRemoteAddr = $authRemoteAddr !== null && trim($authRemoteAddr) !== ''
            ? trim($authRemoteAddr)
            : null;
    }

    public static function defaultPort(): int
    {
        return 1237;
    }

    /** Authenticate once and cache the IBSng session for this client instance. */
    private function login(): string
    {
        $result = $this->rawRequest('login.login', [
            'auth_type' => 'ANONYMOUS',
            'auth_name' => 'ANONYMOUS',
            'auth_pass' => 'ANONYMOUS',
            'login_auth_type' => 'ADMIN',
            'login_auth_name' => $this->username,
            'login_auth_pass' => $this->password,
            'auth_remoteaddr' => $this->remoteAddress(),
            'create_session' => true,
        ]);

        if (!is_string($result) || trim($result) === '') {
            throw new RuntimeException('IBSng authentication failed: no session was returned.');
        }

        return $this->session = $result;
    }

    /**
     * Return the address IBSng should record for the API caller.
     * It can be explicitly supplied later as an advanced setting; otherwise
     * use the web server address/hostname as a best-effort value.
     */
    private function remoteAddress(): string
    {
        if ($this->authRemoteAddr !== null) {
            return $this->authRemoteAddr;
        }

        $serverAddr = (string)($_SERVER['SERVER_ADDR'] ?? '');
        if ($this->isUsableIPv4($serverAddr)) {
            return $serverAddr;
        }

        $hostAddr = gethostbyname(gethostname());
        if ($this->isUsableIPv4($hostAddr)) {
            return $hostAddr;
        }

        return '127.0.0.1';
    }

    private function isUsableIPv4(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && !in_array($address, ['0.0.0.0', '127.0.0.1'], true);
    }

    /** Perform one HTTP JSON-RPC call without adding authentication/session data. */
    private function rawRequest(string $method, array $params = []): mixed
    {
        $payload = json_encode([
            'id' => null,
            'method' => $method,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new RuntimeException('Could not encode IBSng JSON-RPC request.');
        }

        $url = 'http://' . $this->host . ':' . $this->port . '/';
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize IBSng connection.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Charset: utf-8',
                'Cache-Control: no-cache',
            ],
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('IBSng connection failed: ' . ($error ?: 'unknown cURL error'));
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('IBSng HTTP error: ' . $code);
        }

        $response = json_decode((string)$raw, true);
        if (!is_array($response)) {
            throw new RuntimeException('IBSng returned invalid JSON.');
        }

        if (array_key_exists('error', $response) && $response['error'] !== null && $response['error'] !== '') {
            $errorValue = $response['error'];
            if (is_array($errorValue)) {
                $errorValue = json_encode($errorValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            throw new RuntimeException('IBSng API error: ' . (string)$errorValue);
        }

        return $response['result'] ?? null;
    }

    public function request(string $method, array $params = []): mixed
    {
        if ($method === 'login.login') {
            return $this->rawRequest($method, $params);
        }

        if ($this->session === null) {
            $this->login();
        }

        $params['auth_remoteaddr'] = $this->remoteAddress();
        $params['auth_type'] = 'ADMIN';
        $params['auth_name'] = $this->username;
        $params['auth_session'] = $this->session;

        try {
            return $this->rawRequest($method, $params);
        } catch (RuntimeException $e) {
            // IBSng sessions can expire. Re-authenticate once and retry the
            // original call only when the server explicitly reports a session
            // problem; do not duplicate arbitrary write operations.
            $message = strtolower($e->getMessage());
            if (!str_contains($message, 'session')) {
                throw $e;
            }

            $this->session = null;
            $this->login();
            $params['auth_session'] = $this->session;
            return $this->rawRequest($method, $params);
        }
    }

    public function listGroups(): array
    {
        $groups = $this->request('group.listGroups');
        return is_array($groups) ? array_values(array_map('strval', $groups)) : [];
    }

    public function getGroupInfo(string $groupName): array
    {
        $result = $this->request('group.getGroupInfo', ['group_name' => $groupName]);
        return is_array($result) ? $result : [];
    }

    public function getUserInfoByUsername(string $username): array
    {
        $result = $this->request('user.getUserInfo', ['normal_username' => $username]);
        return is_array($result) ? $result : [];
    }

    public function createUser(string $ispName, string $groupName, int $credit = 0): array
    {
        $result = $this->request('user.addNewUsers', [
            'count' => 1,
            'credit' => $credit,
            'isp_name' => $ispName,
            'group_name' => $groupName,
            'credit_comment' => '',
        ]);
        return is_array($result) ? ['user_ids' => $result] : ['user_ids' => [$result]];
    }

    public function setUserCredentials(string|int $userId, string $username, string $password): mixed
    {
        return $this->request('user.updateUserAttrs', [
            'user_id' => (string)$userId,
            'attrs' => [
                'normal_user_spec' => [
                    'normal_username' => $username,
                    'normal_password' => $password,
                ],
            ],
            'to_del_attrs' => [],
        ]);
    }

    public function renewUser(string|int $userId, string $comment = ''): mixed
    {
        return $this->request('user.renewUsers', [
            'user_id' => (string)$userId,
            'comment' => $comment,
        ]);
    }

    public function changeUserGroup(string|int $userId, string $groupName): mixed
    {
        return $this->request('user.updateUserAttrs', [
            'user_id' => (string)$userId,
            'attrs' => ['group_name' => $groupName],
            'to_del_attrs' => [],
        ]);
    }
}
