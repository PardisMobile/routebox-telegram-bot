<?php
require __DIR__.'/../src/bootstrap.php';
require_admin();

$ok=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $current=(string)($_POST['current_password']??'');
        $new=(string)($_POST['new_password']??'');
        $confirm=(string)($_POST['confirm_password']??'');
        if(!password_verify($current,admin_password_hash())) throw new RuntimeException('Current password is incorrect.');
        if(strlen($new)<8) throw new RuntimeException('New password must be at least 8 characters.');
        if($new!==$confirm) throw new RuntimeException('New passwords do not match.');
        set_admin_password_hash(password_hash($new,PASSWORD_DEFAULT));
        session_regenerate_id(true);
        $_SESSION['admin']=true;
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
        log_event('info','Administrator password changed from the web panel.');
        $ok='✅ Password changed successfully. Save the new password securely.';
    }catch(Throwable $e){$err=$e->getMessage();}
}
?><!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Security</title><style>:root{color-scheme:dark}body{margin:0;background:#070b14;color:#e5e7eb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif}.wrap{max-width:520px;margin:8vh auto;padding:18px}.card{background:linear-gradient(145deg,#111827,#0d1422);border:1px solid #263247;border-radius:20px;padding:26px;box-shadow:0 10px 35px rgba(0,0,0,.2)}h1{margin-top:0}label{display:block;color:#cbd5e1;margin:15px 0 6px}input,button{box-sizing:border-box;width:100%;padding:12px 13px;border-radius:11px;border:1px solid #344156;background:#09111f;color:#fff}button{margin-top:18px;background:#2563eb;border:0;font-weight:700;cursor:pointer}.msg{padding:12px;border-radius:10px;margin:12px 0}.ok{background:#064e3b;color:#a7f3d0}.err{background:#4c0519;color:#fecdd3}.muted{color:#94a3b8;font-size:13px}a{color:#93c5fd;text-decoration:none}</style></head><body><div class="wrap"><div class="card"><h1>🔐 Admin Security</h1><p class="muted">Change the password used for the RouteBox Telegram Bot admin panel.</p><?php if($ok):?><div class="msg ok"><?=h($ok)?></div><?php endif;?><?php if($err):?><div class="msg err">❌ <?=h($err)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required><label>New password</label><input type="password" name="new_password" autocomplete="new-password" minlength="8" required><label>Confirm new password</label><input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required><button type="submit">🔑 Change password</button></form><p class="muted">Minimum 8 characters. The password is stored only as a secure password hash.</p><p><a href="/">← Back to dashboard</a></p></div></div></body></html>
