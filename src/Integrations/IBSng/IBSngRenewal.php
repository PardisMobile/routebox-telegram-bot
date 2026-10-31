<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Single business-logic entry point for IBSng renewals.
 *
 * The caller must supply account state parsed and verified from the IBSng
 * A1.24 Web Panel. This class owns policy, idempotency and the renewal ledger;
 * IBSngClient owns HTTP/session mechanics only.
 *
 * It deliberately fails closed when account state is incomplete.
 */
final class IBSngRenewal
{
    /**
     * Client-facing renewal implementation. IBSngClient::renewUser() delegates
     * here, while this module uses a distinct low-level transport method to
     * avoid recursion.
     */
    public static function executeClientRenewal(
        IBSngClient $client,
        string|int $userId,
        string $username = '',
        ?int $relativeExpiration = null,
        bool $resetFirstLogin = false,
        string $comment = '',
        string|int $groupId = '',
        string $groupName = ''
    ): array {
        if (
            trim($username) === ''
            || ($relativeExpiration === null && !$resetFirstLogin)
            || (string)$groupId === ''
            || trim($groupName) === ''
        ) {
            throw new RuntimeException(
                'IBSng renewal requires verified account state and a mapped IBSng group.'
            );
        }

        return $client->submitRenewalAttributes(
            $userId,
            $username,
            $relativeExpiration,
            $resetFirstLogin,
            $comment,
            $groupId,
            $groupName
        );
    }

    /**
     * Pure renewal-policy calculation. Kept separate from HTTP and persistence
     * so the two account states and the intentional zero-gift case are testable.
     *
     * @return array{relative_expiration:int,reset_first_login:bool}
     */
    public static function calculateRelativeExpiration(
        bool $expired,
        int $remainingDays,
        int $validityDays,
        int $giftDays
    ): array {
        if ($remainingDays < 0 || $validityDays < 1 || $giftDays < 0) {
            throw new RuntimeException('IBSng renewal duration values are invalid.');
        }

        return [
            'relative_expiration' => ($expired ? 0 : $remainingDays) + $validityDays + $giftDays,
            'reset_first_login' => $expired,
        ];
    }

    public function __construct(private readonly PDO $db)
    {
        IBSngSchema::ensure($db);
    }

    /**
     * @param array{expired:bool,remaining_days?:int} $accountState
     */
    public function renew(
        IBSngClient $client,
        int $serverId,
        string|int $userId,
        string $username,
        int $planId,
        array $accountState,
        string $idempotencyKey
    ): array {
        $username = trim($username);
        $idempotencyKey = trim($idempotencyKey);
        if ($serverId < 1 || (string)$userId === '' || $username === '' || $planId < 1) {
            throw new RuntimeException('IBSng renewal request is incomplete.');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 200) {
            throw new RuntimeException('A valid IBSng renewal idempotency key is required.');
        }

        // A failed request may have reached IBSng before its HTTP response was
        // lost. Require manual reconciliation instead of risking a second grant.
        $unresolved = $this->db->prepare(
            "SELECT idempotency_key,status FROM ibsng_renewals
             WHERE ibsng_server_id=? AND ibsng_user_id=? AND status IN ('pending','failed')
             LIMIT 1"
        );
        $unresolved->execute([$serverId, (string)$userId]);
        $unresolvedRow = $unresolved->fetch(PDO::FETCH_ASSOC);
        if (is_array($unresolvedRow)) {
            throw new RuntimeException(
                'This IBSng user has an unresolved renewal operation; reconcile it before starting another renewal.'
            );
        }

        if (!array_key_exists('expired', $accountState) || !is_bool($accountState['expired'])) {
            throw new RuntimeException('IBSng account state has not been verified.');
        }

        $planQuery = $this->db->prepare(
            "SELECT ibsng_validity_days,provider_plan_key FROM service_plans
             WHERE id=? AND provider_key='ibsng' AND provider_server_id=?"
        );
        $planQuery->execute([$planId, $serverId]);
        $plan = $planQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan) || $plan['ibsng_validity_days'] === null || (int)$plan['ibsng_validity_days'] < 1) {
            throw new RuntimeException('Set a valid IBSng plan duration before renewing this account.');
        }
        $validityDays = (int)$plan['ibsng_validity_days'];
        $groupName = trim((string)($plan['provider_plan_key'] ?? ''));
        if ($groupName === '') {
            throw new RuntimeException('The selected IBSng plan has no mapped group name.');
        }
        $groupInfo = $client->getGroupInfo($groupName);
        $groupId = $groupInfo['group_id'] ?? null;
        if ($groupId === null || (string)$groupId === '') {
            throw new RuntimeException('Could not verify the IBSng group ID from its Web Panel.');
        }

        $settingsQuery = $this->db->query(
            "SELECT setting_key,setting_value FROM ibsng_renewal_settings
             WHERE setting_key IN ('gift_days_first','gift_days_repeat')"
        );
        $settings = [];
        foreach ($settingsQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string)$row['setting_key']] = (int)$row['setting_value'];
        }
        $firstGift = max(0, $settings['gift_days_first'] ?? 1);
        $repeatGift = max(0, $settings['gift_days_repeat'] ?? 5);

        $countQuery = $this->db->prepare(
            "SELECT COUNT(*) FROM ibsng_renewals
             WHERE ibsng_server_id=? AND ibsng_user_id=? AND status='completed'"
        );
        $countQuery->execute([$serverId, (string)$userId]);
        $completedRenewals = (int)$countQuery->fetchColumn();
        $giftDays = $completedRenewals === 0 ? $firstGift : $repeatGift;

        $expired = $accountState['expired'];
        if (!$expired && (!isset($accountState['remaining_days']) || !is_int($accountState['remaining_days']) || $accountState['remaining_days'] < 0)) {
            throw new RuntimeException('Remaining validity for this active IBSng account is not verified.');
        }

        // IBSng A1.24 renewal policy is calculated in one pure function.
        // Absolute Expiration is never included in or changed by this operation.
        $policy = self::calculateRelativeExpiration(
            $expired,
            $expired ? 0 : (int)$accountState['remaining_days'],
            $validityDays,
            $giftDays
        );
        $relativeExpiration = $policy['relative_expiration'];
        $resetFirstLogin = $policy['reset_first_login'];
        $accountStateLabel = $expired ? 'expired' : 'active';
        $now = time();

        // Reserve this operation before touching IBSng. A duplicate key never
        // causes a second external mutation, including ambiguous timeouts.
        try {
            $insert = $this->db->prepare(
                "INSERT INTO ibsng_renewals
                 (idempotency_key,ibsng_server_id,ibsng_user_id,username,plan_id,status,
                  account_state,validity_days,gift_days,result_json,error_text,created_at,updated_at)
                 VALUES(?,?,?,?,?,'pending',?,?,?,'{}',NULL,?,?)"
            );
            $insert->execute([
                $idempotencyKey,
                $serverId,
                (string)$userId,
                $username,
                $planId,
                $accountStateLabel,
                $validityDays,
                $giftDays,
                $now,
                $now,
            ]);
        } catch (Throwable $e) {
            $existing = $this->db->prepare(
                'SELECT status,result_json FROM ibsng_renewals WHERE idempotency_key=?'
            );
            $existing->execute([$idempotencyKey]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && (string)$row['status'] === 'completed') {
                $decoded = json_decode((string)$row['result_json'], true);
                return is_array($decoded) ? $decoded : ['completed' => true];
            }
            if (is_array($row)) {
                throw new RuntimeException(
                    'This IBSng renewal key is already recorded as pending or failed; inspect the operation before retrying.'
                );
            }
            throw $e;
        }

        try {
            $result = $client->renewUser(
                $userId,
                $username,
                $relativeExpiration,
                $resetFirstLogin,
                'RouteBox renewal ' . $idempotencyKey,
                (string)$groupId,
                $groupName
            );
            $payload = [
                'completed' => true,
                'user_id' => (string)$userId,
                'username' => $username,
                'account_state' => $accountStateLabel,
                'validity_days' => $validityDays,
                'gift_days' => $giftDays,
                'relative_expiration' => $relativeExpiration,
                'first_login_reset' => $resetFirstLogin,
                'raw' => $result,
            ];
            $finish = $this->db->prepare(
                "UPDATE ibsng_renewals
                 SET status='completed',result_json=?,error_text=NULL,completed_at=?,updated_at=?
                 WHERE idempotency_key=? AND status='pending'"
            );
            $finish->execute([
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                time(),
                time(),
                $idempotencyKey,
            ]);
            if ($finish->rowCount() !== 1) {
                throw new RuntimeException('IBSng renewal result could not be committed to the ledger.');
            }
            return $payload;
        } catch (Throwable $e) {
            // Do not automatically retry an uncertain external side effect.
            $fail = $this->db->prepare(
                "UPDATE ibsng_renewals SET status='failed',error_text=?,updated_at=?
                 WHERE idempotency_key=? AND status='pending'"
            );
            $fail->execute([substr($e->getMessage(), 0, 1000), time(), $idempotencyKey]);
            throw $e;
        }
    }
}
