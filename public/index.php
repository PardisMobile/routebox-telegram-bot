<?php
declare(strict_types=1);

// Keep the existing RouteBox Admin UI intact and expose IBSng as a separate
// module entry point. This wrapper intentionally avoids changing RouteBox core.
if ((string)($_GET['section'] ?? '') === 'ibsng') {
    require __DIR__ . '/ibsng.php';
    exit;
}

ob_start();
require __DIR__ . '/index.core.php';
$html = (string)ob_get_clean();

$ibsngLink = <<<'HTML'
    <a class="ibsng-nav" href="/ibsng.php" aria-label="IBSng">
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8M8 13h5M8 17h3"/></svg><span>IBSng</span>
    </a>
HTML;

if (strpos($html, 'class="ibsng-nav"') === false) {
    // Add the service entry after the existing RouteBox navigation items,
    // rather than before Dashboard.
    $html = str_replace('</nav>', $ibsngLink . "\n  </nav>", $html, $count);
    $html = str_replace('</head>', '<style>@media(max-width:800px){.nav{grid-template-columns:repeat(7,minmax(0,1fr))}}</style>\n</head>', $html, $count);
}

echo $html;
