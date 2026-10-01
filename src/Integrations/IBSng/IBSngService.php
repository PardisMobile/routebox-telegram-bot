<?php

declare(strict_types=1);

namespace RouteBox\Integrations\IBSng;

use PDO;
use RuntimeException;

final class IBSngService
{
    public function __construct(private readonly PDO $db) { IBSngSchema::ensure($db); }
    public function server(int $id): array { $q=$this->db->prepare('SELECT * FROM ibsng_servers WHERE id=?'); $q->execute([$id]); $s=$q->fetch(PDO::FETCH_ASSOC); if(!$s) throw new RuntimeException('IBSng server not found.'); return $s; }
    public function client(int $id): IBSngClient { $s=$this->server($id); return new IBSngClient((string)$s['host'],dec((string)$s['admin_user_enc']),dec((string)$s['admin_pass_enc']),(int)$s['port']); }
    public function test(int $id): array { $groups=$this->client($id)->listGroups(); $this->db->prepare('UPDATE ibsng_servers SET last_test_at=?,last_error=NULL WHERE id=?')->execute([time(),$id]); return ['ok'=>true,'groups'=>$groups]; }
    public function syncGroups(int $id): int { $c=$this->client($id); $groups=$c->listGroups(); $now=time(); $st=$this->db->prepare('INSERT INTO ibsng_groups(ibsng_server_id,group_name,group_id,group_info_json,enabled,synced_at) VALUES(?,?,?,?,1,?) ON CONFLICT(ibsng_server_id,group_name) DO UPDATE SET group_id=excluded.group_id,group_info_json=excluded.group_info_json,synced_at=excluded.synced_at'); foreach($groups as $name){$info=$c->getGroupInfo((string)$name);$gid=isset($info['group_id'])&&is_numeric($info['group_id'])?(int)$info['group_id']:null;$st->execute([$id,(string)$name,$gid,json_encode($info,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$now]);} return count($groups); }
    public function createAccount(int $serverId,string $ispName,string $groupName,string $username,string $password,int $credit=0): array { $c=$this->client($serverId); $created=$c->createUser($ispName,$groupName,$credit); $ids=$created['user_ids']??$created['users']??$created; $userId=is_array($ids)?reset($ids):$ids; if($userId===false||$userId===null||$userId==='') throw new RuntimeException('IBSng did not return a user id.'); $c->setUserCredentials((string)$userId,$username,$password); return ['user_id'=>(string)$userId,'username'=>$username,'password'=>$password,'group'=>$groupName]; }
    public function userInfo(int $serverId,string $username): array { return $this->client($serverId)->getUserInfoByUsername($username); }
    public function renew(int $serverId,string $userId,string $comment=''): mixed { return $this->client($serverId)->renewUser($userId,$comment); }
    public function changeGroup(int $serverId,string $userId,string $group): mixed { return $this->client($serverId)->changeUserGroup($userId,$group); }
    public function savePlan(array $data): int { $category=(int)$this->db->query("SELECT id FROM service_categories WHERE service_key='ibsng'")->fetchColumn(); if(!$category) throw new RuntimeException('IBSng category is missing.'); $now=time(); $st=$this->db->prepare('INSERT INTO service_plans(category_id,provider_key,provider_server_id,provider_plan_key,display_name_fa,display_name_en,price_minor,duration_days,quota_gb,enabled,sort_order,metadata_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'); $st->execute([$category,'ibsng',(int)$data['server_id'],(string)$data['group_name'],(string)$data['name_fa'],(string)$data['name_en'],(int)$data['price'],(int)$data['days'],(float)$data['quota'],1,(int)$data['sort_order'],'{}',$now,$now]); return (int)$this->db->lastInsertId(); }
}
