<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/RouteBoxClient.php';

function sget(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function telegramApi(string $token, string $method, array $data = []): array
{
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    if ($ch === false) throw new RuntimeException('Could not initialize Telegram cURL.');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data, CURLOPT_TIMEOUT => 35, CURLOPT_CONNECTTIMEOUT => 10]);
    $raw = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new RuntimeException('Telegram connection failed: ' . ($error ?: 'unknown cURL error'));
    $json = json_decode($raw, true);
    if (!is_array($json) || empty($json['ok'])) throw new RuntimeException($json['description'] ?? 'Telegram API error');
    return is_array($json['result'] ?? null) ? $json['result'] : [];
}

function upsertTelegramUser(array $user): int
{
    $now = time();
    $stmt = db()->prepare(
        'INSERT INTO telegram_users(telegram_id,username,first_name,created_at,last_seen)
         VALUES(?,?,?,?,?)
         ON CONFLICT(telegram_id) DO UPDATE SET username=excluded.username,first_name=excluded.first_name,last_seen=excluded.last_seen'
    );
    $stmt->execute([(string) $user['id'], $user['username'] ?? null, $user['first_name'] ?? null, $now, $now]);
    $lookup = db()->prepare('SELECT id FROM telegram_users WHERE telegram_id = ?');
    $lookup->execute([(string) $user['id']]);
    return (int) $lookup->fetchColumn();
}

function provisionUser(int $userId, string $telegramId, ?int $planId = null): array
{
    $used = db()->prepare('SELECT COUNT(*) FROM provisions WHERE telegram_user_id = ?');
    $used->execute([$userId]);
    if ((int) $used->fetchColumn() > 0) {
        throw new RuntimeException('🎟️ این حساب قبلاً یک سرویس دریافت کرده است. برای تمدید/ارتقا، سیستم اشتراک در مرحله بعد اضافه می‌شود.');
    }

    $durationSeconds = 0;
    $quotaBytes = null;
    $planName = 'تست رایگان';
    if ($planId !== null) {
        $planStmt = db()->prepare('SELECT * FROM plans WHERE id = ? AND enabled = 1');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) throw new RuntimeException('❌ این پلن دیگر فعال نیست. لطفاً /start را دوباره بزنید.');
        $durationSeconds = max(1, (int) $plan['duration_days']) * 86400;
        $quotaGb = (float) $plan['quota_gb'];
        if ($quotaGb > 0) $quotaBytes = (int) round($quotaGb * 1024 * 1024 * 1024);
        $planName = (string) $plan['name'];
    } else {
        $durationSeconds = max(1, (int) sget('trial_hours', '12')) * 3600;
    }

    $servers = db()->query('SELECT * FROM routebox_servers WHERE enabled = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if (!$servers) throw new RuntimeException('🌐 هیچ RouteBox فعالی در پنل تنظیم نشده است.');

    $expiresAt = time() + $durationSeconds;
    $peerName = 'user' . $telegramId;
    $output = [];
    $createdPeers = [];

    try {
        foreach ($servers as $server) {
            $client = new RouteBoxClient((string) $server['base_url'], dec((string) $server['user_enc']), dec((string) $server['pass_enc']), (bool) $server['verify_tls']);
            $peer = null;
            foreach ($client->peers() as $candidate) {
                if (($candidate['name'] ?? '') === $peerName) { $peer = $candidate; break; }
            }
            $createdHere = false;
            if ($peer === null) { $peer = $client->createPeer($peerName); $createdHere = true; }
            $publicKey = (string) ($peer['public_key'] ?? $peer['publicKey'] ?? '');
            if ($publicKey === '') throw new RuntimeException('RouteBox did not return a public key for ' . $server['name']);
            if ($createdHere) $createdPeers[] = [$client, $publicKey];

            $client->setExpiry($publicKey, $expiresAt, $quotaBytes);
            $config = $client->config($publicKey);
            db()->prepare('INSERT OR REPLACE INTO provisions(telegram_user_id,server_id,peer_name,public_key,expires_at,created_at) VALUES(?,?,?,?,?,?)')->execute([$userId, $server['id'], $peerName, $publicKey, $expiresAt, time()]);
            $output[] = ['server' => (string) $server['name'], 'conf' => $config, 'expires' => $expiresAt, 'plan' => $planName];
        }
    } catch (Throwable $error) {
        foreach ($createdPeers as [$client, $publicKey]) {
            try { $client->deletePeer($publicKey); } catch (Throwable $rollbackError) { log_event('error', 'Provision rollback failed: ' . $rollbackError->getMessage()); }
        }
        throw $error;
    }
    log_event('info', 'Provisioned Telegram user ' . $telegramId . ' with ' . $planName);
    return $output;
}

function planButtons(): array
{
    $plans = db()->query('SELECT id,name,duration_days,quota_gb FROM plans WHERE enabled = 1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $buttons = [];
    foreach ($plans as $plan) {
        $quota = (float) $plan['quota_gb'] > 0 ? rtrim(rtrim(number_format((float)$plan['quota_gb'],1,'.',''),'0'),'.') . 'GB' : '∞';
        $buttons[] = ['text' => '🚀 ' . $plan['name'] . ' • ' . $plan['duration_days'] . ' روز • ' . $quota, 'callback_data' => 'plan:' . $plan['id']];
    }
    return $buttons;
}

$storedToken = sget('telegram_token');
if (!$storedToken) exit("Telegram token is not configured\n");
$token = dec($storedToken);
@mkdir(__DIR__ . '/storage', 0700, true);
$offset = (int) (@file_get_contents(__DIR__ . '/storage/update.offset') ?: 0);

while (true) {
    try {
        $updates = telegramApi($token, 'getUpdates', ['offset' => $offset, 'timeout' => 25, 'allowed_updates' => json_encode(['message', 'callback_query'])]);
        foreach ($updates as $update) {
            $offset = (int) $update['update_id'] + 1;
            file_put_contents(__DIR__ . '/storage/update.offset', (string) $offset, LOCK_EX);
            $message = $update['message'] ?? null;
            $callback = $update['callback_query'] ?? null;
            $chatId = $message['chat']['id'] ?? $callback['message']['chat']['id'] ?? null;
            $from = $message['from'] ?? $callback['from'] ?? null;
            if ($chatId === null || !is_array($from)) continue;
            $userId = upsertTelegramUser($from);

            if ($message && str_starts_with((string) ($message['text'] ?? ''), '/start')) {
                $keyboard = [];
                foreach (array_chunk(planButtons(), 1) as $row) $keyboard[] = $row;
                $keyboard[] = [
                    ['text' => '🎁 دریافت تست رایگان', 'callback_data' => 'trial'],
                    ['text' => '👤 حساب من', 'callback_data' => 'account'],
                ];
                telegramApi($token, 'sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "🚀 RouteBox Telegram Bot\n\nسلام 👋\nسرویس موردنظر را انتخاب کنید:",
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard], JSON_UNESCAPED_UNICODE),
                ]);
                continue;
            }
            if (!$callback) continue;
            telegramApi($token, 'answerCallbackQuery', ['callback_query_id' => $callback['id']]);
            $action = (string) ($callback['data'] ?? '');

            if ($action === 'account') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM provisions WHERE telegram_user_id = ? AND expires_at > ?');
                $stmt->execute([$userId, time()]);
                $active = (int) $stmt->fetchColumn();
                telegramApi($token, 'sendMessage', ['chat_id' => $chatId, 'text' => "👤 حساب شما\n\n🟢 سرویس فعال: {$active}"]);
                continue;
            }

            $planId = null;
            if (str_starts_with($action, 'plan:')) {
                $parsed = (int) substr($action, 5);
                if ($parsed > 0) $planId = $parsed;
            }
            if ($action === 'trial' || $planId !== null) {
                try {
                    $items = provisionUser($userId, (string) $from['id'], $planId);
                    foreach ($items as $item) {
                        $tmp = tempnam(sys_get_temp_dir(), 'rbt');
                        if ($tmp === false) throw new RuntimeException('Could not create temporary config file.');
                        file_put_contents($tmp, $item['conf']);
                        try {
                            $caption = ($planId === null ? "🎁 تست فعال شد" : "🚀 سرویس فعال شد") . "\n\n📦 پلن: {$item['plan']}\n🖥️ سرور: {$item['server']}\n⏱️ اعتبار تا: " . date('Y-m-d H:i', $item['expires']);
                            telegramApi($token, 'sendDocument', ['chat_id' => $chatId, 'document' => new CURLFile($tmp, 'text/plain', 'RouteBox-' . $item['server'] . '.conf'), 'caption' => $caption]);
                        } finally { @unlink($tmp); }
                    }
                } catch (Throwable $error) {
                    log_event('error', 'Provision: ' . $error->getMessage());
                    telegramApi($token, 'sendMessage', ['chat_id' => $chatId, 'text' => '❌ ' . $error->getMessage()]);
                }
            }
        }
    } catch (Throwable $error) {
        log_event('error', 'Telegram worker: ' . $error->getMessage());
        sleep(3);
    }
}
