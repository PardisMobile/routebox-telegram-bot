<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

/** Renders MikroTik inside the existing RouteBox Admin shell. */
final class MikroTikSection
{
    public static function navIcon(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="10" rx="2"></rect><path d="M7 17v2"></path><path d="M17 17v2"></path><circle cx="8" cy="12" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="16" cy="12" r="1"></circle></svg>';
    }
    public static function navLabel(string $lang): string { return 'MikroTik WireGuard'; }

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

        $formatBytes = static function ($bytes): string {
            $bytes = max(0, (float)$bytes);
            if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
            if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
            if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
            return number_format($bytes, 0) . ' B';
        };

        $serverCards = '';
        foreach (($data['servers'] ?? []) as $s) {
            $id=(int)$s['id'];
            $interfaces=is_array($s['interfaces']??null)?$s['interfaces']:[];
            $pools=is_array($s['pools']??null)?$s['pools']:[];
            $router=is_array($s['router']??null)?$s['router']:[];
            $serverName=(string)($s['name'] ?? 'MikroTik');
            $identity=(string)($router['identity'] ?? '—');
            $version=(string)($router['version'] ?? '—');
            $uptime=(string)($router['uptime'] ?? '—');
            $cpu=(string)($router['cpu-load'] ?? '—');
            $totalMemory=(float)($router['total-memory'] ?? 0);
            $freeMemory=(float)($router['free-memory'] ?? 0);
            $usedMemory=max(0, $totalMemory-$freeMemory);
            $memoryText=$totalMemory>0 ? $formatBytes($usedMemory).' / '.$formatBytes($totalMemory) : '—';
            $latency=$s['latency_ms'] !== null ? number_format((float)$s['latency_ms'],1).' ms' : '—';
            $status=!empty($s['discovery_error'])?'Connection issue':'Connected';
            $statusDot=!empty($s['discovery_error'])?'var(--red)':'var(--green)';
            $serverCards .= '<article class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2>' . $h($serverName) . '</h2><p dir="ltr">' . $h((string)$s['host']) . ':' . $h((string)$s['api_port']) . ' · ' . (!empty($s['tls_mode'])?'HTTPS REST':'HTTP REST') . '</p></div></div><span class="status"><span class="dot" style="background:'.$statusDot.'"></span>'.$h($status).'</span></div>'
                . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:14px 0">'
                . '<div style="padding:12px;border:1px solid var(--line);border-radius:12px"><div class="help">Identity</div><strong>'.$h($identity).'</strong></div>'
                . '<div style="padding:12px;border:1px solid var(--line);border-radius:12px"><div class="help">Server / RouterOS</div><strong>'.$h($version).'</strong></div>'
                . '<div style="padding:12px;border:1px solid var(--line);border-radius:12px"><div class="help">Uptime</div><strong dir="ltr">'.$h($uptime).'</strong></div>'
                . '<div style="padding:12px;border:1px solid var(--line);border-radius:12px"><div class="help">CPU Load</div><strong dir="ltr">'.$h($cpu).'%</strong></div>'
                . '<div style="padding:12px;border:1px solid var(--line);border-radius:12px"><div class="help">Memory</div><strong dir="ltr">'.$h($memoryText).'</strong></div>'
                . '<div style="padding:12px;border:1px solid var(--line);border-radius:12px"><div class="help">Ping / REST latency</div><strong dir="ltr">'.$h($latency).'</strong></div>'
                . '</div>'
                . '<div class="grid">'
                . '<div><div class="help">Architecture / Board</div><strong>' . $h((string)($router['architecture-name'] ?? '—')) . ' · ' . $h((string)($router['board-name'] ?? '—')) . '</strong></div>'
                . '<div><div class="help">WireGuard interfaces</div><code>' . $h(implode(', ', array_map(static fn(array $x): string => (string)($x['name']??''), $interfaces))) . '</code></div>'
                . '<div><div class="help">IP pools</div><code>' . $h(implode(', ', array_map(static fn(array $x): string => (string)($x['name']??''), $pools))) . '</code></div>'
                . '<div><div class="help">DNS</div><code>' . $h((string)($s['dns'] ?: $s['dns_servers'])) . '</code></div>'
                . '</div>'
                . (!empty($s['discovery_error']) ? '<div class="help err" style="margin-top:12px">'.$h((string)$s['discovery_error']).'</div>' : '')
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
            $peers.='<tr><td><strong>'.$h((string)$p['username']).'</strong></td><td dir="ltr"><code>'.$h((string)$p['assigned_ip']).'</code></td><td>'.$h((string)$p['server_name']).'</td><td>'.$h((string)$p['interface_name']).'</td><td>'.$h((string)$p['status']).'</td><td>'.$h((string)($p['expires_at'] ?? '—')).'</td><td><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button class="btn btn-secondary" type="submit">'.$label.'</button></form></td></tr>';
        }
        if($peers==='')$peers='<tr><td colspan="7" class="help">No RouteBox MikroTik peers yet.</td></tr>';

        $addServer='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>Add MikroTik Server</h2><p>RouterOS REST API connection. No container is required on the MikroTik device.</p></div></div></div>'.self::serverForm([], $csrf, false).'</section>';
        $planForm='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◫</div><div><h2>WireGuard Plans</h2><p>Plans use the shared RouteBox service catalog.</p></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="add_plan"><div class="grid"><div class="field"><label>Server</label><select name="server_id" required>'.implode('',array_map(static fn(array $s):string=>'<option value="'.(int)$s['id'].'">'.$h((string)$s['name']).'</option>',$data['servers']??[])).'</select></div><div class="field"><label>Persian name</label><input name="display_name_fa" required placeholder="یک ماهه"></div><div class="field"><label>English name</label><input name="display_name_en" required placeholder="One Month"></div><div class="field"><label>Price (minor currency)</label><input type="number" name="price_minor" min="0" value="0"></div><div class="field"><label>Duration days</label><input type="number" name="duration_days" min="0" value="30"></div><div class="field"><label>Quota GB</label><input type="number" step="0.01" name="quota_gb" min="0" value="0"></div><div class="field"><label>Upload limit</label><input name="upload_limit" placeholder="10M"></div><div class="field"><label>Download limit</label><input name="download_limit" placeholder="50M"></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">+ Add plan</button></div></form><div style="margin-top:16px">'.$plans.'</div></section>';

        $peerTable='<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◌</div><div><h2>WireGuard Peers</h2><p>Each peer is a RouteBox account. Traffic counters come directly from RouterOS.</p></div></div></div><div style="overflow:auto"><table style="width:100%"><thead><tr><th>Username</th><th>IP</th><th>Server</th><th>Interface</th><th>Status</th><th>Expiry</th><th>Action</th></tr></thead><tbody>'.$peers.'</tbody></table></div></section>';

        $guide='<section class="card"><details><summary style="cursor:pointer;font-weight:800">⚙ MikroTik Setup Guide</summary><div style="margin-top:14px"><p class="help">Configure RouterOS REST access, create the RouteBox user and verify the connection before adding the server.</p><ol style="line-height:1.9;padding-inline-start:22px"><li>Enable <code>www</code> for temporary HTTP testing, or preferably <code>www-ssl</code> with a valid certificate for production.</li><li>Create a dedicated RouterOS user with <code>rest-api</code> plus only the permissions RouteBox needs.</li><li>Allow the REST port from the RouteBox server IP in the firewall.</li><li>In RouteBox enter the router IP/hostname, REST port, username and password. For temporary HTTP testing disable the TLS checkbox and use port 80.</li><li>Use <strong>Test Connection</strong>. The panel will display RouterOS version, uptime, CPU, memory and REST latency.</li></ol><p class="help">HTTP REST sends credentials without transport encryption. Use it only for controlled testing; production should use HTTPS/TLS.</p></div></details></section>';

        return $flash.$addServer.$guide.'<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2>MikroTik Servers</h2><p>RouterOS version, uptime, CPU, memory, REST latency, WireGuard interfaces, IP pools and DNS are discovered from the router.</p></div></div></div>'.$serverCards.'</section>'.$planForm.$peerTable;
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
            .'<div class="field"><label>API Password</label><input type="password" name="password" '.($edit?'placeholder="Leave blank to keep current password"':'required').' value=""></div>'
            .'<div class="field"><label><input type="checkbox" name="tls_mode" value="1" '.(!empty($s['tls_mode'])||(!$edit && (int)($s['api_port']??443)===443)?'checked':'').'> Use HTTPS / TLS</label></div>'
            .'<div class="field"><label>VPN Endpoint</label><input name="vpn_endpoint" dir="ltr" value="'.$h((string)($s['vpn_endpoint']??'')).'"></div>'
            .'<div class="field"><label>WireGuard Port</label><input type="number" name="vpn_port" min="1" max="65535" value="'.(int)($s['vpn_port']??51820).'"></div>'
            .'<div class="field"><label>WireGuard Interface</label><input name="interface_name" value="'.$h((string)($s['interface_name']??'')).'"></div>'
            .'<div class="field"><label>IP Pool</label><input name="pool_name" value="'.$h((string)($s['pool_name']??'')).'"></div>'
            .'<div class="field"><label>DNS Servers</label><input name="dns_servers" dir="ltr" value="'.$h((string)($s['dns_servers']??'')).'" placeholder="9.9.9.9,1.1.1.1"></div>'
            .'</div><div class="form-actions"><button class="btn btn-primary" type="submit">'.($edit?'Save & Test':'Connect & Save').'</button></div></form>';
    }
}
