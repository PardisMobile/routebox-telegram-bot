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
        if ($safe !== '') {
            $candidates[] = $safe . '-worker.service';
            $candidates[] = $safe . '.service';
        }
        $candidates[] = 'routebox-telegram-bot-dev-worker.service';
        $candidates[] = 'routebox-telegram-bot-dev.service';
        $candidates[] = 'routebox-telegram-bot-worker.service';
        $candidates[] = 'routebox-telegram-bot.service';
        return array_values(array_unique($candidates));
    }

    private static function unitInfo(string $service): array
    {
        $cmd = '/usr/bin/systemctl show --property=LoadState --property=ActiveState --property=SubState --property=MainPID --property=ExecStart --property=Description --value ' . escapeshellarg($service) . ' 2>/dev/null';
        $lines = preg_split('/\R/', trim((string)@shell_exec($cmd))) ?: [];
        return [
            'load'=>strtolower(trim((string)($lines[0] ?? ''))),
            'active'=>strtolower(trim((string)($lines[1] ?? ''))),
            'sub'=>strtolower(trim((string)($lines[2] ?? ''))),
            'pid'=>(int)($lines[3] ?? 0),
            'exec'=>(string)($lines[4] ?? ''),
            'description'=>(string)($lines[5] ?? ''),
        ];
    }

    private static function processLooksLikeWorker(int $pid): bool
    {
        if ($pid <= 1 || !is_file('/proc/' . $pid . '/cmdline')) return false;
        $cmdline = str_replace("\0", ' ', (string)@file_get_contents('/proc/' . $pid . '/cmdline'));
        return $cmdline !== '' && preg_match('/(?:^|\s)(?:[^\s]*\/)?worker\.php(?:\s|$)/i', $cmdline) === 1;
    }

    public static function serviceName(): string
    {
        foreach (self::candidateUnits() as $service) {
            $info = self::unitInfo($service);
            if ($info['load'] !== 'loaded') continue;
            if (stripos($info['exec'], 'worker.php') !== false || stripos($info['description'], 'worker') !== false || self::processLooksLikeWorker((int)$info['pid'])) {
                return $service;
            }
        }
        return 'routebox-telegram-bot-worker.service';
    }

    public static function status(): array
    {
        $service = self::serviceName();
        $info = self::unitInfo($service);
        $pid = (int)$info['pid'];
        $running = $info['load'] === 'loaded'
            && $info['active'] === 'active'
            && in_array($info['sub'], ['running', 'auto-restart'], true)
            && $pid > 0
            && self::processLooksLikeWorker($pid);
        return ['service'=>$service,'running'=>$running,'active'=>$info['active'],'sub'=>$info['sub'],'pid'=>$pid];
    }

    public static function reload(): array
    {
        $status = self::status();
        $pid = (int)$status['pid'];
        if (!$status['running'] || $pid <= 1) {
            return ['ok'=>false,'message'=>'Worker process is not running.','status'=>$status];
        }
        if (!function_exists('posix_kill') || !@posix_kill($pid, SIGTERM)) {
            return ['ok'=>false,'message'=>'Worker reload permission was denied.','status'=>$status];
        }
        usleep(500000);
        return ['ok'=>true,'message'=>'Worker reloaded.','status'=>self::status()];
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
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = trim((string)@shell_exec("/usr/bin/ip -4 route get 1.1.1.1 2>/dev/null | /usr/bin/awk '/src/ {for(i=1;i<=NF;i++) if(\$i==\"src\") {print \$(i+1); exit}}'"));
        }

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
