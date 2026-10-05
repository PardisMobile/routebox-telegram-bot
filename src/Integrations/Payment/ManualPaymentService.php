<?php

declare(strict_types=1);

namespace RouteBox\Integrations\Payment;

use PDO;
use RuntimeException;
use RouteBox\Services\ServiceCatalog;

require_once __DIR__ . '/../../Services/ServiceCatalog.php';

/**
 * Provider-neutral manual/card-to-card payment workflow.
 *
 * This class owns Order/Payment/Receipt state only. Provider provisioning is
 * deliberately delegated to ServiceProvisioner after an Admin approval.
 */
final class ManualPaymentService
{
    private static bool $schemaReady = false;

    public function __construct(private readonly PDO $db)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL UNIQUE,
            method TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'awaiting_receipt',
            amount_minor INTEGER NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT 'IRR',
            reviewed_by TEXT,
            reviewed_at INTEGER,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            metadata_json TEXT NOT NULL DEFAULT '{}',
            FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS payment_receipts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            payment_id INTEGER NOT NULL,
            order_id INTEGER NOT NULL,
            telegram_user_id INTEGER NOT NULL,
            telegram_file_id TEXT NOT NULL,
            telegram_file_unique_id TEXT,
            file_type TEXT NOT NULL,
            mime_type TEXT,
            caption TEXT,
            status TEXT NOT NULL DEFAULT 'submitted',
            created_at INTEGER NOT NULL,
            FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE CASCADE,
            FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY(telegram_user_id) REFERENCES telegram_users(id) ON DELETE CASCADE
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status,created_at DESC)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_payment_receipts_payment ON payment_receipts(payment_id,created_at DESC)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_payment_receipts_user ON payment_receipts(telegram_user_id,created_at DESC)');

        $defaults = [
            ['manual_payment_enabled', '0'],
            ['manual_payment_card_number', ''],
            ['manual_payment_card_holder', ''],
            ['manual_payment_bank', ''],
            ['manual_payment_instructions', ''],
        ];
        $insert = $this->db->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)');
        foreach ($defaults as [$key, $value]) {
            $insert->execute([$key, $value]);
        }

        self::$schemaReady = true;
    }

    public function enabled(): bool
    {
        return $this->setting('manual_payment_enabled', '0') === '1';
    }

    public function settings(): array
    {
        return [
            'enabled' => $this->enabled(),
            'card_number' => $this->setting('manual_payment_card_number', ''),
            'card_holder' => $this->setting('manual_payment_card_holder', ''),
            'bank' => $this->setting('manual_payment_bank', ''),
            'instructions' => $this->setting('manual_payment_instructions', ''),
            'currency' => $this->setting('payment_currency', 'IRR'),
        ];
    }

    public function createOrder(int $telegramUserId, int $planId): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('پرداخت کارت‌به‌کارت هنوز توسط مدیریت فعال نشده است.');
        }

        $catalog = new ServiceCatalog($this->db);
        $plan = $catalog->plan($planId);
        $amount = (int)$plan['price_minor'];
        if ($amount <= 0) {
            throw new RuntimeException('این پلن قیمت قابل پرداخت ندارد.');
        }

        $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare(
                "SELECT o.id,o.status,p.status payment_status
                 FROM orders o
                 LEFT JOIN payments p ON p.order_id=o.id
                 WHERE o.telegram_user_id=?
                   AND o.status IN ('pending_payment','receipt_pending','approved','provisioning','provision_failed')
                 ORDER BY o.id DESC LIMIT 1"
            );
            $existing->execute([$telegramUserId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                throw new RuntimeException('یک سفارش فعال دارید: #' . (int)$row['id'] . ' — ابتدا همان سفارش را تکمیل کنید.');
            }

            $now = time();
            $currency = $this->setting('payment_currency', 'IRR');
            $order = $this->db->prepare(
                'INSERT INTO orders(telegram_user_id,category_id,plan_id,subtotal_minor,discount_minor,total_minor,currency,status,payment_provider,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)'
            );
            $order->execute([
                $telegramUserId,
                (int)$plan['category_id'],
                $planId,
                $amount,
                0,
                $amount,
                $currency,
                'pending_payment',
                'card_to_card',
                $now,
                $now,
            ]);
            $orderId = (int)$this->db->lastInsertId();

            $payment = $this->db->prepare(
                'INSERT INTO payments(order_id,method,status,amount_minor,currency,created_at,updated_at) VALUES(?,?,?,?,?,?,?)'
            );
            $payment->execute([$orderId, 'card_to_card', 'awaiting_receipt', $amount, $currency, $now, $now]);
            $paymentId = (int)$this->db->lastInsertId();

            $this->db->commit();
            return ['order_id' => $orderId, 'payment_id' => $paymentId, 'plan' => $plan, 'amount' => $amount, 'currency' => $currency];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function currentOrder(int $telegramUserId): ?array
    {
        $q = $this->db->prepare(
            "SELECT o.*,p.id payment_id,p.method,p.status payment_status,p.amount_minor payment_amount,p.reviewed_by,p.reviewed_at,
                    c.name_fa category_name_fa,c.name_en category_name_en,
                    sp.display_name_fa,sp.display_name_en,sp.provider_key
             FROM orders o
             JOIN payments p ON p.order_id=o.id
             JOIN service_categories c ON c.id=o.category_id
             JOIN service_plans sp ON sp.id=o.plan_id
             WHERE o.telegram_user_id=?
               AND o.status IN ('pending_payment','receipt_pending','approved','provisioning','provision_failed')
             ORDER BY o.id DESC LIMIT 1"
        );
        $q->execute([$telegramUserId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function order(int $orderId, ?int $telegramUserId = null): ?array
    {
        $sql = "SELECT o.*,p.id payment_id,p.method,p.status payment_status,p.amount_minor payment_amount,p.reviewed_by,p.reviewed_at,
                       c.name_fa category_name_fa,c.name_en category_name_en,
                       sp.display_name_fa,sp.display_name_en,sp.provider_key,
                       u.telegram_id,u.username tg_username,u.first_name
                FROM orders o
                JOIN payments p ON p.order_id=o.id
                JOIN service_categories c ON c.id=o.category_id
                JOIN service_plans sp ON sp.id=o.plan_id
                JOIN telegram_users u ON u.id=o.telegram_user_id
                WHERE o.id=?";
        $params = [$orderId];
        if ($telegramUserId !== null) {
            $sql .= ' AND o.telegram_user_id=?';
            $params[] = $telegramUserId;
        }
        $sql .= ' LIMIT 1';
        $q = $this->db->prepare($sql);
        $q->execute($params);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function attachReceipt(
        int $telegramUserId,
        string $fileId,
        string $fileUniqueId,
        string $fileType,
        ?string $mimeType,
        ?string $caption
    ): array {
        $order = $this->currentOrder($telegramUserId);
        if (!$order) {
            throw new RuntimeException('سفارش پرداخت فعالی ندارید. ابتدا یک سرویس را انتخاب کنید.');
        }
        if (!in_array((string)$order['payment_status'], ['awaiting_receipt', 'review'], true)) {
            throw new RuntimeException('این سفارش دیگر در انتظار رسید نیست.');
        }

        $now = time();
        $this->db->beginTransaction();
        try {
            $receipt = $this->db->prepare(
                'INSERT INTO payment_receipts(payment_id,order_id,telegram_user_id,telegram_file_id,telegram_file_unique_id,file_type,mime_type,caption,status,created_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            );
            $receipt->execute([
                (int)$order['payment_id'],
                (int)$order['id'],
                $telegramUserId,
                $fileId,
                $fileUniqueId !== '' ? $fileUniqueId : null,
                $fileType,
                $mimeType,
                $caption,
                'submitted',
                $now,
            ]);
            $receiptId = (int)$this->db->lastInsertId();
            $this->db->prepare("UPDATE payments SET status='review',updated_at=? WHERE id=? AND status IN ('awaiting_receipt','review')")
                ->execute([$now, (int)$order['payment_id']]);
            $this->db->prepare("UPDATE orders SET status='receipt_pending',updated_at=? WHERE id=? AND status IN ('pending_payment','receipt_pending')")
                ->execute([$now, (int)$order['id']]);
            $this->db->commit();
            return ['order' => $this->order((int)$order['id'], $telegramUserId), 'receipt_id' => $receiptId];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function pendingPayments(int $limit = 20): array
    {
        $q = $this->db->prepare(
            "SELECT p.id payment_id,p.status payment_status,p.amount_minor,p.currency,p.created_at payment_created_at,
                    o.id order_id,o.status order_status,o.telegram_user_id,
                    u.telegram_id,u.username tg_username,u.first_name,
                    sp.display_name_fa,sp.display_name_en,sp.provider_key,
                    r.id receipt_id,r.telegram_file_id,r.telegram_file_unique_id,r.file_type,r.mime_type,r.caption,r.created_at receipt_created_at
             FROM payments p
             JOIN orders o ON o.id=p.order_id
             JOIN telegram_users u ON u.id=o.telegram_user_id
             JOIN service_plans sp ON sp.id=o.plan_id
             JOIN payment_receipts r ON r.id=(SELECT rr.id FROM payment_receipts rr WHERE rr.payment_id=p.id ORDER BY rr.id DESC LIMIT 1)
             WHERE p.status='review'
             ORDER BY r.created_at ASC,p.id ASC LIMIT ?"
        );
        $q->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function payment(int $paymentId): ?array
    {
        $q = $this->db->prepare(
            "SELECT p.*,o.status order_status,o.telegram_user_id,o.category_id,o.plan_id,
                    u.telegram_id,u.username tg_username,u.first_name,
                    sp.display_name_fa,sp.display_name_en,sp.provider_key,
                    r.id receipt_id,r.telegram_file_id,r.telegram_file_unique_id,r.file_type,r.mime_type,r.caption,r.status receipt_status
             FROM payments p
             JOIN orders o ON o.id=p.order_id
             JOIN telegram_users u ON u.id=o.telegram_user_id
             JOIN service_plans sp ON sp.id=o.plan_id
             LEFT JOIN payment_receipts r ON r.id=(SELECT rr.id FROM payment_receipts rr WHERE rr.payment_id=p.id ORDER BY rr.id DESC LIMIT 1)
             WHERE p.id=? LIMIT 1"
        );
        $q->execute([$paymentId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function approve(int $paymentId, string $adminTelegramId): array
    {
        $payment = $this->payment($paymentId);
        if (!$payment) {
            throw new RuntimeException('Payment پیدا نشد.');
        }
        if ((string)$payment['payment_status'] !== 'review') {
            return $payment;
        }
        $now = time();
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare(
                "UPDATE payments SET status='approved',reviewed_by=?,reviewed_at=?,updated_at=? WHERE id=? AND status='review'"
            );
            $q->execute([$adminTelegramId, $now, $now, $paymentId]);
            if ($q->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->payment($paymentId) ?? $payment;
            }
            $this->db->prepare("UPDATE orders SET status='approved',updated_at=? WHERE id=? AND status='receipt_pending'")
                ->execute([$now, (int)$payment['order_id']]);
            $this->db->prepare("UPDATE payment_receipts SET status='approved' WHERE payment_id=? AND status='submitted'")
                ->execute([$paymentId]);
            $this->db->commit();
            return $this->payment($paymentId) ?? $payment;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function reject(int $paymentId, string $adminTelegramId): array
    {
        $payment = $this->payment($paymentId);
        if (!$payment) {
            throw new RuntimeException('Payment پیدا نشد.');
        }
        if ((string)$payment['payment_status'] !== 'review') {
            return $payment;
        }
        $now = time();
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare(
                "UPDATE payments SET status='rejected',reviewed_by=?,reviewed_at=?,updated_at=? WHERE id=? AND status='review'"
            );
            $q->execute([$adminTelegramId, $now, $now, $paymentId]);
            if ($q->rowCount() !== 1) {
                $this->db->rollBack();
                return $this->payment($paymentId) ?? $payment;
            }
            $this->db->prepare("UPDATE orders SET status='rejected',updated_at=? WHERE id=? AND status='receipt_pending'")
                ->execute([$now, (int)$payment['order_id']]);
            $this->db->prepare("UPDATE payment_receipts SET status='rejected' WHERE payment_id=? AND status='submitted'")
                ->execute([$paymentId]);
            $this->db->commit();
            return $this->payment($paymentId) ?? $payment;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function claimProvisioning(int $orderId): bool
    {
        $q = $this->db->prepare("UPDATE orders SET status='provisioning',updated_at=? WHERE id=? AND status='approved'");
        $q->execute([time(), $orderId]);
        return $q->rowCount() === 1;
    }

    public function retryProvisioning(int $orderId): bool
    {
        $q = $this->db->prepare("UPDATE orders SET status='provisioning',updated_at=? WHERE id=? AND status='provision_failed'");
        $q->execute([time(), $orderId]);
        return $q->rowCount() === 1;
    }

    public function completeProvisioning(int $orderId, array $result): void
    {
        $this->db->prepare("UPDATE orders SET status='completed',updated_at=?,metadata_json=? WHERE id=? AND status='provisioning'")
            ->execute([time(), json_encode(['provisioning' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $orderId]);
        $this->db->prepare("UPDATE payments SET status='completed',updated_at=? WHERE order_id=? AND status='approved'")
            ->execute([time(), $orderId]);
    }

    public function failProvisioning(int $orderId, string $error): void
    {
        $this->db->prepare("UPDATE orders SET status='provision_failed',updated_at=?,metadata_json=? WHERE id=? AND status='provisioning'")
            ->execute([time(), json_encode(['provisioning_error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $orderId]);
        $this->db->prepare("UPDATE payments SET status='failed',updated_at=? WHERE order_id=? AND status='approved'")
            ->execute([time(), $orderId]);
    }

    public function cancel(int $orderId, int $telegramUserId): bool
    {
        $q = $this->db->prepare("UPDATE orders SET status='cancelled',updated_at=? WHERE id=? AND telegram_user_id=? AND status='pending_payment'");
        $q->execute([time(), $orderId, $telegramUserId]);
        return $q->rowCount() === 1;
    }

    private function setting(string $key, string $default): string
    {
        $q = $this->db->prepare('SELECT value FROM settings WHERE key=? LIMIT 1');
        $q->execute([$key]);
        $value = $q->fetchColumn();
        return $value === false ? $default : (string)$value;
    }
}
