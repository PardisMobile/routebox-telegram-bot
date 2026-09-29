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
    $new=prompt_secret('New admin password (min 8 chars): ');
    if (strlen($new)<8) throw new RuntimeException('Password must be at least 8 characters.');
    $confirm=prompt_secret('Confirm new password: ');
    if ($new!==$confirm) throw new RuntimeException('Passwords do not match.');
    set_admin_password_hash(password_hash($new,PASSWORD_DEFAULT));
    log_event('warning','Administrator password reset from the CLI recovery tool.');
    echo "✓ Admin password changed successfully.\n";
} catch (Throwable $e) {
    if (function_exists('shell_exec')) { @shell_exec('stty echo'); }
    $message=$e->getMessage();
    if (stripos($message,'database is locked')!==false) {
        $message='Database is busy/locked. Stop the RouteBox web/bot service temporarily, run this recovery command again, then start the service again.';
    }
    fwrite(STDERR,"[ERROR] ".$message."\n");
    exit(1);
}
