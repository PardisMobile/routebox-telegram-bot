<?php

declare(strict_types=1);

namespace RouteBox\Integrations\MikroTik;

final class MikroTikSection
{
    public static function navIcon(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="10" rx="2"></rect><path d="M7 17v2"></path><path d="M17 17v2"></path><circle cx="8" cy="12" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="16" cy="12" r="1"></circle></svg>';
    }

    public static function navLabel(string $lang): string
    {
        return 'MikroTik WireGuard';
    }

    public static function handlePost(MikroTikAdmin $admin): void
    {
        $admin->handle($_POST, 'POST');
        header('Location: /?section=mikrotik');
        exit;
    }

    public static function render(MikroTikAdmin $admin, string $lang, string $csrf): string
    {
        $data = $admin->viewData();
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flash = (string)($data['flash'] ?? '');
        $error = (bool)($data['error'] ?? false);
        $flashHtml = $flash !== '' ? '<div class="flash '.($error ? 'err' : '').'">'.$h($flash).'</div>' : '';

        $serversHtml = '';
        foreach (($data['servers'] ?? []) as $s) {
            $router = $s['router'] ?? [];
            $status = !empty($s['discovery_error']) ? 'Connection issue' : 'Connected';
            $statusClass = !empty($s['discovery_error']) ? 'bad' : 'ok';
            $identity = (string)($router['identity'] ?? $s['name'] ?? '—');
            $version = (string)($router['version'] ?? '—');
            $uptime = (string)($router['uptime'] ?? '—');
            $cpu = (string)($router['cpu_load'] ?? '—');
            $memory = (string)($router['memory'] ?? '—');
            $latency = $s['latency_ms'] === null ? '—' : ((string)$s['latency_ms'].' ms');
            $interfaces = is_array($s['interfaces'] ?? null) ? $s['interfaces'] : [];
            $serverId = (int)$s['id'];
            $editId = 'mikrotik-edit-'.$serverId;

            $serversHtml .= '<article class="server-card">'
                .'<div class="server-card-head">'
                    .'<div><div class="server-title">'.$h($identity).'</div><div class="server-sub" dir="ltr">'.$h((string)$s['host']).':'.$h((string)$s['api_port']).'</div></div>'
                    .'<span class="status '.$statusClass.'">'.$h($status).'</span>'
                .'</div>'
                .'<div class="server-meta">'
                    .'<span>RouterOS '.$h($version).'</span><span>Uptime '.$h($uptime).'</span><span>CPU '.$h($cpu).'</span><span>Memory '.$h($memory).'</span><span>Ping '.$h($latency).'</span>'
                .'</div>'
                .'<div class="server-meta">'
                    .'<span>WireGuard: '.count($interfaces).'</span><span>VPN port '.((int)$s['vpn_port'] > 0 ? $h((string)$s['vpn_port']) : 'Auto-detect').'</span><span>Interface '.$h((string)$s['interface_name']).'</span><span>Pool '.$h((string)$s['pool_name']).'</span>'
                .'</div>'
                .'<div class="form-actions" style="margin-top:14px">'
                    .'<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="'.$serverId.'"><button class="btn btn-secondary" type="submit">↻ Test Connection</button></form>'
                    .'<button class="btn btn-primary" type="button" onclick="document.getElementById(\''.$h($editId).'\').open=!document.getElementById(\''.$h($editId).'\').open">✎ Edit Server</button>'
                .'</div>'
                .'<details id="'.$h($editId).'" style="margin-top:12px"><summary style="cursor:pointer;font-weight:700">Server settings</summary>'.self::serverForm($s, $csrf, true).'</details>'
                .'</article>';
        }
        if ($serversHtml === '') $serversHtml = '<div class="help">No MikroTik servers configured yet.</div>';

        $planRows = '';
        foreach (($data['plans'] ?? []) as $p) {
            $id = (int)$p['id'];
            $meta = json_decode((string)($p['metadata_json'] ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];
            $upload = (string)($meta['upload_limit'] ?? '');
            $download = (string)($meta['download_limit'] ?? '');
            $planEditId = 'mikrotik-plan-edit-'.$id;
            $planRows .= '<tr>'
                .'<td>'.$h((string)$p['display_name_fa']).'</td>'
                .'<td>'.$h((string)$p['display_name_en']).'</td>'
                .'<td>'.$h((string)($p['server_name'] ?? '—')).'</td>'
                .'<td>'.$h((string)$p['duration_days']).' d</td>'
                .'<td>'.$h((string)$p['price_minor']).'</td>'
                .'<td>'.(!empty($p['enabled']) ? 'Active' : 'Disabled').'</td>'
                .'<td><div class="form-actions">'
                    .'<button class="btn btn-secondary" type="button" onclick="document.getElementById(\''.$h($planEditId).'\').open=!document.getElementById(\''.$h($planEditId).'\').open">✎ Edit</button>'
                    .'<form method="post" style="display:inline" onsubmit="return confirm(\'Delete this plan? Existing subscription history will be preserved.\')"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-danger" type="submit">⌫ Delete</button></form>'
                .'</div><details id="'.$h($planEditId).'" style="margin-top:10px">'
                    .'<summary style="cursor:pointer;font-weight:700">Edit plan</summary>'
                    .self::planForm($p, $data['servers'] ?? [], $csrf, true)
                .'</details></td>'
            .'</tr>';
        }
        if ($planRows === '') $planRows = '<tr><td colspan="7" class="help">No MikroTik WireGuard plans yet.</td></tr>';

        $peerRows = '';
        foreach (($data['peers'] ?? []) as $p) {
            $action = $p['status'] === 'active' ? 'disable_peer' : 'enable_peer';
            $label = $p['status'] === 'active' ? 'Disable' : 'Enable';
            $peerId = (int)$p['id'];
            $peerRows .= '<tr>'
                .'<td>'.$h((string)$p['username']).'</td><td dir="ltr">'.$h((string)$p['assigned_ip']).'</td><td>'.$h((string)($p['server_name'] ?? '—')).'</td><td>'.$h((string)$p['interface_name']).'</td><td>'.$h((string)$p['status']).'</td><td>'.(!empty($p['expires_at']) ? date('Y-m-d H:i',(int)$p['expires_at']) : '—').'</td>'
                .'<td><div class="form-actions">'
                    .'<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$peerId.'"><button class="btn btn-secondary" type="submit">'.$label.'</button></form>'
                    .'<form method="post" style="display:inline" onsubmit="return confirm(\'Delete this WireGuard peer? The peer will also be removed from RouterOS.\')"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="delete_peer"><input type="hidden" name="id" value="'.$peerId.'"><button class="btn btn-danger" type="submit">⌫ Delete</button></form>'
                .'</div></td>'
            .'</tr>';
        }
        if ($peerRows === '') $peerRows = '<tr><td colspan="7" class="help">No RouteBox MikroTik peers yet.</td></tr>';

        $addServer = '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">+</div><div><h2>Add MikroTik Server</h2><p>RouterOS REST API connection. No container is required on the MikroTik device.</p></div></div></div>'.self::serverForm([], $csrf, false).'</section>';

        $planForm = '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◫</div><div><h2>WireGuard Plans</h2><p>Create, edit, disable or delete plans without touching existing RouterOS peers.</p></div></div></div>'
            .self::planForm([], $data['servers'] ?? [], $csrf, false)
            .'<div style="overflow:auto;margin-top:18px"><table style="width:100%"><thead><tr><th>Persian</th><th>English</th><th>Server</th><th>Duration</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead><tbody>'.$planRows.'</tbody></table></div></section>';

        $peerTable = '<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◌</div><div><h2>WireGuard Peers</h2><p>Each peer is a RouteBox account. Disable keeps the peer; Delete removes it from RouterOS and RouteBox.</p></div></div></div><div style="overflow:auto"><table style="width:100%"><thead><tr><th>Username</th><th>IP</th><th>Server</th><th>Interface</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead><tbody>'.$peerRows.'</tbody></table></div></section>';

        $guide = '<section class="card"><details><summary style="cursor:pointer;font-weight:800">⚙ MikroTik Setup Guide</summary><div style="margin-top:14px"><p class="help">Configure RouterOS REST access, create the RouteBox user and verify the connection before adding the server.</p><ol style="line-height:1.9;padding-inline-start:22px"><li>Enable <code>www</code> for temporary HTTP testing, or preferably <code>www-ssl</code> with a valid certificate for production.</li><li>Create a dedicated RouterOS user with <code>rest-api</code> plus only the permissions RouteBox needs.</li><li>Allow the REST port from the RouteBox server IP in the firewall.</li><li>In RouteBox enter the router IP/hostname, REST port, username and password. For temporary HTTP testing disable the TLS checkbox and use port 80.</li><li>VPN Endpoint is the public hostname/IP used by WireGuard clients. Leave <strong>WireGuard Port</strong> blank and RouteBox will automatically read the WireGuard interface <code>listen-port</code> from RouterOS.</li><li>Use <strong>Test Connection</strong>. The panel will display RouterOS version, uptime, CPU, memory and REST latency.</li></ol><p class="help">HTTP REST sends credentials without transport encryption. Use it only for controlled testing; production should use HTTPS/TLS.</p></div></details></section>';

        return $flashHtml.$addServer.$guide
            .'<section class="card"><div class="section-head"><div class="section-title"><div class="section-icon">◉</div><div><h2>MikroTik Servers</h2><p>RouterOS version, uptime, CPU, memory, REST latency, WireGuard interfaces, IP pools and DNS are discovered from the router.</p></div></div></div>'.$serversHtml.'</section>'
            .$planForm.$peerTable;
    }

    private static function planForm(array $p, array $servers, string $csrf, bool $edit): string
    {
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $meta = json_decode((string)($p['metadata_json'] ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $action = $edit ? 'update_plan' : 'add_plan';
        $id = $edit ? (int)($p['id'] ?? 0) : 0;
        $serverId = (int)($p['provider_server_id'] ?? 0);
        $options = '';
        foreach ($servers as $s) {
            $selected = $serverId === (int)$s['id'] ? ' selected' : '';
            $options .= '<option value="'.(int)$s['id'].'"'.$selected.'>'.$h((string)$s['name']).'</option>';
        }
        return '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'"><div class="grid">'
            .'<div class="field"><label>Server</label><select name="server_id" required>'.$options.'</select></div>'
            .'<div class="field"><label>Persian name</label><input name="display_name_fa" required value="'.$h((string)($p['display_name_fa'] ?? '')).'" placeholder="یک ماهه"></div>'
            .'<div class="field"><label>English name</label><input name="display_name_en" required value="'.$h((string)($p['display_name_en'] ?? '')).'" placeholder="One Month"></div>'
            .'<div class="field"><label>Price (minor currency)</label><input type="number" name="price_minor" min="0" value="'.(int)($p['price_minor'] ?? 0).'"></div>'
            .'<div class="field"><label>Duration days</label><input type="number" name="duration_days" min="0" value="'.(int)($p['duration_days'] ?? 30).'"></div>'
            .'<div class="field"><label>Quota GB</label><input type="number" step="0.01" name="quota_gb" min="0" value="'.(float)($p['quota_gb'] ?? 0).'"></div>'
            .'<div class="field"><label>Upload limit</label><input name="upload_limit" value="'.$h((string)($meta['upload_limit'] ?? '')).'" placeholder="10M"></div>'
            .'<div class="field"><label>Download limit</label><input name="download_limit" value="'.$h((string)($meta['download_limit'] ?? '')).'" placeholder="50M"></div>'
            .'<div class="field"><label>Sort order</label><input type="number" name="sort_order" min="0" value="'.(int)($p['sort_order'] ?? 0).'"></div>'
            .'</div><div class="form-actions"><button class="btn btn-primary" type="submit">'.($edit ? 'Save Plan' : '+ Add plan').'</button></div></form>';
    }

    private static function serverForm(array $s, string $csrf, bool $edit): string
    {
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = $edit ? 'update_server' : 'add_server';
        $id = $edit ? (int)$s['id'] : 0;
        $vpnPort = (int)($s['vpn_port'] ?? 0);
        $vpnValue = $vpnPort > 0 ? (string)$vpnPort : '';
        return '<form method="post" style="margin-top:14px"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'"><div class="grid">'
            .'<div class="field"><label>Server Name</label><input name="name" required value="'.$h((string)($s['name'] ?? '')).'"></div>'
            .'<div class="field"><label>IP / Hostname</label><input name="host" required dir="ltr" value="'.$h((string)($s['host'] ?? '')).'"></div>'
            .'<div class="field"><label>REST API Port</label><input type="number" name="api_port" min="1" max="65535" value="'.(int)($s['api_port'] ?? 443).'"></div>'
            .'<div class="field"><label>API Username</label><input name="username" required value="'.$h((string)($s['username'] ?? '')).'"></div>'
            .'<div class="field"><label>API Password</label><input type="password" name="password" '.($edit ? 'placeholder="Leave blank to keep current password"' : 'required').' value=""></div>'
            .'<div class="field"><label><input type="checkbox" name="tls_mode" value="1" '.(!empty($s['tls_mode']) || (!$edit && (int)($s['api_port'] ?? 443) === 443) ? 'checked' : '').'> Use HTTPS / TLS</label></div>'
            .'<div class="field"><label>VPN Endpoint</label><input name="vpn_endpoint" dir="ltr" value="'.$h((string)($s['vpn_endpoint'] ?? '')).'"></div>'
            .'<div class="field"><label>WireGuard Port</label><input type="number" name="vpn_port" min="1" max="65535" value="'.$h($vpnValue).'" placeholder="Auto-detect from RouterOS"></div>'
            .'<div class="field"><label>WireGuard Interface</label><input name="interface_name" value="'.$h((string)($s['interface_name'] ?? '')).'"></div>'
            .'<div class="field"><label>IP Pool</label><input name="pool_name" value="'.$h((string)($s['pool_name'] ?? '')).'"></div>'
            .'<div class="field"><label>DNS Servers</label><input name="dns_servers" dir="ltr" value="'.$h((string)($s['dns_servers'] ?? '')).'" placeholder="9.9.9.9,1.1.1.1"></div>'
            .'</div><div class="form-actions"><button class="btn btn-primary" type="submit">'.($edit ? 'Save & Test' : 'Connect & Save').'</button></div></form>';
    }
}
