<?php

declare(strict_types=1);

// bootstrap.php may load admin_features.php before public/index.php requires this file.
// Return early when the class is already loaded to prevent a duplicate declaration.
if (class_exists('RouteBoxClient', false)) {
    return;
}

final class RouteBoxClient
{
    private string $base;
    private string $user;
    private string $pass;
    private bool $verifyTls;
    private ?string $sessionCookie = null;
    private bool $sessionAttempted = false;

    public function __construct(string $base, string $user = '', string $pass = '', bool $verifyTls = true)
    {
        $base = trim($base);
        if (!preg_match('#^https?://[^/[:space:]]+$#i', $base)) {
            throw new InvalidArgumentException('Invalid RouteBox Panel URL. Use the exact URL that opens the RouteBox panel.');
        }
        $this->base = rtrim($base, '/');
        $this->user = $user;
        $this->pass = $pass;
        $this->verifyTls = $verifyTls;
    }

    private function authenticateSession(): bool
    {
        if ($this->sessionAttempted) {
            return $this->sessionCookie !== null;
        }
        $this->sessionAttempted = true;

        if ($this->user === '' || $this->pass === '') {
            return false;
        }

        $result = $this->rawRequest('POST', '/api/auth/login', [
            'username' => $this->user,
            'password' => $this->pass,
        ], 'application/json', false, false);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return false;
        }

        $cookie = $this->extractSessionCookie($result['headers']);
        if ($cookie === null) {
            return false;
        }
        $this->sessionCookie = $cookie;
        return true;
    }

    /** @param list<string> $headers */
    private function extractSessionCookie(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') !== 0) {
                continue;
            }
            $value = trim(substr($header, strlen('Set-Cookie:')));
            $pair = explode(';', $value, 2)[0];
            if (str_contains($pair, '=')) {
                return $pair;
            }
        }
        return null;
    }

    /** @return array{status:int,data:mixed,raw:string,headers:list<string>} */
    private function rawRequest(
        string $method,
        string $path,
        ?array $body = null,
        string $accept = 'application/json',
        bool $useBasic = true,
        bool $useSession = true
    ): array {
        $url = $this->base . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $headers = ['Accept: ' . $accept];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($useSession && $this->sessionCookie !== null) {
            $headers[] = 'Cookie: ' . $this->sessionCookie;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ];

        if ($useBasic && $this->sessionCookie === null && ($this->user !== '' || $this->pass !== '')) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $this->user . ':' . $this->pass;
        }

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        }

        curl_setopt_array($ch, $options);
        $rawWithHeaders = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($rawWithHeaders === false) {
            throw new RuntimeException('RouteBox connection failed: ' . ($error ?: 'unknown cURL error'));
        }

        $headerBlock = substr($rawWithHeaders, 0, $headerSize);
        $raw = substr($rawWithHeaders, $headerSize);
        $headersOut = preg_split('/\r\n|\n|\r/', trim($headerBlock)) ?: [];

        return [
            'status' => $code,
            'data' => $raw,
            'raw' => $raw,
            'headers' => array_values(array_filter($headersOut, static fn(string $h): bool => $h !== '')),
        ];
    }

    /** @return array{status:int,data:mixed,raw:string} */
    private function request(string $method, string $path, ?array $body = null, string $accept = 'application/json'): array
    {
        $this->authenticateSession();

        $result = $this->rawRequest(
            $method,
            $path,
            $body,
            $accept,
            useBasic: $this->sessionCookie === null,
            useSession: $this->sessionCookie !== null
        );

        if ($result['status'] === 401 && $this->sessionCookie !== null) {
            $this->sessionCookie = null;
            $this->sessionAttempted = false;
            if ($this->authenticateSession()) {
                $result = $this->rawRequest($method, $path, $body, $accept, false, true);
            }
        }

        if ($result['status'] === 401 && $this->sessionCookie === null && ($this->user !== '' || $this->pass !== '')) {
            $result = $this->rawRequest($method, $path, $body, $accept, true, false);
        }

        $code = $result['status'];
        $raw = (string) $result['raw'];
        if ($code < 200 || $code >= 300) {
            $json = json_decode($raw, true);
            $message = is_array($json)
                ? ($json['error'] ?? $json['message'] ?? $json['description'] ?? '')
                : '';
            throw new RuntimeException(
                'RouteBox HTTP ' . $code . ': ' . ($message ?: trim($raw) ?: 'request failed')
            );
        }

        if ($raw === '') {
            return ['status' => $code, 'data' => null, 'raw' => $raw];
        }

        if (stripos($accept, 'application/json') !== false) {
            $json = json_decode($raw, true);
            if (!is_array($json)) {
                throw new RuntimeException('RouteBox returned invalid JSON.');
            }
            if (array_key_exists('success', $json) && $json['success'] === false) {
                throw new RuntimeException((string) ($json['error'] ?? 'RouteBox API request failed.'));
            }
            $data = array_key_exists('data', $json) ? $json['data'] : $json;
            return ['status' => $code, 'data' => $data, 'raw' => $raw];
        }

        return ['status' => $code, 'data' => $raw, 'raw' => $raw];
    }

    public function health(): array
    {
        $result = $this->request('GET', '/api/health')['data'];
        return is_array($result) ? $result : [];
    }

    public function status(): array
    {
        $result = $this->request('GET', '/api/status')['data'];
        return is_array($result) ? $result : [];
    }

    public function awgStatus(): array
    {
        $result = $this->request('GET', '/api/awg/status')['data'];
        return is_array($result) ? $result : [];
    }

    public function settings(): array
    {
        $result = $this->request('GET', '/api/settings')['data'];
        return is_array($result) ? $result : [];
    }

    public function peers(): array
    {
        $result = $this->request('GET', '/api/awg/peers')['data'];
        return is_array($result) ? $result : [];
    }

    public function validateIntegration(): array
    {
        $health = $this->health();
        $status = $this->status();
        $awg = $this->awgStatus();
        $peers = $this->peers();
        $settings = $this->settings();

        return [
            'health' => $health,
            'status' => $status,
            'awg' => $awg,
            'peer_count' => count($peers),
            'settings' => $settings,
        ];
    }

    public function createPeer(string $name): array
    {
        $result = $this->request('POST', '/api/awg/peers', ['name' => $name])['data'];
        return is_array($result) ? $result : [];
    }

    /**
     * Run the same end-to-end operation required by real provisioning:
     * create a temporary peer, fetch its real .conf, and delete it again.
     * Any created peer is cleaned up on success or failure.
     *
     * @return array{peer_name:string,public_key:string,config_bytes:int}
     */
    public function smokeTest(string $namePrefix = 'rbt-install-test'): array
    {
        $name = $namePrefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $publicKey = '';
        $configBytes = 0;
        $failure = null;

        try {
            $peer = $this->createPeer($name);
            $publicKey = (string) ($peer['public_key'] ?? $peer['publicKey'] ?? '');

            if ($publicKey === '') {
                foreach ($this->peers() as $candidate) {
                    if (($candidate['name'] ?? '') === $name) {
                        $publicKey = (string) ($candidate['public_key'] ?? $candidate['publicKey'] ?? '');
                        break;
                    }
                }
            }

            if ($publicKey === '') {
                throw new RuntimeException('RouteBox created the smoke-test peer but did not return its public key.');
            }

            $config = $this->config($publicKey);
            $configBytes = strlen($config);
            if (trim($config) === '') {
                throw new RuntimeException('RouteBox returned an empty client configuration.');
            }
        } catch (Throwable $error) {
            $failure = $error;
        }

        if ($publicKey !== '') {
            try {
                $this->deletePeer($publicKey);
            } catch (Throwable $cleanupError) {
                if ($failure === null) {
                    $failure = new RuntimeException('Smoke-test cleanup failed: ' . $cleanupError->getMessage(), (int) $cleanupError->getCode(), $cleanupError);
                } else {
                    $failure = new RuntimeException(
                        $failure->getMessage() . ' Cleanup also failed: ' . $cleanupError->getMessage(),
                        (int) $failure->getCode(),
                        $failure
                    );
                }
            }
        }

        if ($failure !== null) {
            throw $failure;
        }

        return [
            'peer_name' => $name,
            'public_key' => $publicKey,
            'config_bytes' => $configBytes,
        ];
    }

    public function config(string $publicKey): string
    {
        $path = '/api/awg/peers/' . rawurlencode($publicKey) . '/config';
        $result = $this->request('GET', $path, null, 'text/plain; charset=utf-8');
        $raw = (string) $result['data'];
        if (trim($raw) === '') {
            throw new RuntimeException('RouteBox returned an empty client configuration.');
        }
        return $raw;
    }

    public function vpnLink(string $publicKey): string
    {
        $path = '/api/awg/peers/' . rawurlencode($publicKey) . '/vpn-link';
        return trim((string) $this->request('GET', $path, null, 'text/plain; charset=utf-8')['data']);
    }

    public function singbox(string $publicKey): array
    {
        $result = $this->request('GET', '/api/awg/peers/' . rawurlencode($publicKey) . '/singbox')['data'];
        return is_array($result) ? $result : [];
    }

    public function setExpiry(string $publicKey, int $expiresAt, ?int $quotaBytes = null): array
    {
        $body = ['expires_at' => $expiresAt];
        if ($quotaBytes !== null) {
            $body['quota_bytes'] = max(0, $quotaBytes);
        }
        $result = $this->request(
            'PATCH',
            '/api/awg/peers/' . rawurlencode($publicKey) . '/expiry',
            $body
        )['data'];
        return is_array($result) ? $result : [];
    }

    public function resetTraffic(string $publicKey): void
    {
        $this->request('POST', '/api/awg/peers/' . rawurlencode($publicKey) . '/traffic/reset');
    }

    public function deletePeer(string $publicKey): void
    {
        $this->request('DELETE', '/api/awg/peers/' . rawurlencode($publicKey));
    }

    public function enableAwg(): array
    {
        $result = $this->request('POST', '/api/awg/enable')['data'];
        return is_array($result) ? $result : [];
    }

    public function disableAwg(): array
    {
        $result = $this->request('POST', '/api/awg/disable')['data'];
        return is_array($result) ? $result : [];
    }
}
