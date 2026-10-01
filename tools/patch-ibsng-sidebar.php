<?php
declare(strict_types=1);

// Safely adds IBSng to the existing RouteBox sidebar without replacing index.php.
// Creates a timestamped backup before changing anything.
$index = __DIR__ . '/../public/index.php';
if (!is_file($index) || !is_writable($index)) {
    fwrite(STDERR, "ERROR: public/index.php is missing or not writable.\n");
    exit(1);
}

$src = (string) file_get_contents($index);
$backup = $index . '.bak-ibsng-' . date('Ymd-His');

if (str_contains($src, "'ibsng'")) {
    echo "IBSng sidebar patch already present. Nothing to do.\n";
    exit(0);
}

$oldAllowed = "\$allowedSections = ['dashboard', 'servers', 'bot', 'plans', 'security', 'updates'];";
$newAllowed = "\$allowedSections = ['dashboard', 'servers', 'bot', 'plans', 'ibsng', 'security', 'updates'];";
if (!str_contains($src, $oldAllowed)) {
    fwrite(STDERR, "ERROR: RouteBox section declaration was not found; no changes made.\n");
    exit(2);
}

$oldNav = "foreach(['dashboard','servers','bot','plans','security','updates'] as \$key)";
$newNav = "foreach(['dashboard','servers','bot','plans','ibsng','security','updates'] as \$key)";
if (!str_contains($src, $oldNav)) {
    fwrite(STDERR, "ERROR: RouteBox sidebar navigation was not found; no changes made.\n");
    exit(3);
}

// Keep the existing panel layout intact. Only insert the new navigation entry and
// minimal translations. The actual IBSng UI remains in its isolated module.
$src = str_replace($oldAllowed, $newAllowed, $src);
$src = str_replace($oldNav, $newNav, $src);

$faNeedle = "'dash'=>'داشبورد','servers'=>'سرورها','bot'=>'ربات تلگرام','plans'=>'پلن‌ها','security'=>'امنیت','updates'=>'به‌روزرسانی',";
$faReplace = "'dash'=>'داشبورد','servers'=>'سرورها','bot'=>'ربات تلگرام','plans'=>'پلن‌ها','ibsng'=>'IBSng','security'=>'امنیت','updates'=>'به‌روزرسانی',";
$enNeedle = "'dash'=>'Dashboard','servers'=>'Servers','bot'=>'Telegram Bot','plans'=>'Plans','security'=>'Security','updates'=>'Updates',";
$enReplace = "'dash'=>'Dashboard','servers'=>'Servers','bot'=>'Telegram Bot','plans'=>'Plans','ibsng'=>'IBSng','security'=>'Security','updates'=>'Updates',";
if (!str_contains($src, $faNeedle) || !str_contains($src, $enNeedle)) {
    fwrite(STDERR, "ERROR: Navigation translations were not found; no changes made.\n");
    exit(4);
}
$src = str_replace($faNeedle, $faReplace, $src);
$src = str_replace($enNeedle, $enReplace, $src);

// The existing navIcon() has no IBSng icon. Add a small plug/server icon.
$iconNeedle = "'updates'=>'<svg viewBox=\"0 0 24 24\"><path d=\"M20 11a8 8 0 0 0-14.9-4L3 10m0 0V5m0 5h5M4 13a8 8 0 0 0 14.9 4L21 14m0 0v5m0-5h-5\"/></svg>',";
$iconReplace = $iconNeedle . "\n        'ibsng'=>'<svg viewBox=\"0 0 24 24\"><rect x=\"4\" y=\"4\" width=\"16\" height=\"16\" rx=\"3\"/><path d=\"M8 8h8M8 12h8M8 16h5\"/></svg>',";
if (!str_contains($src, $iconNeedle)) {
    fwrite(STDERR, "ERROR: navIcon() marker was not found; no changes made.\n");
    exit(5);
}
$src = str_replace($iconNeedle, $iconReplace, $src);

if (file_put_contents($backup, $src === '' ? '' : $src) === false) {
    fwrite(STDERR, "ERROR: Could not create backup. No changes made.\n");
    exit(6);
}
// Re-read the original after backup generation; backup intentionally contains the
// original source, not the patched source.
$original = (string) file_get_contents($index);
if (file_put_contents($backup, $original, LOCK_EX) === false || file_put_contents($index, $src, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR: Could not write the sidebar patch. Restore from $backup if needed.\n");
    exit(7);
}

echo "OK: IBSng was added to the existing RouteBox sidebar.\n";
echo "Backup: $backup\n";
