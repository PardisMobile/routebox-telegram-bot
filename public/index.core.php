<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require_admin();
require_once __DIR__ . '/../src/RouteBoxClient.php';

const GITHUB_VERSION_URL = 'https://raw.githubusercontent.com/PardisMobile/routebox-telegram-bot/main/VERSION';
const SUPPORT_URL = 'https://t.me/+918807085399';

function telegramCheckAndPrepare(string $token): void
{
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/getMe');
    if ($ch === false) throw new RuntimeException('Telegram connection unavailable.');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Telegram connection failed: ' . ($err ?: 'unknown'));
    $j = json_decode($raw, true);
    if (!is_array($j) || empty($j['ok'])) throw new RuntimeException('Invalid Telegram Bot Token.');

    $d = curl_init('https://api.telegram.org/bot' . $token . '/deleteWebhook');
    curl_setopt_array($d, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['drop_pending_updates' => 'false'], CURLOPT_TIMEOUT => 15]);
    $r = curl_exec($d);
    curl_close($d);
    $dj = json_decode((string)$r, true);
    if (!is_array($dj) || empty($dj['ok'])) throw new RuntimeException('Telegram webhook cleanup failed.');
}

function validateRouteBoxForAdmin(string $url, string $user, string $pass, bool $verify): void
{
    $c = new RouteBoxClient($url, $user, $pass, $verify);
    $c->validateIntegration();
    $c->smokeTest('rbt-admin-test');
}

function routeBoxEndpoint(string $url): array
{
    $p = parse_url($url);
    if (!is_array($p) || empty($p['host'])) throw new RuntimeException('Invalid RouteBox URL.');
    return [(string)$p['host'], isset($p['port']) ? (int)$p['port'] : (($p['scheme'] ?? 'https') === 'https' ? 443 : 80)];
}

function measureServerPing(string $url): ?float
{
    try {
        [$host, $port] = routeBoxEndpoint($url);
        $ip = gethostbyname($host);
        $target = $ip !== $host ? $ip : $host;
        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $s = @fsockopen($target, $port, $errno, $errstr, 3);
        if ($s === false) return null;
        $ms = (microtime(true) - $start) * 1000;
        fclose($s);
        return round($ms, 1);
    } catch (Throwable) {
        return null;
    }
}

function detectCountryCode(string $url): string
{
    try {
        [$host] = routeBoxEndpoint($url);
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!$ip) return '';
        $ch = curl_init('https://ipapi.co/' . rawurlencode($ip) . '/country/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_USERAGENT => 'RouteBox-Telegram-Bot']);
        $v = trim((string)curl_exec($ch));
        curl_close($ch);
        return preg_match('/^[A-Za-z]{2}$/', $v) ? strtoupper($v) : '';
    } catch (Throwable) {
        return '';
    }
}

function countryFlag(string $code): string
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z]{2}$/', $code) || !function_exists('mb_chr')) return '🌐';
    return mb_chr(127397 + ord($code[0])) . mb_chr(127397 + ord($code[1]));
}

function q(float $n): string
{
    return $n <= 0 ? '∞' : rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.') . ' GB';
}

function cleanBotText(string $text): string
{
    // Accept both real newlines and the common literal /n or \\n typo.
    $text = str_replace(["\\r\\n", "\\n", "/n"], "\n", $text);
    return trim($text);
}

function localVersion(): string
{
    $file = __DIR__ . '/../VERSION';
    return is_file($file) ? trim((string)file_get_contents($file)) : '0.0.0';
}

function remoteVersion(): ?string
{
    $ch = curl_init(GITHUB_VERSION_URL);
    if ($ch === false) return null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT => 'RouteBox-Telegram-Bot-Admin',
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) return null;
    $version = trim((string)$raw);
    return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?$/', $version) ? $version : null;
}

function updaterReady(): bool
{
    // PHP open_basedir intentionally does not include /usr/local/sbin.
    // The actual updater is executed through the restricted sudo rule.
    // Check the local marker instead of probing the root-owned binary.
    return is_file(__DIR__ . '/../.updater-installed');
}

function panelLang(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['fa', 'en'], true)) $_SESSION['panel_lang'] = $_GET['lang'];
    return (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';
}

$lang = panelLang();
$section = (string)($_GET['section'] ?? 'dashboard');
$allowedSections = ['dashboard', 'servers', 'bot', 'plans', 'security', 'updates'];
if (!in_array($section, $allowedSections, true)) $section = 'dashboard';

$T = $lang === 'fa' ? [
    'dash'=>'داشبورد','servers'=>'سرورها','bot'=>'ربات تلگرام','plans'=>'پلن‌ها','security'=>'امنیت','updates'=>'به‌روزرسانی',
    'active'=>'فعال','offline'=>'غیرفعال','add'=>'افزودن','save'=>'ذخیره تغییرات','test'=>'تست اتصال','welcome'=>'پیام خوشامد',
    'buttons'=>'دکمه‌های ربات','settings'=>'تنظیمات ربات','trial_hours'=>'مدت تست (ساعت)','token'=>'توکن ربات','name'=>'نام',
    'url'=>'آدرس پنل RouteBox','username'=>'نام کاربری','password'=>'رمز عبور','country'=>'کشور','country_hint'=>'کد دوحرفی مثل US یا DE؛ اگر خالی باشد خودکار تشخیص داده می‌شود.',
    'days'=>'روز','quota'=>'حجم (GB)','enabled'=>'فعال','disabled'=>'غیرفعال','theme_toggle'=>'تغییر تم','logout'=>'خروج','version'=>'نسخه',
    'creator'=>'سازنده','update'=>'بررسی و به‌روزرسانی','users'=>'کاربران','footer'=>'ساخته شده توسط Amir Taheri','add_plan'=>'افزودن پلن',
    'plan_name'=>'نام پلن','ping'=>'پینگ','bot_preview'=>'پیش‌نمایش منوی ربات','welcome_hint'=>'پیام جداگانه فارسی و انگلیسی را همین‌جا مدیریت کنید.',
    'button_hint'=>'متن هر دکمه در هر دو زبان ذخیره می‌شود.','password_changed'=>'رمز پنل تغییر کرد.','bad_password'=>'رمزها یکسان نیستند یا کمتر از ۸ کاراکترند.',
    'change_password'=>'تغییر رمز پنل','new_password'=>'رمز جدید','confirm_password'=>'تکرار رمز جدید','no_servers'=>'هنوز سروری اضافه نشده است.',
    'no_plans'=>'هنوز پلنی ساخته نشده است.','server_list'=>'RouteBox های متصل','english'=>'English','persian'=>'فارسی',
    'latest'=>'این آخرین نسخه موجود است.','new_version'=>'نسخه جدید موجود است.','check_failed'=>'نتوانستیم نسخه GitHub را بررسی کنیم.',
    'updater_ready'=>'Updater آماده است','updater_missing'=>'Updater روی این سرور نصب نیست','update_started'=>'به‌روزرسانی با موفقیت شروع شد؛ پنل ممکن است چند ثانیه قطع شود.',
    'support'=>'ارتباط با پشتیبانی','support_hint'=>'برای راهنمایی یا گزارش مشکل با پشتیبانی در تلگرام در ارتباط باشید.',
    'dashboard_sub'=>'مدیریت حرفه‌ای RouteBox Telegram Bot','servers_sub'=>'مدیریت اتصال و وضعیت RouteBox ها','bot_sub'=>'تنظیمات، پیام‌ها و منوی دو زبانه ربات',
    'plans_sub'=>'مدیریت پلن‌های قابل فروش','security_sub'=>'امنیت و دسترسی پنل','updates_sub'=>'وضعیت نسخه و به‌روزرسانی امن',
] : [
    'dash'=>'Dashboard','servers'=>'Servers','bot'=>'Telegram Bot','plans'=>'Plans','security'=>'Security','updates'=>'Updates',
    'active'=>'Active','offline'=>'Offline','add'=>'Add','save'=>'Save changes','test'=>'Test connection','welcome'=>'Welcome message',
    'buttons'=>'Bot buttons','settings'=>'Bot settings','trial_hours'=>'Trial duration (hours)','token'=>'Bot token','name'=>'Name',
    'url'=>'RouteBox panel URL','username'=>'Username','password'=>'Password','country'=>'Country','country_hint'=>'Two-letter code such as US or DE. Leave blank for automatic detection.',
    'days'=>'Days','quota'=>'Quota (GB)','enabled'=>'Enabled','disabled'=>'Disabled','theme_toggle'=>'Toggle theme','logout'=>'Sign out','version'=>'Version',
    'creator'=>'Created by','update'=>'Check for updates','users'=>'Users','footer'=>'Created by Amir Taheri','add_plan'=>'Add plan','plan_name'=>'Plan name',
    'ping'=>'Ping','bot_preview'=>'Bot menu preview','welcome_hint'=>'Manage separate Persian and English welcome messages here.',
    'button_hint'=>'Each button is stored in both languages.','password_changed'=>'Panel password changed.','bad_password'=>'Passwords do not match or are shorter than 8 characters.',
    'change_password'=>'Change panel password','new_password'=>'New password','confirm_password'=>'Confirm new password','no_servers'=>'No servers have been added yet.',
    'no_plans'=>'No plans have been created yet.','server_list'=>'Connected RouteBox servers','english'=>'English','persian'=>'فارسی',
    'latest'=>'This is the latest available version.','new_version'=>'A new version is available.','check_failed'=>'Could not check GitHub for the latest version.',
    'updater_ready'=>'Updater ready','updater_missing'=>'Updater is not installed on this server','update_started'=>'Update started successfully; the panel may disconnect briefly.',
    'support'=>'Contact support','support_hint'=>'Contact support on Telegram for help or to report a problem.',
    'dashboard_sub'=>'Professional RouteBox Telegram Bot management','servers_sub'=>'Manage RouteBox connections and status','bot_sub'=>'Bot settings, messages and bilingual menu',
    'plans_sub'=>'Manage plans available to users','security_sub'=>'Panel security and access','updates_sub'=>'Version status and safe updates',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $returnSection = (string)($_POST['section'] ?? 'dashboard');
    if (!in_array($returnSection, $allowedSections, true)) $returnSection = 'dashboard';
    try {
        if ($action === 'add_server') {
            $name = trim((string)($_POST['name'] ?? ''));
            $url = rtrim(trim((string)($_POST['url'] ?? '')), '/');
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $verify = isset($_POST['verify_tls']) ? 1 : 0;
            $country = strtoupper(trim((string)($_POST['country_code'] ?? '')));
            if ($country === '') $country = detectCountryCode($url);
            if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) throw new RuntimeException('Invalid country code.');
            if ($name === '' || mb_strlen($name) > 80 || !filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('Invalid server name or URL.');
            validateRouteBoxForAdmin($url, $u, $p, (bool)$verify);
            db()->prepare('INSERT INTO routebox_servers(name,base_url,user_enc,pass_enc,verify_tls,enabled,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$name,$url,enc($u),enc($p),$verify,1,time()]);
            $id = (int)db()->lastInsertId();
            db()->prepare('INSERT OR REPLACE INTO server_meta(server_id,country_code,ping_ms,ping_checked_at) VALUES(?,?,?,?)')->execute([$id,$country,measureServerPing($url),time()]);
            $_SESSION['flash'] = '✓ Server saved.';
            $returnSection = 'servers';
        } elseif ($action === 'test_server') {
            $s = db()->prepare('SELECT * FROM routebox_servers WHERE id=?');
            $s->execute([(int)($_POST['id'] ?? 0)]);
            $server = $s->fetch(PDO::FETCH_ASSOC);
            if (!$server) throw new RuntimeException('Server not found.');
            validateRouteBoxForAdmin((string)$server['base_url'], dec((string)$server['user_enc']), dec((string)$server['pass_enc']), (bool)$server['verify_tls']);
            $country = detectCountryCode((string)$server['base_url']);
            $ping = measureServerPing((string)$server['base_url']);
            $old = db()->prepare('SELECT country_code FROM server_meta WHERE server_id=?');
            $old->execute([(int)$server['id']]);
            $country = $country ?: (string)$old->fetchColumn();
            db()->prepare('INSERT OR REPLACE INTO server_meta(server_id,country_code,ping_ms,ping_checked_at) VALUES(?,?,?,?)')->execute([(int)$server['id'],$country,$ping,time()]);
            $_SESSION['flash'] = '✓ ' . $T['test'] . ' OK';
            $returnSection = 'servers';
        } elseif ($action === 'toggle_server') {
            db()->prepare('UPDATE routebox_servers SET enabled=1-enabled WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '✓ ' . $T['save'];
            $returnSection = 'servers';
        } elseif ($action === 'save_settings') {
            $hours = max(1, min(720, (int)($_POST['trial_hours'] ?? 12)));
            db()->prepare("INSERT INTO settings(key,value) VALUES('trial_hours',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([(string)$hours]);
            $token = trim((string)($_POST['telegram_token'] ?? ''));
            if ($token !== '') {
                telegramCheckAndPrepare($token);
                db()->prepare("INSERT INTO settings(key,value) VALUES('telegram_token',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([enc($token)]);
            }
            foreach (['welcome_fa','welcome_en'] as $k) {
                db()->prepare("INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([$k,cleanBotText((string)($_POST[$k] ?? ''))]);
            }
            $_SESSION['flash'] = '✓ ' . $T['save'];
            $returnSection = 'bot';
        } elseif ($action === 'save_button') {
            $id = (int)($_POST['id'] ?? 0);
            $fa = cleanBotText((string)($_POST['text_fa'] ?? ''));
            $en = cleanBotText((string)($_POST['text_en'] ?? ''));
            if ($id < 1 || $fa === '' || $en === '') throw new RuntimeException('Button text cannot be empty.');
            db()->prepare('UPDATE telegram_buttons SET text_fa=?,text_en=?,updated_at=? WHERE id=?')->execute([$fa,$en,time(),$id]);
            $_SESSION['flash'] = '✓ ' . $T['save'];
            $returnSection = 'bot';
        } elseif ($action === 'toggle_button') {
            db()->prepare('UPDATE telegram_buttons SET enabled=1-enabled,updated_at=? WHERE id=?')->execute([time(),(int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '✓ ' . $T['save'];
            $returnSection = 'bot';
        } elseif ($action === 'add_plan' || $action === 'update_plan') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['plan_name'] ?? ''));
            $days = max(1, min(3650, (int)($_POST['duration_days'] ?? 30)));
            $quota = max(0, min(1024, (float)($_POST['quota_gb'] ?? 0)));
            if ($name === '') throw new RuntimeException('Plan name is required.');
            if ($action === 'add_plan') db()->prepare('INSERT INTO plans(name,duration_days,quota_gb,enabled,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([$name,$days,$quota,1,0,time(),time()]);
            else db()->prepare('UPDATE plans SET name=?,duration_days=?,quota_gb=?,updated_at=? WHERE id=?')->execute([$name,$days,$quota,time(),$id]);
            $_SESSION['flash'] = '✓ ' . $T['save'];
            $returnSection = 'plans';
        } elseif ($action === 'toggle_plan') {
            db()->prepare('UPDATE plans SET enabled=1-enabled,updated_at=? WHERE id=?')->execute([time(),(int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '✓ ' . $T['save'];
            $returnSection = 'plans';
        } elseif ($action === 'delete_plan') {
            db()->prepare('DELETE FROM plans WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            $_SESSION['flash'] = '✓ Plan deleted.';
            $returnSection = 'plans';
        } elseif ($action === 'password') {
            $a = (string)($_POST['new_password'] ?? '');
            $b = (string)($_POST['confirm_password'] ?? '');
            if (strlen($a) < 8 || $a !== $b) throw new RuntimeException($T['bad_password']);
            set_admin_password_hash(password_hash($a, PASSWORD_DEFAULT));
            $_SESSION['flash'] = '✓ ' . $T['password_changed'];
            $returnSection = 'security';
        } elseif ($action === 'update_now') {
            $local = localVersion();
            $remote = remoteVersion();
            if ($remote !== null && version_compare($remote, $local, '<=')) {
                $_SESSION['flash'] = '✓ ' . $T['latest'];
            } elseif (!updaterReady()) {
                throw new RuntimeException($T['updater_missing'] . '. Run update.sh once from SSH.');
            } else {
                $cmd = '/usr/bin/sudo -n /usr/local/sbin/routebox-telegram-bot-update';
                $pid = @shell_exec('nohup ' . $cmd . ' >/dev/null 2>&1 & echo $!');
                if (trim((string)$pid) === '') throw new RuntimeException('Could not start the updater.');
                $_SESSION['flash'] = '✓ ' . $T['update_started'];
            }
            $returnSection = 'updates';
        } else {
            throw new RuntimeException('Invalid request.');
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = '❌ ' . $e->getMessage();
        log_event('error', 'Admin action failed: ' . $e->getMessage());
    }
    header('Location: /?section=' . rawurlencode($returnSection));
    exit;
}

$servers = db()->query('SELECT s.*,m.country_code,m.ping_ms,m.ping_checked_at FROM routebox_servers s LEFT JOIN server_meta m ON m.server_id=s.id ORDER BY s.id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($servers as &$sv) {
    if (trim((string)$sv['country_code']) === '') {
        $c = detectCountryCode((string)$sv['base_url']);
        if ($c !== '') {
            db()->prepare('INSERT OR REPLACE INTO server_meta(server_id,country_code,ping_ms,ping_checked_at) VALUES(?,?,?,?)')->execute([(int)$sv['id'],$c,$sv['ping_ms'],$sv['ping_checked_at']]);
            $sv['country_code'] = $c;
        }
    }
}
unset($sv);

$users = (int)db()->query('SELECT COUNT(*) FROM telegram_users')->fetchColumn();
$plans = db()->query('SELECT * FROM plans ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);

/* ATD stats: global on Dashboard/Telegram Bot, provider-scoped on RouteBox Servers. */
$providerServerCount = static function (string $table): int {
    if (!in_array($table, ['routebox_servers', 'ibsng_servers', 'mikrotik_servers'], true)) return 0;
    $exists = db()->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $exists->execute([$table]);
    if ($exists->fetchColumn() === false) return 0;
    return (int)db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
};
$globalServerCount = $providerServerCount('routebox_servers') + $providerServerCount('ibsng_servers') + $providerServerCount('mikrotik_servers');
$globalPlanCount = (int)db()->query("SELECT COUNT(*) FROM service_plans")->fetchColumn();
$routeboxUserCount = (int)db()->query('SELECT COUNT(DISTINCT telegram_user_id) FROM provisions')->fetchColumn();
$routeboxPlanCount = (int)db()->query("SELECT COUNT(*) FROM service_plans WHERE provider_key='routebox'")->fetchColumn();
$routeboxConnectedCount = 0;
foreach ($servers as $sv) { if ((int)$sv['enabled'] === 1) $routeboxConnectedCount++; }
$routeboxStatusServer = $servers[0] ?? null;
$buttons = db()->query('SELECT * FROM telegram_buttons ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
$trial = (int)(db()->query("SELECT value FROM settings WHERE key='trial_hours'")->fetchColumn() ?: 12);
$wf = cleanBotText((string)(db()->query("SELECT value FROM settings WHERE key='welcome_fa'")->fetchColumn() ?: "🚀 RouteBox Telegram Bot\n\nسلام 👋\nسرویس موردنظر را انتخاب کنید:"));
$we = cleanBotText((string)(db()->query("SELECT value FROM settings WHERE key='welcome_en'")->fetchColumn() ?: "🚀 RouteBox Telegram Bot\n\nHello 👋\nChoose a service:"));
$tokenValue = (string)(db()->query("SELECT value FROM settings WHERE key='telegram_token'")->fetchColumn() ?: '');
$hasToken = $tokenValue !== '';
$version = localVersion();
$remote = ($section === 'dashboard' || $section === 'updates' || isset($_GET['check_update'])) ? remoteVersion() : null;
$updater = updaterReady();
$flash = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

function navIcon(string $key): string
{
    $icons = [
        'dashboard'=>'<svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/></svg>',
        'servers'=>'<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>',
        'bot'=>'<svg viewBox="0 0 24 24"><path d="M12 3v3m-5 2h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Z"/><path d="M8 13h.01M16 13h.01M9 17h6"/></svg>',
        'plans'=>'<svg viewBox="0 0 24 24"><path d="m12 3 8 4-8 4-8-4 8-4Zm-8 9 8 4 8-4M4 17l8 4 8-4"/></svg>',
        'security'=>'<svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 5-3.2 8-8 9-4.8-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'updates'=>'<svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5"/></svg>',
    ];
    return $icons[$key] ?? $icons['dashboard'];
}
?>
<!doctype html>
<html lang="<?=h($lang)?>" dir="<?=$lang==='fa'?'rtl':'ltr'?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#070b16">
<title>RouteBox Admin</title>
<style>
:root{color-scheme:dark;--bg:#070b16;--bg2:#0b1220;--card:rgba(15,23,42,.78);--card2:rgba(18,28,48,.9);--line:rgba(148,163,184,.15);--text:#eef4ff;--muted:#91a0b8;--primary:#5b8cff;--primary2:#7c5cff;--cyan:#36d6e7;--green:#39d98a;--red:#ff647c;--amber:#f7b955;--shadow:0 24px 80px rgba(0,0,0,.34)}
:root.light{color-scheme:light;--bg:#f4f7fb;--bg2:#eef3f9;--card:rgba(255,255,255,.86);--card2:#fff;--line:#dce5f0;--text:#101828;--muted:#66758a;--primary:#376df6;--primary2:#7657ee;--shadow:0 18px 50px rgba(20,34,60,.09)}
*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:24px}body{margin:0;min-height:100vh;color:var(--text);background:radial-gradient(900px 500px at 10% -10%,rgba(91,140,255,.23),transparent 60%),radial-gradient(800px 500px at 100% 10%,rgba(124,92,255,.16),transparent 60%),var(--bg);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Tahoma,Arial,sans-serif}button,input,textarea{font:inherit}a{color:inherit}.app{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100vh}.sidebar{position:sticky;top:0;height:100vh;padding:22px 14px;background:rgba(7,11,22,.72);backdrop-filter:blur(24px);border-inline-end:1px solid var(--line);z-index:20}.light .sidebar{background:rgba(255,255,255,.78)}.brand{display:flex;align-items:center;gap:12px;padding:4px 9px 25px}.brand-mark{width:43px;height:43px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--primary),var(--primary2));box-shadow:0 10px 30px rgba(91,140,255,.25)}.brand-mark svg{width:23px;height:23px;fill:none;stroke:white;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.brand-title{font-weight:800;letter-spacing:.2px}.brand-sub{font-size:11px;color:var(--muted);margin-top:2px}.nav{display:grid;gap:6px}.nav a{display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--muted);padding:11px 12px;border:1px solid transparent;border-radius:13px;transition:.2s ease}.nav a:hover{color:var(--text);background:rgba(255,255,255,.04)}.nav a.active{color:var(--text);background:linear-gradient(135deg,rgba(91,140,255,.19),rgba(124,92,255,.13));border-color:rgba(91,140,255,.24);box-shadow:inset 3px 0 0 var(--primary)}.nav svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}.sidebar-foot{position:absolute;left:14px;right:14px;bottom:17px;display:grid;gap:9px}.mini-link{display:flex;align-items:center;justify-content:center;gap:7px;color:var(--muted);text-decoration:none;font-size:12px;padding:9px;border:1px solid var(--line);border-radius:11px}.main{min-width:0;padding:28px clamp(18px,3vw,42px) 50px;max-width:1540px;width:100%;margin:auto}.topbar{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:24px}.eyebrow{font-size:12px;color:var(--primary);font-weight:800;letter-spacing:.06em;text-transform:uppercase}.topbar h1{margin:5px 0 4px;font-size:30px;letter-spacing:-.025em}.topbar p{margin:0;color:var(--muted);font-size:13px}.top-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.chip{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line);background:var(--card);padding:7px 10px;border-radius:999px;font-size:12px;color:var(--muted);text-decoration:none}.dot{width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 0 4px rgba(57,217,138,.09)}.icon-button{width:40px;height:40px;border:1px solid var(--line);background:var(--card);color:var(--text);border-radius:12px;display:grid;place-items:center;cursor:pointer}.icon-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.hero{position:relative;overflow:hidden;padding:25px;border:1px solid var(--line);border-radius:24px;background:linear-gradient(135deg,rgba(21,35,64,.92),rgba(20,24,49,.78));box-shadow:var(--shadow);margin-bottom:18px}.hero:after{content:"";position:absolute;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(54,214,231,.18),transparent 68%);right:-90px;top:-120px;pointer-events:none}.hero-row{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:18px}.hero h2{margin:0 0 7px;font-size:24px}.hero p{margin:0;color:#aebbd0;line-height:1.7;font-size:13px}.hero-actions{display:flex;gap:9px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:10px 14px;border:1px solid transparent;border-radius:11px;text-decoration:none;font-weight:750;cursor:pointer;transition:.18s ease}.btn:hover{transform:translateY(-1px)}.btn:focus-visible,.icon-button:focus-visible,.nav a:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid rgba(91,140,255,.45);outline-offset:2px}.btn-primary{color:white;background:linear-gradient(135deg,var(--primary),var(--primary2));box-shadow:0 10px 25px rgba(91,140,255,.18)}.btn-secondary{color:var(--text);background:rgba(255,255,255,.05);border-color:var(--line)}.btn-danger{color:#fff;background:linear-gradient(135deg,#e33b5b,#b91c3c)}.btn-support{color:#fff;background:linear-gradient(135deg,#2aabee,#168bd0)}.btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.flash{display:flex;align-items:center;gap:9px;padding:13px 15px;margin:0 0 17px;border:1px solid rgba(91,140,255,.32);background:rgba(45,91,190,.13);border-radius:14px}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 18px}.stat{padding:17px;border:1px solid var(--line);border-radius:17px;background:var(--card);backdrop-filter:blur(18px)}.stat-top{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:12px}.stat-icon{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;background:rgba(91,140,255,.12);color:var(--primary)}.stat-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.stat b{display:block;font-size:26px;margin-top:9px;letter-spacing:-.02em}.section{scroll-margin-top:20px}.card{border:1px solid var(--line);border-radius:20px;background:var(--card);backdrop-filter:blur(20px);box-shadow:0 18px 55px rgba(0,0,0,.12);padding:20px;margin:0 0 16px}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:17px}.section-title{display:flex;gap:11px;align-items:flex-start}.section-icon{width:38px;height:38px;flex:0 0 auto;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(91,140,255,.18),rgba(124,92,255,.14));color:var(--primary)}.section-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.section-head h2{font-size:18px;margin:0 0 4px}.section-head p{font-size:12px;color:var(--muted);margin:0;line-height:1.65}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{margin-bottom:12px}.field label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:7px}input,textarea{width:100%;border:1px solid var(--line);background:rgba(2,7,18,.34);color:var(--text);border-radius:12px;padding:11px 12px;outline:none}textarea{min-height:118px;resize:vertical;line-height:1.7}:root.light input,:root.light textarea{background:#fff}input::placeholder,textarea::placeholder{color:#708099}.help{color:var(--muted);font-size:11px;line-height:1.7;margin-top:5px}.form-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.switch{display:inline-flex;align-items:center;gap:9px;color:var(--muted);font-size:12px;cursor:pointer}.switch input{width:40px;height:22px;appearance:none;padding:0;border-radius:999px;background:#344054;position:relative;cursor:pointer;transition:.2s}.switch input:after{content:"";position:absolute;width:16px;height:16px;top:2px;left:3px;border-radius:50%;background:#fff;transition:.2s}.switch input:checked{background:var(--primary)}.switch input:checked:after{left:21px}.server-list{display:grid;gap:0}.server{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:13px;align-items:center;padding:15px 0;border-top:1px solid var(--line)}.server:first-child{border-top:0}.server-title{display:flex;align-items:center;gap:9px;font-weight:800}.flag{font-size:21px}.server-url{direction:ltr;text-align:left;color:var(--muted);font-size:11px;margin-top:5px;word-break:break-all}.status{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--muted);padding:5px 8px;border:1px solid var(--line);border-radius:999px}.status .dot{width:7px;height:7px}.ping{font-variant-numeric:tabular-nums;white-space:nowrap;color:var(--muted);font-size:12px}.btnrow{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}.empty{padding:28px;text-align:center;border:1px dashed var(--line);border-radius:15px;color:var(--muted)}.plans{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.plan{border:1px solid var(--line);border-radius:16px;padding:15px;background:rgba(255,255,255,.025)}.plan-top{display:flex;justify-content:space-between;gap:8px}.plan h3{margin:0;font-size:15px}.plan-meta{display:flex;gap:7px;flex-wrap:wrap;margin:11px 0;color:var(--muted);font-size:11px}.tag{border:1px solid var(--line);padding:5px 8px;border-radius:999px}.preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.phone{max-width:390px;margin:auto;border:1px solid rgba(255,255,255,.12);border-radius:25px;padding:13px;background:#0a1020;box-shadow:0 20px 60px rgba(0,0,0,.28)}.phone-head{height:25px;display:flex;align-items:center;justify-content:center;color:#8795ab;font-size:10px}.chat{border-radius:18px;padding:16px;background:linear-gradient(160deg,#121a2d,#0d1425);min-height:285px}.bubble{background:#202a42;border:1px solid rgba(255,255,255,.06);border-radius:15px 15px 15px 5px;padding:13px;white-space:pre-wrap;line-height:1.65;font-size:12px}.preview-btn{display:block;width:100%;border:1px solid rgba(91,140,255,.18);background:#18243d;color:#dce7ff;border-radius:10px;padding:9px;margin-top:7px;text-align:center;font-size:11px}.preview-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;color:var(--muted);font-size:11px}.button-editor{border:1px solid var(--line);border-radius:15px;padding:14px;margin-top:11px}.button-editor:first-child{margin-top:0}.button-title{display:flex;justify-content:space-between;gap:8px;align-items:center;margin-bottom:11px}.button-title strong{font-size:13px}.update-box{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.version-box{border:1px solid var(--line);border-radius:15px;padding:16px;background:rgba(255,255,255,.025)}.version-label{font-size:11px;color:var(--muted)}.version-value{font-size:22px;font-weight:850;margin-top:6px;direction:ltr;text-align:left}.state-ok{color:var(--green)}.state-new{color:var(--amber)}.state-bad{color:var(--red)}.support{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:17px;border-radius:16px;background:linear-gradient(135deg,rgba(42,171,238,.12),rgba(91,140,255,.08));border:1px solid rgba(42,171,238,.18)}.support h3{margin:0 0 4px;font-size:15px}.support p{margin:0;color:var(--muted);font-size:11px}.footer{padding:20px 0 5px;text-align:center;color:var(--muted);font-size:11px}.footer a{color:var(--primary);text-decoration:none}.mobile-menu{display:none}
@media(max-width:1050px){.app{grid-template-columns:220px 1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.update-box{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:800px){.app{display:block}.sidebar{position:fixed;inset:auto 12px 12px 12px;height:auto;padding:8px;border:1px solid var(--line);border-radius:18px;background:rgba(10,16,29,.9);box-shadow:var(--shadow);backdrop-filter:blur(22px)}.brand,.sidebar-foot{display:none}.nav{display:grid;grid-template-columns:repeat(6,1fr);gap:3px}.nav a{padding:9px 5px;justify-content:center;border-radius:11px;font-size:0}.nav a.active{box-shadow:inset 0 0 0 1px rgba(91,140,255,.22)}.nav svg{width:20px;height:20px}.main{padding:18px 14px 100px}.topbar h1{font-size:25px}.top-actions .chip:not(.keep){display:none}.hero-row{align-items:flex-start;flex-direction:column}.grid,.preview-grid,.plans{grid-template-columns:1fr}.server{grid-template-columns:1fr}.btnrow{justify-content:flex-start}.update-box{grid-template-columns:1fr}.support{align-items:flex-start;flex-direction:column}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}.btn:hover{transform:none}}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar">
  <div class="brand"><div class="brand-mark"><?=navIcon('bot')?></div><div><div class="brand-title">RouteBox Admin</div><div class="brand-sub">Telegram Bot Control Center</div></div></div>
  <nav class="nav" aria-label="Main navigation">
    <?php foreach(['dashboard','servers','bot','plans','security','updates'] as $key): ?>
      <a class="<?= $section===$key?'active':'' ?>" aria-current="<?= $section===$key?'page':'false' ?>" href="/?section=<?=$key?>"><?=navIcon($key)?><span><?=h($T[$key==='dashboard'?'dash':$key])?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a class="mini-link" href="https://github.com/PardisMobile/routebox-telegram-bot" target="_blank" rel="noopener noreferrer">GitHub</a>
    <a class="mini-link" href="/?section=<?=$section?>&lang=<?=$lang==='fa'?'en':'fa'?>"><?= $lang==='fa' ? 'English' : 'فارسی' ?></a>
  </div>
</aside>
<main class="main">
  <header class="topbar">
    <div><div class="eyebrow">ROUTEBOX TELEGRAM BOT</div><h1><?=h($T[$section==='dashboard'?'dash':$section])?></h1><p><?=h($T[$section.'_sub'])?></p></div>
    <div class="top-actions"><span class="chip keep"><span class="dot"></span><?=h($T['active'])?></span><span class="chip keep">v<?=h($version)?></span><button class="icon-button" id="themeBtn" type="button" aria-label="<?=h($T['theme_toggle'])?>"><svg viewBox="0 0 24 24"><path d="M12 3v2m0 14v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M3 12h2m14 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/><circle cx="12" cy="12" r="4"/></svg></button></div>
  </header>

  <?php if($flash): ?><div class="flash" role="status"><?=h($flash)?></div><?php endif; ?>

  <?php if($section==='dashboard'): ?>
  <section class="hero"><div class="hero-row"><div><div class="eyebrow">CONTROL CENTER</div><h2>RouteBox Telegram Bot</h2><p><?=$lang==='fa'?'مدیریت سرورها، ربات، پلن‌ها و به‌روزرسانی‌ها از یک پنل یکپارچه و تمیز.':'Manage servers, bot settings, plans and updates from one clean control center.'?></p></div><div class="hero-actions"><a class="btn btn-primary" href="/?section=servers"><?=navIcon('servers')?> <?=$T['servers']?></a><a class="btn btn-support" href="<?=h(SUPPORT_URL)?>" target="_blank" rel="noopener"><?=$T['support']?></a></div></div></section>
  <?php endif; ?>

  <?php
$statsServers = count($servers);
$statsUsers = $users;
$statsPlans = count($plans);
$statsGlobal = $section === 'dashboard' || $section === 'bot';
$statsServerStatus = $section === 'servers';
if ($statsGlobal) {
    $statsServers = $globalServerCount;
    $statsPlans = $globalPlanCount;
} elseif ($statsServerStatus) {
    $statsUsers = $routeboxUserCount;
    $statsPlans = $routeboxPlanCount;
}
?>
<div class="stats">
  <div class="stat"><div class="stat-top"><span><?=$T['servers']?></span><span class="stat-icon"><?=navIcon('servers')?></span></div><b><?=number_format($statsServers)?></b></div>
  <div class="stat"><div class="stat-top"><span><?=$T['users']?></span><span class="stat-icon"><?=navIcon('bot')?></span></div><b><?=number_format($statsUsers)?></b></div>
  <div class="stat"><div class="stat-top"><span><?=$T['plans']?></span><span class="stat-icon"><?=navIcon('plans')?></span></div><b><?=number_format($statsPlans)?></b></div>
  <?php if ($statsServerStatus): ?>
  <div class="stat">
    <div class="stat-top"><span><?=$lang==='fa'?'وضعیت سرور':'Server Status'?></span><span class="stat-icon"><?=navIcon('servers')?></span></div>
    <?php if ($routeboxStatusServer): ?>
      <b style="font-size:16px" class="state-ok">● <?=$routeboxConnectedCount?> <?=$lang==='fa'?'متصل':'Connected'?></b>
      <div style="margin-top:7px;font-size:11px;color:var(--muted);direction:ltr;text-align:left"><?=h((string)($routeboxStatusServer['base_url'] ?? ''))?></div>
      <div style="margin-top:4px;font-size:11px;color:var(--muted)">⚡ <?= $routeboxStatusServer['ping_ms'] !== null ? h((string)$routeboxStatusServer['ping_ms']).' ms' : '—' ?> · <?=h(countryFlag((string)$routeboxStatusServer['country_code']))?></div>
      <div class="form-actions" style="margin-top:9px">
        <form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="servers"><input type="hidden" name="id" value="<?=h((string)$routeboxStatusServer['id'])?>"><button class="btn btn-secondary" type="submit">↻ <?=$lang==='fa'?'رفرش / تست':'Refresh / Check'?></button></form>
      </div>
    <?php else: ?>
      <b style="font-size:15px;color:var(--muted)"><?=$lang==='fa'?'سروری ثبت نشده':'No server configured'?></b>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="stat"><div class="stat-top"><span><?=$T['version']?></span><span class="stat-icon"><?=navIcon('updates')?></span></div><b style="font-size:18px">v<?=h($version)?></b></div>
  <?php endif; ?>
</div>

  <?php if($section==='dashboard'): ?>
    <section class="card"><div class="section-head"><div class="section-title"><div class="section-icon"><?=navIcon('updates')?></div><div><h2><?=$T['updates']?></h2><p><?=$T['updates_sub']?></p></div></div><a class="btn btn-secondary" href="/?section=updates">View status</a></div><div class="update-box"><div class="version-box"><div class="version-label">Installed</div><div class="version-value">v<?=h($version)?></div></div><div class="version-box"><div class="version-label">GitHub</div><div class="version-value"><?=$remote!==null?'v'.h($remote):'—'?></div></div><div class="version-box"><div class="version-label">Updater</div><div class="version-value <?=$updater?'state-ok':'state-bad'?>" style="font-size:15px"><?=$updater?$T['updater_ready']:$T['updater_missing']?></div></div></div></section>
  <?php endif; ?>

  <?php if($section==='servers'): ?>
  <section class="section card" id="servers"><div class="section-head"><div class="section-title"><div class="section-icon"><?=navIcon('servers')?></div><div><h2><?=$T['server_list']?></h2><p><?=$T['servers_sub']?></p></div></div></div>
    <?php if(!$servers): ?><div class="empty"><?=$T['no_servers']?></div><?php else: ?><div class="server-list"><?php foreach($servers as $sv): ?><div class="server"><div><div class="server-title"><span class="flag"><?=h(countryFlag((string)$sv['country_code']))?></span><?=h((string)$sv['name'])?> <span class="status"><span class="dot" style="background:<?=$sv['enabled']?'var(--green)':'var(--red)'?>"></span><?= $sv['enabled']?$T['active']:$T['offline'] ?></span></div><div class="server-url"><?=h((string)$sv['base_url'])?></div></div><div class="ping">⚡ <?= $sv['ping_ms']!==null ? h((string)$sv['ping_ms']).' ms' : '—' ?></div><div class="btnrow"><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="servers"><input type="hidden" name="id" value="<?=h((string)$sv['id'])?>"><button class="btn btn-secondary" type="submit">↻ <?=$T['test']?></button></form><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_server"><input type="hidden" name="section" value="servers"><input type="hidden" name="id" value="<?=h((string)$sv['id'])?>"><button class="btn <?= $sv['enabled']?'btn-secondary':'btn-primary' ?>" type="submit"><?=$sv['enabled']?$T['disabled']:$T['enabled']?></button></form></div></div><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2><?=$T['add']?> RouteBox</h2><p><?=$T['servers_sub']?></p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="add_server"><input type="hidden" name="section" value="servers"><div class="grid"><div class="field"><label><?=$T['name']?></label><input name="name" required placeholder="RouteBox-USA"></div><div class="field"><label><?=$T['url']?></label><input name="url" required placeholder="https://example.com:8443"></div><div class="field"><label><?=$T['username']?></label><input name="username" required></div><div class="field"><label><?=$T['password']?></label><input name="password" type="password" required></div><div class="field"><label><?=$T['country']?></label><input name="country_code" maxlength="2" placeholder="US"><div class="help"><?=$T['country_hint']?></div></div></div><label class="switch"><input type="checkbox" name="verify_tls" checked><span><?=$lang==='fa'?'اعتبارسنجی گواهی TLS':'Verify TLS certificate'?></span></label><div class="form-actions"><button class="btn btn-primary" type="submit">+ <?=$T['add']?></button></div></form></section>
  <?php endif; ?>

  <?php if($section==='bot'): ?>
  <section class="card" id="bot"><div class="section-head"><div class="section-title"><div class="section-icon"><?=navIcon('bot')?></div><div><h2><?=$T['settings']?></h2><p><?=$T['bot_sub']?></p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="section" value="bot"><div class="grid"><div class="field"><label><?=$T['trial_hours']?></label><input type="number" min="1" max="720" name="trial_hours" value="<?=h((string)$trial)?>"></div><div class="field"><label><?=$T['token']?></label><input type="password" name="telegram_token" placeholder="<?=$hasToken?'••••••••••••••••':'123456:ABC...'?>" autocomplete="off"></div><div class="field"><label><?=$T['welcome']?> · فارسی</label><textarea name="welcome_fa"><?=h($wf)?></textarea></div><div class="field"><label><?=$T['welcome']?> · English</label><textarea name="welcome_en"><?=h($we)?></textarea></div></div><div class="help"><?=$T['welcome_hint']?></div><div class="form-actions"><button class="btn btn-primary" type="submit">✓ <?=$T['save']?></button></div></form></section>

  <section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">☷</div><div><h2><?=$T['buttons']?></h2><p><?=$T['button_hint']?></p></div></div></div><?php if(!$buttons): ?><div class="empty">No bot buttons.</div><?php else: ?><?php foreach($buttons as $b): ?><div class="button-editor"><div class="button-title"><strong><?=h((string)$b['action_key'])?></strong><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_button"><input type="hidden" name="section" value="bot"><input type="hidden" name="id" value="<?=h((string)$b['id'])?>"><button class="btn <?=$b['enabled']?'btn-secondary':'btn-primary'?>" type="submit"><?=$b['enabled']?$T['enabled']:$T['disabled']?></button></form></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_button"><input type="hidden" name="section" value="bot"><input type="hidden" name="id" value="<?=h((string)$b['id'])?>"><div class="grid"><div class="field"><label>فارسی</label><input name="text_fa" value="<?=h(cleanBotText((string)$b['text_fa']))?>"></div><div class="field"><label>English</label><input name="text_en" value="<?=h(cleanBotText((string)$b['text_en']))?>"></div></div><button class="btn btn-secondary" type="submit">✓ <?=$T['save']?></button></form></div><?php endforeach; ?><?php endif; ?></section>

  <section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2><?=$T['bot_preview']?></h2><p><?=$lang==='fa'?'هر دو زبان هم‌زمان نمایش داده می‌شوند تا قبل از ذخیره بتوانید منو را مقایسه کنید.':'Both languages are shown together so you can compare the menu before saving.'?></p></div></div></div><div class="preview-grid"><?php foreach([['fa','فارسی',$wf],['en','English',$we]] as [$pk,$pt,$msg]): ?><div class="phone"><div class="phone-head">RouteBox Bot · <?=$pt?></div><div class="chat"><div class="preview-title"><span><?=$pk==='fa'?'پیام خوشامد':'Welcome message'?></span><span>●</span></div><div class="bubble" dir="<?=$pk==='fa'?'rtl':'ltr'?>"><?=h($msg)?></div><?php foreach($buttons as $b): $label=cleanBotText((string)($pk==='fa'?$b['text_fa']:$b['text_en'])); if((int)$b['enabled']===1): ?><div class="preview-btn" dir="<?=$pk==='fa'?'rtl':'ltr'?>"><?=h($label)?></div><?php endif; endforeach; ?><?php foreach($plans as $p): if((int)$p['enabled']===1): ?><div class="preview-btn">🚀 <?=h((string)$p['name'])?> · <?=h((string)$p['duration_days'])?> <?=$T['days']?> · <?=h(q((float)$p['quota_gb']))?></div><?php endif; endforeach; ?></div></div><?php endforeach; ?></div></section>
  <?php endif; ?>

  <?php if($section==='plans'): ?>
  <section class="card" id="plans"><div class="section-head"><div class="section-title"><div class="section-icon"><?=navIcon('plans')?></div><div><h2><?=$T['plans']?></h2><p><?=$T['plan_sub']?></p></div></div></div><?php if(!$plans): ?><div class="empty"><?=$T['no_plans']?></div><?php else: ?><div class="plans"><?php foreach($plans as $p): ?><div class="plan"><div class="plan-top"><h3><?=h((string)$p['name'])?></h3><span class="status"><span class="dot" style="background:<?=$p['enabled']?'var(--green)':'var(--red)'?>"></span><?=$p['enabled']?$T['enabled']:$T['disabled']?></span></div><div class="plan-meta"><span class="tag">⏱ <?=h((string)$p['duration_days'])?> <?=$T['days']?></span><span class="tag">◈ <?=h(q((float)$p['quota_gb']))?></span></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="update_plan"><input type="hidden" name="section" value="plans"><input type="hidden" name="id" value="<?=h((string)$p['id'])?>"><div class="grid"><div class="field"><label><?=$T['plan_name']?></label><input name="plan_name" value="<?=h((string)$p['name'])?>"></div><div class="field"><label><?=$T['days']?></label><input type="number" min="1" max="3650" name="duration_days" value="<?=h((string)$p['duration_days'])?>"></div><div class="field"><label><?=$T['quota']?></label><input type="number" min="0" max="1024" step="0.1" name="quota_gb" value="<?=h((string)$p['quota_gb'])?>"></div></div><div class="form-actions"><button class="btn btn-secondary" type="submit">✓ <?=$T['save']?></button></form><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_plan"><input type="hidden" name="section" value="plans"><input type="hidden" name="id" value="<?=h((string)$p['id'])?>"><button class="btn <?=$p['enabled']?'btn-secondary':'btn-primary'?>" type="submit"><?=$p['enabled']?$T['disabled']:$T['enabled']?></button></form><form method="post" onsubmit="return confirm('Delete this plan?')"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="section" value="plans"><input type="hidden" name="id" value="<?=h((string)$p['id'])?>"><button class="btn btn-danger" type="submit">×</button></form></div></div><?php endforeach; ?></div><?php endif; ?></section>
  <section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2><?=$T['add_plan']?></h2><p><?=$T['plan_sub']?></p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="add_plan"><input type="hidden" name="section" value="plans"><div class="grid"><div class="field"><label><?=$T['plan_name']?></label><input name="plan_name" required></div><div class="field"><label><?=$T['days']?></label><input type="number" min="1" max="3650" name="duration_days" value="30"></div><div class="field"><label><?=$T['quota']?></label><input type="number" min="0" max="1024" step="0.1" name="quota_gb" value="0"></div></div><button class="btn btn-primary" type="submit">+ <?=$T['add_plan']?></button></form></section>
  <?php endif; ?>

  <?php if($section==='security'): ?>
  <section class="card" id="security"><div class="section-head"><div class="section-title"><div class="section-icon"><?=navIcon('security')?></div><div><h2><?=$T['change_password']?></h2><p><?=$T['security_sub']?></p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="password"><input type="hidden" name="section" value="security"><div class="grid"><div class="field"><label><?=$T['new_password']?></label><input type="password" name="new_password" minlength="8" required autocomplete="new-password"></div><div class="field"><label><?=$T['confirm_password']?></label><input type="password" name="confirm_password" minlength="8" required autocomplete="new-password"></div></div><button class="btn btn-primary" type="submit">✓ <?=$T['change_password']?></button></form></section>
  <div class="support"><div><h3><?=$T['support']?></h3><p><?=$T['support_hint']?></p></div><a class="btn btn-support" href="<?=h(SUPPORT_URL)?>" target="_blank" rel="noopener"><?=$T['support']?></a></div>
  <?php endif; ?>

  <?php if($section==='updates'): ?>
  <section class="card" id="updates"><div class="section-head"><div class="section-title"><div class="section-icon"><?=navIcon('updates')?></div><div><h2><?=$T['updates']?></h2><p><?=$T['updates_sub']?></p></div></div><a class="btn btn-secondary" href="/?section=updates&check_update=1">↻ <?=$T['update']?></a></div><div class="update-box"><div class="version-box"><div class="version-label">Installed</div><div class="version-value">v<?=h($version)?></div></div><div class="version-box"><div class="version-label">GitHub</div><div class="version-value"><?=$remote!==null?'v'.h($remote):'—'?></div></div><div class="version-box"><div class="version-label">Updater</div><div class="version-value <?=$updater?'state-ok':'state-bad'?>" style="font-size:15px"><?=$updater?$T['updater_ready']:$T['updater_missing']?></div></div></div><div style="margin-top:15px"><?php if($remote===null): ?><div class="help">⚠ <?=$T['check_failed']?></div><?php elseif(version_compare($remote,$version,'<=')): ?><div class="state-ok">✓ <?=$T['latest']?></div><?php else: ?><div class="state-new">↑ <?=$T['new_version']?></div><?php endif; ?></div><div class="form-actions"><?php if($remote!==null && version_compare($remote,$version,'>') && $updater): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="update_now"><input type="hidden" name="section" value="updates"><button class="btn btn-primary" type="submit">↑ <?=$T['update']?></button></form><?php elseif($remote!==null && version_compare($remote,$version,'<=')): ?><span class="chip">✓ <?=$T['latest']?></span><?php endif; ?><a class="btn btn-support" href="<?=h(SUPPORT_URL)?>" target="_blank" rel="noopener"><?=$T['support']?></a></div></section>
  <?php endif; ?>

  <div class="footer">© 2026 <a href="<?=h(SUPPORT_URL)?>" target="_blank" rel="noopener">Amir Taheri</a> · RouteBox Telegram Bot</div>
</main>
</div>
<script>
(function(){
  const root=document.documentElement;
  const saved=localStorage.getItem('rbt-theme');
  if(saved==='light') root.classList.add('light');
  const btn=document.getElementById('themeBtn');
  if(btn) btn.addEventListener('click',()=>{root.classList.toggle('light');localStorage.setItem('rbt-theme',root.classList.contains('light')?'light':'dark');});
  const forms=document.querySelectorAll('form');
  forms.forEach(form=>form.addEventListener('submit',()=>{const b=form.querySelector('button[type="submit"]');if(b){b.dataset.old=b.innerHTML;b.innerHTML='…';b.disabled=true;}}));
  const params=new URLSearchParams(location.search); const active=params.get('section');
  if(active){const el=document.getElementById(active);if(el && history.scrollRestoration==='manual'){requestAnimationFrame(()=>el.scrollIntoView({block:'start'}));}}
})();
</script>
</body>
</html>
