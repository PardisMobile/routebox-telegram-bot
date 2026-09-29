<?php

declare(strict_types=1);

$configFile=__DIR__.'/../config/config.php';
if(!is_file($configFile)||!is_readable($configFile)){
    http_response_code(503);
    $reason=!is_file($configFile)?'configuration file is missing':'configuration file is not readable by the web-service user';
    error_log('RouteBox Telegram Bot bootstrap: '.$reason.' at '.$configFile);
    exit('RouteBox Telegram Bot is not initialized: '.$reason.'. Re-run install.sh or check the web-service permissions.');
}
$config=require $configFile;
if(!is_array($config)||!isset($config['db'],$config['app_key'])){http_response_code(503);exit('RouteBox Telegram Bot configuration is invalid. Re-run install.sh.');}
date_default_timezone_set($config['timezone']??'UTC');
if(session_status()!==PHP_SESSION_ACTIVE&&PHP_SAPI!=='cli'){session_name($config['security']['session_name']??'rbt_session');session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>(bool)($config['security']['cookie_secure']??false)]);}
$pdo=new PDO('sqlite:'.$config['db']);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('PRAGMA foreign_keys=ON');
function db():PDO{global $pdo;return $pdo;} function app_config():array{global $config;return $config;} function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function enc(string $plain):string{$key=base64_decode(app_config()['app_key'],true);if(!$key||strlen($key)!==32)throw new RuntimeException('Invalid app key');$iv=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($iv.sodium_crypto_secretbox($plain,$iv,$key));}
function dec(string $cipher):string{$key=base64_decode(app_config()['app_key'],true);$raw=base64_decode($cipher,true);if(!$key||strlen($key)!==32||!$raw)throw new RuntimeException('Invalid secret');$iv=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$iv,$key)?:'';}
function log_event(string $level,string $message):void{$s=db()->prepare('INSERT INTO logs(level,message,created_at)VALUES(?,?,?)');$s->execute([$level,$message,time()]);}
function admin_password_hash():string{
    $stored=db()->query("SELECT value FROM settings WHERE key='admin_password_hash'")->fetchColumn();
    if(is_string($stored)&&$stored!=='') return $stored;
    return (string)($GLOBALS['config']['admin_password_hash']??'');
}
function set_admin_password_hash(string $hash):void{
    db()->prepare("INSERT INTO settings(key,value) VALUES('admin_password_hash',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([$hash]);
}
function require_admin():void{if(empty($_SESSION['admin'])){header('Location:/login.php');exit;}}
function csrf_token():string{if(empty($_SESSION['csrf_token'])){$_SESSION['csrf_token']=bin2hex(random_bytes(32));}return (string)$_SESSION['csrf_token'];}
function verify_csrf():void{$token=(string)($_POST['csrf_token']??'');$expected=(string)($_SESSION['csrf_token']??'');if($expected===''||$token===''||!hash_equals($expected,$token)){http_response_code(403);exit('Invalid CSRF token.');}}
function json_response($data,int $status=200):never{http_response_code($status);header('Content-Type:application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
