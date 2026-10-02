<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use RuntimeException;

/**
 * RouterOS REST API client used by the MikroTik WireGuard provider.
 *
 * MTWireGuard's reference implementation uses RouterOS REST resources such as
 * interface/wireguard, interface/wireguard/peers, ip/pool, ip/address,
 * ip/dns and queue/simple. RouteBox keeps the transport isolated here and does
 * not require a container or a second MikroTik-side application.
 */
final class MikroTikClient
{
    private string $baseUrl;
    private float $lastRequestMs = 0.0;

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 443,
        private readonly bool $tls = true,
        private readonly int $timeout = 10,
    ) {
        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('MikroTik connection settings are incomplete.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new RuntimeException('MikroTik API port is invalid.');
        }
        $scheme = $this->tls ? 'https' : 'http';
        $this->baseUrl = $scheme . '://' . $this->host . ':' . $this->port . '/rest';
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize MikroTik HTTP client.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Connection: close',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'RouteBox-MikroTik-WireGuard/1.0',
        ];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $started = microtime(true);
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $this->lastRequestMs = round((microtime(true) - $started) * 1000, 1);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('MikroTik REST connection failed: ' . ($error !== '' ? $error : 'cURL error ' . $errno));
        }
        $decoded = json_decode((string)$body, true);
        if ($status < 200 || $status >= 300) {
            $detail = is_array($decoded) ? (string)($decoded['detail'] ?? $decoded['message'] ?? '') : trim((string)$body);
            throw new RuntimeException('MikroTik REST HTTP ' . $status . ($detail !== '' ? ': ' . $detail : '.'));
        }
        if ($body === '' || $decoded === null) {
            return [];
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('MikroTik REST returned an invalid JSON response.');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    public function testConnection(): array
    {
        $info = $this->routerInfo();
        $latency = $this->lastRequestMs;
        $interfaces = $this->wireguardInterfaces();
        return [
            'status' => 'ok',
            'message' => 'RouterOS REST API connection successful.',
            'router' => $info,
            'latency_ms' => $latency,
            'wireguard_available' => count($interfaces) > 0,
            'wireguard_interfaces' => $interfaces,
            'pools' => $this->ipPools(),
            'dns' => $this->dnsSettings(),
        ];
    }

    /** @return array<string,mixed> */
    public function routerInfo(): array
    {
        $rows = $this->request('GET', 'system/resource');

        // RouterOS returns /system/resource as a single JSON object, while
        // collection endpoints return an array of objects. Keep both forms
        // compatible so fields such as version/uptime/cpu-load are preserved.
        if (array_is_list($rows)) {
            $first = $rows[0] ?? [];
            return is_array($first) ? $first : [];
        }

        return $rows;
    }

    public function lastRequestLatencyMs(): float
    {
        return $this->lastRequestMs;
    }

    /** @return array<int,array<string,mixed>> */
    public function wireguardInterfaces(): array
    {
        return $this->request('GET', 'interface/wireguard');
    }

    /** @return array<int,array<string,mixed>> */
    public function wireguardPeers(?string $interface = null): array
    {
        $rows = $this->request('GET', 'interface/wireguard/peers');
        if ($interface === null || $interface === '') {
            return $rows;
        }
        return array_values(array_filter($rows, static fn(array $row): bool => (string)($row['interface'] ?? '') === $interface));
    }

    /** @return array<int,array<string,mixed>> */
    public function ipPools(): array
    {
        return $this->request('GET', 'ip/pool');
    }

    /** @return array<int,array<string,mixed>> */
    public function ipAddresses(): array
    {
        return $this->request('GET', 'ip/address');
    }

    /** @return array<string,mixed> */
    public function dnsSettings(): array
    {
        $rows = $this->request('GET', 'ip/dns');
        if (array_is_list($rows)) {
            $first = $rows[0] ?? [];
            return is_array($first) ? $first : [];
        }
        return $rows;
    }

    /** @return array<string,mixed> */
    public function createPeer(array $peer): array
    {
        return $this->request('PUT', 'interface/wireguard/peers', $peer);
    }

    /** @return array<string,mixed> */
    public function updatePeer(string $id, array $peer): array
    {
        return $this->request('PATCH', 'interface/wireguard/peers/' . rawurlencode($id), $peer);
    }

    public function deletePeer(string $id): void
    {
        $this->request('DELETE', 'interface/wireguard/peers/' . rawurlencode($id));
    }

    /** @return array<string,mixed> */
    public function createQueue(array $queue): array
    {
        return $this->request('PUT', 'queue/simple', $queue);
    }

    public function deleteQueue(string $id): void
    {
        $this->request('DELETE', 'queue/simple/' . rawurlencode($id));
    }

    /** @return array<int,array<string,mixed>> */
    public function simpleQueues(): array
    {
        return $this->request('GET', 'queue/simple');
    }
}
