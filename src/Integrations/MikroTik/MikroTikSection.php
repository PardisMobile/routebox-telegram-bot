<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

final class MikroTikSection
{
    public static function render(array $data, string $csrf): string
    {
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $flash=(string)($data['flash']??'');
        $error=(bool)($data['error']??false);
        $serversHtml='';
        foreach(($data['servers']??[]) as $s){
            $router=$s['router']??[];
            $status=!empty($s['discovery_error'])?'Connection issue':'Connected';
            $statusClass=!empty($s['discovery_error'])?'bad':'ok';
            $identity=(string)($router['identity']??$s['name']??'—');
            $version=(string)($router['version']??'—');
            $uptime=(string)($router['uptime']??'—');
            $cpu=(string)($router['cpu_load']??'—');
            $memory=(string)($router['memory']??'—');
            $latency=$s['latency_ms']===null?'—':((string)$s['latency_ms'].' ms');
            $interfaces=$s['interfaces']??[];
            $pools=$s['pools']??[];
            $serverId=(int)$s['id'];
            $serversHtml.='<article class="server-card">'
                .'<div class="server-card-head"><div><div class="server-title">'.$h($identity).'</div><div class="server-sub">'.$h((string)$s['host']).':'.$h((string)$s['api_port']).'</div></div><span class="status '.$statusClass.'">'.$h($status).'</span></div>'
                .'<div class="server-meta"><span>RouterOS '.$h($version).'</span><span>Uptime '.$h($uptime).'</span><span>CPU '.$h($cpu).'</span><span>Memory '.$h($memory).'</span><span>Ping '.$h($latency).'</span></div>'
                .'<div class="server-meta"><span>WireGuard: '.count($interfaces).'</span><span>VPN port '.((int)$s['vpn_port']>0?$h((string)$s['vpn_port']):'Auto-detect').'</span><span>Interface '.$h((string)$s['interface_name']).'</span><span>Pool '.$h((string)$s['pool_name']).'</span></div>'
                .'<form method="post" style="margin-top:12px"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="'.$serverId.'"><button class="btn btn-secondary" type="submit">Test Connection</button></form>'
                .self::serverForm($s,$csrf,true)
                .'</article>';
        }
        if($serversHtml==='')$serversHtml='<div class="help">No MikroTik servers configured yet.</div>';

        $plans='';
        foreach(($data['plans']??[]) as $p){
            $plans.='<tr><td>'.$h((string)$p['display_name_fa']).'</td><td>'.$h((string)$p['display_name_en']).'</td><td>'.$h((string)($p['server_name']??'—')).'</td><td>'.$h((string)$p['duration_days']).'</td><td>'.$h((string)$p['price_minor']).'</td><td>'.(!empty($p['enabled'])?'Active':'Disabled').'</td><td><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button class="btn btn-danger" type="submit">Disable</button></form></td></tr>';
        }
        if($plans==='')$plans='<tr><td colspan="7" class="help">No MikroTik WireGuard plans yet.</td></tr>';

        $peers='';
        foreach(($data['peers']??[]) as $p){
            $action=$p['status']==='active'?'disable_peer':'enable_peer';
            $label=$p['status']==='active'?'Disable':'Enable';
            $peers.='<tr><td>'.$h((string)$p['username']).'</td><td dir="ltr">'.$h((string)$p['assigned_ip']).'</td><td>'.$h((string)($p['server_name']??'—')).'</td><td>'.$h((string)$p['interface_name']).'</td><td>'.$h((string)$p['status']).'</td><td>'.(!empty($p['expires_at'])?date('Y-m-d H:i',(int)$p['expires_at']):'—').'</td><td><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button class="btn btn-secondary" type="submit">'.$label.'</button></form></td></tr>';
        }
        if($peers==='')$peers='<tr><td colspan="7" class="help">No RouteBox MikroTik peers yet.</td></tr>';

        $addServer='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>Add MikroTik Server</h2><p>RouterOS REST API connection. No container is required on the MikroTik device.</p></div></div></div>'.self::serverForm([], $csrf, false).'</section>';
        $planForm='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◫</div><div><h2>WireGuard Plans</h2><p>Plans use the shared RouteBox service catalog.</p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="add_plan"><div class="grid"><div class="field"><label>Server</label><select name="server_id" required>'.implode('',array_map(static fn(array $s):string=>'<option value="'.(int)$s['id'].'">'.$h((string)$s['name']).'</option>',$data['servers']??[])).'</select></div><div class="field"><label>Persian name</label><input name="display_name_fa" required placeholder="یک ماهه"></div><div class="field"><label>English name</label><input name="display_name_en" required placeholder="One Month"></div><div class="field"><label>Price (minor currency)</label><input type="number" name="price_minor" min="0" value="0"></div><div class="field"><label>Duration days</label><input type="number" name="duration_days" min="0" value="30"></div><div class="field"><label>Quota GB</label><input type="number" step="0.01" name="quota_gb" min="0" value="0"></div><div class="field"><label>Upload limit</label><input name="upload_limit" placeholder="10M"></div><div class="field"><label>Download limit</label><input name="download_limit" placeholder="50M"></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">+ Add plan</button></div></form><div style="margin-top:16px">'.$plans.'</div></section>';

        $peerTable='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◌</div><div><h2>WireGuard Peers</h2><p>Each peer is a RouteBox account. Traffic counters come directly from RouterOS.</p></div></div></div><div style="overflow:auto"><table style="width:100%"><thead><tr><th>Username</th><th>IP</th><th>Server</th><th>Interface</th><th>Status</th><th>Expiry</th><th>Action</th></tr></thead><tbody>'.$peers.'</tbody></table></div></section>';

        $guide='<section class="card"><details><summary style="cursor:pointer;font-weight:800">⚙ MikroTik Setup Guide</summary><div style="margin-top:14px"><p class="help">Configure RouterOS REST access, create the RouteBox user and verify the connection before adding the server.</p><ol style="line-height:1.9;padding-inline-start:22px"><li>Enable <code>www</code> for temporary HTTP testing, or preferably <code>www-ssl</code> with a valid certificate for production.</li><li>Create a dedicated RouterOS user with <code>rest-api</code> plus only the permissions RouteBox needs.</li><li>Allow the REST port from the RouteBox server IP in the firewall.</li><li>In RouteBox enter the router IP/hostname, REST port, username and password. For temporary HTTP testing disable the TLS checkbox and use port 80.</li><li>VPN Endpoint is the public hostname/IP used by WireGuard clients. Leave <strong>WireGuard Port</strong> blank and RouteBox will automatically read the WireGuard interface <code>listen-port</code> from RouterOS.</li><li>Use <strong>Test Connection</strong>. The panel will display RouterOS version, uptime, CPU, memory and REST latency.</li></ol><p class="help">HTTP REST sends credentials without transport encryption. Use it only for controlled testing; production should use HTTPS/TLS.</p></div></details></section>';

        return $flash.$addServer.$guide.'<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2>MikroTik Servers</h2><p>RouterOS version, uptime, CPU, memory, REST latency, WireGuard interfaces, IP pools and DNS are discovered from the router.</p></div></div></div>'.$serversHtml.'</section>'.$planForm.$peerTable;
    }

    private static function serverForm(array $s,string $csrf,bool $edit): string
    {
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $action=$edit?'update_server':'add_server'; $id=$edit?(int)$s['id']:0;
        $vpnPort=(int)($s['vpn_port']??0);
        $vpnValue=$vpnPort>0?(string)$vpnPort:'';
        return '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'"><div class="grid">'
            .'<div class="field"><label>Server Name</label><input name="name" required value="'.$h((string)($s['name']??'')).'"></div>'
            .'<div class="field"><label>IP / Hostname</label><input name="host" required dir="ltr" value="'.$h((string)($s['host']??'')).'"></div>'
            .'<div class="field"><label>REST API Port</label><input type="number" name="api_port" min="1" max="65535" value="'.(int)($s['api_port']??443).'"></div>'
            .'<div class="field"><label>API Username</label><input name="username" required value="'.$h((string)($s['username']??'')).'"></div>'
            .'<div class="field"><label>API Password</label><input type="password" name="password" '.($edit?'placeholder="Leave blank to keep current password"':'required').' value=""></div>'
            .'<div class="field"><label><input type="checkbox" name="tls_mode" value="1" '.(!empty($s['tls_mode'])||(!$edit && (int)($s['api_port']??443)===443)?'checked':'').'> Use HTTPS / TLS</label></div>'
            .'<div class="field"><label>VPN Endpoint</label><input name="vpn_endpoint" dir="ltr" value="'.$h((string)($s['vpn_endpoint']??'')).'"></div>'
            .'<div class="field"><label>WireGuard Port</label><input type="number" name="vpn_port" min="1" max="65535" value="'.$h($vpnValue).'" placeholder="Auto-detect from RouterOS"></div>'
            .'<div class="field"><label>WireGuard Interface</label><input name="interface_name" value="'.$h((string)($s['interface_name']??'')).'"></div>'
            .'<div class="field"><label>IP Pool</label><input name="pool_name" value="'.$h((string)($s['pool_name']??'')).'"></div>'
            .'<div class="field"><label>DNS Servers</label><input name="dns_servers" dir="ltr" value="'.$h((string)($s['dns_servers']??'')).'" placeholder="9.9.9.9,1.1.1.1"></div>'
            .'</div><div class="form-actions"><button class="btn btn-primary" type="submit">'.($edit?'Save & Test':'Connect & Save').'</button></div></form>';
    }
}
