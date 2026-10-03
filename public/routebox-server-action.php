<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();
require_once __DIR__ . '/../src/RouteBoxClient.php';

function routeboxActionRedirect(string $message, bool $error = false): never
{
    $_SESSION['flash'] = ($error ? '❌ ' : '✓ ') . $message;
    header('Location: /?section=servers');
    exit;
}

function routeboxActionPing(string $url): ?float
{
    try {
        $parsed = parse_url($url);
        if (!is_array($parsed) || empty($parsed['host'])) return null;
        $host = (string)$parsed['host'];
        $port = isset($parsed['port']) ? (int)$parsed['port'] : (($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80);
        $ip = gethostbyname($host);
        $target = $ip !== $host ? $ip : $host;
        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($target, $port, $errno, $errstr, 3);
        if ($socket === false) return null;
        $ms = round((microtime(true) - $start) * 1000, 1);
        fclose($socket);
        return $ms;
    } catch (Throwable) {
        return null;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    routeboxActionRedirect('Invalid request.', true);
}

verify_csrf();
$action = trim((string)($_POST['action'] ?? ''));
$id = (int)($_POST['id'] ?? 0);

try {
    $q = db()->prepare('SELECT * FROM routebox_servers WHERE id=?');
    $q->execute([$id]);
    $server = $q->fetch(PDO::FETCH_ASSOC);
    if (!$server) throw new RuntimeException('RouteBox server was not found.');

    if ($action === 'delete_server') {
        $plans = db()->prepare("SELECT COUNT(*) FROM service_plans WHERE provider_key='routebox' AND provider_server_id=?");
        $plans->execute([$id]);
        if ((int)$plans->fetchColumn() > 0) {
            throw new RuntimeException('This RouteBox server is used by provider plans; remove or move those plans first.');
        }

        $subscriptions = db()->prepare("SELECT COUNT(*) FROM service_subscriptions WHERE provider_key='routebox' AND provider_server_id=?");
        $subscriptions->execute([$id]);
        if ((int)$subscriptions->fetchColumn() > 0) {
            throw new RuntimeException('This RouteBox server is used by subscriptions and cannot be deleted yet.');
        }

        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM server_meta WHERE server_id=?')->execute([$id]);
            db()->prepare('DELETE FROM routebox_servers WHERE id=?')->execute([$id]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }

        routeboxActionRedirect('RouteBox server deleted.');
    }

    if ($action === 'update_server') {
        $name = trim((string)($_POST['name'] ?? ''));
        $url = rtrim(trim((string)($_POST['url'] ?? '')), '/');
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $verify = isset($_POST['verify_tls']) ? 1 : 0;
        $country = strtoupper(trim((string)($_POST['country_code'] ?? '')));

        if ($name === '' || mb_strlen($name) > 80 || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid server name or URL.');
        }
        if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            throw new RuntimeException('Country code must contain two letters.');
        }

        $effectiveUser = $username !== '' ? $username : dec((string)$server['user_enc']);
        $effectivePass = $password !== '' ? $password : dec((string)$server['pass_enc']);
        if ($effectiveUser === '' || $effectivePass === '') {
            throw new RuntimeException('Username and password are required.');
        }

        $client = new RouteBoxClient($url, $effectiveUser, $effectivePass, (bool)$verify);
        $client->validateIntegration();
        $client->smokeTest('rbt-admin-edit');

        if ($country === '') {
            $oldMeta = db()->prepare('SELECT country_code FROM server_meta WHERE server_id=?');
            $oldMeta->execute([$id]);
            $country = strtoupper(trim((string)$oldMeta->fetchColumn()));
        }

        db()->prepare('UPDATE routebox_servers SET name=?,base_url=?,user_enc=?,pass_enc=?,verify_tls=? WHERE id=?')->execute([
            $name,
            $url,
            enc($effectiveUser),
            enc($effectivePass),
            $verify,
            $id,
        ]);

        db()->prepare('INSERT OR REPLACE INTO server_meta(server_id,country_code,ping_ms,ping_checked_at) VALUES(?,?,?,?)')->execute([
            $id,
            $country,
            routeboxActionPing($url),
            time(),
        ]);

        routeboxActionRedirect('RouteBox server updated and tested successfully.');
    }

    throw new RuntimeException('Unknown RouteBox server operation.');
} catch (Throwable $e) {
    log_event('error', 'RouteBox server action failed: ' . $e->getMessage());
    routeboxActionRedirect($e->getMessage(), true);
}
