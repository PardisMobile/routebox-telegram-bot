<?php

declare(strict_types=1);

namespace RouteBox\Admin;

final class ATDWorker
{
    private static function candidateUnits(): array
    {
        $app = basename(dirname(__DIR__, 2));
        $safe = preg_replace('/[^A-Za-z0-9_.@-]/', '', $app);
        $candidates = [];

        // Prefer the service that actually owns worker.php on this installation.
        if ($safe !== '') {
            $candidates[] = $safe . '.service';
            $candidates[] = $safe . '-worker.service';
        }
        $candidates[] = 'routebox-telegram-bot-dev.service';
        $candidates[] = 'routebox-telegram-bot-dev-worker.service';
        $candidates[] = 'routebox-worker.service';
        $candidates[] = 'routebox-telegram-bot-worker.service';
        $candidates[] = 'routebox-telegram-bot.service';

        $listed = @shell_exec("/usr/bin/systemctl list-unit-files --type=service --no-legend 2>/dev/null");
        foreach (preg_split('/\R/', trim((string)$listed)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line));
            $unit = (string)($parts[0] ?? '');
            if ($unit !== '' && preg_match('/(?:routebox|telegram).*\.service$/i', $unit)) {
                $candidates[] = $unit;
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function property(string $service, string $property): string
    {
        $cmd = '/usr/bin/systemctl show -p ' . escapeshellarg($property) . ' --value ' . escapeshellarg($service) . ' 2>/dev/null';
        return trim((string)@shell_exec($cmd));
    }

    private static function unitInfo(string $service): array
    {
        return [
            'load' => strtolower(self::property($service, 'LoadState')),
            'active' => strtolower(self::property($service, 'ActiveState')),
            'sub' => strtolower(self::property($service, 'SubState')),
            'pid' => (int)self::property($service, 'MainPID'),
            'exec' => self::property($service, 'ExecStart'),
            'description' => self::property($service, 'Description'),
        ];
    }

    public static function serviceName(): string
    {
        $best = null;
        $bestScore = -1;

        foreach (self::candidateUnits() as $service) {
            $info = self::unitInfo($service);
            if ($info['load'] !== 'loaded') continue;

            $exec = strtolower((string)$info['exec']);
            $description = strtolower((string)$info['description']);
            $name = strtolower($service);
            $score = 0;

            // A unit executing this project's worker.php is the authoritative match.
            if (stripos($exec, '/worker.php') !== false || stripos($exec, ' worker.php') !== false) $score += 100;
            if (stripos($description, 'worker') !== false) $score += 20;
            if (stripos($name, 'worker') !== false) $score += 10;
            if ($info['active'] === 'active') $score += 50;
            if (in_array($info['sub'], ['running', 'auto-restart'], true)) $score += 20;
            if ((int)$info['pid'] > 0) $score += 10;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $service;
            }
        }

        return $best ?? 'routebox-telegram-bot-dev.service';
    }

    private static function pingMs(string $ip): ?float
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return null;
        $output = @shell_exec('/bin/ping -n -c 1 -W 1 ' . escapeshellarg($ip) . ' 2>/dev/null');
        if (preg_match('/time[=<]([0-9.]+)\s*ms/i', (string)$output, $m)) return round((float)$m[1], 1);
        return null;
    }

    public static function status(): array
    {
        $service = self::serviceName();
        $info = self::unitInfo($service);
        $running = $info['load'] === 'loaded'
            && $info['active'] === 'active'
            && in_array($info['sub'], ['running', 'auto-restart'], true)
            && (int)$info['pid'] > 0;

        // The worker is also directly identifiable by its PHP process. This keeps the
        // panel accurate if systemd briefly reports a transitional state during restart.
        if (!$running) {
            $pid = trim((string)@shell_exec("/usr/bin/pgrep -f '/opt/" . preg_quote(basename(dirname(__DIR__, 2)), '/') . "/worker\\.php' | /usr/bin/head -n 1"));
            if ((int)$pid > 0) $running = true;
        }

        $server = self::serverInfo();
        $ping = self::pingMs((string)$server['ip']);
        return [
            'service' => $ping !== null ? number_format($ping, 1) . ' ms' : '—',
            'running' => $running,
            'active' => $info['active'],
            'sub' => $info['sub'],
            'pid' => (int)$info['pid'],
            'unit' => $service,
        ];
    }

    public static function reload(): array
    {
        $service = self::serviceName();
        $info = self::unitInfo($service);
        if ($info['load'] !== 'loaded') {
            return ['ok'=>false,'message'=>'Worker service was not found.','status'=>self::status()];
        }

        // First use the systemd unit so the service keeps its configured restart policy.
        foreach ([
            '/usr/bin/sudo -n /usr/bin/systemctl restart ' . escapeshellarg($service),
            '/usr/bin/systemctl restart ' . escapeshellarg($service),
        ] as $cmd) {
            $rc = 1;
            $out = [];
            @exec($cmd . ' 2>&1', $out, $rc);
            if ($rc === 0) {
                usleep(900000);
                return ['ok'=>true,'message'=>'Worker reloaded.','status'=>self::status()];
            }
        }

        // www-data owns the worker process on this installation, so this fallback is safe
        // and lets systemd's Restart=always bring it back without requiring sudo.
        $pid = (int)$info['pid'];
        if ($pid > 1 && function_exists('posix_kill') && @posix_kill($pid, SIGTERM)) {
            $deadline = microtime(true) + 4.0;
            do {
                usleep(250000);
                $status = self::status();
                if (!empty($status['running'])) {
                    return ['ok'=>true,'message'=>'Worker reloaded.','status'=>$status];
                }
            } while (microtime(true) < $deadline);

            return ['ok'=>true,'message'=>'Worker reload requested.','status'=>self::status()];
        }

        return ['ok'=>false,'message'=>'Worker reload permission was denied.','status'=>self::status()];
    }

    public static function serverInfo(): array
    {
        $ip = '';
        $ch = @curl_init('https://api.ipify.org');
        if ($ch !== false) {
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_CONNECTTIMEOUT=>2, CURLOPT_USERAGENT=>'RouteBox-Telegram-Bot-Admin']);
            $ip = trim((string)@curl_exec($ch));
            @curl_close($ch);
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = trim((string)@shell_exec("/usr/bin/ip -4 route get 1.1.1.1 2>/dev/null | /usr/bin/awk '/src/ {for(i=1;i<=NF;i++) if(\$i==\"src\") {print \$(i+1); exit}}'"));

        $country = '';
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $ch = @curl_init('https://ipapi.co/' . rawurlencode($ip) . '/country/');
            if ($ch !== false) {
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_CONNECTTIMEOUT=>2, CURLOPT_USERAGENT=>'RouteBox-Telegram-Bot-Admin']);
                $country = strtoupper(trim((string)@curl_exec($ch)));
                @curl_close($ch);
            }
        }
        return ['ip'=>filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '—', 'country'=>preg_match('/^[A-Z]{2}$/', $country) ? $country : ''];
    }

    public static function flag(string $country): string
    {
        $country = strtoupper(trim($country));
        if (!preg_match('/^[A-Z]{2}$/', $country) || !function_exists('mb_chr')) return '🌐';
        return mb_chr(127397 + ord($country[0])) . mb_chr(127397 + ord($country[1]));
    }
}
