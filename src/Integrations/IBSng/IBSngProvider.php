<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RouteBox\Integrations\ServiceProviderInterface;
use RuntimeException;

/** IBSng service adapter. Provider-specific Web Panel details stay in IBSngClient. */
final class IBSngProvider implements ServiceProviderInterface
{
    public function __construct(private readonly IBSngClient $client) {}

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
        // Keep the provider contract available. Callers must pass account state
        // already verified from IBSng A1.24 and the mapped group context.
        $userId = $context['user_id'] ?? $context['provider_reference'] ?? null;
        if ($userId === null || (string)$userId === '') {
            throw new RuntimeException('IBSng user ID is required for renewal.');
        }

        $username = trim((string)($context['username'] ?? ''));
        $accountState = $context['account_state'] ?? [];
        if (!is_array($accountState) || !isset($accountState['expired']) || !is_bool($accountState['expired'])) {
            throw new RuntimeException('Verified IBSng account state is required for renewal.');
        }

        $groupName = trim((string)($plan['provider_plan_key'] ?? $plan['group_name'] ?? ''));
        $groupId = $context['group_id'] ?? '';
        $validityDays = (int)($plan['ibsng_validity_days'] ?? $context['validity_days'] ?? 0);
        if ($groupName === '' || (string)$groupId === '' || $validityDays < 1) {
            throw new RuntimeException('IBSng renewal requires a mapped group and positive validity duration.');
        }

        $expired = $accountState['expired'];
        $relativeExpiration = $expired
            ? null
            : (int)($accountState['remaining_days'] ?? -1) + $validityDays + (int)($context['gift_days'] ?? 0);
        if (!$expired && (int)($accountState['remaining_days'] ?? -1) < 0) {
            throw new RuntimeException('Remaining validity for this active IBSng account is not verified.');
        }

        $result = IBSngRenewal::executeClientRenewal(
            $this->client,
            $userId,
            $username,
            $relativeExpiration,
            $expired,
            (string)($context['comment'] ?? 'RouteBox renewal'),
            $groupId,
            $groupName
        );

        return ['provider_reference' => (string)$userId, 'user_id' => (string)$userId, 'raw' => $result];
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
