<?php

declare(strict_types=1);

final class RouteBoxClient
{
    private string $base;
    private string $user;
    private string $pass;
    private bool $verifyTls;

    public function __construct(string $base, string $user, string $pass, bool $verifyTls = true)
    {
        $this->base = rtrim($base, '/');
        $this->user = $user;
        $this->pass = $pass;
        $this->verifyTls = $verifyTls;
    }

    /** @return array{status:int,data:mixed,raw:string} */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->base . $path);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }
        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->user . ':' . $this->pass,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('RouteBox connection failed: ' . ($error ?: 'unknown cURL error'));
        }
        if ($code < 200 || $code >= 300) {
            $json = json_decode($raw, true);
            $message = is_array($json) ? ($json['error'] ?? $json['message'] ?? '') : '';
            throw new RuntimeException('RouteBox HTTP ' . $code . ': ' . ($message ?: trim($raw) ?: 'request failed'));
        }
        if ($raw === '') {
            return ['status' => $code, 'data' => null, 'raw' => $raw];
        }
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

    public function status(): array
    {
        $result = $this->request('GET', '/api/status')['data'];
        return is_array($result) ? $result : [];
    }

    public function peers(): array
    {
        $result = $this->request('GET', '/api/awg/peers')['data'];
        return is_array($result) ? $result : [];
    }

    public function createPeer(string $name): array
    {
        $result = $this->request('POST', '/api/awg/peers', ['name' => $name])['data'];
        return is_array($result) ? $result : [];
    }

    public function config(string $publicKey): string
    {
        $ch = curl_init($this->base . '/api/awg/peers/' . rawurlencode($publicKey) . '/config');
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->user . ':' . $this->pass,
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('RouteBox config request failed: ' . ($error ?: 'unknown cURL error'));
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('RouteBox config HTTP ' . $code . ': ' . trim($raw));
        }
        if (trim($raw) === '') {
            throw new RuntimeException('RouteBox returned an empty client configuration.');
        }
        return $raw;
    }

    public function setExpiry(string $publicKey, int $expiresAt): array
    {
        $result = $this->request('PATCH', '/api/awg/peers/' . rawurlencode($publicKey) . '/expiry', ['expires_at' => $expiresAt])['data'];
        return is_array($result) ? $result : [];
    }

    public function deletePeer(string $publicKey): void
    {
        $this->request('DELETE', '/api/awg/peers/' . rawurlencode($publicKey));
    }
}
