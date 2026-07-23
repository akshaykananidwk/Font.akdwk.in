<?php
/**
 * GitHub One-Click Auto-Update સિસ્ટમ.
 *
 * પ્રક્રિયા (વિભાગ 8):
 *  1. Pre-flight checks    2. Maintenance ON      3. Auto backup (DB + files)
 *  4. Download zipball     5. Extract             6. Protected files check
 *  7. Replace files        8. DB migrations       9. Finalize (cache, version)
 * 10. ભૂલ આવે તો AUTO ROLLBACK — સાઇટ 100% પહેલા જેવી.
 *
 * Progress: storage/temp/update_progress.json માં લખાય છે (admin UI polling માટે).
 * GitHub token DB માં AES-256-GCM encrypted રહે છે, log માં masked.
 */

defined('BASE_PATH') or die('Direct access denied');

class Updater
{
    private const PROGRESS_FILE = TEMP_PATH . '/update_progress.json';
    private array $log = [];
    private ?string $dbBackupPath = null;
    private ?string $filesBackupPath = null;

    // ---------------------------------------------------------------
    //  Settings
    // ---------------------------------------------------------------

    /** Update settings મેળવો (token decrypt કરીને). */
    public static function getSettings(): array
    {
        return [
            'repo'      => (string)App::setting('github_repo', ''),
            'branch'    => (string)App::setting('github_branch', 'main'),
            'token'     => Helper::decrypt((string)App::setting('github_token_encrypted', '')),
            'frequency' => (string)App::setting('update_check_frequency', 'manual'),
        ];
    }

    /** Settings સેવ કરો — token encrypt થઈને જ DB માં જાય. */
    public static function saveSettings(string $repo, string $branch, ?string $token, string $frequency): void
    {
        if (!preg_match('#^[\w.\-]+/[\w.\-]+$#', $repo)) {
            throw new InvalidArgumentException('Repository format: username/repo');
        }
        if (!preg_match('#^[\w.\-/]+$#', $branch)) {
            throw new InvalidArgumentException('Invalid branch name');
        }
        App::setSetting('github_repo', $repo, 'update');
        App::setSetting('github_branch', $branch, 'update');
        App::setSetting('update_check_frequency', in_array($frequency, ['daily', 'weekly', 'manual'], true) ? $frequency : 'manual', 'update');
        // ખાલી token = હાલનો રાખો (ફરી type કરવાની જરૂર નહીં)
        if ($token !== null && $token !== '') {
            App::setSetting('github_token_encrypted', Helper::encrypt($token), 'update');
        }
    }

    // ---------------------------------------------------------------
    //  GitHub API
    // ---------------------------------------------------------------

    /**
     * GitHub API request (cURL).
     *
     * @param string $url  full URL
     * @param string|null $saveTo file path — આપો તો response ફાઇલમાં stream થાય
     */
    private static function githubRequest(string $url, ?string $saveTo = null): array
    {
        $settings = self::getSettings();
        $ch = curl_init($url);
        $headers = [
            'User-Agent: GFC-Updater/' . APP_VERSION,
            'Accept: application/vnd.github+json',
        ];
        if ($settings['token'] !== '') {
            $headers[] = 'Authorization: token ' . $settings['token'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => $saveTo === null,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $fp = null;
        if ($saveTo !== null) {
            $fp = fopen($saveTo, 'wb');
            if ($fp === false) {
                curl_close($ch);
                throw new RuntimeException("Cannot open {$saveTo} for writing");
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
        }
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($fp !== null) {
            fclose($fp);
        }
        if ($err !== '') {
            throw new RuntimeException('GitHub request failed: ' . $err);
        }
        if ($status === 401 || $status === 403) {
            throw new RuntimeException("GitHub authentication failed (HTTP {$status}) — check your token");
        }
        if ($status === 404) {
            throw new RuntimeException('GitHub resource not found (HTTP 404) — check the repo/branch');
        }
        if ($status >= 400) {
            throw new RuntimeException("GitHub API error (HTTP {$status})");
        }
        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }

    /**
     * "Check for Update" — latest commit + remote version.json.
     */
    public static function checkForUpdate(): array
    {
        $settings = self::getSettings();
        if ($settings['repo'] === '') {
            throw new RuntimeException('Configure the GitHub repository first (Update → Settings)');
        }
        $repo = $settings['repo'];
        $branch = $settings['branch'];

        // Latest commit
        $commitRes = self::githubRequest("https://api.github.com/repos/{$repo}/commits/{$branch}");
        $commit = json_decode($commitRes['body'], true);
        if (!is_array($commit) || empty($commit['sha'])) {
            throw new RuntimeException('Commit info not found');
        }

        // Remote version.json
        $remoteVersion = null;
        try {
            $verRes = self::githubRequest("https://raw.githubusercontent.com/{$repo}/{$branch}/version.json");
            $remoteVersion = json_decode($verRes['body'], true);
        } catch (Throwable $e) {
            // version.json ન હોય તો commit-based comparison
        }

        // Changelog (optional)
        $changelog = null;
        try {
            $clRes = self::githubRequest("https://raw.githubusercontent.com/{$repo}/{$branch}/CHANGELOG.md");
            $changelog = mb_substr($clRes['body'], 0, 4000);
        } catch (Throwable $e) {
            // optional
        }

        $localVersion = self::localVersion();
        $remoteVer = $remoteVersion['version'] ?? null;
        $updateAvailable = $remoteVer !== null
            ? version_compare($remoteVer, $localVersion['version'], '>')
            : false;

        // નવી migrations છે?
        $newMigrations = [];
        try {
            $db = Database::getInstance();
            $ran = array_column(
                $db->fetchAll("SELECT migration_file FROM `" . $db->table('migrations') . "`"),
                'migration_file'
            );
            $treeRes = self::githubRequest("https://api.github.com/repos/{$repo}/contents/migrations?ref={$branch}");
            $tree = json_decode($treeRes['body'], true);
            if (is_array($tree)) {
                foreach ($tree as $item) {
                    $name = $item['name'] ?? '';
                    if (str_ends_with($name, '.sql') && !in_array($name, $ran, true)) {
                        $newMigrations[] = $name;
                    }
                }
            }
        } catch (Throwable $e) {
            // migrations folder ન હોય તો ઠીક
        }

        return [
            'current_version' => $localVersion['version'],
            'remote_version'  => $remoteVer,
            'update_available' => $updateAvailable,
            'is_latest'       => !$updateAvailable,
            'commit_hash'     => substr((string)$commit['sha'], 0, 8),
            'commit_full_sha' => (string)$commit['sha'],
            'commit_message'  => (string)($commit['commit']['message'] ?? ''),
            'commit_date'     => (string)($commit['commit']['committer']['date'] ?? ''),
            'commit_author'   => (string)($commit['commit']['author']['name'] ?? ''),
            'files_changed'   => count($commit['files'] ?? []),
            'changelog'       => $changelog,
            'new_migrations'  => $newMigrations,
        ];
    }

    /** Local version.json વાંચો. */
    public static function localVersion(): array
    {
        $data = json_decode((string)@file_get_contents(BASE_PATH . '/version.json'), true);
        return is_array($data) ? $data : ['version' => APP_VERSION, 'db_version' => DB_SCHEMA_VERSION];
    }

    // ---------------------------------------------------------------
    //  Progress reporting
    // ---------------------------------------------------------------

    /** Progress લખો (admin UI polling માટે). */
    private function progress(int $step, string $message, string $state = 'running'): void
    {
        $masked = preg_replace('/(token|ghp_|github_pat_)[A-Za-z0-9_]+/i', '$1***MASKED***', $message);
        $this->log[] = '[' . date('H:i:s') . "] Step {$step}: {$masked}";
        Logger::info("Update step {$step}: {$masked}", 'update');
        @file_put_contents(self::PROGRESS_FILE, json_encode([
            'step'    => $step,
            'total'   => 9,
            'state'   => $state,
            'message' => $masked,
            'log'     => $this->log,
            'time'    => time(),
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** હાલની progress વાંચો (AJAX endpoint માટે). */
    public static function readProgress(): ?array
    {
        $data = json_decode((string)@file_get_contents(self::PROGRESS_FILE), true);
        return is_array($data) ? $data : null;
    }

    // ---------------------------------------------------------------
    //  મુખ્ય update પ્રક્રિયા
    // ---------------------------------------------------------------

    /**
     * "Update Now" — આખી પ્રક્રિયા. Exception આવે તો auto-rollback.
     *
     * @param bool $dryRun true = ફક્ત બતાવો શું બદલાશે, ખરેખર કંઈ ન બદલો
     */
    public function run(bool $dryRun = false): array
    {
        $db = Database::getInstance();
        $check = self::checkForUpdate();
        $settings = self::getSettings();
        $fromVersion = $check['current_version'];
        $toVersion = $check['remote_version'] ?? $check['commit_hash'];
        $logId = null;

        // ---- Step 1: Pre-flight ----
        $this->progress(1, 'Starting pre-flight checks...');
        if (is_file(UPDATE_LOCK_FILE) && (time() - (int)filemtime(UPDATE_LOCK_FILE)) < 1800) {
            throw new RuntimeException('Another update is already running (update.lock). Try again in 30 minutes.');
        }
        @file_put_contents(UPDATE_LOCK_FILE, (string)getmypid());
        @set_time_limit(0);
        ignore_user_abort(true);

        $freeSpace = (float)@disk_free_space(BASE_PATH);
        $needed = 200 * 1024 * 1024; // અંદાજિત: zip size × 3 ≈ 200MB સલામત મર્યાદા
        if ($freeSpace > 0 && $freeSpace < $needed) {
            @unlink(UPDATE_LOCK_FILE);
            throw new RuntimeException('Not enough disk space: ' . Helper::formatBytes((int)$freeSpace) . ' free');
        }
        foreach ([BASE_PATH, STORAGE_PATH, TEMP_PATH, BACKUPS_PATH] as $dir) {
            if (!is_writable($dir)) {
                @unlink(UPDATE_LOCK_FILE);
                throw new RuntimeException("Not writable: {$dir}");
            }
        }
        $this->progress(1, 'Pre-flight OK — disk: ' . Helper::formatBytes((int)$freeSpace) . ' free');

        try {
            $logId = $db->insert('updates_log', [
                'from_version'   => $fromVersion,
                'to_version'     => (string)$toVersion,
                'commit_hash'    => $check['commit_hash'],
                'commit_message' => mb_substr($check['commit_message'], 0, 1000),
                'status'         => 'running',
                'started_at'     => date('Y-m-d H:i:s'),
            ]);

            // ---- Step 2: Maintenance ON ----
            if (!$dryRun) {
                $this->progress(2, 'Turning on maintenance mode...');
                file_put_contents(MAINTENANCE_FLAG, date('c'));
            } else {
                $this->progress(2, '[DRY RUN] Maintenance skipped');
            }

            // ---- Step 3: Auto Backup (ફરજિયાત) ----
            $this->progress(3, 'Backing up the database...');
            $stamp = date('Ymd_His');
            $this->dbBackupPath = BACKUPS_PATH . "/db_{$fromVersion}_{$stamp}.sql";
            $this->backupDatabase($this->dbBackupPath);
            if (!is_file($this->dbBackupPath) || filesize($this->dbBackupPath) < 100) {
                throw new RuntimeException('DB backup verification failed — update stopped');
            }
            $this->progress(3, 'DB backup OK: ' . Helper::formatBytes((int)filesize($this->dbBackupPath)));

            $this->progress(3, 'Backing up files...');
            $this->filesBackupPath = BACKUPS_PATH . "/files_{$fromVersion}_{$stamp}.zip";
            $this->backupFiles($this->filesBackupPath);
            $zipTest = new ZipArchive();
            if (!is_file($this->filesBackupPath)
                || filesize($this->filesBackupPath) < 100
                || $zipTest->open($this->filesBackupPath) !== true) {
                throw new RuntimeException('Files backup verification failed — update stopped');
            }
            $zipTest->close();
            $this->progress(3, 'Files backup OK: ' . Helper::formatBytes((int)filesize($this->filesBackupPath)));

            // ---- Step 4: Download ----
            $this->progress(4, 'Downloading from GitHub...');
            $zipPath = TEMP_PATH . '/update.zip';
            @unlink($zipPath);
            self::githubRequest(
                "https://api.github.com/repos/{$settings['repo']}/zipball/{$settings['branch']}",
                $zipPath
            );
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('The downloaded ZIP is invalid');
            }
            $this->progress(4, 'Download OK: ' . Helper::formatBytes((int)filesize($zipPath)) . " ({$zip->numFiles} files)");

            // ---- Step 5: Extract ----
            $this->progress(5, 'Extracting...');
            $extractDir = TEMP_PATH . '/extracted';
            Helper::rrmdir($extractDir);
            mkdir($extractDir, 0755, true);
            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                throw new RuntimeException('Extraction failed');
            }
            $zip->close();
            // GitHub zip માં એક root folder (repo-hash/) હોય છે
            $rootDirs = glob($extractDir . '/*', GLOB_ONLYDIR) ?: [];
            if (count($rootDirs) !== 1) {
                throw new RuntimeException('Unexpected ZIP structure');
            }
            $sourceDir = $rootDirs[0];
            $this->progress(5, 'Extract OK: ' . basename($sourceDir));

            // ---- Step 6: Protected files list ----
            $this->progress(6, 'Checking protected files...');
            $protected = self::protectedPaths();
            $this->progress(6, 'Protected: ' . implode(', ', $protected));

            // ---- Step 7: Replace files ----
            $stats = ['copied' => 0, 'skipped' => 0, 'deleted' => 0];
            if ($dryRun) {
                $this->progress(7, '[DRY RUN] Building the list of file changes...');
                $changes = $this->collectChanges($sourceDir, $protected);
                $this->progress(7, '[DRY RUN] ' . count($changes['copy']) . ' file(s) will change, '
                    . count($changes['skip']) . ' protected file(s) will be skipped');
                $this->finalizeDryRun($logId, $changes);
                return [
                    'success' => true,
                    'dry_run' => true,
                    'changes' => $changes,
                    'log'     => $this->log,
                ];
            }
            $this->progress(7, 'Replacing files...');
            $this->copyTree($sourceDir, BASE_PATH, $protected, $stats);
            // delete_manifest.txt — repo માંથી delete થયેલી ફાઇલો (safety: manual list)
            $manifest = $sourceDir . '/delete_manifest.txt';
            if (is_file($manifest)) {
                foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $rel = trim($line);
                    if ($rel === '' || str_starts_with($rel, '#') || self::isProtected($rel, $protected)) {
                        continue;
                    }
                    $target = BASE_PATH . '/' . ltrim($rel, '/');
                    $real = realpath($target);
                    if ($real !== false && str_starts_with($real, (string)realpath(BASE_PATH)) && is_file($real)) {
                        @unlink($real);
                        $stats['deleted']++;
                        $this->progress(7, "Deleted: {$rel}");
                    }
                }
            }
            $this->progress(7, "Files: {$stats['copied']} copied, {$stats['skipped']} protected skipped, {$stats['deleted']} deleted");

            // ---- Step 8: DB Migrations ----
            $this->progress(8, 'Running database migrations...');
            $ranMigrations = $this->runMigrations();
            $this->progress(8, $ranMigrations === []
                ? 'No new migrations'
                : 'Migrations: ' . implode(', ', $ranMigrations));

            // ---- Step 9: Finalize ----
            $this->progress(9, 'Clearing cache + finalizing...');
            Cache::clear();
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            Helper::rrmdir($extractDir);
            @unlink($zipPath);
            self::cleanupOldBackups();

            $newLocal = self::localVersion();
            $db->update('updates_log', [
                'status'       => 'success',
                'to_version'   => $newLocal['version'] ?? (string)$toVersion,
                'backup_path'  => basename((string)$this->filesBackupPath),
                'log_output'   => implode("\n", $this->log),
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$logId]);

            @unlink(MAINTENANCE_FLAG);
            @unlink(UPDATE_LOCK_FILE);
            $this->progress(9, "Update successful! {$fromVersion} → " . ($newLocal['version'] ?? $toVersion), 'success');

            // Admin ને email
            $adminEmail = App::config()['site']['admin_email'] ?? '';
            if ($adminEmail !== '') {
                Mailer::sendTemplate(
                    $adminEmail,
                    '✅ Site update successful — v' . ($newLocal['version'] ?? $toVersion),
                    'Your site was updated successfully.<br>Version: ' . Helper::e($fromVersion)
                    . ' → ' . Helper::e((string)($newLocal['version'] ?? $toVersion))
                    . '<br>Commit: ' . Helper::e($check['commit_hash'])
                );
            }

            return ['success' => true, 'log' => $this->log];
        } catch (Throwable $e) {
            // ---- Step 10: AUTO ROLLBACK ----
            $this->progress(10, '❌ Error: ' . $e->getMessage() . ' — starting rollback...', 'error');
            $rolledBack = $this->rollback();
            @unlink(MAINTENANCE_FLAG);
            @unlink(UPDATE_LOCK_FILE);
            try {
                if ($logId !== null) {
                    $db->update('updates_log', [
                        'status'       => $rolledBack ? 'rolled_back' : 'failed',
                        'log_output'   => implode("\n", $this->log) . "\nERROR: " . $e->getMessage(),
                        'backup_path'  => basename((string)$this->filesBackupPath),
                        'completed_at' => date('Y-m-d H:i:s'),
                    ], 'id = ?', [$logId]);
                }
            } catch (Throwable $e2) {
                Logger::error('updates_log update failed: ' . $e2->getMessage(), 'update');
            }
            $adminEmail = App::config()['site']['admin_email'] ?? '';
            if ($adminEmail !== '') {
                Mailer::sendTemplate(
                    $adminEmail,
                    '⚠ Site update ' . ($rolledBack ? 'rolled back' : 'failed'),
                    'Update error: ' . Helper::e($e->getMessage())
                    . '<br>Rollback: ' . ($rolledBack ? 'successful — the site is running as before' : 'manual restore required!')
                );
            }
            $this->progress(10, $rolledBack
                ? '✓ Rollback successful — the site is running as before'
                : '✗ Rollback failed — restore manually from storage/backups', 'rolled_back');
            throw $e;
        }
    }

    // ---------------------------------------------------------------
    //  Backup / Restore
    // ---------------------------------------------------------------

    /**
     * Pure-PHP database dump (mysqldump ની જરૂર નહીં — shared hosting friendly).
     * Statements વચ્ચે "-- STMT_END" marker મૂકાય છે — restore split માટે.
     */
    public function backupDatabase(string $path): void
    {
        $db = Database::getInstance();
        $pdo = $db->pdo();
        $fp = fopen($path, 'wb');
        if ($fp === false) {
            throw new RuntimeException("Could not open backup file: {$path}");
        }
        fwrite($fp, "-- GFC Database Backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n-- STMT_END\n");
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n-- STMT_END\n");
            fwrite($fp, $create[1] . ";\n-- STMT_END\n");
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $batch = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                    array_values($row)
                );
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 200) {
                    fwrite($fp, "INSERT INTO `{$table}` VALUES " . implode(',', $batch) . ";\n-- STMT_END\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                fwrite($fp, "INSERT INTO `{$table}` VALUES " . implode(',', $batch) . ";\n-- STMT_END\n");
            }
        }
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n-- STMT_END\n");
        fclose($fp);
    }

    /**
     * Files backup — આખો project zip (storage/backups, temp, cache, logs exclude).
     */
    public function backupFiles(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create backup zip: {$path}");
        }
        $excludes = ['storage/backups', 'storage/temp', 'storage/cache', 'storage/logs', '.git'];
        $baseLen = strlen(BASE_PATH) + 1;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS),
                function ($file) use ($baseLen, $excludes) {
                    $rel = str_replace('\\', '/', substr($file->getPathname(), $baseLen));
                    foreach ($excludes as $ex) {
                        if ($rel === $ex || str_starts_with($rel, $ex . '/')) {
                            return false;
                        }
                    }
                    return true;
                }
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), $baseLen));
                $zip->addFile($file->getPathname(), $rel);
            }
        }
        $zip->close();
    }

    /**
     * Rollback — files + DB backup restore.
     *
     * @return bool rollback સફળ?
     */
    public function rollback(?string $filesBackup = null, ?string $dbBackup = null): bool
    {
        $filesBackup = $filesBackup ?? $this->filesBackupPath;
        $dbBackup = $dbBackup ?? $this->dbBackupPath;
        $ok = true;

        // 1. Files restore
        if ($filesBackup !== null && is_file($filesBackup)) {
            try {
                $zip = new ZipArchive();
                if ($zip->open($filesBackup) === true) {
                    $zip->extractTo(BASE_PATH);
                    $zip->close();
                    $this->progress(10, 'Files restored');
                } else {
                    $ok = false;
                }
            } catch (Throwable $e) {
                Logger::error('Files rollback failed: ' . $e->getMessage(), 'update');
                $ok = false;
            }
        }

        // 2. Database restore (migrations ટેબલ પણ પાછું આવે)
        if ($dbBackup !== null && is_file($dbBackup)) {
            try {
                $this->restoreDatabase($dbBackup);
                $this->progress(10, 'Database restored');
            } catch (Throwable $e) {
                Logger::error('DB rollback failed: ' . $e->getMessage(), 'update');
                $ok = false;
            }
        }

        // 3. Cache clear
        Cache::clear();
        return $ok;
    }

    /** SQL backup ફાઇલમાંથી restore ("-- STMT_END" delimiter પર split). */
    public function restoreDatabase(string $path): void
    {
        $pdo = Database::getInstance()->pdo();
        $sql = (string)file_get_contents($path);
        foreach (explode("-- STMT_END", $sql) as $statement) {
            $statement = trim($statement);
            // Comment-only ટુકડા skip
            $lines = array_filter(
                explode("\n", $statement),
                fn($l) => trim($l) !== '' && !str_starts_with(trim($l), '--')
            );
            $statement = trim(implode("\n", $lines));
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    // ---------------------------------------------------------------
    //  File operations
    // ---------------------------------------------------------------

    /** Protected paths — config + admin-added (DB setting). */
    public static function protectedPaths(): array
    {
        $fromConfig = App::config()['protected_paths'] ?? [];
        $extra = json_decode((string)App::setting('extra_protected_paths', '[]'), true);
        return array_values(array_unique(array_merge(
            is_array($fromConfig) ? $fromConfig : [],
            is_array($extra) ? $extra : []
        )));
    }

    /** Path protected છે? (file exact match અથવા folder prefix match) */
    private static function isProtected(string $relPath, array $protected): bool
    {
        $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
        foreach ($protected as $p) {
            $p = ltrim(str_replace('\\', '/', $p), '/');
            if (str_ends_with($p, '/')) {
                if (str_starts_with($relPath . '/', $p) || str_starts_with($relPath, $p)) {
                    return true;
                }
            } elseif ($relPath === $p) {
                return true;
            }
        }
        return false;
    }

    /** Recursive copy — protected skip કરીને, દરેક op log થાય. */
    private function copyTree(string $src, string $dst, array $protected, array &$stats, string $rel = ''): void
    {
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            $relPath = ltrim($rel . '/' . $item, '/');
            $dstPath = $dst . '/' . $item;
            if (self::isProtected($relPath, $protected)) {
                $stats['skipped']++;
                $this->progress(7, "Protected skip: {$relPath}");
                continue;
            }
            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) {
                    mkdir($dstPath, 0755, true);
                }
                $this->copyTree($srcPath, $dstPath, $protected, $stats, $relPath);
            } else {
                if (!copy($srcPath, $dstPath)) {
                    throw new RuntimeException("Copy failed: {$relPath}");
                }
                $stats['copied']++;
            }
        }
    }

    /** Dry run — શું બદલાશે તેની યાદી. */
    private function collectChanges(string $src, array $protected, string $rel = ''): array
    {
        $changes = ['copy' => [], 'skip' => [], 'new' => []];
        $walk = function (string $dir, string $rel) use (&$walk, &$changes, $protected): void {
            foreach (scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..' || $item === '.git') {
                    continue;
                }
                $path = $dir . '/' . $item;
                $relPath = ltrim($rel . '/' . $item, '/');
                if (self::isProtected($relPath, $protected)) {
                    $changes['skip'][] = $relPath;
                    continue;
                }
                if (is_dir($path)) {
                    $walk($path, $relPath);
                } else {
                    $existing = BASE_PATH . '/' . $relPath;
                    if (!is_file($existing)) {
                        $changes['new'][] = $relPath;
                    } elseif (md5_file($existing) !== md5_file($path)) {
                        $changes['copy'][] = $relPath;
                    }
                }
            }
        };
        $walk($src, $rel);
        return $changes;
    }

    /** Dry-run પછી log entry + સફાઈ. */
    private function finalizeDryRun(int $logId, array $changes): void
    {
        $db = Database::getInstance();
        $db->update('updates_log', [
            'status'       => 'success',
            'log_output'   => "[DRY RUN]\n" . implode("\n", $this->log)
                . "\nWill change: " . count($changes['copy'])
                . "\nNew files: " . count($changes['new'])
                . "\nProtected skip: " . count($changes['skip']),
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$logId]);
        Helper::rrmdir(TEMP_PATH . '/extracted');
        @unlink(TEMP_PATH . '/update.zip');
        @unlink(UPDATE_LOCK_FILE);
        @unlink(MAINTENANCE_FLAG);
    }

    // ---------------------------------------------------------------
    //  Migrations
    // ---------------------------------------------------------------

    /**
     * migrations/*.sql ક્રમમાં ચલાવો — already-run skip, દરેક transaction માં.
     * (નોંધ: MySQL માં DDL auto-commit થાય છે, એટલે migrations idempotent
     *  લખવી ફરજિયાત છે — CREATE TABLE IF NOT EXISTS વગેરે.)
     *
     * @return array ચલાવેલી migration files
     */
    public function runMigrations(): array
    {
        $db = Database::getInstance();
        $table = $db->table('migrations');
        $ran = array_column($db->fetchAll("SELECT migration_file FROM `{$table}`"), 'migration_file');
        $batch = (int)$db->fetchValue("SELECT COALESCE(MAX(batch), 0) + 1 FROM `{$table}`");
        $executed = [];

        $files = glob(MIGRATIONS_PATH . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) {
                continue;
            }
            $sql = str_replace('{{prefix}}', App::config()['db']['prefix'] ?? '', (string)file_get_contents($file));
            $pdo = $db->pdo();
            $pdo->beginTransaction();
            try {
                // Statements ';' + newline પર split (migrations માં stored procedures ન વાપરો)
                foreach (preg_split('/;\s*[\r\n]+/', $sql) ?: [] as $statement) {
                    $statement = trim($statement);
                    if ($statement === '' || str_starts_with($statement, '--')) {
                        continue;
                    }
                    $pdo->exec($statement);
                }
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $db->insert('migrations', ['migration_file' => $name, 'batch' => $batch]);
                $executed[] = $name;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new RuntimeException("Migration failed ({$name}): " . $e->getMessage());
            }
        }
        return $executed;
    }

    // ---------------------------------------------------------------
    //  Housekeeping
    // ---------------------------------------------------------------

    /** છેલ્લા MAX_BACKUPS_KEPT backups રાખો, બાકી delete. */
    public static function cleanupOldBackups(): int
    {
        $deleted = 0;
        foreach (['db_*.sql', 'files_*.zip'] as $pattern) {
            $files = glob(BACKUPS_PATH . '/' . $pattern) ?: [];
            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($files, MAX_BACKUPS_KEPT) as $old) {
                if (@unlink($old)) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    /** ઉપલબ્ધ backups ની list (manual rollback UI માટે). */
    public static function listBackups(): array
    {
        $backups = [];
        foreach (glob(BACKUPS_PATH . '/files_*.zip') ?: [] as $file) {
            $name = basename($file);
            // files_{version}_{stamp}.zip → સાથેની db ફાઇલ શોધો
            $dbFile = BACKUPS_PATH . '/' . str_replace(['files_', '.zip'], ['db_', '.sql'], $name);
            $backups[] = [
                'files_zip' => $name,
                'db_sql'    => is_file($dbFile) ? basename($dbFile) : null,
                'size'      => Helper::formatBytes((int)filesize($file)),
                'date'      => date('Y-m-d H:i:s', (int)filemtime($file)),
            ];
        }
        usort($backups, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $backups;
    }
}
