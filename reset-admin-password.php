#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
if (function_exists('posix_geteuid') && posix_geteuid() !== 0) exit("Run as root: sudo php /opt/routebox-telegram-bot/reset-admin-password.php\n");

require __DIR__.'/src/bootstrap.php';

function prompt_secret(string $label): string {
    if (function_exists('shell_exec')) { @shell_exec('stty -echo'); }
    fwrite(STDOUT,$label);
    $value=trim((string)fgets(STDIN));
    if (function_exists('shell_exec')) { @shell_exec('stty echo'); }
    fwrite(STDOUT,"\n");
    return $value;
}

try {
    $new=prompt_secret('New admin password (min 12 chars): ');
    if (strlen($new)<12) throw new RuntimeException('Password must be at least 12 characters.');
    $confirm=prompt_secret('Confirm new password: ');
    if ($new!==$confirm) throw new RuntimeException('Passwords do not match.');
    set_admin_password_hash(password_hash($new,PASSWORD_DEFAULT));
    log_event('warning','Administrator password reset from the CLI recovery tool.');
    echo "✓ Admin password changed successfully.\n";
} catch (Throwable $e) {
    if (function_exists('shell_exec')) { @shell_exec('stty echo'); }
    fwrite(STDERR,"[ERROR] ".$e->getMessage()."\n");
    exit(1);
}
