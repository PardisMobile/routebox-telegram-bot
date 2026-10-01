<?php
declare(strict_types=1);

ob_start();
require __DIR__ . '/../src/bootstrap.php';
require_admin();

require_once __DIR__ . '/../src/Integrations/IBSng/IBSngSchema.php';
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngClient.php';
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngService.php';
require_once __DIR__ . '/../src/Integrations/IBSng/IBSngAdmin.php';

$admin = new \RouteBox\Integrations\IBSng\IBSngAdmin(db());
$admin->handle($_POST, $_SERVER['REQUEST_METHOD'] ?? 'GET');
$data = $admin->viewData();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IBSng — RouteBox Admin</title>
<style>
:root{--bg:#070b16;--panel:#0f172a;--panel2:#111c31;--border:rgba(148,163,184,.16);--text:#eef4ff;--muted:#91a0b8;--blue:#5b8cff;--blue2:#7aa2ff;--green:#39d98a;--red:#ff647c;--sidebar:#0a1020}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif}a{color:inherit}
.layout{min-height:100vh;display:flex;direction:ltr}.sidebar{width:250px;flex:0 0 250px;background:linear-gradient(180deg,#0a1020,#080d19);border-right:1px solid var(--border);padding:24px 16px;direction:rtl}.brand{display:flex;align-items:center;gap:12px;padding:8px 10px 28px}.brand-icon{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,#4f7cff,#7c4dff);display:grid;place-items:center;font-size:24px;box-shadow:0 10px 28px rgba(71,93,255,.25)}.brand-title{font-size:18px;font-weight:800}.brand-sub{font-size:11px;color:var(--muted);margin-top:4px}.nav{display:flex;flex-direction:column;gap:7px}.nav a{display:flex;align-items:center;gap:12px;text-decoration:none;color:#aab6cb;padding:12px 13px;border-radius:13px;font-weight:700;transition:.15s}.nav a:hover{background:#111d33;color:#fff}.nav a.active{background:linear-gradient(90deg,rgba(91,140,255,.22),rgba(91,140,255,.10));color:#fff;box-shadow:inset 0 0 0 1px rgba(91,140,255,.25)}.nav .ico{width:24px;text-align:center;font-size:18px}.nav .spacer{height:8px}.content{direction:rtl;flex:1;min-width:0;padding:34px;max-width:1450px}.topline{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px}.eyebrow{color:#6e9cff;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.title{font-size:36px;line-height:1.15;margin:7px 0 5px;font-weight:900}.subtitle{color:var(--muted);margin:0;max-width:850px}.card{background:linear-gradient(180deg,rgba(17,28,49,.96),rgba(13,22,39,.96));border:1px solid var(--border);border-radius:20px;padding:22px;margin-bottom:18px;box-shadow:0 14px 40px rgba(0,0,0,.12)}.card h2{margin:0 0 8px;font-size:20px}.muted{color:var(--muted)}.flash{padding:14px 16px;border-radius:14px;margin-bottom:18px;border:1px solid var(--border);font-weight:700}.ok{color:var(--green);background:rgba(57,217,138,.07)}.err{color:var(--red);background:rgba(255,100,124,.07)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field label{display:block;color:#aab6cb;font-size:12px;font-weight:700;margin-bottom:7px}.field input,.field select{width:100%;background:#091221;color:#eef4ff;border:1px solid #27364f;border-radius:11px;padding:11px 12px;outline:none}.field input:focus,.field select:focus{border-color:#5b8cff;box-shadow:0 0 0 3px rgba(91,140,255,.12)}.hint{font-size:11px;color:#71809a;margin-top:6px}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.btn{border:0;border-radius:10px;padding:10px 14px;background:var(--blue);color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block}.btn.secondary{background:#17243b;color:#c9d5e8;border:1px solid #2a3a56}.btn.danger{background:#3a1620;color:#ffb5c0}.server{background:#0b1425;border:1px solid #22324c;border-radius:17px;padding:18px;margin-top:13px}.server-head{display:flex;justify-content:space-between;gap:15px;align-items:flex-start}.server-name{font-size:18px;font-weight:900}.meta{color:var(--muted);font-size:12px;margin-top:6px}.badge{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800;background:#15243c;color:#9fc0ff}.badge.ok{background:rgba(57,217,138,.10);color:var(--green)}.badge.err{background:rgba(255,100,124,.10);color:var(--red)}.server-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:17px}.server-edit{padding-top:15px;border-top:1px solid var(--border);margin-top:16px}.group-list{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.tag{display:inline-block;padding:6px 10px;border-radius:999px;background:#16233d;color:#a9c5ff;border:1px solid #263b60;font-size:12px}.empty{color:var(--muted);padding:18px 0}.security-note{padding:12px 14px;border-radius:12px;background:#0b1629;border:1px solid #20324e;color:#9fb0c9;font-size:12px;line-height:1.8}.footer{color:#61708a;text-align:center;font-size:11px;padding:24px 0}.back{color:#9fc0ff;text-decoration:none;font-weight:700}@media(max-width:900px){.layout{display:block}.sidebar{width:100%;border-right:0;border-bottom:1px solid var(--border);padding:12px}.brand{padding:8px 10px 12px}.nav{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:5px}.nav a{justify-content:center;font-size:11px;padding:9px 5px;flex-direction:column;gap:4px}.content{padding:20px 14px}.grid,.server-grid{grid-template-columns:1fr}.title{font-size:29px}}@media(max-width:520px){.nav{grid-template-columns:repeat(2,minmax(0,1fr))}.topline{display:block}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar">
  <div class="brand"><div class="brand-icon">🤖</div><div><div class="brand-title">RouteBox Admin</div><div class="brand-sub">Telegram Bot Control Center</div></div></div>
  <nav class="nav" aria-label="RouteBox Admin">
    <a href="/?section=dashboard"><span class="ico">▦</span><span>داشبورد</span></a>
    <a href="/?section=servers"><span class="ico">▤</span><span>سرورها</span></a>
    <a href="/?section=bot"><span class="ico">🤖</span><span>ربات تلگرام</span></a>
    <a href="/?section=plans"><span class="ico">▱</span><span>پلن‌ها</span></a>
    <a href="/?section=security"><span class="ico">♢</span><span>امنیت</span></a>
    <a href="/?section=updates"><span class="ico">↻</span><span>به‌روزرسانی</span></a>
    <div class="spacer"></div>
    <a class="active" href="/ibsng.php"><span class="ico">▤</span><span>IBSng</span></a>
  </nav>
</aside>
<main class="content">
  <div class="topline">
    <div><div class="eyebrow">ROUTEBOX SERVICE MODULE</div><h1 class="title">مدیریت IBSng</h1><p class="subtitle">اتصال و مدیریت IBSng A1.24 از طریق Web Panel. اطلاعات ورود رمزنگاری می‌شوند و هیچ‌وقت در صفحه نمایش داده نمی‌شوند.</p></div>
    <a class="back" href="/">← بازگشت به داشبورد</a>
  </div>

  <?php if (!empty($data['flash'])): ?><div class="flash <?=!empty($data['error'])?'err':'ok'?>"><?=h((string)$data['flash'])?></div><?php endif; ?>

  <section class="card">
    <h2>➕ افزودن سرور IBSng</h2>
    <p class="muted">قبل از ذخیره، اتصال Web Panel تست می‌شود و گروه‌ها همان لحظه Sync می‌شوند.</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=h($csrf)"><input type="hidden" name="action" value="add_server">
      <div class="grid">
        <div class="field"><label>نام سرور</label><input name="name" required maxlength="80" placeholder="IBSng Main"></div>
        <div class="field"><label>IP / Host</label><input name="host" required placeholder="185.18.x.x"></div>
        <div class="field"><label>پورت Web Panel</label><select name="port"><option value="80">80 — HTTP</option><option value="443">443 — HTTPS</option></select></div>
        <div class="field"><label>ISP Name</label><input name="isp_name" value="Main" required></div>
        <div class="field"><label>Admin Username</label><input name="username" required autocomplete="off"></div>
        <div class="field"><label>Admin Password</label><input type="password" name="password" required autocomplete="new-password"></div>
      </div>
      <div class="actions"><button class="btn" type="submit">تست اتصال و ذخیره</button></div>
    </form>
  </section>

  <section class="card">
    <h2>🖥 سرورهای IBSng</h2>
    <p class="muted">نام، Host، پورت و ISP قابل ویرایش هستند. برای امنیت، Username و Password ذخیره‌شده هرگز نمایش داده نمی‌شوند.</p>
    <?php if (!$data['servers']): ?>
      <div class="empty">هنوز هیچ سرور IBSng اضافه نشده است.</div>
    <?php else: foreach ($data['servers'] as $s): ?>
      <article class="server">
        <div class="server-head">
          <div><div class="server-name"><?=h((string)$s['name'])?></div><div class="meta"><?=h((string)$s['host'])?> : <?=h((string)$s['port'])?> · ISP: <?=h((string)$s['isp_name'])?></div></div>
          <?php if (!empty($s['last_test_at']) && empty($s['last_error'])): ?><span class="badge ok">● اتصال موفق</span><?php elseif (!empty($s['last_error'])): ?><span class="badge err">● خطای اتصال</span><?php else: ?><span class="badge">● تست نشده</span><?php endif; ?>
        </div>

        <div class="server-grid">
          <div>
            <div class="meta">دسترسی‌ها</div>
            <div class="security-note">Admin Username: <strong>••••••••</strong><br>Password: <strong>••••••••</strong><br>Credentialها رمزنگاری‌شده نگهداری می‌شوند.</div>
          </div>
          <div>
            <div class="meta">گروه‌های Sync شده (<?=count($s['groups']??[])?>)</div>
            <?php if (!empty($s['groups'])): ?><div class="group-list"><?php foreach ($s['groups'] as $g): ?><span class="tag"><?=h((string)$g['group_name'])?></span><?php endforeach; ?></div><?php else: ?><div class="empty">گروهی Sync نشده است.</div><?php endif; ?>
          </div>
        </div>

        <?php if (!empty($s['last_error'])): ?><div class="flash err" style="margin-top:15px;margin-bottom:0"><?=h((string)$s['last_error'])?></div><?php endif; ?>

        <div class="actions">
          <form method="post"><input type="hidden" name="csrf_token" value="<?=h($csrf)"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="<?=h((string)$s['id'])"><button class="btn secondary" type="submit">تست اتصال</button></form>
          <form method="post"><input type="hidden" name="csrf_token" value="<?=h($csrf)"><input type="hidden" name="action" value="sync_groups"><input type="hidden" name="id" value="<?=h((string)$s['id'])"><button class="btn secondary" type="submit">Sync گروه‌ها</button></form>
        </div>

        <div class="server-edit">
          <details>
            <summary style="cursor:pointer;font-weight:800;color:#c9d5e8">✏️ ویرایش اطلاعات سرور</summary>
            <form method="post" style="margin-top:16px">
              <input type="hidden" name="csrf_token" value="<?=h($csrf)"><input type="hidden" name="action" value="update_server"><input type="hidden" name="id" value="<?=h((string)$s['id'])">
              <div class="grid">
                <div class="field"><label>نام سرور</label><input name="name" required maxlength="80" value="<?=h((string)$s['name'])"></div>
                <div class="field"><label>IP / Host</label><input name="host" required value="<?=h((string)$s['host'])"></div>
                <div class="field"><label>پورت Web Panel</label><select name="port"><option value="80" <?=((int)$s['port']===80?'selected':'')?>>80 — HTTP</option><option value="443" <?=((int)$s['port']===443?'selected':'')?>>443 — HTTPS</option></select></div>
                <div class="field"><label>ISP Name</label><input name="isp_name" value="<?=h((string)$s['isp_name'])"></div>
                <div class="field"><label>Admin Username جدید</label><input name="username" autocomplete="off" placeholder="خالی = بدون تغییر"><div class="hint">Username فعلی نمایش داده نمی‌شود.</div></div>
                <div class="field"><label>Admin Password جدید</label><input type="password" name="password" autocomplete="new-password" placeholder="خالی = بدون تغییر"><div class="hint">رمز فعلی هرگز نمایش داده نمی‌شود.</div></div>
              </div>
              <div class="actions"><button class="btn" type="submit">تست و ذخیره تغییرات</button></div>
            </form>
          </details>
        </div>
      </article>
    <?php endforeach; endif; ?>
  </section>

  <div class="footer">RouteBox Telegram Bot · IBSng A1.24 Web Panel Adapter · Created by Amir Taheri</div>
</main>
</div>
<script>
(function(){
  var html=document.documentElement;
  var body=document.body;
  var walker=document.createTreeWalker(body,NodeFilter.SHOW_TEXT);
  var nodes=[];
  while(walker.nextNode()) nodes.push(walker.currentNode);
  nodes.forEach(function(n){ if(n.nodeValue && (/^\s*\\n\s*$/).test(n.nodeValue)) n.nodeValue=''; });
})();
</script>
</body>
</html>
<?php
$html = (string)ob_get_clean();
$html = preg_replace('/^(?:\s*(?:\\n|\/n)\s*)+/u', '', $html) ?? $html;
echo $html;
