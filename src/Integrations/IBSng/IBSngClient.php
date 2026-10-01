<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/**
 * Thin JSON-RPC client for IBSng's Admin API.
 *
 * This class intentionally knows nothing about RouteBox, Telegram, products,
 * orders or payment. It is the only place that should know IBSng RPC details.
 */
final class IBSngClient
{
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 1237,
        private readonly int $timeout = 30,
    ) {
        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('IBSng connection settings are incomplete.');
        }
    }

    public function request(string $method, array $params = []): mixed
    {
        $params['auth_type'] = 'ADMIN';
        $params['auth_name'] = $this->username;
        $params['auth_pass'] = $this->password;

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
            ],
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('IBSng connection failed: ' . ($error ?: 'unknown error'));
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('IBSng HTTP error: ' . $code);
        }

        $response = json_decode((string) $raw, true);
        if (!is_array($response)) {
            throw new RuntimeException('IBSng returned invalid JSON.');
        }
        if (!empty($response['error'])) {
            throw new RuntimeException('IBSng API error: ' . (string) $response['error']);
        }

        return $response['result'] ?? null;
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
        return is_array($result) ? $result : ['user_ids' => $result];
    }

    public function setUserCredentials(string|int $userId, string $username, string $password): mixed
    {
        return $this->request('user.updateUserAttrs', [
            'user_id' => (string) $userId,
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
            'user_id' => (string) $userId,
            'comment' => $comment,
        ]);
    }

    public function changeUserGroup(string|int $userId, string $groupName): mixed
    {
        return $this->request('user.updateUserAttrs', [
            'user_id' => (string) $userId,
            'attrs' => ['group_name' => $groupName],
            'to_del_attrs' => [],
        ]);
    }
}
