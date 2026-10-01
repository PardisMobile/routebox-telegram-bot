<?php
declare(strict_types=1);

$requestedSection = (string)($_GET['section'] ?? 'dashboard');
$isIbsng = $requestedSection === 'ibsng';

if ($isIbsng) {
    // Render IBSng inside the existing RouteBox Admin shell. The legacy
    // public/ibsng.php remains the controller/view source, but its standalone
    // HTML shell/sidebar are intentionally discarded here.
    // Process IBSng POST actions in the IBSng controller, not RouteBox core.
    $savedGet = $_GET;
    $savedPost = $_POST;
    ob_start();
    require __DIR__ . '/ibsng.php';
    $ibsngPage = (string)ob_get_clean();
    $_GET = $savedGet;
    $_POST = [];
    $_GET['section'] = 'dashboard';
    ob_start();
    require __DIR__ . '/index.core.php';
    $html = (string)ob_get_clean();
    $_GET = $savedGet;
    $_POST = $savedPost;

    if (preg_match('~<main class="content">(.*?)</main>~s', $ibsngPage, $m)) {
        $ibsngContent = trim($m[1]);
    } else {
        $ibsngContent = '<div class="card"><div class="flash">IBSng view could not be loaded.</div></div>';
    }

    // The old IBSng page was Persian-only. Follow the same language stored by
    // the main panel so ?section=ibsng behaves like every other section.
    $lang = (string)($_SESSION['panel_lang'] ?? 'fa') === 'en' ? 'en' : 'fa';
    if ($lang === 'en') {
        $translations = [
            'مدیریت IBSng' => 'IBSng Management',
            'اتصال و مدیریت IBSng A1.24 از طریق Web Panel. اطلاعات ورود رمزنگاری می‌شوند و هیچ‌وقت در صفحه نمایش داده نمی‌شوند.' => 'Connect and manage IBSng A1.24 through the Web Panel. Credentials are encrypted and never displayed.',
            '← بازگشت به داشبورد' => '← Back to Dashboard',
            '➕ افزودن سرور IBSng' => '➕ Add IBSng Server',
            'قبل از ذخیره، اتصال Web Panel تست می‌شود و گروه‌ها همان لحظه Sync می‌شوند.' => 'The Web Panel connection is tested before saving and groups are synced immediately.',
            'نام سرور' => 'Server name',
            'پورت Web Panel' => 'Web Panel port',
            'Admin Username' => 'Admin Username',
            'Admin Password' => 'Admin Password',
            'تست اتصال و ذخیره' => 'Test connection & save',
            '🖥 سرورهای IBSng' => '🖥 IBSng Servers',
            'نام، Host، پورت و ISP قابل ویرایش هستند. برای امنیت، Username و Password ذخیره‌شده هرگز نمایش داده نمی‌شوند.' => 'Server name, Host, port and ISP are editable. Stored username and password are never displayed for security.',
            'هنوز هیچ سرور IBSng اضافه نشده است.' => 'No IBSng servers have been added yet.',
            '● اتصال موفق' => '● Connected',
            '● خطای اتصال' => '● Connection error',
            '● تست نشده' => '● Not tested',
            'دسترسی‌ها' => 'Credentials',
            'Credentialها رمزنگاری‌شده نگهداری می‌شوند.' => 'Credentials are stored encrypted.',
            'گروه‌های Sync شده' => 'Synced groups',
            'گروهی Sync نشده است.' => 'No groups synced.',
            'تست اتصال' => 'Test connection',
            'Sync گروه‌ها' => 'Sync groups',
            '✏️ ویرایش اطلاعات سرور' => '✏️ Edit server information',
            'ذخیره تغییرات' => 'Save changes',
        ];
        $ibsngContent = strtr($ibsngContent, $translations);
    }

    $dir = $lang === 'fa' ? 'rtl' : 'ltr';
    $ibsngContent = '<div class="ibsng-module" dir="' . $dir . '">' . $ibsngContent . '</div>';

    $header = $lang === 'fa'
        ? '<header class="topbar"><div><div class="eyebrow">IBSNG SERVICE MODULE</div><h1>مدیریت IBSng</h1><p>اتصال و مدیریت IBSng A1.24 از طریق Web Panel</p></div><div class="top-actions"><span class="chip keep"><span class="dot"></span>فعال</span><span class="chip keep">IBSng A1.24</span><button class="icon-button" id="themeBtn" type="button" aria-label="تغییر تم"><svg viewBox="0 0 24 24"><path d="M12 3v2m0 14v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M3 12h2m14 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/><circle cx="12" cy="12" r="4"/></svg></button></div></header>'
        : '<header class="topbar"><div><div class="eyebrow">IBSNG SERVICE MODULE</div><h1>IBSng Management</h1><p>Connect and manage IBSng A1.24 through the Web Panel</p></div><div class="top-actions"><span class="chip keep"><span class="dot"></span>Active</span><span class="chip keep">IBSng A1.24</span><button class="icon-button" id="themeBtn" type="button" aria-label="Toggle theme"><svg viewBox="0 0 24 24"><path d="M12 3v2m0 14v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M3 12h2m14 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/><circle cx="12" cy="12" r="4"/></svg></button></div></header>';

    $html = preg_replace('~(<main class="main">).*?(<div class="footer">.*?</div>\s*</main>)~s', '$1' . $header . $ibsngContent . '$2', $html, 1);

    // Mark IBSng as the active item and add it to the same sidebar as the
    // dashboard. No second sidebar is rendered.
    $ibsngLink = '<a class="active" href="/?section=ibsng" aria-current="page"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg><span>IBSng</span></a>';
    $html = str_replace('</nav>', $ibsngLink . '\n  </nav>', $html, $count);

    // Compatibility styling for the legacy IBSng fragment using the main
    // RouteBox Admin visual system instead of its old standalone CSS.
    $css = '<style>.ibsng-module{width:100%}.ibsng-module .content{padding:0;max-width:none;direction:inherit}.ibsng-module .topline{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:18px}.ibsng-module .title{font-size:24px;line-height:1.2;margin:0 0 5px;font-weight:800}.ibsng-module .subtitle{color:var(--muted);margin:0;max-width:850px;font-size:13px;line-height:1.7}.ibsng-module .card{margin-bottom:16px}.ibsng-module .field input,.ibsng-module .field select{background:rgba(2,7,18,.34);color:var(--text);border:1px solid var(--line)}.ibsng-module .btn{border:1px solid transparent}.ibsng-module .btn.secondary{color:var(--text);background:rgba(255,255,255,.05);border-color:var(--line)}.ibsng-module .server{background:rgba(255,255,255,.025);border-color:var(--line)}.ibsng-module .server-edit{border-color:var(--line)}.ibsng-module .security-note{background:rgba(2,7,18,.22);border-color:var(--line)}.ibsng-module .back{color:var(--primary)}.ibsng-module .flash.err{color:var(--red);background:rgba(255,100,124,.07);border-color:rgba(255,100,124,.2)}@media(max-width:900px){.ibsng-module .topline{display:block}.ibsng-module .topline .back{display:inline-block;margin-top:10px}}</style>';
    $html = str_replace('</head>', $css . '</head>', $html, 1);

    echo $html;
    exit;
}

ob_start();
require __DIR__ . '/index.core.php';
$html = (string)ob_get_clean();

$ibsngLink = <<<'HTML'
    <a class="ibsng-nav" href="/?section=ibsng" aria-label="IBSng">
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg><span>IBSng</span>
    </a>
HTML;

if (strpos($html, 'class="ibsng-nav"') === false) {
    $html = str_replace('</nav>', $ibsngLink . "\n  </nav>", $html, $count);
    $html = str_replace('</head>', '<style>@media(max-width:800px){.nav{grid-template-columns:repeat(7,minmax(0,1fr))}}</style>\n</head>', $html, $count);
}

echo $html;
