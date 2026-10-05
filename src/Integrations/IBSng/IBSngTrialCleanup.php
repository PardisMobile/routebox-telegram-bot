<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use RuntimeException;

/** Cleanup-only adapter for expired ATD trials. Normal IBSng provisioning is untouched. */
final class IBSngTrialCleanup
{
    public static function deleteUser(array $server,string $userId): void
    {
        if($userId===''||!ctype_digit($userId))throw new RuntimeException('IBSng provider reference is invalid.');
        $host=trim((string)($server['host']??''));$name=dec((string)($server['admin_user_enc']??''));$pass=dec((string)($server['admin_pass_enc']??''));
        if($host===''||$name===''||$pass==='')throw new RuntimeException('IBSng cleanup credentials are incomplete.');
        $url='http://'.$host.':1237/';
        $session=self::rpc($url,'login.login',['auth_remoteaddr'=>'127.0.0.1','auth_type'=>'ANONYMOUS','auth_name'=>'ANONYMOUS','auth_pass'=>'ANONYMOUS','login_auth_name'=>$name,'login_auth_pass'=>$pass,'create_session'=>true,'login_auth_type'=>'ADMIN']);
        if(!is_string($session)||$session==='')throw new RuntimeException('IBSng cleanup login failed.');
        self::rpc($url,'user.delUser',['auth_remoteaddr'=>'127.0.0.1','auth_type'=>'ADMIN','auth_name'=>$name,'auth_pass'=>$pass,'auth_session'=>$session,'user_id'=>$userId,'del_connection_logs'=>true,'del_audit_logs'=>true,'delete_comment'=>'ATD expired trial cleanup']);
    }
    private static function rpc(string $url,string $method,array $params):mixed
    {
        $payload=json_encode(['id'=>null,'method'=>$method,'params'=>$params],JSON_UNESCAPED_SLASHES);$ch=curl_init($url);if($ch===false)throw new RuntimeException('IBSng JSON-RPC initialization failed.');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20]);$raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($raw===false||$code<200||$code>=400)throw new RuntimeException('IBSng JSON-RPC request failed: '.($err?:('HTTP '.$code)));$json=json_decode((string)$raw,true);if(!is_array($json))throw new RuntimeException('IBSng JSON-RPC returned invalid JSON.');if(($json['error']??null)!==null)throw new RuntimeException('IBSng delete failed: '.json_encode($json['error'],JSON_UNESCAPED_UNICODE));return $json['result']??null;
    }
}
