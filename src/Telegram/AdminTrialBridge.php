<?php

declare(strict_types=1);
namespace RouteBox\Telegram;
use RouteBox\Services\TrialService;
require_once __DIR__.'/AdminBot.php'; require_once __DIR__.'/../Services/TrialService.php';
final class AdminTrialBridge{
 public static function handle(string $token,int|string $chat,array $from,?array $callback):bool{
  $data=trim((string)($callback['data']??''));if($data!=='adm:menu'&&!str_starts_with($data,'admtrial:'))return false;$adminId=(string)($from['id']??'');if($adminId===''||AdminBot::admin($adminId)===null){\tg($token,'sendMessage',['chat_id'=>$chat,'text'=>'❌ Unauthorized.']);return true;}\tg($token,'answerCallbackQuery',['callback_query_id'=>(string)($callback['id']??'')]);
  try{
   if($data==='adm:menu'){\tg($token,'sendMessage',['chat_id'=>$chat,'text'=>'🛡️ ATD Panel Admin\n\nیک عملیات را انتخاب کنید:','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'👥 Users','callback_data'=>'adm:users'],['text'=>'🛠 Services','callback_data'=>'adm:services']],[['text'=>'💳 Payments','callback_data'=>'adm:payments']],[['text'=>'➕ RouteBox بدون پرداخت','callback_data'=>'adm:create:routebox']],[['text'=>'➕ IBSng بدون پرداخت','callback_data'=>'adm:create:ibsng']],[['text'=>'🎁 Trials','callback_data'=>'admtrial:list']],[['text'=>'📜 Audit Log','callback_data'=>'adm:audit']],[['text'=>'👤 Customer Menu','callback_data'=>'adm:customer']]],],JSON_UNESCAPED_UNICODE)]);return true;}
   if($data==='admtrial:list'){ $rows=TrialService::listTrials(\db());$text="🎁 Trials\n\n";$k=[];foreach($rows as $r){$expired=$r['expires_at']!==null&&(int)$r['expires_at']<=time()&&$r['status']!=='deleted';$name=trim((string)($r['first_name']??''))?:((string)($r['username']??'')!==''?'@'.$r['username']:(string)$r['telegram_id']);$text.='#'.(int)$r['id'].' · '.$name.' · '.$r['provider_key'].' · '.$r['status'].' · '.($r['expires_at']?date('Y-m-d H:i',(int)$r['expires_at']):'—')."\n";if($expired)$k[]=[['text'=>'🗑 Delete expired #'.(int)$r['id'],'callback_data'=>'admtrial:delete:'.(int)$r['id']]];}if(!$rows)$text.='No trials.';$k[]=[['text'=>'↩️ Admin Menu','callback_data'=>'adm:menu']];\tg($token,'sendMessage',['chat_id'=>$chat,'text'=>$text,'reply_markup'=>json_encode(['inline_keyboard'=>$k],JSON_UNESCAPED_UNICODE)]);return true;}
   if(preg_match('/^admtrial:delete:(\d+)$/',$data,$m)){TrialService::deleteExpired(\db(),(int)$m[1]);\tg($token,'sendMessage',['chat_id'=>$chat,'text'=>'✅ Expired Trial cleaned up. The permanent trial claim was kept.']);return true;}
  }catch(\Throwable $e){\tg($token,'sendMessage',['chat_id'=>$chat,'text'=>'❌ '.$e->getMessage()]);}return true;
 }
}
