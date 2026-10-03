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

        $formatBytes = static function ($bytes): string {
            $bytes = max(0, (float)$bytes);
            if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1).' GB';
            if ($bytes >= 1048576) return number_format($bytes / 1048576, 0).' MB';
            if ($bytes >= 1024) return number_format($bytes / 1024, 0).' KB';
            return number_format($bytes, 0).' B';
        };

        $metric = static function (string $icon, string $label, string $value, string $sub = '', string $dir = '') use ($h): string {
            return '<div style="padding:14px 15px;border:1px solid var(--line);border-radius:15px;background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.015));min-width:0">'
                .'<div style="display:flex;align-items:center;gap:8px;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.03em">'
                .'<span style="font-size:17px;line-height:1">'.$icon.'</span><span>'.$h($label).'</span></div>'
                .'<div style="margin-top:8px;font-size:16px;font-weight:800;line-height:1.25"'.($dir !== '' ? ' dir="'.$h($dir).'"' : '').'>'.$h($value).'</div>'
                .($sub !== '' ? '<div class="help" style="margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"'.($dir !== '' ? ' dir="'.$h($dir).'"' : '').'>'.$h($sub).'</div>' : '')
                .'</div>';
        };

        $serversHtml = '';
        foreach (($data['servers'] ?? []) as $s) {
            $router = is_array($s['router'] ?? null) ? $s['router'] : [];
            $hasError = !empty($s['discovery_error']);
            $identity = trim((string)($router['identity'] ?? '')) ?: (string)($s['name'] ?? 'MikroTik');
            $version = (string)($router['version'] ?? '—');
            $uptime = (string)($router['uptime'] ?? '—');
            $cpuRaw = $router['cpu-load'] ?? ($router['cpu_load'] ?? null);
            $cpu = $cpuRaw === null || $cpuRaw === '' ? '—' : (string)$cpuRaw;
            $totalMemory = (float)($router['total-memory'] ?? ($router['total_memory'] ?? 0));
            $freeMemory = (float)($router['free-memory'] ?? ($router['free_memory'] ?? 0));
            $usedMemory = max(0, $totalMemory - $freeMemory);
            $memory = $totalMemory > 0 ? $formatBytes($usedMemory).' / '.$formatBytes($totalMemory) : '—';
            $memoryPercent = $totalMemory > 0 ? max(0, min(100, ($usedMemory / $totalMemory) * 100)) : 0;
            $latency = $s['latency_ms'] === null ? '—' : number_format((float)$s['latency_ms'], 1).' ms';
            $interfaces = is_array($s['interfaces'] ?? null) ? $s['interfaces'] : [];
            $interfaceNames = array_values(array_filter(array_map(static fn(array $x): string => (string)($x['name'] ?? ''), $interfaces)));
            $interfaceText = $interfaceNames ? implode(', ', $interfaceNames) : '—';
            $serverId = (int)$s['id'];
            $editId = 'mikrotik-edit-'.$serverId;
            $statusText = $hasError ? 'Connection issue' : 'Connected';
            $statusColor = $hasError ? 'var(--red)' : 'var(--green)';
            $vpnPort = (int)($s['vpn_port'] ?? 0) > 0 ? (string)$s['vpn_port'] : 'Auto-detect';
            $errorHtml = $hasError
                ? '<div class="flash err" style="margin-top:14px">'.$h((string)$s['discovery_error']).'</div>'
                : '';

            $serversHtml .= '<article class="card" style="margin-bottom:14px;padding:18px">'
                .'<div class="section-head" style="margin-bottom:14px"><div class="section-title"><div class="section-icon">◉</div><div style="min-width:0">'
                    .'<h2 style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">'.$h($identity).'<span class="status"><span class="dot" style="background:'.$statusColor.'"></span>'.$h($statusText).'</span></h2>'
                    .'<p dir="ltr" style="margin-top:4px">'.$h((string)$s['host']).' : '.$h((string)$s['api_port']).' · '.($h(!empty($s['tls_mode']) ? 'HTTPS REST' : 'HTTP REST')).'</p>'
                .'</div></div></div>'
                .'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px">'
                    .$metric('🖥️','RouterOS',$version,'Uptime: '.$uptime,'ltr')
                    .$metric('⚡','CPU Load',$cpu === '—' ? '—' : $cpu.' %','Live system load','ltr')
                    .$metric('💾','Memory',$memory,$totalMemory > 0 ? number_format($memoryPercent,0).'% used' : 'Unavailable','ltr')
                    .$metric('📡','REST Ping',$latency,'API request latency','ltr')
                    .$metric('🔐','WireGuard',count($interfaces).' interface(s)','Port '.$vpnPort,'ltr')
                .'</div>'
                .($totalMemory > 0 ? '<div style="margin-top:10px;height:5px;border-radius:99px;background:var(--line);overflow:hidden"><div style="height:100%;width:'.number_format($memoryPercent,1,'.','').'%;background:var(--accent);border-radius:99px"></div></div>' : '')
                .'<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-top:12px">'
                    .'<div style="padding:11px 13px;border:1px solid var(--line);border-radius:13px"><div class="help">Interface</div><strong dir="ltr">'.$h($interfaceText).'</strong></div>'
                    .'<div style="padding:11px 13px;border:1px solid var(--line);border-radius:13px"><div class="help">IP Pool</div><strong dir="ltr">'.$h((string)($s['pool_name'] ?: '—')).'</strong></div>'
                    .'<div style="padding:11px 13px;border:1px solid var(--line);border-radius:13px"><div class="help">DNS</div><strong dir="ltr">'.$h((string)($s['dns'] ?: ($s['dns_servers'] ?? '—'))).'</strong></div>'
                    .'<div style="padding:11px 13px;border:1px solid var(--line);border-radius:13px"><div class="help">Architecture / Board</div><strong>'.$h((string)($router['architecture-name'] ?? '—')).' · '.$h((string)($router['board-name'] ?? '—')).'</strong></div>'
                .'</div>'
                .$errorHtml
                .'<div class="form-actions" style="margin-top:14px">'
                    .'<form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><input type="hidden" name="action" value="test_server"><input type="hidden" name="id" value="'.$serverId.'"><button class="btn btn-secondary" type="submit">↻ Test Connection</button></form>'
                    .'<details id="'.$h($editId).'" style="display:inline"><summary class="btn btn-primary" style="cursor:pointer;list-style:none">✎ Edit Server</summary><div style="margin-top:14px">'.self::serverForm($s, $csrf, true).'</div></details>'
                .'</div>'
                .'</article>';
        }
        if ($serversHtml === '') $serversHtml = '<div class="empty">No MikroTik servers configured yet.</div>';

        $planRows = '';
        foreach (($data['plans'] ?? []) as $p) {
            $id = (int)$p['id'];
            $planEditId = 'mikrotik-plan-edit-'.$id;
            $planRows .= '<tr>'