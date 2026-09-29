<?php

declare(strict_types=1);

final class RouteBoxClient
{
    private string $base;
    private string $user;
    private string $pass;
    private bool $verifyTls;

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

    /** @return array{status:int,data:mixed,raw:string} */
    private function request(string $method, string $path, ?array $body = null, string $accept = 'application/json'): array
    {
        $url = $this->base . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $headers = ['Accept: ' . $accept];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ];

        // RouteBox explicitly supports HTTP Basic authentication for scripts.
        // If authentication is disabled on a router-mode install, empty
        // credentials are valid and no Authorization header is sent.
        if ($this->user !== '' || $this->pass !== '') {
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
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('RouteBox connection failed: ' . ($error ?: 'unknown cURL error'));
        }

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

    /**
     * Performs the exact read-only calls required by the bot before a RouteBox
     * server is accepted. This validates authentication as well as API routing.
     */
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
