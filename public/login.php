<?php
require __DIR__.'/../src/bootstrap.php';
if(!empty($_SESSION['admin'])){header('Location:/');exit;}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        if(hash_equals((string)$config['admin_user'],(string)($_POST['user']??''))&&password_verify((string)($_POST['password']??''),admin_password_hash())){
            session_regenerate_id(true);
            $_SESSION['admin']=true;
            $_SESSION['csrf_token']=bin2hex(random_bytes(32));
            header('Location:/');exit;
        }
        $err='Invalid username or password.';
    }catch(Throwable $e){
        http_response_code(403);
        $err='Invalid security token. Reload the page and try again.';
    }
}
?><!doctype html><html lang="en" dir="ltr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><style>body{background:#0b1120;color:#fff;font-family:sans-serif;display:grid;place-items:center;height:100vh}.box{width:min(360px,90%);background:#111827;padding:25px;border-radius:18px}input,button{box-sizing:border-box;width:100%;padding:12px;margin:7px 0;border-radius:9px;border:1px solid #374151}input{background:#0f172a;color:#fff}button{background:#2563eb;color:#fff;border:0}.err{color:#fb7185}</style><div class="box"><h2>🚀 RouteBox Telegram Bot</h2><div>🛡️ Administrator Login</div><?php if($err):?><p class="err"><?=h($err)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input name="user" placeholder="Username" required><input name="password" type="password" placeholder="Password" required><button type="submit">Sign in</button></form></div>