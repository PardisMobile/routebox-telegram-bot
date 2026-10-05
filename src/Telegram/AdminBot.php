<?php

declare(strict_types=1);

namespace RouteBox\Telegram;

use PDO;
use RuntimeException;
use RouteBox\Integrations\Payment\ManualPaymentService;
use RouteBox\Services\ServiceCatalog;
use RouteBox\Services\ServiceProvisioner;

require_once __DIR__ . '/../Services/ServiceCatalog.php';
require_once __DIR__ . '/../Services/ServiceProvisioner.php';
require_once __DIR__ . '/../Integrations/Payment/ManualPaymentService.php';

/** Telegram Bot Admin orchestration. Provider logic remains in ServiceProvisioner. */
final class AdminBot
{
    private const ACTION_TTL = 900;
    private static bool $schemaReady = false;

    private static function db(): PDO { return \db(); }

    private static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $db = self::db();
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_bot_admins (id INTEGER PRIMARY KEY AUTOINCREMENT, telegram_id TEXT UNIQUE NOT NULL, role TEXT NOT NULL DEFAULT 'admin', enabled INTEGER NOT NULL DEFAULT 1, created_by TEXT, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_admin_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER, admin_telegram_id TEXT NOT NULL, action TEXT NOT NULL, target_user_id INTEGER, target_service_id INTEGER, provider_key TEXT, result TEXT NOT NULL DEFAULT 'success', details_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL, FOREIGN KEY(admin_id) REFERENCES telegram_bot_admins(id) ON DELETE SET NULL, FOREIGN KEY(target_user_id) REFERENCES telegram_users(id) ON DELETE SET NULL, FOREIGN KEY(target_service_id) REFERENCES service_subscriptions(id) ON DELETE SET NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_admin_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT UNIQUE NOT NULL, admin_telegram_id TEXT NOT NULL, action TEXT NOT NULL, payload_json TEXT NOT NULL DEFAULT '{}', status TEXT NOT NULL DEFAULT 'pending', result_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_telegram_admin_audit_created_at ON telegram_admin_audit(created_at DESC)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_telegram_admin_audit_admin ON telegram_admin_audit(admin_telegram_id, created_at DESC)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_telegram_admin_actions_admin_status ON telegram_admin_actions(admin_telegram_id, status, created_at DESC)');
        self::$schemaReady = true;
    }

    public static function admin(string $telegramId): ?array
    {
        self::ensureSchema();
        $q = self::db()->prepare('SELECT * FROM telegram_bot_admins WHERE telegram_id=? AND enabled=1 LIMIT 1');
        $q->execute([$telegramId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function handle(string $token, int|string $chat, array $from, int $userId, ?array $message, ?array $callback): bool
    {
        $telegramId = (string)($from['id'] ?? '');
        if ($telegramId === '' || self::admin($telegramId) === null) return false;
        $text = trim((string)($message['text'] ?? ''));
        $data = trim((string)($callback['data'] ?? ''));

        if ($text === '/admin' || $text === '/adminpanel' || $text === '/start') {
            self::menu($token, $chat, $telegramId);
            return true;
        }
        if ($text === '/customer') {
            \menu($token, $chat, $userId);
            return true;
        }
        if ($data === '' || (!str_starts_with($data, 'adm:') && !str_starts_with($data, 'admdo:'))) return false;
        if ($callback !== null) \tg($token, 'answerCallbackQuery', ['callback_query_id' => (string)($callback['id'] ?? '')]);

        try {
            if ($data === 'adm:menu') self::menu($token, $chat, $telegramId);
            elseif ($data === 'adm:users') self::users($token, $chat);
            elseif ($data === 'adm:services') self::services($token, $chat);
            elseif ($data === 'adm:payments') self::payments($token, $chat);
            elseif (preg_match('/^adm:payment:(\d+)$/', $data, $m)) self::paymentDetail($token, $chat, (int)$m[1]);
            elseif (preg_match('/^adm:payment:(approve|reject|retry):(\d+)$/', $data, $m)) self::paymentAction($token, $chat, $telegramId, $m[1], (int)$m[2]);
            elseif ($data === 'adm:audit') self::auditList($token, $chat, $telegramId);
            elseif ($data === 'adm:create:routebox') self::chooseUser($token, $chat, 'routebox');
            elseif ($data === 'adm:create:ibsng') self::chooseUser($token, $chat, 'ibsng');
            elseif ($data === 'adm:customer') \menu($token, $chat, $userId);
            elseif (str_starts_with($data, 'adm:user:')) self::userDetails($token, $chat, (int)substr($data, 9));
            elseif (str_starts_with($data, 'adm:plans:')) {
                $p = explode(':', $data);
                self::choosePlan($token, $chat, $telegramId, (string)($p[2] ?? ''), (int)($p[3] ?? 0));
            } elseif (str_starts_with($data, 'admdo:')) self::executeAction($token, $chat, $telegramId, substr($data, 6));
            else return true;
        } catch (\Throwable $e) {
            self::audit($telegramId, 'admin_action_failed', null, null, null, 'failed', ['error' => $e->getMessage()]);
            \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => '❌ ' . $e->getMessage()]);
        }
        return true;
    }

    private static function menu(string $token, int|string $chat, string $telegramId): void
    {
        $a = self::admin($telegramId);
        $role = (string)($a['role'] ?? 'admin');
        $k = [
            [['text' => '👥 Users', 'callback_data' => 'adm:users'], ['text' => '🛠 Services', 'callback_data' => 'adm:services']],
            [['text' => '💳 Payments', 'callback_data' => 'adm:payments']],
            [['text' => '➕ RouteBox بدون پرداخت', 'callback_data' => 'adm:create:routebox']],
            [['text' => '➕ IBSng بدون پرداخت', 'callback_data' => 'adm:create:ibsng']],
            [['text' => '📜 Audit Log', 'callback_data' => 'adm:audit']],
            [['text' => '👤 Customer Menu', 'callback_data' => 'adm:customer']],
        ];
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => "🛡️ ATD Panel Admin\n\n👤 Admin ID: {$telegramId}\n🔐 Role: {$role}\n\nیک عملیات را انتخاب کنید:", 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
    }

    private static function users(string $token, int|string $chat): void
    {
        $rows = self::db()->query('SELECT id,telegram_id,username,first_name,last_seen FROM telegram_users ORDER BY last_seen DESC,id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
        $text = "👥 Telegram Users\n\n";
        $k = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['first_name'] ?? '')) ?: ('ID ' . $row['telegram_id']);
            $label = mb_substr($name, 0, 24) . ((string)($row['username'] ?? '') !== '' ? ' @' . mb_substr((string)$row['username'], 0, 18) : '');
            $text .= '• ' . $name . ' — ' . $row['telegram_id'] . "\n";
            $k[] = [['text' => '👤 ' . $label, 'callback_data' => 'adm:user:' . (int)$row['id']]];
        }
        if (!$rows) $text .= 'هنوز کاربری ثبت نشده است.';
        $k[] = [['text' => '↩️ Admin Menu', 'callback_data' => 'adm:menu']];
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
    }

    private static function services(string $token, int|string $chat): void
    {
        $rows = self::db()->query('SELECT s.id,s.provider_key,s.username,s.status,s.expires_at,u.telegram_id,u.username tg_username FROM service_subscriptions s JOIN telegram_users u ON u.id=s.telegram_user_id ORDER BY s.created_at DESC,s.id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
        $text = "🛠 Recent Services\n\n";
        foreach ($rows as $row) $text .= sprintf("• #%d %s · %s · %s · %s\n", (int)$row['id'], $row['provider_key'], (string)($row['username'] ?: $row['tg_username']), $row['status'], $row['expires_at'] ? date('Y-m-d H:i', (int)$row['expires_at']) : '—');
        if (!$rows) $text .= 'سرویسی ثبت نشده است.';
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '↩️ Admin Menu', 'callback_data' => 'adm:menu']]]], JSON_UNESCAPED_UNICODE)]);
    }

    private static function payments(string $token, int|string $chat): void
    {
        $service = new ManualPaymentService(self::db());
        $rows = $service->pendingPayments(20);
        $text = "💳 Pending Payments / Receipts\n\n";
        $k = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['first_name'] ?? '')) ?: ((string)($row['tg_username'] ?? '') !== '' ? '@' . $row['tg_username'] : $row['telegram_id']);
            $plan = (string)($row['display_name_en'] ?? $row['provider_key']);
            $text .= sprintf("• #%d · %s · %s · %s %s\n", (int)$row['order_id'], $name, $plan, number_format((int)$row['amount_minor']), $row['currency']);
            $k[] = [['text' => '🧾 Order #' . (int)$row['order_id'], 'callback_data' => 'adm:payment:' . (int)$row['payment_id']]];
        }
        if (!$rows) $text .= 'رسید پرداخت در انتظار بررسی وجود ندارد.';
        $k[] = [['text' => '↩️ Admin Menu', 'callback_data' => 'adm:menu']];
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
    }

    private static function paymentDetail(string $token, int|string $chat, int $paymentId): void
    {
        $payment = (new ManualPaymentService(self::db()))->payment($paymentId);
        if (!$payment) throw new RuntimeException('Payment پیدا نشد.');
        $text = "💳 Payment #{$paymentId}\n\n🧾 Order: #" . (int)$payment['order_id'] . "\n👤 Telegram ID: " . (string)$payment['telegram_id'] . "\n📦 Plan: " . (string)$payment['display_name_en'] . "\n🔌 Provider: " . (string)$payment['provider_key'] . "\n💰 Amount: " . number_format((int)$payment['amount_minor']) . ' ' . (string)$payment['currency'] . "\n📌 Status: " . (string)$payment['payment_status'];
        if (trim((string)($payment['caption'] ?? '')) !== '') $text .= "\n📝 Caption: " . (string)$payment['caption'];
        $k = [];
        if ((string)$payment['payment_status'] === 'review') {
            $k[] = [['text' => '✅ Approve', 'callback_data' => 'adm:payment:approve:' . $paymentId], ['text' => '❌ Reject', 'callback_data' => 'adm:payment:reject:' . $paymentId]];
        } elseif ((string)$payment['order_status'] === 'provision_failed') {
            $k[] = [['text' => '🔁 Retry Provisioning', 'callback_data' => 'adm:payment:retry:' . (int)$payment['order_id']]];
        }
        $k[] = [['text' => '↩️ Payments', 'callback_data' => 'adm:payments'], ['text' => '🏠 Admin Menu', 'callback_data' => 'adm:menu']];
        $markup = json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE);
        if ((string)($payment['file_type'] ?? '') === 'photo' && (string)($payment['telegram_file_id'] ?? '') !== '') {
            \tg($token, 'sendPhoto', ['chat_id' => $chat, 'photo' => (string)$payment['telegram_file_id'], 'caption' => $text, 'reply_markup' => $markup]);
        } elseif ((string)($payment['telegram_file_id'] ?? '') !== '') {
            \tg($token, 'sendDocument', ['chat_id' => $chat, 'document' => (string)$payment['telegram_file_id'], 'caption' => $text, 'reply_markup' => $markup]);
        } else {
            \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => $markup]);
        }
    }

    private static function paymentAction(string $token, int|string $chat, string $adminTelegramId, string $action, int $id): void
    {
        $service = new ManualPaymentService(self::db());
        if ($action === 'approve') {
            $payment = $service->approve($id, $adminTelegramId);
            if ((string)$payment['order_status'] === 'completed') {
                \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => 'ℹ️ این پرداخت قبلاً Provision شده است و دوباره اجرا نمی‌شود.']);
                return;
            }
            if (!$service->claimProvisioning((int)$payment['order_id'])) {
                $latest = $service->payment($id);
                if ($latest && (string)$latest['order_status'] === 'completed') {
                    \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => 'ℹ️ این پرداخت قبلاً Provision شده است و دوباره اجرا نمی‌شود.']);
                    return;
                }
                throw new RuntimeException('این سفارش در وضعیت قابل Provision نیست.');
            }
            try {
                $result = (new ServiceProvisioner(self::db()))->provision((int)$payment['telegram_user_id'], (string)$payment['telegram_id'], (int)$payment['plan_id']);
                $service->completeProvisioning((int)$payment['order_id'], [
                    'provider' => $payment['provider_key'],
                    'subscription_id' => (int)($result['subscription_id'] ?? 0),
                    'provision_count' => count((array)($result['items'] ?? [])),
                ]);
                self::audit($adminTelegramId, 'payment_approved_and_provisioned', (int)$payment['telegram_user_id'], (int)($result['subscription_id'] ?? 0) ?: null, (string)$payment['provider_key'], 'success', ['order_id' => (int)$payment['order_id']]);
                \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => '✅ پرداخت تأیید و سرویس با موفقیت Provision شد.\nOrder #' . (int)$payment['order_id']]);
                self::notifyProvisionedCustomer($token, (string)$payment['telegram_id'], $payment, $result);
            } catch (\Throwable $e) {
                $service->failProvisioning((int)$payment['order_id'], $e->getMessage());
                self::audit($adminTelegramId, 'payment_provision_failed', (int)$payment['telegram_user_id'], null, (string)$payment['provider_key'], 'failed', ['order_id' => (int)$payment['order_id'], 'error' => $e->getMessage()]);
                throw new RuntimeException('پرداخت تأیید شد اما Provision سرویس ناموفق بود. از Retry Provisioning استفاده کنید.');
            }
            return;
        }
        if ($action === 'reject') {
            $payment = $service->reject($id, $adminTelegramId);
            self::audit($adminTelegramId, 'payment_rejected', (int)$payment['telegram_user_id'], null, (string)$payment['provider_key'], 'success', ['order_id' => (int)$payment['order_id']]);
            \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => '❌ رسید پرداخت رد شد. Order #' . (int)$payment['order_id']]);
            \tg($token, 'sendMessage', ['chat_id' => (string)$payment['telegram_id'], 'text' => '❌ رسید پرداخت شما برای Order #' . (int)$payment['order_id'] . ' تأیید نشد. لطفاً با پشتیبانی تماس بگیرید یا سفارش جدید ثبت کنید.']);
            return;
        }
        if ($action === 'retry') {
            $payment = $service->payment($id);
            if (!$payment) throw new RuntimeException('Payment پیدا نشد.');
            if (!$service->retryProvisioning((int)$payment['order_id'])) {
                $latest = $service->payment($id);
                if ($latest && (string)$latest['order_status'] === 'completed') {
                    \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => 'ℹ️ این سفارش قبلاً Provision شده است.']);
                    return;
                }
                throw new RuntimeException('این سفارش در وضعیت Retry Provisioning نیست.');
            }
            try {
                $result = (new ServiceProvisioner(self::db()))->provision((int)$payment['telegram_user_id'], (string)$payment['telegram_id'], (int)$payment['plan_id']);
                $service->completeProvisioning((int)$payment['order_id'], ['provider' => $payment['provider_key'], 'subscription_id' => (int)($result['subscription_id'] ?? 0)]);
                self::audit($adminTelegramId, 'payment_provision_retry', (int)$payment['telegram_user_id'], (int)($result['subscription_id'] ?? 0) ?: null, (string)$payment['provider_key'], 'success', ['order_id' => (int)$payment['order_id']]);
                \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => '✅ Provisioning مجدد با موفقیت انجام شد.']);
                self::notifyProvisionedCustomer($token, (string)$payment['telegram_id'], $payment, $result);
            } catch (\Throwable $e) {
                $service->failProvisioning((int)$payment['order_id'], $e->getMessage());
                self::audit($adminTelegramId, 'payment_provision_retry', (int)$payment['telegram_user_id'], null, (string)$payment['provider_key'], 'failed', ['order_id' => (int)$payment['order_id'], 'error' => $e->getMessage()]);
                throw new RuntimeException('Retry Provisioning ناموفق بود.');
            }
            return;
        }
        throw new RuntimeException('عملیات پرداخت نامعتبر است.');
    }

    private static function notifyProvisionedCustomer(string $token, string $telegramId, array $payment, array $result): void
    {
        $provider = (string)$payment['provider_key'];
        $plan = (string)$payment['display_name_fa'];
        if ($provider === 'ibsng') {
            $username = (string)($result['username'] ?? '');
            $password = (string)($result['password'] ?? '');
            $text = "✅ پرداخت شما تأیید شد و سرویس IBSng فعال شد.\n\n📦 پلن: {$plan}\n🔐 نام کاربری: {$username}\n🔑 رمز عبور: {$password}\n\n🌐 OpenVPN / Cisco / L2TP\n\n📋 جزئیات سرویس در «سرویس‌های من» قابل مشاهده است.";
        } elseif ($provider === 'mikrotik_wireguard') {
            $ip = (string)($result['assigned_ip'] ?? '—');
            $text = "✅ پرداخت شما تأیید شد و سرویس MikroTik WireGuard فعال شد.\n\n📦 پلن: {$plan}\n🌐 IP: {$ip}\n\n📋 برای دریافت Config یا QR از «سرویس‌های من» استفاده کنید.";
        } else {
            $text = "✅ پرداخت شما تأیید شد و سرویس RouteBox فعال شد.\n\n📦 پلن: {$plan}\n\n📋 برای دریافت Config یا QR از «سرویس‌های من» استفاده کنید.";
        }
        \tg($token, 'sendMessage', ['chat_id' => $telegramId, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '📋 سرویس‌های من', 'callback_data' => 'services']]]], JSON_UNESCAPED_UNICODE)]);
    }

    private static function chooseUser(string $token, int|string $chat, string $provider): void
    {
        $rows = self::db()->query('SELECT id,telegram_id,username,first_name FROM telegram_users ORDER BY last_seen DESC,id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
        $text = '➕ Create ' . ($provider === 'ibsng' ? 'IBSng' : 'RouteBox') . "\n\nکاربر مقصد را انتخاب کنید:";
        $k = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['first_name'] ?? '')) ?: (string)$row['telegram_id'];
            $k[] = [['text' => '👤 ' . mb_substr($name, 0, 22) . ' · ' . $row['telegram_id'], 'callback_data' => 'adm:plans:' . $provider . ':' . (int)$row['id']]];
        }
        if (!$rows) $text .= "\nهنوز کاربری ثبت نشده است.";
        $k[] = [['text' => '↩️ Admin Menu', 'callback_data' => 'adm:menu']];
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
    }

    private static function choosePlan(string $token, int|string $chat, string $adminTelegramId, string $provider, int $userId): void
    {
        if (!in_array($provider, ['routebox', 'ibsng'], true) || $userId < 1) throw new RuntimeException('درخواست ایجاد سرویس نامعتبر است.');
        $user = self::telegramUser($userId);
        $catalog = new ServiceCatalog(self::db());
        $plans = [];
        foreach ($catalog->categories($provider) as $category) foreach ($catalog->plans((int)$category['id']) as $plan) if ((string)$plan['provider_key'] === $provider) $plans[] = $plan;
        $text = "📦 انتخاب Plan\n\n👤 {$user['telegram_id']}\nProvider: {$provider}\n\n";
        $k = [];
        foreach ($plans as $plan) {
            $label = (string)$plan['display_name_fa'] ?: (string)$plan['display_name_en'];
            $label .= ' · ' . number_format((int)$plan['price_minor']) . ' IRR';
            $action = self::createAction($adminTelegramId, 'provision', ['telegram_user_id' => $userId, 'telegram_id' => (string)$user['telegram_id'], 'plan_id' => (int)$plan['id'], 'provider_key' => $provider]);
            $k[] = [['text' => '⚡ ' . mb_substr($label, 0, 45), 'callback_data' => 'admdo:' . $action]];
        }
        if (!$plans) $text .= 'برای این Provider هیچ Plan فعالی تعریف نشده است.';
        $k[] = [['text' => '↩️ Admin Menu', 'callback_data' => 'adm:menu']];
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
    }

    private static function userDetails(string $token, int|string $chat, int $userId): void
    {
        $user = self::telegramUser($userId);
        $q = self::db()->prepare('SELECT id,provider_key,username,status,expires_at FROM service_subscriptions WHERE telegram_user_id=? ORDER BY created_at DESC LIMIT 10');
        $q->execute([$userId]);
        $services = $q->fetchAll(PDO::FETCH_ASSOC);
        $text = "👤 User\n\nTelegram ID: {$user['telegram_id']}\nUsername: @" . ((string)($user['username'] ?? '') ?: '—') . "\nName: " . ((string)($user['first_name'] ?? '') ?: '—') . "\n\nServices:\n";
        foreach ($services as $service) $text .= '#' . (int)$service['id'] . ' · ' . $service['provider_key'] . ' · ' . ((string)$service['username'] ?: '—') . ' · ' . $service['status'] . ' · ' . ($service['expires_at'] ? date('Y-m-d H:i', (int)$service['expires_at']) : '—') . "\n";
        if (!$services) $text .= 'No services.';
        $k = [[['text' => '➕ RouteBox', 'callback_data' => 'adm:plans:routebox:' . $userId], ['text' => '➕ IBSng', 'callback_data' => 'adm:plans:ibsng:' . $userId]], [['text' => '↩️ Users', 'callback_data' => 'adm:users'], ['text' => '🏠 Admin Menu', 'callback_data' => 'adm:menu']]];
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => $k], JSON_UNESCAPED_UNICODE)]);
    }

    private static function executeAction(string $token, int|string $chat, string $adminTelegramId, string $tokenValue): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $tokenValue)) throw new RuntimeException('Admin action token is invalid.');
        $db = self::db();
        $q = $db->prepare('SELECT * FROM telegram_admin_actions WHERE token=? AND admin_telegram_id=? LIMIT 1');
        $q->execute([$tokenValue, $adminTelegramId]);
        $action = $q->fetch(PDO::FETCH_ASSOC);
        if (!$action) throw new RuntimeException('Admin action was not found or is not assigned to this Admin.');
        if ((string)$action['status'] === 'success') { \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => 'ℹ️ این عملیات قبلاً با موفقیت انجام شده و دوباره Provision نمی‌شود.']); return; }
        if ((string)$action['status'] !== 'pending') throw new RuntimeException('این عملیات دیگر قابل اجرا نیست.');
        if ((int)$action['created_at'] + self::ACTION_TTL < time()) throw new RuntimeException('این دکمه منقضی شده است. دوباره عملیات را شروع کنید.');
        $payload = json_decode((string)$action['payload_json'], true);
        if (!is_array($payload)) throw new RuntimeException('Admin action payload is invalid.');
        $targetUserId = (int)($payload['telegram_user_id'] ?? 0);
        $planId = (int)($payload['plan_id'] ?? 0);
        $provider = (string)($payload['provider_key'] ?? '');
        $targetTelegramId = (string)($payload['telegram_id'] ?? '');
        if ($targetUserId < 1 || $planId < 1 || !in_array($provider, ['routebox', 'ibsng'], true) || $targetTelegramId === '') throw new RuntimeException('Admin action target is invalid.');
        $user = self::telegramUser($targetUserId);
        if ((string)$user['telegram_id'] !== $targetTelegramId) throw new RuntimeException('Admin action target changed.');
        $plan = (new ServiceCatalog($db))->plan($planId);
        if ((string)$plan['provider_key'] !== $provider) throw new RuntimeException('Provider/Plan mismatch.');

        $claim = $db->prepare("UPDATE telegram_admin_actions SET status='processing',updated_at=? WHERE id=? AND status='pending'");
        $claim->execute([time(), (int)$action['id']]);
        if ($claim->rowCount() !== 1) throw new RuntimeException('این عملیات همزمان توسط درخواست دیگری در حال اجراست.');
        try {
            $result = (new ServiceProvisioner($db))->provision($targetUserId, $targetTelegramId, $planId);
            $safe = ['provider' => $provider, 'subscription_id' => (int)($result['subscription_id'] ?? 0), 'plan_id' => $planId];
            if ($provider === 'routebox') $safe['provision_count'] = count((array)($result['items'] ?? []));
            $db->prepare("UPDATE telegram_admin_actions SET status='success',result_json=?,updated_at=? WHERE id=?")->execute([json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time(), (int)$action['id']]);
            self::audit($adminTelegramId, 'provision_without_payment', $targetUserId, $safe['subscription_id'] ?: null, $provider, 'success', $safe);
            \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => "✅ {$provider} service created without customer payment.\n\n👤 User: {$targetTelegramId}\n📦 Plan: " . (string)$plan['display_name_en']]);
            self::notifyCustomer($token, $targetTelegramId, $provider, $result, $plan);
        } catch (\Throwable $e) {
            $db->prepare("UPDATE telegram_admin_actions SET status='failed',result_json=?,updated_at=? WHERE id=?")->execute([json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time(), (int)$action['id']]);
            self::audit($adminTelegramId, 'provision_without_payment', $targetUserId, null, $provider, 'failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private static function notifyCustomer(string $token, string $telegramId, string $provider, array $result, array $plan): void
    {
        if ($provider === 'ibsng') {
            $username = (string)($result['username'] ?? '');
            $password = (string)($result['password'] ?? '');
            if ($username === '' || $password === '') return;
            \tg($token, 'sendMessage', ['chat_id' => $telegramId, 'text' => "✅ سرویس IBSng شما توسط Admin فعال شد.\n\n📦 Plan: " . (string)$plan['display_name_fa'] . "\n🔐 Username: {$username}\n🔑 Password: {$password}\n\n🌐 OpenVPN / Cisco / L2TP"]);
        } elseif ($provider === 'routebox') {
            \tg($token, 'sendMessage', ['chat_id' => $telegramId, 'text' => '✅ سرویس RouteBox شما توسط Admin فعال شد. برای دریافت Config یا QR از «سرویس‌های من» استفاده کنید.']);
        }
    }

    private static function createAction(string $adminTelegramId, string $action, array $payload): string
    {
        self::ensureSchema();
        $token = bin2hex(random_bytes(16));
        $now = time();
        self::db()->prepare("INSERT INTO telegram_admin_actions(token,admin_telegram_id,action,payload_json,status,result_json,created_at,updated_at) VALUES(?,?,?,?, 'pending','{}',?,?)")
            ->execute([$token, $adminTelegramId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now, $now]);
        return $token;
    }

    private static function audit(string $adminTelegramId, string $action, ?int $targetUserId, ?int $targetServiceId, ?string $provider, string $result, array $details = []): void
    {
        self::ensureSchema();
        $a = self::admin($adminTelegramId);
        self::db()->prepare('INSERT INTO telegram_admin_audit(admin_id,admin_telegram_id,action,target_user_id,target_service_id,provider_key,result,details_json,created_at) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$a ? (int)$a['id'] : null, $adminTelegramId, $action, $targetUserId, $targetServiceId, $provider, $result, json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time()]);
    }

    private static function auditList(string $token, int|string $chat, string $adminTelegramId): void
    {
        $q = self::db()->prepare('SELECT action,target_user_id,target_service_id,provider_key,result,created_at FROM telegram_admin_audit WHERE admin_telegram_id=? ORDER BY id DESC LIMIT 15');
        $q->execute([$adminTelegramId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $text = "📜 Admin Audit\n\n";
        foreach ($rows as $row) $text .= date('Y-m-d H:i', (int)$row['created_at']) . ' · ' . $row['action'] . ' · ' . ((string)($row['provider_key'] ?? '') ?: '—') . ' · ' . $row['result'] . "\n";
        if (!$rows) $text .= 'No audit records.';
        \tg($token, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '↩️ Admin Menu', 'callback_data' => 'adm:menu']]]], JSON_UNESCAPED_UNICODE)]);
    }

    private static function telegramUser(int $id): array
    {
        $q = self::db()->prepare('SELECT * FROM telegram_users WHERE id=? LIMIT 1');
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('کاربر Telegram پیدا نشد.');
        return $row;
    }
}
