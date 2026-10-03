<?php

declare(strict_types=1);

namespace RouteBox\Admin;

use PDO;

require_once __DIR__ . '/ProviderGuideSection.php';

final class ATDPanelSections
{
    private static function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private static function fa(string $lang): bool { return $lang === 'fa'; }
    private static function input(string $name, string $label, string $value, string $type='text', bool $required=false): string {
        $tag = $type === 'textarea' ? '<textarea name="'.self::esc($name).'" rows="8"'.($required?' required':'').'>'.self::esc($value).'</textarea>' : '<input name="'.self::esc($name).'" type="'.self::esc($type).'" value="'.self::esc($value).'"'.($required?' required':'').'>';
        return '<div class="field"><label>'.self::esc($label).'</label>'.$tag.'</div>';
    }

    private static function ensureSchema(PDO $db): void
    {
        $now = time();
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_service_guides (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER, title_fa TEXT NOT NULL, title_en TEXT NOT NULL, body_fa TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, UNIQUE(category_id), FOREIGN KEY(category_id) REFERENCES service_categories(id) ON DELETE CASCADE)");
        $general = $db->query("SELECT COUNT(*) FROM telegram_service_guides WHERE category_id IS NULL")->fetchColumn();
        if ((int)$general === 0) {
            $q=$db->prepare("SELECT value FROM settings WHERE key=?");
            $q->execute(['guide_fa']); $fa=(string)($q->fetchColumn()?:'');
            $q->execute(['guide_en']); $en=(string)($q->fetchColumn()?:'');
            $db->prepare('INSERT INTO telegram_service_guides(category_id,title_fa,title_en,body_fa,body_en,enabled,sort_order,created_at,updated_at) VALUES(NULL,?,?,?,?,1,0,?,?)')->execute(['راهنمای عمومی','General Guide',$fa,$en,$now,$now]);
        }
        $cats=$db->query('SELECT id,name_fa,name_en,sort_order FROM service_categories')->fetchAll(PDO::FETCH_ASSOC);
        $ins=$db->prepare("INSERT OR IGNORE INTO telegram_service_guides(category_id,title_fa,title_en,body_fa,body_en,enabled,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,1,?,?,?)");
        foreach($cats as $c)$ins->execute([(int)$c['id'],(string)$c['name_fa'],(string)$c['name_en'],'','',10+(int)$c['sort_order'],$now,$now]);
        $db->exec("INSERT OR IGNORE INTO payment_providers(provider_key,display_name,enabled,config_json,sort_order,created_at,updated_at) VALUES('zarinpal','ZarinPal',0,'{}',10,strftime('%s','now'),strftime('%s','now')),('crypto','Crypto Gateway',0,'{}',20,strftime('%s','now'),strftime('%s','now'))");
        $db->exec("INSERT OR IGNORE INTO settings(key,value) VALUES('payment_currency','IRR'),('payment_callback_url','')");
    }

    public static function handle(PDO $db, array $post): string
    {
        self::ensureSchema($db);
        $action = (string)($post['atd_action'] ?? '');
        if ($action === '') return (string)($post['return_section'] ?? 'dashboard');
        $now = time();
        if ($action === 'save_bot_guide') {
            $id = (int)($post['id'] ?? 0);
            $cat = ($post['category_id'] ?? '') === '' ? null : (int)$post['category_id'];
            $enabled = isset($post['enabled']) ? 1 : 0;
            $sort = (int)($post['sort_order'] ?? 0);
            if ($id > 0) {
                $q=$db->prepare('UPDATE telegram_service_guides SET category_id=?,title_fa=?,title_en=?,body_fa=?,body_en=?,enabled=?,sort_order=?,updated_at=? WHERE id=?');
                $q->execute([$cat,trim((string)$post['title_fa']),trim((string)$post['title_en']),trim((string)$post['body_fa']),trim((string)$post['body_en']),$enabled,$sort,$now,$id]);
            } else {
                $q=$db->prepare('INSERT INTO telegram_service_guides(category_id,title_fa,title_en,body_fa,body_en,enabled,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
                $q->execute([$cat,trim((string)$post['title_fa']),trim((string)$post['title_en']),trim((string)$post['body_fa']),trim((string)$post['body_en']),$enabled,$sort,$now,$now]);
            }
            return 'bot-guides';
        }
        if ($action === 'delete_bot_guide') {
            $id=(int)($post['id']??0); if($id>0)$db->prepare('DELETE FROM telegram_service_guides WHERE id=?')->execute([$id]); return 'bot-guides';
        }
        if ($action === 'save_payment_global') {
            foreach (['payment_currency','payment_callback_url'] as $key) {
                $value=trim((string)($post[$key]??''));
                $db->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$key,$value]);
            }
            return 'payment-settings';
        }
        if ($action === 'toggle_payment_provider') {
            $id=(int)($post['id']??0); $db->prepare('UPDATE payment_providers SET enabled=CASE enabled WHEN 1 THEN 0 ELSE 1 END,updated_at=? WHERE id=?')->execute([$now,$id]); return 'payment-settings';
        }
        if ($action === 'save_payment_provider') {
            $id=(int)($post['id']??0); $key=trim((string)$post['provider_key']); $name=trim((string)$post['display_name']);
            $config=[]; foreach(['merchant_id','api_key','sandbox'] as $k) if(isset($post[$k])) $config[$k]=(string)$post[$k];
            $db->prepare('UPDATE payment_providers SET provider_key=?,display_name=?,config_json=?,updated_at=? WHERE id=?')->execute([$key,$name,json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$now,$id]); return 'payment-settings';
        }
        return (string)($post['return_section'] ?? 'dashboard');
    }

    private static function shell(string $title,string $subtitle,string $body,string $back='dashboard'): string {
        return '<section class="atd-admin-section card"><div class="section-head"><div><div class="eyebrow">ATD PANEL</div><h2>'.self::esc($title).'</h2><p>'.self::esc($subtitle).'</p></div><a class="btn btn-secondary" href="/?section='.rawurlencode($back).'">← Back</a></div>'.$body.'</section>'.self::styles();
    }

    private static function styles(): string {
        return '<style id="atd-extra-sections">.atd-admin-section{margin-top:8px}.atd-admin-section .section-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px}.atd-admin-section h2{margin:3px 0 6px;font-size:26px}.atd-admin-section .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.atd-admin-section .field{display:flex;flex-direction:column;gap:7px}.atd-admin-section .field label{font-weight:700;color:var(--muted)}.atd-admin-section input,.atd-admin-section textarea,.atd-admin-section select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid var(--line);border-radius:11px;background:var(--card);color:inherit;font:inherit}.atd-admin-section textarea{resize:vertical;min-height:150px}.atd-admin-section .full{grid-column:1/-1}.atd-admin-section .form-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.atd-admin-section .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:14px}.atd-admin-section table{width:100%;border-collapse:collapse}.atd-admin-section th,.atd-admin-section td{padding:12px 13px;border-bottom:1px solid var(--line);text-align:start;white-space:nowrap}.atd-admin-section th{color:var(--muted);font-size:12px}.atd-admin-section tr:last-child td{border-bottom:0}.atd-admin-section .list{display:grid;gap:12px}.atd-admin-section .item{border:1px solid var(--line);border-radius:15px;padding:15px;background:var(--card2)}.atd-admin-section .item-head{display:flex;justify-content:space-between;gap:12px;align-items:center}.atd-admin-section .muted{color:var(--muted);font-size:12px}.atd-admin-section details{margin-top:12px;border-top:1px solid var(--line);padding-top:12px}.atd-admin-section summary{cursor:pointer;font-weight:750}.atd-admin-section .badge{display:inline-flex;padding:4px 8px;border-radius:999px;background:rgba(110,140,255,.13);font-size:11px}.atd-admin-section .danger{color:var(--red)}.atd-admin-section .user-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center}.atd-admin-section .searchbar{display:flex;gap:8px;margin-bottom:16px}.atd-admin-section .searchbar input{flex:1}.atd-admin-section .guide-copy{white-space:pre-wrap;line-height:1.75;padding:12px;border:1px dashed var(--line);border-radius:12px;background:var(--card)}@media(max-width:760px){.atd-admin-section .grid{grid-template-columns:1fr}.atd-admin-section .user-card{grid-template-columns:1fr}.atd-admin-section .section-head{flex-direction:column}}
</style>';
    }

    public static function renderBot(PDO $db,string $lang,string $csrf): string {
        self::ensureSchema($db);
        $cats=$db->query('SELECT id,name_fa,name_en,provider_key FROM service_categories ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        $guides=$db->query('SELECT g.*,c.name_fa as cat_fa,c.name_en as cat_en FROM telegram_service_guides g LEFT JOIN service_categories c ON c.id=g.category_id ORDER BY g.sort_order,g.id')->fetchAll(PDO::FETCH_ASSOC);
        $fa=self::fa($lang); $catOptions='<option value="">'.($fa?'راهنمای عمومی':'General guide').'</option>'; foreach($cats as $c)$catOptions.='<option value="'.(int)$c['id'].'">'.self::esc($fa?$c['name_fa']:$c['name_en']).'</option>';
        $body='<div class="item"><div class="item-head"><div><strong>'.($fa?'راهنمای عمومی فعلی حفظ شده است':'Existing general guide is preserved').'</strong><div class="muted">'.($fa?'می‌توانید محتوای عمومی و راهنمای هر سرویس را جداگانه مدیریت کنید.':'Manage the existing general guide and each service guide separately.').'</div></div></div><details><summary>'.($fa?'افزودن راهنمای جدید':'Add service guide').'</summary>'.self::guideForm($csrf,$catOptions,$fa,null).'</details></div><div class="list">';
        foreach($guides as $g){$title=$fa?(string)$g['title_fa']:(string)$g['title_en'];$cat=$g['category_id']?(string)($fa?$g['cat_fa']:$g['cat_en']):($fa?'عمومی':'General');$body.='<div class="item"><div class="item-head"><div><strong>'.self::esc($title).'</strong><div class="muted">'.self::esc($cat).' · '.((int)$g['enabled'] ? ($fa ? 'فعال' : 'Enabled') : ($fa ? 'غیرفعال' : 'Disabled')).'</div></div><span class="badge">Guide</span></div><details><summary>'.($fa?'ویرایش':'Edit').'</summary>'.self::guideForm($csrf,$catOptions,$fa,$g).'</details></div>';}
        $body.='</div>';
        return self::shell($fa?'راهنمای ربات':'Telegram Bot Usage Guides',$fa?'راهنمای عمومی و راهنمای اتصال هر سرویس را از اینجا مدیریت کنید.':'Manage the existing general guide and separate connection guides for each service.',$body,'bot');
    }

    private static function guideForm(string $csrf,string $catOptions,bool $fa,?array $g): string {
        $id=$g?(int)$g['id']:0; $options=$catOptions;
        if($g && $g['category_id']) $options=str_replace('value="'.(int)$g['category_id'].'"','value="'.(int)$g['category_id'].'" selected',$options);
        $html='<form method="post" action="/?section=provider-guide"><input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="atd_action" value="save_bot_guide"><input type="hidden" name="id" value="'.$id.'"><div class="grid"><div class="field"><label>'.($fa?'دسته سرویس':'Service').'</label><select name="category_id">'.$options.'</select></div><div class="field"><label>'.($fa?'ترتیب':'Sort order').'</label><input name="sort_order" type="number" value="'.(int)($g['sort_order']??0).'" min="0"></div>'.self::input('title_fa',$fa?'عنوان فارسی':'Persian title',(string)($g['title_fa']??''), 'text',true).self::input('title_en','English title',(string)($g['title_en']??''),'text',true).'<div class="field full"><label>'.($fa?'متن فارسی':'Persian content').'</label><textarea name="body_fa" rows="10">'.self::esc((string)($g['body_fa']??'')).'</textarea></div><div class="field full"><label>English content</label><textarea name="body_en" rows="10">'.self::esc((string)($g['body_en']??'')).'</textarea></div><div class="field"><label><input type="checkbox" name="enabled" '.((int)($g['enabled']??1)?'checked':'').'> '.($fa?'فعال':'Enabled').'</label></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">'.($fa?'ذخیره راهنما':'Save guide').'</button></div></form>';
        return $html;
    }

    public static function renderProviderGuide(PDO $db,string $provider,string $lang,string $csrf): string {
        return ProviderGuideSection::render($provider, $lang);
    }

    public static function renderUsers(PDO $db,string $lang,string $csrf): string {
        self::ensureSchema($db);
        $fa=self::fa($lang);$search=trim((string)($_GET['q']??''));$sql='SELECT u.*,COUNT(s.id) AS subscription_count,MAX(s.expires_at) AS latest_expiry FROM telegram_users u LEFT JOIN service_subscriptions s ON s.telegram_user_id=u.id';$params=[];if($search!==''){$sql.=' WHERE u.telegram_id LIKE ? OR COALESCE(u.username,\'\') LIKE ? OR COALESCE(u.first_name,\'\') LIKE ?';$like='%'.$search.'%';$params=[$like,$like,$like];}$sql.=' GROUP BY u.id ORDER BY u.last_seen DESC LIMIT 100';$q=$db->prepare($sql);$q->execute($params);$users=$q->fetchAll(PDO::FETCH_ASSOC);
        $body='<form class="searchbar" method="get"><input type="hidden" name="section" value="users"><input name="q" value="'.self::esc($search).'" placeholder="'.($fa?'جستجو با Telegram ID، username یا نام':'Search Telegram ID, username or name').'"/><button class="btn btn-primary" type="submit">🔎 '.($fa?'جستجو':'Search').'</button></form><div class="table-wrap"><table><thead><tr><th>'.($fa?'کاربر':'User').'</th><th>Telegram ID</th><th>'.($fa?'سرویس‌ها':'Services').'</th><th>'.($fa?'آخرین فعالیت':'Last seen').'</th><th></th></tr></thead><tbody>';
        foreach($users as $u){$name=trim((string)($u['first_name']??''));$uname=trim((string)($u['username']??''));$display=$name!==''?$name:($uname!==''?'@'.$uname:(string)$u['telegram_id']);$body.='<tr><td><strong>'.self::esc($display).'</strong><div class="muted">'.self::esc($uname!==''?'@'.$uname:'').'</div></td><td>'.self::esc((string)$u['telegram_id']).'</td><td>'.(int)$u['subscription_count'].'</td><td>'.date('Y-m-d H:i',(int)$u['last_seen']).'</td><td><a class="btn btn-secondary" href="/?section=user-details&id='.(int)$u['id'].'">'.($fa?'جزئیات':'Details').'</a></td></tr>';}
        if(!$users)$body.='<tr><td colspan="5">'.($fa?'کاربری پیدا نشد.':'No users found.').'</td></tr>'; $body.='</tbody></table></div>';
        return self::shell($fa?'کاربران':'Users',$fa?'تمام کاربران ربات و وضعیت سرویس‌هایشان.':'All Telegram bot users and their service subscriptions.',$body,'dashboard');
    }

    public static function renderUserDetails(PDO $db,int $id,string $lang,string $csrf): string {
        self::ensureSchema($db);
        $fa=self::fa($lang);$q=$db->prepare('SELECT * FROM telegram_users WHERE id=?');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u)return self::shell($fa?'کاربر پیدا نشد':'User not found','', '<div class="empty-state">'.($fa?'کاربر وجود ندارد.':'User does not exist.').'</div>','users');
        $q=$db->prepare('SELECT s.*,c.name_fa,c.name_en,p.display_name_fa,p.display_name_en FROM service_subscriptions s LEFT JOIN service_categories c ON c.id=s.category_id LEFT JOIN service_plans p ON p.id=s.plan_id WHERE s.telegram_user_id=? ORDER BY s.created_at DESC');$q->execute([$id]);$subs=$q->fetchAll(PDO::FETCH_ASSOC);
        $body='<div class="item"><div class="user-card"><div><strong>'.self::esc((string)($u['first_name']??'' )).'</strong><div class="muted">@'.self::esc((string)($u['username']??'' )).' · Telegram ID '.self::esc((string)$u['telegram_id']).'</div></div><span class="badge">'.self::esc((string)$u['language']).'</span></div></div><div class="list">';foreach($subs as $s){$name=$fa?(string)$s['name_fa']:(string)$s['name_en'];$plan=$fa?(string)$s['display_name_fa']:(string)$s['display_name_en'];$body.='<div class="item"><div class="item-head"><div><strong>'.self::esc($name).' — '.self::esc($plan).'</strong><div class="muted">'.self::esc((string)$s['status']).' · '.($s['expires_at']?date('Y-m-d H:i',(int)$s['expires_at']):'—').'</div></div><span class="badge">'.self::esc((string)$s['provider_key']).'</span></div></div>';}$body.='</div>';
        return self::shell($fa?'جزئیات کاربر':'User Details',$fa?'سرویس‌ها و Subscriptionهای این کاربر.':'Services and subscriptions for this user.',$body,'users');
    }

    public static function renderPayments(PDO $db,string $lang,string $csrf): string {
        self::ensureSchema($db);
        $fa=self::fa($lang);$currency=(string)($db->query("SELECT value FROM settings WHERE key='payment_currency'")->fetchColumn()?:'IRR');$callback=(string)($db->query("SELECT value FROM settings WHERE key='payment_callback_url'")->fetchColumn()?:'');$providers=$db->query('SELECT * FROM payment_providers ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
        $body='<form method="post" class="item"><input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="atd_action" value="save_payment_global"><div class="grid">'.self::input('payment_currency',$fa?'واحد پول':'Currency',$currency,'text',true).self::input('payment_callback_url',$fa?'آدرس Callback':'Callback URL',$callback).'</div><div class="form-actions"><button class="btn btn-primary">'.($fa?'ذخیره تنظیمات پرداخت':'Save payment settings').'</button></div></form><div class="list">';
        foreach($providers as $p){$cfg=json_decode((string)$p['config_json'],true);if(!is_array($cfg))$cfg=[];$body.='<div class="item"><div class="item-head"><div><strong>'.self::esc((string)$p['display_name']).'</strong><div class="muted">'.self::esc((string)$p['provider_key']).'</div></div><span class="badge">'.((int)$p['enabled']?($fa?'فعال':'Enabled'):($fa?'غیرفعال':'Disabled')).'</span></div><details><summary>'.($fa?'ویرایش تنظیمات':'Edit settings').'</summary><form method="post"><input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="atd_action" value="save_payment_provider"><input type="hidden" name="id" value="'.(int)$p['id'].'"><div class="grid">'.self::input('provider_key','Provider key',(string)$p['provider_key'], 'text',true).self::input('display_name',$fa?'نام نمایشی':'Display name',(string)$p['display_name'],'text',true).self::input('merchant_id','Merchant ID',(string)($cfg['merchant_id']??'')).self::input('api_key','API Key',(string)($cfg['api_key']??'')).'</div><div class="form-actions"><button class="btn btn-primary">'.($fa?'ذخیره':'Save').'</button></div></form></details><form method="post" style="margin-top:8px"><input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="atd_action" value="toggle_payment_provider"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button class="btn btn-secondary">'.((int)$p['enabled']?($fa?'غیرفعال کردن':'Disable'):($fa?'فعال کردن':'Enable')).'</button></form></div>';}
        $body.='</div>';
        return self::shell($fa?'تنظیمات پرداخت':'Payment Settings',$fa?'زیرساخت مشترک پرداخت برای همه Providerها؛ بدون تغییر در provisioning فعلی.':'Shared payment foundation for all providers; existing provisioning remains untouched.',$body,'dashboard');
    }
}

/*
 * ATD stats normalizer.
 * index.php already captures the legacy core page into $html and then includes
 * this file.  Keep the provider implementations untouched, but replace the
 * legacy/duplicate stats block with one canonical ATD block for the core
 * sections. This also makes Dashboard and Telegram Bot use the same global
 * counts while provider/server pages use provider-scoped counts.
 */
if (isset($html, $section, $lang) && is_string($html) && is_string($section) && is_string($lang)) {
    $atdDb = db();
    $atdTableCount = static function (PDO $db, string $table): int {
        if (!preg_match('/^[a-z0-9_]+$/', $table)) return 0;
        $q = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $q->execute([$table]);
        if ($q->fetchColumn() === false) return 0;
        return (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    };
    $atdGlobalServers = $atdTableCount($atdDb, 'routebox_servers') + $atdTableCount($atdDb, 'ibsng_servers') + $atdTableCount($atdDb, 'mikrotik_servers');
    $atdGlobalUsers = $atdTableCount($atdDb, 'telegram_users');
    $atdGlobalPlans = $atdTableCount($atdDb, 'service_plans');
    $atdRouteboxUsers = (int)$atdDb->query('SELECT COUNT(DISTINCT telegram_user_id) FROM provisions')->fetchColumn();
    $atdRouteboxPlans = (int)$atdDb->query("SELECT COUNT(*) FROM service_plans WHERE provider_key='routebox'")->fetchColumn();
    $atdServers = $atdDb->query('SELECT s.*,m.country_code,m.ping_ms FROM routebox_servers s LEFT JOIN server_meta m ON m.server_id=s.id ORDER BY s.id')->fetchAll(PDO::FETCH_ASSOC);
    $atdConnected = 0;
    foreach ($atdServers as $atdServer) { if ((int)$atdServer['enabled'] === 1) $atdConnected++; }
    $atdStatusServer = $atdServers[0] ?? null;
    $atdVersionFile = __DIR__ . '/../../VERSION';
    $atdVersion = is_file($atdVersionFile) ? trim((string)file_get_contents($atdVersionFile)) : '0.0.0';

    $atdBotService = 'routebox-telegram-bot.service';
    $atdBotStatus = 'unknown';
    foreach (['routebox-telegram-bot.service', 'routebox-telegram-bot-dev.service'] as $candidate) {
        $candidateStatus = trim((string)@shell_exec('systemctl is-active ' . escapeshellarg($candidate) . ' 2>/dev/null'));
        if ($candidateStatus !== '' || is_file('/etc/systemd/system/' . $candidate)) {
            $atdBotService = $candidate;
            $atdBotStatus = $candidateStatus !== '' ? $candidateStatus : 'unknown';
            break;
        }
    }
    $atdBotIp = trim((string)($_SERVER['SERVER_ADDR'] ?? ''));
    if ($atdBotIp === '' || filter_var($atdBotIp, FILTER_VALIDATE_IP) === false) {
        $atdBotIp = trim((string)@shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    }
    $atdBotCountry = '';
    if ($atdBotIp !== '' && function_exists('detectCountryCode')) {
        $atdBotCountry = (string)\detectCountryCode('http://' . $atdBotIp);
    }
    $atdBotRunning = $atdBotStatus === 'active';
    $atdCsrf = function_exists('csrf_token') ? (string)csrf_token() : '';
    $atdFa = $lang === 'fa';
    $atdLabel = static function (string $fa, string $en) use ($atdFa): string {
        return htmlspecialchars($atdFa ? $fa : $en, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $atdFlag = static function (string $code): string {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{2}$/', $code) || !function_exists('mb_chr')) return '🌐';
        return mb_chr(127397 + ord($code[0])) . mb_chr(127397 + ord($code[1]));
    };
    $atdStatusCard = function (bool $routeboxScope = false) use ($atdFa, $atdLabel, $atdStatusServer, $atdConnected, $atdFlag, $atdCsrf): string {
        if (!$atdStatusServer) {
            return '<div class="stat"><div class="stat-top"><span>'.$atdLabel('وضعیت سرور','Server Status').'</span><span class="stat-icon">●</span></div><b style="font-size:15px;color:var(--muted)">'.$atdLabel('سروری ثبت نشده','No server configured').'</b></div>';
        }
        $url = htmlspecialchars((string)($atdStatusServer['base_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ping = $atdStatusServer['ping_ms'] !== null ? htmlspecialchars((string)$atdStatusServer['ping_ms'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').' ms' : '—';
        $flag = $atdFlag((string)($atdStatusServer['country_code'] ?? ''));
        $connectedText = $atdConnected > 0 ? '<span class="state-ok">● '.$atdConnected.' '.$atdLabel('متصل','Connected').'</span>' : '<span class="state-bad">● '.$atdLabel('قطع','Offline').'</span>';
        return '<div class="stat"><div class="stat-top"><span>'.$atdLabel('وضعیت سرور','Server Status').'</span><span class="stat-icon">↔</span></div><b style="font-size:16px">'.$connectedText.'</b><div style="margin-top:7px;font-size:11px;color:var(--muted);direction:ltr;text-align:left">'.$url.'</div><div style="margin-top:4px;font-size:11px;color:var(--muted)">⚡ '.$ping.' · '.$flag.'</div><div class="form-actions" style="margin-top:9px"><form method="post"><input type="hidden" name="csrf_token" value="'.htmlspecialchars($atdCsrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"><input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="servers"><input type="hidden" name="id" value="'.(int)$atdStatusServer['id'].'"><button class="btn btn-secondary" type="submit">↻ '.$atdLabel('رفرش / تست','Refresh / Check').'</button></form></div></div>';
    };

    $atdStats = '<div class="stats">';
    $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('سرورها','Servers').'</span><span class="stat-icon">'.$atdLabel('▦','▦').'</span></div><b>'.number_format($section === 'servers' ? count($atdServers) : $atdGlobalServers).'</b></div>';
    $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('کاربران','Users').'</span><span class="stat-icon">'.$atdLabel('♙','♙').'</span></div><b>'.number_format($section === 'servers' ? $atdRouteboxUsers : $atdGlobalUsers).'</b></div>';
    $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('پلن‌ها','Plans').'</span><span class="stat-icon">'.$atdLabel('≋','≋').'</span></div><b>'.number_format($section === 'servers' ? $atdRouteboxPlans : $atdGlobalPlans).'</b></div>';
    if ($section === 'dashboard') {
        $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('نسخه','Version').'</span><span class="stat-icon">↻</span></div><b style="font-size:18px">v'.htmlspecialchars($atdVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</b></div>';
    } elseif ($section === 'bot') {
        $workerClass = $atdBotRunning ? 'state-ok' : 'state-bad';
        $workerText = $atdBotRunning ? $atdLabel('Worker فعال','Worker Active') : $atdLabel('Worker متوقف','Worker '.($atdBotStatus ?: 'unknown'));
        $ipEsc = htmlspecialchars($atdBotIp !== '' ? $atdBotIp : '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flag = $atdFlag($atdBotCountry);
        $atdStats .= '<div class="stat"><div class="stat-top"><span>'.$atdLabel('سرور ربات','Bot Server').'</span><span class="stat-icon">⌁</span></div><b style="font-size:15px" class="'.$workerClass.'">● '.$workerText.'</b><div style="margin-top:7px;font-size:11px;color:var(--muted);direction:ltr;text-align:left">'.$ipEsc.' · '.$flag.'</div><div class="form-actions" style="margin-top:9px"><form method="post" action="/reload-worker.php"><input type="hidden" name="csrf_token" value="'.htmlspecialchars($atdCsrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"><button class="btn btn-secondary" type="submit">↻ '.$atdLabel('Reload Worker','Reload Worker').'</button></form></div></div>';
    } else {
        $atdStats .= $atdStatusCard($section === 'servers');
    }
    $atdStats .= '</div>';

    // Remove the first/legacy stats container, including any duplicate blocks produced by older revisions.
    $atdStart = strpos($html, '<div class="stats">');
    while ($atdStart !== false) {
        $depth = 0;
        $pos = $atdStart;
        $length = strlen($html);
        $end = null;
        while ($pos < $length) {
            $open = strpos($html, '<div', $pos);
            $close = strpos($html, '</div>', $pos);
            if ($close === false) break;
            if ($open !== false && $open < $close) {
                $depth++;
                $pos = $open + 4;
            } else {
                $depth--;
                $pos = $close + 6;
                if ($depth === 0) { $end = $pos; break; }
            }
        }
        if ($end === null) break;
        $html = substr($html, 0, $atdStart) . substr($html, $end);
        $atdStart = strpos($html, '<div class="stats">');
    }

    // Put one canonical stats row directly after the flash message/hero area.
    $anchor = '</header>';
    $insertAt = strpos($html, $anchor);
    if ($insertAt !== false) {
        $insertAt += strlen($anchor);
        $html = substr($html, 0, $insertAt) . $atdStats . substr($html, $insertAt);
    }
}
