<?php

declare(strict_types=1);

namespace RouteBox\Admin;

use PDO;
use RouteBox\Services\TrialService;

require_once __DIR__ . '/../Services/TrialService.php';

final class TrialAdminPanel
{
    private static bool $booted = false;

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function boot(PDO $db): void
    {
        if (self::$booted || PHP_SAPI === 'cli') return;
        self::$booted = true;
        TrialService::ensureSchema($db);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['atd_trial_action'])) {
            require_admin();
            verify_csrf();
            try {
                $action = (string)$_POST['atd_trial_action'];
                if ($action === 'save_plan') {
                    TrialService::savePlan($db, (int)($_POST['category_id'] ?? 0), (int)($_POST['plan_id'] ?? 0), isset($_POST['enabled']));
                } elseif ($action === 'delete_trial') {
                    TrialService::deleteExpired($db, (int)($_POST['trial_id'] ?? 0));
                } else {
                    throw new \RuntimeException('Invalid Trial action.');
                }
                $_SESSION['flash'] = '✓ Trial action completed.';
            } catch (\Throwable $e) {
                $_SESSION['flash'] = '❌ ' . $e->getMessage();
            }
            $return = (string)($_POST['return_section'] ?? 'users');
            header('Location: /?section=' . rawurlencode($return));
            exit;
        }

        ob_start(function (string $html) use ($db): string {
            $section = (string)($_GET['section'] ?? '');
            $lang = (string)($_GET['lang'] ?? 'fa');
            if ($section === 'bot') return self::inject($html, self::botSettings($db, $lang));
            if ($section === 'users') return self::inject($html, self::userTrials($db, $lang));
            return $html;
        });
    }

    private static function inject(string $html, string $panel): string
    {
        $pos = stripos($html, '</main>');
        if ($pos !== false) return substr($html, 0, $pos) . $panel . substr($html, $pos);
        $pos = stripos($html, '</body>');
        if ($pos !== false) return substr($html, 0, $pos) . $panel . substr($html, $pos);
        return $html . $panel;
    }

    private static function styles(): string
    {
        return '<style id="atd-trials-ui">'
            . '.atd-trials-panel{margin-top:18px}'
            . '.atd-trials-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}'
            . '.atd-trials-head h2{margin:2px 0 6px}'
            . '.atd-trials-head p{margin:0;color:var(--muted);font-size:13px}'
            . '.atd-trial-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}'
            . '.atd-trial-card{border:1px solid var(--line);border-radius:15px;padding:15px;background:var(--card2);min-width:0}'
            . '.atd-trial-card h3{margin:0 0 6px;font-size:15px}'
            . '.atd-trial-card .muted{color:var(--muted);font-size:12px}'
            . '.atd-trial-card select{width:100%;box-sizing:border-box;margin-top:11px;padding:10px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:inherit}'
            . '.atd-trial-card .row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}'
            . '.atd-trial-table{overflow:auto;border:1px solid var(--line);border-radius:14px}'
            . '.atd-trial-table table{width:100%;border-collapse:collapse}'
            . '.atd-trial-table th,.atd-trial-table td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:start;white-space:nowrap}'
            . '.atd-trial-table th{font-size:11px;color:var(--muted)}'
            . '.atd-trial-table tr:last-child td{border-bottom:0}'
            . '.atd-trial-status{display:inline-flex;padding:4px 8px;border-radius:999px;background:var(--atd-accent-soft);font-size:11px}'
            . '.atd-trial-status.expired{background:rgba(220,80,80,.12)}'
            . '@media(max-width:900px){.atd-trial-grid{grid-template-columns:1fr 1fr}}'
            . '@media(max-width:650px){.atd-trial-grid{grid-template-columns:1fr}.atd-trials-head{flex-direction:column}}'
            . '</style>';
    }

    private static function botSettings(PDO $db, string $lang): string
    {
        $fa = $lang === 'fa';
        $csrf = self::e(csrf_token());
        $cards = '';
        foreach (TrialService::categoriesWithPlans($db) as $cat) {
            $current = (int)($cat['trial_plan_id'] ?? 0);
            $cards .= '<form class="atd-trial-card" method="post">'
                . '<input type="hidden" name="csrf_token" value="'.$csrf.'">'
                . '<input type="hidden" name="atd_trial_action" value="save_plan">'
                . '<input type="hidden" name="return_section" value="bot">'
                . '<input type="hidden" name="category_id" value="'.(int)$cat['id'].'">'
                . '<h3>'.self::e($fa ? $cat['name_fa'] : $cat['name_en']).'</h3>'
                . '<div class="muted">provider_key: '.self::e($cat['provider_key']).'</div>'
                . '<select name="plan_id">'
                . '<option value="0">— '.($fa?'بدون Trial':'No Trial').' —</option>';
            foreach ($cat['plans'] as $plan) {
                $selected = $current === (int)$plan['id'] ? ' selected' : '';
                $label = $fa ? $plan['display_name_fa'] : $plan['display_name_en'];
                $cards .= '<option value="'.(int)$plan['id'].'"'.$selected.'>'.self::e($label).' · '.(int)$plan['duration_days'].'d</option>';
            }
            $cards .= '</select><div class="row"><label><input type="checkbox" name="enabled" value="1"'.($current>0?' checked':'').'> '.($fa?'فعال':'Enabled').'</label>'
                . '<button class="btn btn-primary" type="submit">'.($fa?'ذخیره':'Save').'</button></div></form>';
        }
        return self::styles().'<section class="card atd-trials-panel"><div class="atd-trials-head"><div><div class="eyebrow">ATD PANEL · BOT</div><h2>🎁 '.($fa?'Trial سرویس‌ها':'Provider Free Trials').'</h2><p>'.($fa?'برای هر Provider یک Plan واقعی از همان Catalog انتخاب می‌شود. این بخش به Bot Settings موجود اضافه می‌شود.':'Choose one real Catalog Plan for each provider. This is an addition to the existing Bot Settings.').'</p></div></div><div class="atd-trial-grid">'.$cards.'</div></section>';
    }

    private static function userTrials(PDO $db, string $lang): string
    {
        $fa = $lang === 'fa';
        $csrf = self::e(csrf_token());
        $rows = TrialService::listTrials($db);
        $body = '';
        foreach ($rows as $row) {
            $expired = $row['expires_at'] !== null && (int)$row['expires_at'] <= time() && (string)$row['status'] !== 'deleted';
            $name = trim((string)($row['first_name'] ?? ''));
            if ($name === '') $name = (string)($row['username'] ?? '') !== '' ? '@'.(string)$row['username'] : (string)$row['telegram_id'];
            $status = $expired ? ($fa?'منقضی‌شده':'Expired') : ((string)$row['status']==='deleted'?($fa?'حذف‌شده':'Deleted'):($fa?'فعال':'Active'));
            $body .= '<tr><td>'.(int)$row['id'].'</td><td>'.self::e($name).'</td><td>'.self::e($row['provider_key']).'</td><td>'.self::e($fa?($row['display_name_fa']??''):($row['display_name_en']??'')).'</td><td><span class="atd-trial-status'.($expired?' expired':'').'">'.self::e($status).'</span></td><td>'.($row['expires_at']?date('Y-m-d H:i',(int)$row['expires_at']):'—').'</td><td>';
            if ($expired) {
                $confirm = $fa ? 'Trial منقضی‌شده حذف شود؟' : 'Delete this expired Trial?';
                $body .= '<form method="post" onsubmit="return confirm(\''.self::e($confirm).'\')">'
                    . '<input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="atd_trial_action" value="delete_trial"><input type="hidden" name="return_section" value="users"><input type="hidden" name="trial_id" value="'.(int)$row['id'].'">'
                    . '<button class="btn btn-secondary" type="submit">🗑 '.($fa?'حذف و پاکسازی':'Cleanup & Delete').'</button></form>';
            } else {
                $body .= '<span style="color:var(--muted)">—</span>';
            }
            $body .= '</td></tr>';
        }
        if ($body === '') $body = '<tr><td colspan="7" style="color:var(--muted)">'.($fa?'هنوز Trialی ثبت نشده است.':'No trials recorded yet.').'</td></tr>';
        return self::styles().'<section class="card atd-trials-panel"><div class="atd-trials-head"><div><div class="eyebrow">ATD PANEL · USERS</div><h2>🎁 Trials</h2><p>'.($fa?'Trialهای فعال و منقضی‌شده اینجا نمایش داده می‌شوند. حذف فقط برای Trial منقضی‌شده انجام می‌شود و سابقه دریافت Trial باقی می‌ماند.':'Active and expired Trials. Cleanup removes the provider resource but preserves permanent Trial eligibility history.').'</p></div></div><div class="atd-trial-table"><table><thead><tr><th>ID</th><th>'.($fa?'کاربر':'User').'</th><th>Provider</th><th>Plan</th><th>'.($fa?'وضعیت':'Status').'</th><th>'.($fa?'انقضا':'Expiry').'</th><th></th></tr></thead><tbody>'.$body.'</tbody></table></div></section>';
    }
}
