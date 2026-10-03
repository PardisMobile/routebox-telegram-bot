<?php

declare(strict_types=1);

namespace RouteBox\Admin;

final class ATDWorker
{
    public static function serviceName(): string
    {
        $app = basename(dirname(__DIR__, 2));
        $candidate = preg_replace('/[^A-Za-z0-9_.@-]/', '', $app) . '.service';
        if ($candidate !== '.service' && self::unitExists($candidate)) return $candidate;

        foreach (['routebox-telegram-bot-dev.service', 'routebox-telegram-bot.service'] as $fallback) {
            if (self::unitExists($fallback)) return $fallback;
        }
        return $candidate !== '.service' ? $candidate : 'routebox-telegram-bot.service';
    }

    private static function unitExists(string $service): bool
    {
        $cmd = '/usr/bin/systemctl show --property=LoadState --value ' . escapeshellarg($service) . ' 2>/dev/null';
        $state = trim((string)@shell_exec($cmd));
        return $state === 'loaded';
    }

    public static function status(): array
    {
        $service = self::serviceName();
        $cmd = '/usr/bin/systemctl show --property=ActiveState --property=SubState --property=MainPID --value ' . escapeshellarg($service) . ' 2>/dev/null';
        $lines = preg_split('/\R/', trim((string)@shell_exec($cmd))) ?: [];
        $active = strtolower((string)($lines[0] ?? ''));
        $sub = strtolower((string)($lines[1] ?? ''));
        $pid = (int)($lines[2] ?? 0);
        $running = $active === 'active' && in_array($sub, ['running', 'auto-restart'], true) && $pid > 0;
        return ['service'=>$service,'running'=>$running,'active'=>$active,'sub'=>$sub,'pid'=>$pid];
    }

    public static function reload(): array
    {
        $status = self::status();
        $pid = (int)$status['pid'];
        if ($pid <= 1 || !is_dir('/proc/' . $pid)) {
            return ['ok'=>false,'message'=>'Worker process not found.','status'=>self::status()];
        }

        $cmdline = (string)@file_get_contents('/proc/' . $pid . '/cmdline');
        if ($cmdline === '' || stripos(str_replace("\0", ' ', $cmdline), 'worker.php') === false) {
            return ['ok'=>false,'message'=>'Worker service was detected, but its main process could not be verified.','status'=>$status];
        }

        if (!function_exists('posix_kill') || !@posix_kill($pid, SIGTERM)) {
            return ['ok'=>false,'message'=>'Worker reload permission was denied.','status'=>$status];
        }

        usleep(250000);
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
