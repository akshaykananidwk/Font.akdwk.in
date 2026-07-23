<?php
/**
 * Scheduled backup — 0 2 * * 0 php /path/to/cron/backup.php (સાપ્તાહિક)
 */
require __DIR__ . '/bootstrap.php';

echo "[" . date('c') . "] Backup cron શરૂ\n";
$updater = new Updater();
$stamp = date('Ymd_His');
$version = Updater::localVersion()['version'] ?? APP_VERSION;

$dbPath = BACKUPS_PATH . "/db_{$version}_{$stamp}.sql";
$updater->backupDatabase($dbPath);
echo "DB backup: " . basename($dbPath) . " (" . Helper::formatBytes((int)filesize($dbPath)) . ")\n";

$filesPath = BACKUPS_PATH . "/files_{$version}_{$stamp}.zip";
$updater->backupFiles($filesPath);
echo "Files backup: " . basename($filesPath) . " (" . Helper::formatBytes((int)filesize($filesPath)) . ")\n";

$n = Updater::cleanupOldBackups();
echo "Old backups deleted: {$n}\n";
echo "[" . date('c') . "] Backup cron પૂરું\n";
