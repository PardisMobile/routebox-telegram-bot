<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

use RuntimeException;

/**
 * MikroTik WireGuard account lifecycle.
 * A WireGuard peer is the provider account; RouteBox owns the local metadata.
 */
final class MikroTikWireGuardService
{
    public function __construct(private readonly MikroTikClient $client) {}

    /** @return array<string,mixed> */
    public function createPeer(array $context): array
    {
        $interface = trim((string)($context['interface'] ?? ''));
        $poolName = trim((string)($context['pool'] ?? ''));
        $username = trim((string)($context['username'] ?? ''));
        if ($interface === '' || $poolName === '' || $username === '') {
            throw new RuntimeException('MikroTik WireGuard interface, IP pool and username are required.');
        }

        $pool = $this->findPool($poolName);
        if (!$pool) {
            throw new RuntimeException('Configured MikroTik IP pool was not found.');
        }

        $used = $this->usedAddresses($interface);
        $assignedIp = $this->allocateAddress((string)($pool['ranges'] ?? ''), $used);
        if ($assignedIp === null) {
            throw new RuntimeException('No free address is available in the configured MikroTik IP pool.');
        }

        [$privateKey, $publicKey] = $this->generateKeyPair();
        $payload = [
            'name' => $username,
            'interface' => $interface,
            'public-key' => $publicKey,
            'allowed-address' => $assignedIp . '/32',
            'disabled' => false,
            'persistent-keepalive' => (string)max(0, (int)($context['keepalive'] ?? 25)),
        ];

        $created = $this->client->createPeer($payload);
        $routerId = (string)($created['.id'] ?? $created['id'] ?? '');
        if ($routerId === '') {
            throw new RuntimeException('MikroTik did not return the created WireGuard peer ID.');
        }

        $interfaceInfo = $this->findInterface($interface);
        $endpoint = trim((string)($context['endpoint'] ?? ''));
        $endpointPort = (int)($context['endpoint_port'] ?? ($interfaceInfo['listen-port'] ?? 51820));
        $dns = trim((string)($context['dns'] ?? ''));
        if ($dns === '') {
            $dns = $this->dnsServers();
        }

        $config = $this->buildConfig(
            $privateKey,
            $assignedIp,
            $dns,
            (string)($interfaceInfo['public-key'] ?? ''),
            $endpoint,
            $endpointPort
        );

        return [
            'routeros_id' => $routerId,
            'username' => $username,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'assigned_ip' => $assignedIp,
            'interface' => $interface,
            'server_public_key' => (string)($interfaceInfo['public-key'] ?? ''),
            'endpoint' => $endpoint,
            'endpoint_port' => $endpointPort,
            'dns' => $dns,
            'config' => $config,
        ];
    }

    /** @return array<string,mixed> */
    public function getPeer(string $username, ?string $interface = null): array
    {
        foreach ($this->client->wireguardPeers($interface) as $peer) {
            if ((string)($peer['name'] ?? '') === $username) {
                return $peer;
            }
        }
        return [];
    }

    public function disablePeer(string $routerId): void
    {
        $this->client->updatePeer($routerId, ['disabled' => true]);
    }

    public function enablePeer(string $routerId): void
    {
        $this->client->updatePeer($routerId, ['disabled' => false]);
    }

    public function deletePeer(string $routerId): void
    {
        $this->client->deletePeer($routerId);
    }

    /** @return array<int,array<string,mixed>> */
    public function listPeers(?string $interface = null): array
    {
        return $this->client->wireguardPeers($interface);
    }

    /** @return array<string,mixed> */
    private function findPool(string $name): array
    {
        foreach ($this->client->ipPools() as $pool) {
            if ((string)($pool['name'] ?? '') === $name) {
                return $pool;
            }
        }
        return [];
    }

    /** @return array<string,mixed> */
    private function findInterface(string $name): array
    {
        foreach ($this->client->wireguardInterfaces() as $interface) {
            if ((string)($interface['name'] ?? '') === $name) {
                return $interface;
            }
        }
        return [];
    }

    /** @return array<string,bool> */
    private function usedAddresses(string $interface): array
    {
        $used = [];
        foreach ($this->client->wireguardPeers($interface) as $peer) {
            $allowed = trim((string)($peer['allowed-address'] ?? ''));
            foreach (preg_split('/\s*,\s*/', $allowed) ?: [] as $cidr) {
                $ip = trim(explode('/', $cidr, 2)[0]);
                if ($ip !== '') {
                    $used[$ip] = true;
                }
            }
        }
        foreach ($this->client->ipAddresses() as $address) {
            $ip = trim((string)($address['address'] ?? ''));
            if ($ip !== '') {
                $used[trim(explode('/', $ip, 2)[0])] = true;
            }
        }
        return $used;
    }

    private function allocateAddress(string $ranges, array $used): ?string
    {
        foreach (preg_split('/\s*,\s*/', $ranges) ?: [] as $range) {
            $range = trim($range);
            if ($range === '') {
                continue;
            }
            if (str_contains($range, '-')) {
                [$from, $to] = array_map('trim', explode('-', $range, 2));
                $start = ip2long($from);
                $end = ip2long($to);
                if ($start === false || $end === false || $end < $start) {
                    continue;
                }
                for ($n = $start; $n <= $end; $n++) {
                    $ip = long2ip($n);
                    if (!isset($used[$ip])) {
                        return $ip;
                    }
                }
                continue;
            }
            if (str_contains($range, '/')) {
                [$network, $bits] = array_map('trim', explode('/', $range, 2));
                $base = ip2long($network);
                $bits = (int)$bits;
                if ($base === false || $bits < 0 || $bits > 32) {
                    continue;
                }
                $size = 2 ** (32 - $bits);
                $first = $base;
                $last = $base + $size - 1;
                for ($n = $first; $n <= $last; $n++) {
                    $ip = long2ip($n);
                    if (!isset($used[$ip])) {
                        return $ip;
                    }
                }
            } elseif (filter_var($range, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !isset($used[$range])) {
                return $range;
            }
        }
        return null;
    }

    /** @return array{0:string,1:string} */
    private function generateKeyPair(): array
    {
        if (!function_exists('sodium_crypto_scalarmult_base')) {
            throw new RuntimeException('PHP sodium extension is required for WireGuard key generation.');
        }
        $private = random_bytes(SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
        $public = sodium_crypto_scalarmult_base($private);
        return [base64_encode($private), base64_encode($public)];
    }

    private function dnsServers(): string
    {
        $dns = $this->client->dnsSettings();
        return trim((string)($dns['servers'] ?? ''));
    }

    private function buildConfig(
        string $privateKey,
        string $assignedIp,
        string $dns,
        string $serverPublicKey,
        string $endpoint,
        int $endpointPort
    ): string {
        if ($serverPublicKey === '') {
            throw new RuntimeException('MikroTik WireGuard interface public key is unavailable.');
        }
        if ($endpoint === '') {
            throw new RuntimeException('VPN endpoint is not configured for this MikroTik server.');
        }
        $dnsLine = $dns !== '' ? "DNS = {$dns}\n" : '';
        return "[Interface]\nPrivateKey = {$privateKey}\nAddress = {$assignedIp}/32\n{$dnsLine}\n[Peer]\nPublicKey = {$serverPublicKey}\nAllowedIPs = 0.0.0.0/0, ::/0\nEndpoint = {$endpoint}:{$endpointPort}\nPersistentKeepalive = 25\n";
    }
}
