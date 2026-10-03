<?php

declare(strict_types=1);

namespace RouteBox\Admin\Plans;

use PDO;

/** Shared provider-scoped Plan screen used by every provider. */
final class ProviderPlansSection
{
    public static function handle(PDO $db, array $post): void
    {
        $action = (string)($post['plan_action'] ?? '');
        if (!in_array($action, ['create', 'update', 'toggle', 'delete'], true)) throw new \RuntimeException('Invalid plan action.');
        $repo = new PlanRepository($db);
        $id = (int)($post['id'] ?? 0);

        if ($action === 'toggle') {
            $row = $repo->find($id);
            if (!$row) throw new \RuntimeException('Plan not found.');
            $repo->setEnabled($id, !(bool)$row['enabled']);
            return;
        }
        if ($action === 'delete') {
            if (!$repo->find($id)) throw new \RuntimeException('Plan not found.');
            $q = $db->prepare('SELECT COUNT(*) FROM service_subscriptions WHERE plan_id=?');
            $q->execute([$id]);
            if ((int)$q->fetchColumn() > 0) throw new \RuntimeException('This plan has subscriptions. Disable it instead of deleting it.');
            $db->prepare('DELETE FROM service_plans WHERE id=?')->execute([$id]);
            return;
        }

        $provider = trim((string)($post['provider_key'] ?? ''));
        $categoryId = (int)($post['category_id'] ?? 0);
        $fa = trim((string)($post['display_name_fa'] ?? ''));
        $en = trim((string)($post['display_name_en'] ?? ''));
        $price = (int)($post['price_minor'] ?? 0);
        $days = max(1, min(3650, (int)($post['duration_days'] ?? 30)));
        $quota = max(0, min(1048576, (float)($post['quota_gb'] ?? 0)));
        $serverId = trim((string)($post['provider_server_id'] ?? '')) === '' ? null : (int)$post['provider_server_id'];
        $planKey = trim((string)($post['provider_plan_key'] ?? ''));
        $sort = (int)($post['sort_order'] ?? 0);
        if ($provider === '' || $categoryId < 1 || $fa === '' || $en === '') throw new \RuntimeException('Provider, category and both plan names are required.');
        PlanPolicy::validatePrice($price);
        $check = $db->prepare('SELECT id FROM service_categories WHERE id=? AND provider_key=? AND enabled=1');
        $check->execute([$categoryId, $provider]);
        if (!$check->fetchColumn()) throw new \RuntimeException('The selected category does not belong to this provider.');

        $data = ['category_id'=>$categoryId,'provider_key'=>$provider,'provider_server_id'=>$serverId,'provider_plan_key'=>$planKey !== '' ? $planKey : null,'display_name_fa'=>$fa,'display_name_en'=>$en,'price_minor'=>$price,'duration_days'=>$days,'quota_gb'=>$quota,'enabled'=>1,'sort_order'=>$sort,'metadata_json'=>'{}'];
        if ($action === 'create') $repo->create($data); else $repo->update($id, $data);
    }

    public static function render(PDO $db, string $provider, string $lang, string $csrf): string
    {
        $repo = new PlanRepository($db);
        $plans = $repo->all($provider, true);
        $catQ = $db->prepare('SELECT * FROM service_categories WHERE provider_key=? AND enabled=1 ORDER BY sort_order,id');
        $catQ->execute([$provider]);
        $categories = $catQ->fetchAll(PDO::FETCH_ASSOC);
        $serverTable = ['routebox'=>'routebox_servers','ibsng'=>'ibsng_servers','mikrotik_wireguard'=>'mikrotik_servers'][$provider] ?? null;
        $servers = $serverTable ? $db->query('SELECT id,name FROM ' . $serverTable . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) : [];
        $fa = $lang === 'fa';
        $providerLabel = ['routebox'=>'RouteBox','ibsng'=>'IBSng','mikrotik_wireguard'=>'MikroTik WireGuard'][$provider] ?? $provider;

        $out = '<section class="provider-plans-page card"><div class="section-head"><div class="section-title"><div class="section-icon">◈</div><div><h2>' . h($providerLabel) . ' — Plans</h2><p>' . ($fa ? 'مدیریت پلن‌ها با یک UI مشترک برای همه Providerها.' : 'Shared plan management UI for every provider.') . '</p></div></div><a class="btn btn-secondary" href="/?section=' . rawurlencode(self::providerSection($provider)) . '">← ' . ($fa ? 'بازگشت به Provider' : 'Back to provider') . '</a></div>';

        $out .= '<form method="post" class="provider-plan-form"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="plan_action" value="create"><input type="hidden" name="provider_key" value="' . h($provider) . '"><div class="grid">'
            . self::field('display_name_fa',$fa?'نام فارسی':'Persian name','',true)
            . self::field('display_name_en','English name','',true)
            . '<div class="field"><label>' . ($fa?'دسته سرویس':'Service category') . '</label><select name="category_id" required>' . self::categoryOptions($categories,null,$fa) . '</select></div>'
            . '<div class="field"><label>' . ($fa?'سرور':'Server') . '</label><select name="provider_server_id"><option value="">' . ($fa?'خودکار / بدون سرور':'Auto / no server') . '</option>' . self::serverOptions($servers) . '</select></div>'
            . self::field('price_minor',$fa?'قیمت':'Price','0',false,'number','min="0" step="1"')
            . self::field('duration_days',$fa?'مدت (روز)':'Duration (days)','30',true,'number','min="1" max="3650"')
            . self::field('quota_gb',$fa?'حجم (GB)':'Quota (GB)','0',false,'number','min="0" step="0.1"')
            . self::field('provider_plan_key','Provider Plan Key','',false)
            . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">＋ ' . ($fa?'افزودن پلن':'Add plan') . '</button></div></form>';

        $out .= '<div class="provider-plan-list">';
        if (!$plans) $out .= '<div class="empty-state">' . ($fa?'هنوز پلنی برای این Provider ساخته نشده است.':'No plans have been created for this provider yet.') . '</div>';
        foreach ($plans as $plan) {
            $name = $fa ? (string)$plan['display_name_fa'] : (string)$plan['display_name_en'];
            $status = (bool)$plan['enabled'];
            $out .= '<article class="provider-plan-card ' . ($status?'':'is-disabled') . '"><div class="plan-main"><div class="plan-name">' . h($name) . '</div><div class="plan-meta">' . h((string)$plan['category_name_en']) . ' · ' . (int)$plan['duration_days'] . ' days · ' . h((string)$plan['quota_gb']) . ' GB · ' . number_format((int)$plan['price_minor']) . '</div></div><div class="plan-actions">'
                . self::miniForm($csrf,'toggle',(int)$plan['id'],$status?'Disable':'Enable','btn btn-secondary')
                . self::miniForm($csrf,'delete',(int)$plan['id'],$fa?'حذف':'Delete','btn btn-danger',true)
                . '</div></article>';

            $out .= '<details class="provider-plan-edit"><summary>✎ ' . ($fa?'ویرایش پلن':'Edit plan') . '</summary><form method="post"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="plan_action" value="update"><input type="hidden" name="id" value="' . (int)$plan['id'] . '"><input type="hidden" name="provider_key" value="' . h($provider) . '"><div class="grid">'
                . self::field('display_name_fa',$fa?'نام فارسی':'Persian name',(string)$plan['display_name_fa'],true)
                . self::field('display_name_en','English name',(string)$plan['display_name_en'],true)
                . '<div class="field"><label>' . ($fa?'دسته سرویس':'Service category') . '</label><select name="category_id" required>' . self::categoryOptions($categories,(int)$plan['category_id'],$fa) . '</select></div>'
                . '<div class="field"><label>' . ($fa?'سرور':'Server') . '</label><select name="provider_server_id"><option value="">' . ($fa?'خودکار / بدون سرور':'Auto / no server') . '</option>' . self::serverOptionsSelected($servers,$plan['provider_server_id']) . '</select></div>'
                . self::field('price_minor',$fa?'قیمت':'Price',(string)$plan['price_minor'],false,'number','min="0" step="1"')
                . self::field('duration_days',$fa?'مدت (روز)':'Duration (days)',(string)$plan['duration_days'],true,'number','min="1" max="3650"')
                . self::field('quota_gb',$fa?'حجم (GB)':'Quota (GB)',(string)$plan['quota_gb'],false,'number','min="0" step="0.1"')
                . self::field('provider_plan_key','Provider Plan Key',(string)($plan['provider_plan_key']??''),false)
                . '</div><div class="form-actions"><button class="btn btn-primary" type="submit">' . ($fa?'ذخیره تغییرات':'Save changes') . '</button></div></form></details>';
        }
        $out .= '</div></section>';
        return '<style>.provider-plans-page{margin-top:8px}.provider-plan-form{margin:18px 0 24px;padding:20px;border:1px solid var(--line);border-radius:18px;background:var(--card2)}.provider-plan-list{display:grid;gap:12px}.provider-plan-card{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:18px;border:1px solid var(--line);border-radius:16px;background:var(--card2)}.provider-plan-card.is-disabled{opacity:.62}.plan-name{font-weight:800;font-size:16px}.plan-meta{color:var(--muted);font-size:12px;margin-top:6px}.plan-actions{display:flex;gap:8px;flex-wrap:wrap}.provider-plan-edit{padding:0 18px 18px;border:1px solid var(--line);border-top:0;border-radius:0 0 16px 16px;background:var(--card2)}.provider-plan-edit summary{cursor:pointer;padding-top:12px;color:var(--muted);font-weight:700}.btn-danger{border-color:rgba(255,100,124,.35)!important;color:var(--red)!important}.empty-state{padding:30px;text-align:center;border:1px dashed var(--line);border-radius:16px;color:var(--muted)}@media(max-width:760px){.provider-plan-card{align-items:flex-start;flex-direction:column}.plan-actions{width:100%}}</style>' . $out;
    }

    private static function field(string $name,string $label,string $value='',bool $required=false,string $type='text',string $extra=''): string
    { return '<div class="field"><label>' . h($label) . '</label><input name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '" ' . ($required?'required ':'') . $extra . '></div>'; }
    private static function categoryOptions(array $rows,?int $selected,bool $fa): string
    { $html='<option value="">'.($fa?'انتخاب کنید':'Select').'</option>'; foreach($rows as $row){$name=$fa?(string)$row['name_fa']:(string)$row['name_en'];$html.='<option value="'.(int)$row['id'].'" '.($selected===(int)$row['id']?'selected':'').'>'.h($name).'</option>';} return $html; }
    private static function serverOptions(array $rows): string
    { $html=''; foreach($rows as $row)$html.='<option value="'.(int)$row['id'].'">'.h((string)$row['name']).'</option>'; return $html; }
    private static function serverOptionsSelected(array $rows,mixed $selected): string
    { $html=''; foreach($rows as $row)$html.='<option value="'.(int)$row['id'].'" '.((string)$selected===(string)$row['id']?'selected':'').'>'.h((string)$row['name']).'</option>'; return $html; }
    private static function miniForm(string $csrf,string $action,int $id,string $label,string $class,bool $confirm=false): string
    { return '<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="'.$csrf.'"><input type="hidden" name="plan_action" value="'.h($action).'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="provider_key" value="'.h((string)($_GET['provider']??'routebox')).'"><button class="'.h($class).'" type="submit"'.($confirm?' onclick="return confirm(\'Delete this plan?\')"':'').'>'.h($label).'</button></form>'; }
    private static function providerSection(string $provider): string
    { return match($provider){ 'ibsng'=>'ibsng','mikrotik_wireguard'=>'mikrotik',default=>'servers' }; }
}
