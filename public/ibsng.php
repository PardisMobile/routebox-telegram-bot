<?php
declare(strict_types=1);

// IBSng module entry point. Kept separate from RouteBox core so future providers
// can expose their own UI without duplicating integration logic.
require __DIR__ . '/../src/bootstrap.php';
require_admin();
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngAdmin.php';

$admin = new \RouteBox\Integrations\IBSng\IBSngAdmin(db());
$admin->handle($_POST, $_SERVER['REQUEST_METHOD'] ?? 'GET');
$data = $admin->viewData();
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>IBSng — RouteBox</title>
<style>
body{margin:0;background:#070b16;color:#eef4ff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif}.wrap{max-width:1100px;margin:0 auto;padding:28px}.card{background:#0f172a;border:1px solid rgba(148,163,184,.16);border-radius:18px;padding:20px;margin-bottom:16px}.row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}label{display:block;color:#91a0b8;font-size:13px;margin-bottom:7px}input,select{width:100%;box-sizing:border-box;background:#0b1220;color:#eef4ff;border:1px solid #26334a;border-radius:10px;padding:11px}button{border:0;border-radius:10px;padding:11px 15px;background:#5b8cff;color:#fff;font-weight:700;cursor:pointer}.muted{color:#91a0b8}.ok{color:#39d98a}.err{color:#ff647c}.tag{display:inline-block;padding:5px 9px;border-radius:999px;background:#16233d;color:#9fc0ff;margin:3px}.secret{letter-spacing:.12em}.back{display:inline-block;color:#9fc0ff;text-decoration:none;margin-bottom:18px}
</style>
</head>
<body><main class="wrap">
<a class="back" href="/?section=dashboard">← بازگشت به RouteBox Admin</a>
<h1>🔌 مدیریت IBSng</h1><p class="muted">اتصال و مدیریت مستقل IBSng — بدون دستکاری هسته RouteBox</p>
<?php if (!empty($data['flash'])): ?><div class="card <?=!empty($data['error'])?'err':'ok'?>"><?=h((string)$data['flash'])?></div><?php endif; ?>
<section class="card"><h2>افزودن سرور IBSng</h2><form method="post"><input type="hidden" name="action" value="add_server"><div class="row">
<div><label>نام</label><input name="name" required maxlength="80"></div>
<div><label>IP / Host</label><input name="host" required></div>
<div><label>Admin Username</label><input name="username" required autocomplete="username"></div>
<div><label>Admin Password</label><input type="password" name="password" required autocomplete="new-password"></div>
</div><p class="muted">پورت API پیش‌فرض: 1237 — فقط در تنظیمات پیشرفته قابل تغییر است.</p><button>تست و ذخیره</button></form></section>
<section class="card"><h2>سرورها</h2><?php if (!$data['servers']): ?><p class="muted">هنوز سروری اضافه نشده است.</p><?php else: ?><?php foreach($data['servers'] as $s): ?><div class="card"><strong><?=h((string)$s['name'])?></strong><p class="muted"><?=h((string)$s['host'])?> · API <?=h((string)$s['api_port'])?></p><p>Username: <span class="secret">••••••••</span> &nbsp; Password: <span class="secret">••••••••</span></p><p>Groups: <?php foreach(($s['groups']??[]) as $g): ?><span class="tag"><?=h((string)$g['name'])?></span><?php endforeach; ?></p></div><?php endforeach; ?><?php endif; ?></section>
</main></body></html>
