<?php
declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RouteBox\Integrations\ServiceProviderInterface;
use RuntimeException;

/** IBSng service adapter. Provider-specific RPC details stay in IBSngClient. */
final class IBSngProvider implements ServiceProviderInterface
{
    public function __construct(private readonly IBSngClient $client) {}

    public function key(): string { return 'ibsng'; }

    public function testConnection(): void
    {
        $this->client->listGroups();
    }

    public function listPlans(): array
    {
        $plans = [];
        foreach ($this->client->listGroups() as $group) {
            $plans[] = ['provider_plan_key' => $group, 'name' => $group];
        }
        return $plans;
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
        $userId = $context['provider_reference'] ?? $context['user_id'] ?? null;
        if ($userId === null) throw new RuntimeException('IBSng user reference is required.');
        $result = $this->client->renewUser($userId, (string)($context['comment'] ?? 'RouteBox renewal'));
        return ['provider_reference' => (string)$userId, 'raw' => $result];
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
