<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

/** Renders MikroTik inside the existing RouteBox Admin shell. */
final class MikroTikSection
{
    public static function navIcon(): string { return '◉'; }
    public static function navLabel(string $lang): string { return $lang === 'fa' ? 'MikroTik WireGuard' : 'MikroTik WireGuard'; }

    public static function handlePost(MikroTikAdmin $admin): void
    {
        $admin->handle($_POST, 'POST');
        header('Location: /?section=mikrotik');
        exit;
    }

    public static function render(MikroTikAdmin $admin, string $lang, string $csrf): string
    {
        $data = $admin->viewData();
        $fa = $lang === 'fa';
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flash = !empty($data['flash']) ? '<div class="flash ' . (!empty($data['error']) ? 'err' : '') . '">' . $h((string)$data['flash']) . '</div>' : '';

        $serverCards = '';
        foreach (($data['servers'] ?? []) as $s) {
            $id=(int)$s['id'];
            $interfaces=is_array($s['interfaces']??null)?$s['interfaces']:[];
            $pools=is_array($s['pools']??null)?$s['pools']:[];
            $serverCards .= '<article class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2>' . $h((string)$s['name']) . '</h2><p dir="ltr">' . $h((string)$s['host']) . ':' . $h((string)$s['api_port']) . ' · ' . (!empty($s['tls_mode'])?'HTTPS REST':'HTTP REST') . '</p></div></div></div>'
                . '<div class="grid">'
                . '<div><div class="help">RouterOS</div><strong>' . $h((string)($s['discovery_error'] ?? 'Connected / discovery available')) . '</strong></div>'
                . '<div><div class="help">WireGuard interfaces</div><code>' . $h(implode(', ', array_map(static fn(array $x):(string)($x['name']??''), $interfaces))) . '</code></div>'
                . '<div><div class="help">IP pools</div><code>' . $h(implode(', ', array_map(static fn(array $x):(string)($x['name']??''), $pools))) . '</code></div>'
                . '<div><div class="help">DNS</div><code>' . $h((string)($s['dns'] ?: $s['dns_servers'])) . '</code></div>'
                . '</div>'
                . '<div class="form-actions"><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="test_server"><input type="hidden" name="section" value="mikrotik"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-secondary" type="submit">↻ Test Connection</button></form>'
                . '<form method="post" onsubmit="return confirm(' . htmlspecialchars(json_encode($fa?'این سرور حذف شود؟':'Delete this MikroTik server?',JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8') . ')"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="delete_server"><input type="hidden" name="section" value="mikrotik"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-secondary" style="color:var(--red)" type="submit">× Delete</button></form></div>'
                . '<details style="margin-top:14px"><summary style="cursor:pointer;font-weight:750">✎ Edit server</summary>' . self::serverForm($s,$csrf,true) . '</details></article>';
        }
        if ($serverCards==='') $serverCards='<div class="empty">No MikroTik servers configured yet.</div>';

        $plans='';
        foreach (($data['plans']??[]) as $p) {
            $meta=json_decode((string)($p['metadata_json']??'{}'),true); if(!is_array($meta))$meta=[];
            $plans.='<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:center;margin-top:10px"><div><strong>'.$h((string)$p['display_name_fa']).'</strong><div class="help">'.$h((string)$p['display_name_en']).' · '.$h((string)$p['server_name']).'</div></div><div>'.$h((string)$p['duration_days']).' days</div><div>'.$h((string)$p['quota_gb']).' GB</div><div>'.$h((string)($meta['download_limit']??'')).'/'.$h((string)($meta['upload_limit']??'')).'</div><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button class="btn btn-secondary" style="color:var(--red)" type="submit">Disable</button></form></div>';
        }
        if($plans==='')$plans='<div class="help">No MikroTik plans configured.</div>';

        $peers='';
        foreach (($data['peers']??[]) as $p) {
            $action=$p['status']==='active'?'disable_peer':'enable_peer'; $label=$p['status']==='active'?'Disable':'Enable';
            $peers.='<tr><td><strong>'.$h((string)$p['username']).'</strong></td><td dir="ltr"><code>'.$h((string)$p['assigned_ip']).'</code></td><td>'.$h((string)$p['server_name']).'</td><td>'.$h((string)$p['interface_name']).'</td><td>'.$h((string)$p['status']).'</td><td>'.($p['expires_at']?(int)$p['expires_at']:'—').'</td><td><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button class="btn btn-secondary" type="submit">'.$label.'</button></form></td></tr>';
        }
        if($peers==='')$peers='<tr><td colspan="7" class="help">No RouteBox MikroTik peers yet.</td></tr>';

        $addServer='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>Add MikroTik Server</h2><p>RouterOS REST API connection. No container is required on the MikroTik device.</p></div></div></div>'.self::serverForm([], $csrf, false).'</section>';
        $planForm='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◫</div><div><h2>WireGuard Plans</h2><p>Plans use the shared RouteBox service catalog.</p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="add_plan"><div class="grid"><div class="field"><label>Server</label><select name="server_id" required>'.implode('',array_map(static fn(array $s):string=>'<option value="'.(int)$s['id'].'">'.$h((string)$s['name']).'</option>',$data['servers']??[])).'</select></div><div class="field"><label>Persian name</label><input name="display_name_fa" required placeholder="یک ماهه"></div><div class="field"><label>English name</label><input name="display_name_en" required placeholder="One Month"></div><div class="field"><label>Price (minor currency)</label><input type="number" name="price_minor" min="0" value="0"></div><div class="field"><label>Duration days</label><input type="number" name="duration_days" min="0" value="30"></div><div class="field"><label>Quota GB</label><input type="number" step="0.01" name="quota_gb" min="0" value="0"></div><div class="field"><label>Upload limit</label><input name="upload_limit" placeholder="10M"></div><div class="field"><label>Download limit</label><input name="download_limit" placeholder="50M"></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">+ Add plan</button></div></form><div style="margin-top:16px">'.$plans.'</div></section>';

        $peerTable='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◌</div><div><h2>WireGuard Peers</h2><p>Each peer is a RouteBox account. Traffic counters come directly from RouterOS.</p></div></div></div><div style="overflow:auto"><table style="width:100%"><thead><tr><th>Username</th><th>IP</th><th>Server</th><th>Interface</th><th>Status</th><th>Expiry</th><th>Action</th></tr></thead><tbody>'.$peers.'</tbody></table></div></section>';

        return $flash.$addServer.'<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2>MikroTik Servers</h2><p>RouterOS version, WireGuard interfaces, IP pools and DNS are discovered from the router.</p></div></div></div>'.$serverCards.'</section>'.$planForm.$peerTable;
    }

    private static function serverForm(array $s,string $csrf,bool $edit): string
    {
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $action=$edit?'update_server':'add_server'; $id=$edit?(int)$s['id']:0;
        return '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'"><div class="grid">'
            .'<div class="field"><label>Server Name</label><input name="name" required value="'.$h((string)($s['name']??'')).'"></div>'
            .'<div class="field"><label>IP / Hostname</label><input name="host" required dir="ltr" value="'.$h((string)($s['host']??'')).'"></div>'
            .'<div class="field"><label>REST API Port</label><input type="number" name="api_port" min="1" max="65535" value="'.(int)($s['api_port']??443).'"></div>'
            .'<div class="field"><label>API Username</label><input name="username" required value="'.$h((string)($s['username']??'')).'"></div>'
            .'<div class="field"><label>API Password</label><input type="password" name="password" autocomplete="new-password" placeholder="'.($edit?'unchanged if empty':'required').'"></div>'
            .'<div class="field"><label>VPN Endpoint</label><input name="vpn_endpoint" dir="ltr" placeholder="vpn.example.com" value="'.$h((string)($s['vpn_endpoint']??'')).'"></div>'
            .'<div class="field"><label>WireGuard Port</label><input type="number" name="vpn_port" value="'.(int)($s['vpn_port']??51820).'"></div>'
            .'<div class="field"><label>WireGuard Interface</label><input name="interface_name" dir="ltr" placeholder="wg1" value="'.$h((string)($s['interface_name']??'')).'"></div>'
            .'<div class="field"><label>IP Pool</label><input name="pool_name" dir="ltr" placeholder="wg-pool" value="'.$h((string)($s['pool_name']??'')).'"></div>'
            .'<div class="field"><label>DNS Servers</label><input name="dns_servers" dir="ltr" placeholder="1.1.1.1,8.8.8.8" value="'.$h((string)($s['dns_servers']??'')).'"></div>'
            .'</div><label class="switch"><input type="checkbox" name="tls_mode" value="1" '.(!isset($s['tls_mode'])||!empty($s['tls_mode'])?'checked':'').'><span>Use HTTPS / TLS for RouterOS REST API</span></label>'
            .'<p class="help">The API connection is separate from the WireGuard endpoint. Passwords are encrypted with the existing RouteBox application key.</p><div class="form-actions"><button class="btn btn-primary" type="submit">✓ '.($edit?'Save & Test':'Test Connection & Save').'</button></div></form>';
    }
}
