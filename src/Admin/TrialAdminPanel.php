<?php

declare(strict_types=1);

namespace RouteBox\Admin;

use PDO;
use RouteBox\Services\TrialService;

require_once __DIR__ . '/../Services/TrialService.php';

/**
 * Presentation/admin controls for the provider-aware Trial lifecycle.
 *
 * This class deliberately does not create a page or route. The existing
 * ATD Panel shell remains the source of truth; the UI is attached to the
 * already-rendered section=bot and section=users output at shutdown, before
 * the existing ATDUICompatibility output callback finalizes the response.
 */
final class TrialAdminPanel
{
    private static bool $booted = false;
    private static bool $shutdownRegistered = false;

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
                    TrialService::savePlan(
                        $db,
                        (int)($_POST['category_id'] ?? 0),
                        (int)($_POST['plan_id'] ?? 0),
                        isset($_POST['enabled'])
                    );
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
            if (!in_array($return, ['bot', 'users'], true)) $return = 'users';
            header('Location: /?section=' . rawurlencode($return));
            exit;
        }

        /*
         * public/index.php starts the existing ATDUICompatibility output
         * buffer after bootstrap. Registering our own output buffer here
         * therefore puts it underneath the real ATD shell buffer and is not
         * reliable for the legacy/special-section renderer. Instead, at PHP
         * shutdown we modify the still-open outer ATD buffer directly. The
         * normal ATDUICompatibility callback then runs unchanged afterwards.
         */
        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(static function () use ($db): void {
                self::injectIntoExistingShell($db);
            });
        }
    }

    private static function injectIntoExistingShell(PDO $db): void
    {
        $section = (string)($_GET['section'] ?? '');
        if (!in_array($section, ['bot', 'users'], true)) return;
        if (ob_get_level() < 1) return;

        $html = ob_get_contents();
        if (!is_string($html) || $html === '') return;

        // Only touch the already-rendered unified ATD shell. This prevents
        // accidental injection into unrelated output buffers.
        if (stripos($html, 'id="atd-ui-compatibility"') === false) return;
        if (stripos($html, '</main>') === false) return;

        $lang = (string)($_GET['lang'] ?? ($_SESSION['panel_lang'] ?? 'fa')) === 'en' ? 'en' : 'fa';
        $panel = $section === 'bot'
            ? self::botSettings($db, $lang)
            : self::userTrials($db, $lang);

        $marker = '</main>';
        $pos = stripos($html, $marker);
        if ($pos === false) return;

        ob_clean();
        echo substr($html, 0, $pos) . $panel . substr($html, $pos);
    }

    private static function styles(): string
    {
        return '<style id="atd-trials-ui">'
            . '.atd-trials-panel{margin-top:18px}'
            . '.atd-trials-panel .atd-trials-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}'
            . '.atd-trials-panel .atd-trials-head h2{margin:3px 0 6px;font-size:22px}'
            . '.atd-trials-panel .atd-trials-head p{margin:0;color:var(--muted);font-size:13px;line-height:1.7}'
            . '.atd-trials-panel .atd-trial-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}'
            . '.atd-trials-panel .atd-trial-card{border:1px solid var(--line);border-radius:15px;padding:15px;background:var(--card2);min-width:0}'
            . '.atd-trials-panel .atd-trial-card h3{margin:0 0 6px;font-size:15px}'
            . '.atd-trials-panel .muted{color:var(--muted);font-size:12px;line-height:1.65}'
            . '.atd-trials-panel select{width:100%;box-sizing:border-box;margin-top:11px;padding:10px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:inherit}'
            . '.atd-trials-panel .row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}'
            . '.atd-trials-panel .row label{font-size:12px;font-weight:700}'
            . '.atd-trials-panel .atd-trial-table{overflow:auto;border:1px solid var(--line);border-radius:14px}'
            . '.atd-trials-panel table{width:100%;border-collapse:collapse}'
            . '.atd-trials-panel th,.atd-trials-panel td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:start;white-space:nowrap}'
            . '.atd-trials-panel th{font-size:11px;color:var(--muted)}'
            . '.atd-trials-panel tr:last-child td{border-bottom:0}'
            . '.atd-trials-panel .status{display:inline-flex;padding:4px 8px;border-radius:999px;background:var(--atd-accent-soft);font-size:11px}'
            . '.atd-trials-panel .status.expired{background:rgba(220,80,80,.12)}'
            . '@media(max-width:900px){.atd-trials-panel .atd-trial-grid{grid-template-columns:1fr 1fr}}'
            . '@media(max-width:650px){.atd-trials-panel .atd-trial-grid{grid-template-columns:1fr}.atd-trials-panel .atd-trials-head{flex-direction:column}}'
            . '</style>';
    }

    private static function botSettings(PDO $db, string $lang): string
    {
        $fa = $lang === 'fa';
        $cards = '';

        foreach (TrialService::categoriesWithPlans($db) as $cat) {
            $current = (int)($cat['trial_plan_id'] ?? 0);
            $cards .= '<form class="atd-trial-card" method="post">'
                . '<input type="hidden" name="csrf_token" value="' . self::e(csrf_token()) . '">'
                . '<input type="hidden" name="atd_trial_action" value="save_plan">'
                . '<input type="hidden" name="return_section" value="bot">'
                . '<input type="hidden" name="category_id" value="' . (int)$cat['id'] . '">'
                . '<h3>' . self::e($fa ? $cat['name_fa'] : $cat['name_en']) . '</h3>'
                . '<div class="muted">provider_key: <code>' . self::e($cat['provider_key']) . '</code></div>'
                . '<select name="plan_id">'
                . '<option value="0">— ' . ($fa ? 'بدون Trial' : 'No Trial') . ' —</option>';

            foreach ($cat['plans'] as $plan) {
                $selected = $current === (int)$plan['id'] ? ' selected' : '';
                $label = $fa ? $plan['display_name_fa'] : $plan['display_name_en'];
                $cards .= '<option value="' . (int)$plan['id'] . '"' . $selected . '>'
                    . self::e($label) . ' · ' . (int)$plan['duration_days'] . 'd</option>';
            }

            $cards .= '</select><div class="row"><label><input type="checkbox" name="enabled" value="1"' . ($current > 0 ? ' checked' : '') . '> '
                . ($fa ? 'فعال' : 'Enabled')
                . '</label><button class="btn btn-primary" type="submit">'
                . ($fa ? 'ذخیره' : 'Save') . '</button></div></form>';
        }

        if ($cards === '') {
            $cards = '<div class="muted">'
                . ($fa ? 'هنوز Provider/Plan فعالی برای انتخاب Trial وجود ندارد.' : 'No active Provider Plans are available for Trial selection yet.')
                . '</div>';
        }

        return self::styles()
            . '<section class="card atd-trials-panel" id="bot-trials">'
            . '<div class="atd-trials-head"><div><div class="eyebrow">ATD PANEL · BOT · TRIALS</div><h2>🎁 '
            . ($fa ? 'Free Trial هر Provider' : 'Provider Free Trials')
            . '</h2><p>'
            . ($fa ? 'Trial هر Provider از Plan واقعی همان Provider/Category انتخاب می‌شود؛ Provider key از Catalog خوانده می‌شود و hard-code نیست.' : 'Each Provider Trial uses a real Plan from that Provider/Category. Provider keys come from the catalog and are not hard-coded.')
            . '</p></div></div><div class="atd-trial-grid">' . $cards . '</div></section>';
    }

    private static function userTrials(PDO $db, string $lang): string
    {
        $fa = $lang === 'fa';
        $rows = TrialService::listTrials($db);
        $body = '';

        foreach ($rows as $row) {
            $expired = $row['expires_at'] !== null
                && (int)$row['expires_at'] <= time()
                && (string)$row['status'] !== 'deleted';
            $name = trim((string)($row['first_name'] ?? ''));
            if ($name === '') {
                $name = trim((string)($row['username'] ?? '')) !== ''
                    ? '@' . (string)$row['username']
                    : (string)$row['telegram_id'];
            }
            $status = $expired
                ? ($fa ? 'منقضی‌شده' : 'Expired')
                : ((string)$row['status'] === 'deleted'
                    ? ($fa ? 'حذف‌شده' : 'Deleted')
                    : ($fa ? 'فعال' : 'Active'));

            $body .= '<tr><td>' . (int)$row['id'] . '</td><td>' . self::e($name)
                . '</td><td>' . self::e($row['provider_key']) . '</td><td>'
                . self::e($fa ? ($row['display_name_fa'] ?? '') : ($row['display_name_en'] ?? ''))
                . '</td><td><span class="status' . ($expired ? ' expired' : '') . '">'
                . self::e($status) . '</span></td><td>'
                . ($row['expires_at'] ? date('Y-m-d H:i', (int)$row['expires_at']) : '—')
                . '</td><td>';

            if ($expired) {
                $confirm = $fa
                    ? 'Trial منقضی‌شده حذف و از Provider پاکسازی شود؟'
                    : 'Cleanup and delete this expired Trial?';
                $body .= '<form method="post" onsubmit="return confirm(\'' . self::e($confirm) . '\')">'
                    . '<input type="hidden" name="csrf_token" value="' . self::e(csrf_token()) . '">'
                    . '<input type="hidden" name="atd_trial_action" value="delete_trial">'
                    . '<input type="hidden" name="return_section" value="users">'
                    . '<input type="hidden" name="trial_id" value="' . (int)$row['id'] . '">'
                    . '<button class="btn btn-secondary" type="submit">🗑 '
                    . ($fa ? 'پاکسازی و حذف' : 'Cleanup & Delete')
                    . '</button></form>';
            } else {
                $body .= '<span style="color:var(--muted)">—</span>';
            }
            $body .= '</td></tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="7" style="color:var(--muted)">'
                . ($fa ? 'هنوز Trialی ثبت نشده است.' : 'No Trials recorded yet.')
                . '</td></tr>';
        }

        return self::styles()
            . '<section class="card atd-trials-panel" id="user-trials">'
            . '<div class="atd-trials-head"><div><div class="eyebrow">ATD PANEL · USERS · TRIALS</div><h2>🎁 Trials</h2><p>'
            . ($fa
                ? 'Trialهای فعال و منقضی‌شده اینجا مدیریت می‌شوند. حذف Trial فقط منبع Provider را پاک می‌کند؛ سابقه دریافت Trial برای جلوگیری از دریافت مجدد باقی می‌ماند.'
                : 'Manage active and expired Trials. Cleanup removes the Provider resource while preserving permanent eligibility history so the Trial cannot be claimed again.')
            . '</p></div></div><div class="atd-trial-table"><table><thead><tr><th>ID</th><th>'
            . ($fa ? 'کاربر' : 'User') . '</th><th>Provider</th><th>Plan</th><th>'
            . ($fa ? 'وضعیت' : 'Status') . '</th><th>' . ($fa ? 'انقضا' : 'Expiry')
            . '</th><th></th></tr></thead><tbody>' . $body
            . '</tbody></table></div></section>';
    }
}
