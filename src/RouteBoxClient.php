<?php
final class RouteBoxClient{
 private string $base,$user,$pass; private bool $verify;
 function __construct(string $base,string $user,string $pass,bool $verify=true){$this->base=rtrim($base,'/');$this->user=$user;$this->pass=$pass;$this->verify=$verify;}
 private function req(string $method,string $path,?array $body=null):array{$ch=curl_init($this->base.$path);$headers=['Accept: application/json'];curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$this->user.':'.$this->pass,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>$this->verify,CURLOPT_SSL_VERIFYHOST=>$this->verify?2:0]);if($body!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_UNICODE));curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);} $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false)throw new RuntimeException('RouteBox connection failed: '.$err);$json=json_decode($raw,true);if($code<200||$code>=300)throw new RuntimeException('RouteBox HTTP '.$code.': '.($json['error']??$json['message']??trim($raw)));return ['code'=>$code,'json'=>$json,'raw'=>$raw];}
 function status():array{return $this->req('GET','/api/status')['json'];}
 function peers():array{$x=$this->req('GET','/api/awg/peers')['json'];return is_array($x)?$x:[];}
 function createPeer(string $name):array{return $this->req('POST','/api/awg/peers',['name'=>$name])['json'];}
 function config(string $publicKey):string{return $this->req('GET','/api/awg/peers/'.rawurlencode($publicKey).'/config')['raw'];}
 function setExpiry(string $publicKey,int $expiresAt):array{return $this->req('PATCH','/api/awg/peers/'.rawurlencode($publicKey).'/expiry',['expires_at'=>$expiresAt])['json'];}
 function deletePeer(string $publicKey):void{$this->req('DELETE','/api/awg/peers/'.rawurlencode($publicKey));}
}
