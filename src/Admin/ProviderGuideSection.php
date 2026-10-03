<?php

declare(strict_types=1);

namespace RouteBox\Admin;

final class ProviderGuideSection
{
    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function markdown(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $parts = preg_split('/(```[a-zA-Z0-9_-]*\n.*?```)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^```([a-zA-Z0-9_-]*)\n(.*?)```$/s', $part, $m)) {
                $lang = self::esc($m[1] !== '' ? $m[1] : 'text');
                $html .= '<pre class="provider-guide-code"><code class="language-'.$lang.'">'.self::esc(rtrim($m[2])).'</code></pre>';
                continue;
            }
            $lines = explode("\n", $part);
            $inList = false;
            foreach ($lines as $line) {
                $trim = trim($line);
                if ($trim === '') {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    continue;
                }
                if (preg_match('/^###\s+(.+)$/', $trim, $m)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h4>'.self::inline($m[1]).'</h4>'; continue;
                }
                if (preg_match('/^##\s+(.+)$/', $trim, $m)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h3>'.self::inline($m[1]).'</h3>'; continue;
                }
                if (preg_match('/^#\s+(.+)$/', $trim, $m)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h2>'.self::inline($m[1]).'</h2>'; continue;
                }
                if (preg_match('/^[-*]\s+(.+)$/', $trim, $m)) {
                    if (!$inList) { $html .= '<ul>'; $inList = true; }
                    $html .= '<li>'.self::inline($m[1]).'</li>'; continue;
                }
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<p>'.self::inline($trim).'</p>';
            }
            if ($inList) $html .= '</ul>';
        }
        return $html;
    }

    private static function inline(string $text): string
    {
        $safe = self::esc($text);
        $safe = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safe) ?? $safe;
        $safe = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
        return $safe;
    }

    private static function styles(): string
    {
        return '<style id="provider-guide-docs-style">.provider-guide-docs{display:grid;gap:18px}.provider-guide-intro{padding:16px 18px;border:1px solid var(--line);border-radius:15px;background:var(--card2);color:var(--muted);line-height:1.8}.provider-guide-content{padding:20px;border:1px solid var(--line);border-radius:15px;background:var(--card)}.provider-guide-content h2{margin:0 0 16px;font-size:25px}.provider-guide-content h3{margin:24px 0 10px;font-size:19px}.provider-guide-content h4{margin:18px 0 8px;font-size:16px}.provider-guide-content p{line-height:1.85;margin:8px 0}.provider-guide-content ul{line-height:1.9;padding-inline-start:24px}.provider-guide-content code{padding:2px 6px;border-radius:6px;background:rgba(127,127,127,.12);font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.provider-guide-code{overflow:auto;margin:12px 0;padding:15px;border:1px solid var(--line);border-radius:12px;background:rgba(0,0,0,.08);direction:ltr;text-align:left}.provider-guide-code code{display:block;padding:0;background:none;color:inherit;white-space:pre;font-size:13px;line-height:1.7}.provider-guide-note{font-size:12px;color:var(--muted)}@media(max-width:760px){.provider-guide-content{padding:14px}} </style>';
    }

    public static function render(string $provider, string $lang): string
    {
        $manifestPath = __DIR__ . '/../../docs/providers/manifest.php';
        $manifest = is_file($manifestPath) ? require $manifestPath : [];
        $item = is_array($manifest[$provider] ?? null) ? $manifest[$provider] : null;
        $back = $provider === 'ibsng' ? 'ibsng' : ($provider === 'mikrotik_wireguard' ? 'mikrotik' : 'servers');
        if ($item === null || !is_file((string)($item['file'] ?? ''))) {
            return '<section class="atd-admin-section card"><div class="section-head"><div><div class="eyebrow">ATD PANEL</div><h2>Provider Guide</h2><p>Guide is not configured for this provider.</p></div><a class="btn btn-secondary" href="/?section='.$back.'">← Back</a></div></section>'.self::styles();
        }
        $fa = $lang === 'fa';
        $title = (string)($fa ? $item['title_fa'] : $item['title_en']);
        $markdown = (string)file_get_contents((string)$item['file']);
        $intro = $fa
            ? 'این راهنما بخشی از خود ATD Panel است و توسط سازنده پنل ارائه می‌شود. برای تغییر محتوا، فایل Provider Guide در نسخه بعدی پنل به‌روزرسانی می‌شود.'
            : 'This guide is shipped as part of ATD Panel and is maintained by the panel author. Content changes are delivered through a future panel release.';
        $body = '<div class="provider-guide-docs"><div class="provider-guide-intro">'.self::esc($intro).'<div class="provider-guide-note" style="margin-top:7px">'.self::esc($fa ? 'منبع: docs/providers/'.$provider : 'Source: docs/providers/'.$provider).'</div></div><article class="provider-guide-content">'.self::markdown($markdown).'</article></div>';
        return '<section class="atd-admin-section card"><div class="section-head"><div><div class="eyebrow">ATD PANEL</div><h2>'.self::esc($title).'</h2><p>'.self::esc($fa ? 'راهنمای فنی آماده‌سازی، اتصال و تست Provider.' : 'Technical guide for provider setup, connection and testing.').'</p></div><a class="btn btn-secondary" href="/?section='.$back.'">← '.self::esc($fa ? 'بازگشت' : 'Back').'</a></div>'.$body.'</section>'.self::styles();
    }
}
