<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RouteBox\Integrations\ServiceProviderInterface;
use RuntimeException;

/** IBSng service adapter. Provider-specific Web Panel details stay in IBSngClient. */
final class IBSngProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly IBSngClient $client,
        private readonly ?PDO $db = null
    ) {}

    public function key(): string { return 'ibsng'; }

    public function testConnection(): void
    {
        $this->client->testConnection();
    }

    public function listPlans(): array
    {
        // IBSng plans are managed manually by RouteBox. The provider no longer
        // depends on the slow/broken Group List page to discover plans.
        return [];
    }

    public function createSubscription(array $context, array $plan): array
    {
        $group = trim((string)($plan['provider_plan_key'] ?? $plan['group_name'] ?? ''));
        $isp = trim((string)($context['isp_name'] ?? 'Main'));
        if ($group === '') throw new RuntimeException('IBSng group is required.');
        $created = $this->client->createUser($isp, $group, (int)($context['credit'] ?? 0));
        $userId = $created['user_id'] ?? $created['user_ids'][0] ?? $created['id'] ?? null;
        if ($userId === null) return ['raw' => $created];
        $username = trim((string)($context['username'] ?? ''));
        $password = (string)($context['password'] ?? '');
        if ($username !== '' && $password !== '') {
            $this->client->setUserCredentials($userId, $username, $password);
        }
        return ['provider_reference' => (string)$userId, 'user_id' => $userId, 'raw' => $created];
    }

    public function renewSubscription(array $context, array $plan): array
    {
        // Renewal policy, plan validity, gift days and idempotency belong to
        // IBSngRenewal. The provider only adapts the generic provider contract.
        if ($this->db === null) {
            throw new RuntimeException('IBSng renewal requires the application database so the central renewal ledger can be used.');
        }

        $userId = $context['user_id'] ?? $context['provider_reference'] ?? null;
        $serverId = (int)($context['provider_server_id'] ?? $context['server_id'] ?? $plan['provider_server_id'] ?? 0);
        $planId = (int)($context['plan_id'] ?? $plan['id'] ?? 0);
        $username = trim((string)($context['username'] ?? ''));
        $accountState = $context['account_state'] ?? null;
        $idempotencyKey = trim((string)($context['idempotency_key'] ?? ''));

        if ($userId === null || (string)$userId === '' || $serverId < 1 || $planId < 1 || $username === '') {
            throw new RuntimeException('IBSng renewal requires the linked user, server, plan and username.');
        }
        if (!is_array($accountState)) {
            throw new RuntimeException('Verified IBSng account state is required for renewal.');
        }
        if ($idempotencyKey === '') {
            throw new RuntimeException('A unique IBSng renewal idempotency key is required.');
        }

        $result = (new IBSngService($this->db))->renewWithVerifiedState(
            $serverId,
            $userId,
            $username,
            $planId,
            $accountState,
            $idempotencyKey
        );

        return [
            'provider_reference' => (string)$userId,
            'user_id' => (string)$userId,
            'renewal' => $result,
        ];
    }

    public function getSubscription(array $context): array
    {
        $username = trim((string)($context['username'] ?? ''));
        if ($username === '') throw new RuntimeException('IBSng username is required.');
        return $this->client->getUserInfoByUsername($username);
    }

    public function deleteSubscription(array $context): void
    {
        throw new RuntimeException('IBSng user deletion is not enabled in the current adapter.');
    }
}
