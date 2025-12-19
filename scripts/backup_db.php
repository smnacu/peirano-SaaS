<?php
/**
 * DB BACKUP SCRIPT
 * Usage: php scripts/backup_db.php
 */

require_once __DIR__ . '/../config/config.php';

// CLI Protection
if (php_sapi_name() !== 'cli') {
    die("CLI Only");
}

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$name = DB_NAME;

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    // Secure dir
    file_put_contents($backupDir . '/.htaccess', 'Deny from all');
}

$date = date('Y-m-d_H-i-s');
$filename = "backup_{$name}_{$date}.sql";
$filepath = "{$backupDir}/{$filename}";

echo "Starting backup for {$name}...\n";

// Command (Assuming mysqldump is in PATH)
// Warning: Putting password in CLI command is visible in process list processes. 
// Safer to use config file, but for standard shared hosting this is often accepted or use .my.cnf
$cmd = "mysqldump --host={$host} --user={$user} --password=\"{$pass}\" {$name} > \"{$filepath}\"";

exec($cmd, $output, $return);

if ($return === 0) {
    echo "Backup success: {$filepath}\n";
    // Optional: Delete old backups (> 30 days)
    // ...
} else {
    echo "Backup failed. Code: {$return}\n";
}
