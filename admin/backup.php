<?php
/**
 * બેકઅપ — DB dump download, files zip, manual backup, restore.
 */
$REQUIRE_SUPER = true;
$PAGE_TITLE = 'Backup';
require __DIR__ . '/includes/header.php';

$msg = '';
$err = '';

// ---- Download backup file ----
if (($file = (string)($_GET['download'] ?? '')) !== '') {
    $file = basename($file); // path traversal રક્ષણ
    $path = BACKUPS_PATH . '/' . $file;
    if (is_file($path) && (str_ends_with($file, '.sql') || str_ends_with($file, '.zip'))) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    $err = 'File not found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    @set_time_limit(0);
    try {
        $updater = new Updater();
        $stamp = date('Ymd_His');
        $version = Updater::localVersion()['version'] ?? APP_VERSION;
        if ($action === 'backup_db') {
            $path = BACKUPS_PATH . "/db_{$version}_{$stamp}.sql";
            $updater->backupDatabase($path);
            $msg = 'DB backup created: ' . basename($path) . ' (' . Helper::formatBytes((int)filesize($path)) . ')';
        } elseif ($action === 'backup_files') {
            $path = BACKUPS_PATH . "/files_{$version}_{$stamp}.zip";
            $updater->backupFiles($path);
            $msg = 'Files backup created: ' . basename($path) . ' (' . Helper::formatBytes((int)filesize($path)) . ')';
        } elseif ($action === 'restore') {
            // Password re-verify — sensitive!
            if (!Auth::reverifyAdminPassword((string)($_POST['admin_password'] ?? ''))) {
                throw new RuntimeException('Incorrect password.');
            }
            $filesZip = basename((string)($_POST['files_zip'] ?? ''));
            $dbSql = basename((string)($_POST['db_sql'] ?? ''));
            $filesPath = $filesZip !== '' ? BACKUPS_PATH . '/' . $filesZip : null;
            $dbPath = $dbSql !== '' ? BACKUPS_PATH . '/' . $dbSql : null;
            if (($filesPath && !is_file($filesPath)) || ($dbPath && !is_file($dbPath))) {
                throw new RuntimeException('Backup file not found.');
            }
            file_put_contents(MAINTENANCE_FLAG, date('c'));
            $ok = $updater->rollback($filesPath, $dbPath);
            @unlink(MAINTENANCE_FLAG);
            if (!$ok) {
                throw new RuntimeException('Restore failed — check the logs.');
            }
            $msg = 'Restore successful! The site has been reverted to the backup state.';
        } elseif ($action === 'cleanup') {
            $n = Updater::cleanupOldBackups();
            $msg = "{$n} old backups deleted (the latest " . MAX_BACKUPS_KEPT . " were kept).";
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'backup', []);
    } catch (Throwable $ex) {
        @unlink(MAINTENANCE_FLAG);
        $err = $ex->getMessage();
    }
}

$backups = Updater::listBackups();
$allFiles = array_merge(
    glob(BACKUPS_PATH . '/db_*.sql') ?: [],
    glob(BACKUPS_PATH . '/files_*.zip') ?: []
);
usort($allFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-card">
  <h2>New Backup</h2>
  <form method="post" class="inline"><?= Security::csrfField() ?>
    <input type="hidden" name="action" value="backup_db">
    <button class="abtn abtn-primary" type="submit">💾 Database Backup</button>
  </form>
  <form method="post" class="inline"><?= Security::csrfField() ?>
    <input type="hidden" name="action" value="backup_files">
    <button class="abtn abtn-primary" type="submit">📦 Files Backup (zip)</button>
  </form>
  <form method="post" class="inline"><?= Security::csrfField() ?>
    <input type="hidden" name="action" value="cleanup">
    <button class="abtn" type="submit">🗑 Clean Up Old Backups</button>
  </form>
  <p class="amuted">For scheduled backups, set up a cron job: <code>php cron/backup.php</code> (see README)</p>
</div>

<div class="admin-card">
  <h2>Available Backups</h2>
  <table class="atable">
    <tr><th>File</th><th>Size</th><th>Date</th><th></th></tr>
    <?php foreach ($allFiles as $f): $name = basename($f); ?>
    <tr>
      <td><code><?= $e($name) ?></code></td>
      <td><?= Helper::formatBytes((int)filesize($f)) ?></td>
      <td><?= date('Y-m-d H:i', (int)filemtime($f)) ?></td>
      <td><a class="abtn abtn-xs" href="?download=<?= urlencode($name) ?>">⬇ Download</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$allFiles): ?><tr><td class="amuted" colspan="4">No backups.</td></tr><?php endif; ?>
  </table>
</div>

<div class="admin-card danger-zone">
  <h2>⚠ Manual Restore (Rollback)</h2>
  <p class="amuted">The site will be reverted to the selected backup. <strong>Current data will be overwritten!</strong></p>
  <form method="post" onsubmit="return confirm('Are you sure you want to restore? Current data will be overwritten!')">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="restore">
    <label>Backup Set</label>
    <select name="files_zip" id="restoreFiles">
      <option value="">— files backup (optional) —</option>
      <?php foreach ($backups as $b): ?>
        <option value="<?= $e($b['files_zip']) ?>" data-db="<?= $e($b['db_sql'] ?? '') ?>"><?= $e($b['files_zip']) ?> (<?= $e($b['date']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <label>DB Backup</label>
    <select name="db_sql">
      <option value="">— db backup (optional) —</option>
      <?php foreach (glob(BACKUPS_PATH . '/db_*.sql') ?: [] as $f): ?>
        <option value="<?= $e(basename($f)) ?>"><?= $e(basename($f)) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Your Admin Password (to confirm)</label>
    <input type="password" name="admin_password" required autocomplete="current-password">
    <button class="abtn abtn-danger" type="submit">⏪ Restore</button>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
