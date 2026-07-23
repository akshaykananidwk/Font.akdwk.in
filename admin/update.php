<?php
/**
 * GitHub One-Click Update — settings, check, update now (live progress), history.
 * super_admin only. No password re-entry required (CSRF + super-admin session are enough).
 */

/*
 * IMPORTANT: The AJAX endpoints (run_update, progress) MUST respond with pure JSON.
 * They therefore run BEFORE includes/header.php (which prints the <!DOCTYPE html> admin
 * layout). Emitting HTML before the JSON is exactly what caused the
 * "Unexpected token '<' ... is not valid JSON" error.
 */
$__ajaxAction = $_POST['action'] ?? $_GET['action'] ?? '';
if ($__ajaxAction === 'run_update' || $__ajaxAction === 'progress') {
    define('BASE_PATH', dirname(__DIR__));
    require_once BASE_PATH . '/config/constants.php';
    require_once CORE_PATH . '/App.php';
    App::bootstrap();

    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');

    // Must be a logged-in super_admin — respond with JSON (never redirect/HTML)
    if (!Auth::isAdminLoggedIn() || Session::get('admin_role') !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized. Please log in again.']);
        exit;
    }

    // Progress polling
    if ($__ajaxAction === 'progress') {
        echo json_encode(Updater::readProgress() ?? ['state' => 'idle'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Run the update (long request; the UI polls ?action=progress meanwhile)
    if (!Security::verifyCsrf()) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }
    $dryRun = !empty($_POST['dry_run']);
    Auth::logAdminActivity((int)Session::get('admin_id'), $dryRun ? 'dry_run' : 'update_now', 'update', []);
    try {
        $updater = new Updater();
        $result = $updater->run($dryRun);
        echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['success' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---------------- Normal page (HTML) ----------------
$REQUIRE_SUPER = true;
$PAGE_TITLE = 'Update';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$msg = '';
$err = '';
$checkResult = null;

// ---- POST: settings / check ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_settings') {
            Updater::saveSettings(
                trim((string)($_POST['github_repo'] ?? '')),
                trim((string)($_POST['github_branch'] ?? 'main')) ?: 'main',
                (string)($_POST['github_token'] ?? ''),
                (string)($_POST['frequency'] ?? 'manual')
            );
            $msg = 'Update settings saved (token stored encrypted).';
            Auth::logAdminActivity((int)Session::get('admin_id'), 'save_update_settings', 'update', ['repo' => $_POST['github_repo'] ?? '']);
        } elseif ($action === 'check') {
            $checkResult = Updater::checkForUpdate();
        }
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$settings = Updater::getSettings();
$localVersion = Updater::localVersion();
$history = $db->fetchAll("SELECT * FROM `" . $db->table('updates_log') . "` ORDER BY id DESC LIMIT 20");
$viewLog = null;
if (($logId = (int)($_GET['view_log'] ?? 0)) > 0) {
    $viewLog = $db->fetch("SELECT * FROM `" . $db->table('updates_log') . "` WHERE id = ?", [$logId]);
}
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>⚙ Update Settings</h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save_settings">
      <label>GitHub Repository (username/repo)</label>
      <input type="text" name="github_repo" value="<?= $e($settings['repo']) ?>" placeholder="username/my-site" required>
      <label>Branch</label>
      <input type="text" name="github_branch" value="<?= $e($settings['branch']) ?>">
      <label>Personal Access Token (for private repos — stored encrypted)</label>
      <input type="password" name="github_token" value="" placeholder="<?= $settings['token'] !== '' ? '•••••• (saved — leave empty to keep unchanged)' : 'ghp_...' ?>" autocomplete="new-password">
      <label>Auto-check</label>
      <select name="frequency">
        <?php foreach (['manual' => 'Manual', 'daily' => 'Daily (cron)', 'weekly' => 'Weekly (cron)'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $settings['frequency'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <button class="abtn abtn-primary" type="submit">Save</button>
    </form>
    <p class="amuted">Once saved, you never need to upload files again — everything happens from here.</p>
  </div>

  <div class="admin-card">
    <h2>🔍 Update Check</h2>
    <p>Current version: <strong>v<?= $e($localVersion['version'] ?? APP_VERSION) ?></strong></p>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="check">
      <button class="abtn abtn-primary" type="submit" <?= $settings['repo'] === '' ? 'disabled' : '' ?>>Check for Update</button>
    </form>

    <?php if ($checkResult !== null): ?>
      <hr class="asep">
      <?php if ($checkResult['is_latest']): ?>
        <div class="admin-alert alert-ok">✓ You are on the latest version (v<?= $e($checkResult['current_version']) ?>)</div>
      <?php else: ?>
        <div class="admin-alert alert-warn">🆕 New version available: v<?= $e((string)$checkResult['remote_version']) ?></div>
      <?php endif; ?>
      <table class="atable">
        <tr><td>Commit</td><td><code><?= $e($checkResult['commit_hash']) ?></code></td></tr>
        <tr><td>Message</td><td><?= $e(mb_substr($checkResult['commit_message'], 0, 140)) ?></td></tr>
        <tr><td>Author</td><td><?= $e($checkResult['commit_author']) ?></td></tr>
        <tr><td>Date</td><td><?= $e($checkResult['commit_date']) ?></td></tr>
        <tr><td>New Migrations</td><td><?= $checkResult['new_migrations'] ? $e(implode(', ', $checkResult['new_migrations'])) : 'None' ?></td></tr>
      </table>
      <?php if ($checkResult['changelog']): ?>
        <details><summary>Changelog</summary><pre class="test-output"><?= $e($checkResult['changelog']) ?></pre></details>
      <?php endif; ?>

      <hr class="asep">
      <h3>Run Update</h3>
      <p class="amuted">A full backup is taken automatically first, and the site rolls back automatically if anything fails.</p>
      <div style="margin-top:10px">
        <button class="abtn" id="btnDryRun" type="button">🔬 Dry Run (preview only)</button>
        <button class="abtn abtn-danger" id="btnUpdateNow" type="button">🚀 Update Now</button>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card" id="progressCard" hidden>
  <h2>📶 Update Progress</h2>
  <div class="progress-track"><div class="progress-fill" id="updProgressBar" style="width:0%"></div></div>
  <pre class="test-output" id="updLog" style="max-height:340px;overflow:auto">…</pre>
</div>

<div class="admin-card">
  <h2>🕘 Update History</h2>
  <?php if ($viewLog): ?>
    <h3>Log #<?= (int)$viewLog['id'] ?> (<?= $e($viewLog['status']) ?>)</h3>
    <pre class="test-output" style="max-height:300px;overflow:auto"><?= $e((string)$viewLog['log_output']) ?></pre>
    <p><a class="abtn abtn-xs" href="update.php">Close</a></p>
  <?php endif; ?>
  <table class="atable">
    <tr><th>#</th><th>From → To</th><th>Commit</th><th>Status</th><th>Started</th><th></th></tr>
    <?php foreach ($history as $h): ?>
    <tr>
      <td><?= (int)$h['id'] ?></td>
      <td>v<?= $e($h['from_version']) ?> → v<?= $e($h['to_version']) ?></td>
      <td><code><?= $e((string)$h['commit_hash']) ?></code></td>
      <td><span class="abadge ab-<?= $h['status'] === 'success' ? 'ok' : ($h['status'] === 'running' ? 'warn' : 'err') ?>"><?= $e($h['status']) ?></span></td>
      <td><small><?= $e((string)$h['started_at']) ?></small></td>
      <td><a class="abtn abtn-xs" href="?view_log=<?= (int)$h['id'] ?>">Log</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="6" class="amuted">No updates yet.</td></tr><?php endif; ?>
  </table>
  <p class="amuted">For a manual rollback, use the <a href="backup.php">Backup page</a>.</p>
</div>

<script>
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  let pollTimer = null;

  function startPolling() {
    document.getElementById('progressCard').hidden = false;
    pollTimer = setInterval(async () => {
      try {
        const res = await fetch('update.php?action=progress');
        const p = await res.json();
        if (p.log) {
          const el = document.getElementById('updLog');
          el.textContent = p.log.join('\n');
          el.scrollTop = el.scrollHeight;
        }
        if (p.step) {
          document.getElementById('updProgressBar').style.width =
            Math.min(100, Math.round((p.step / (p.total || 9)) * 100)) + '%';
        }
        if (p.state === 'success' || p.state === 'error' || p.state === 'rolled_back') {
          clearInterval(pollTimer);
        }
      } catch (e) {}
    }, 1500);
  }

  async function runUpdate(dryRun) {
    if (!dryRun && !confirm('Run the update now? A backup is taken automatically, and the site rolls back if anything fails.')) return;
    startPolling();
    document.getElementById('updLog').textContent = '⏳ Starting update...';
    const body = new URLSearchParams({ action: 'run_update', csrf_token: csrf });
    if (dryRun) body.append('dry_run', '1');
    try {
      const res = await fetch('update.php', { method: 'POST', body });
      const json = await res.json();
      clearInterval(pollTimer);
      if (json.success) {
        document.getElementById('updProgressBar').style.width = '100%';
        document.getElementById('updLog').textContent += '\n\n✅ ' + (dryRun ? 'Dry run complete.' : 'Update successful! Please refresh the page.');
        if (json.result && json.result.changes) {
          const ch = json.result.changes;
          document.getElementById('updLog').textContent +=
            '\n\nWill change: ' + ch.copy.length + ' | New: ' + ch.new.length + ' | Protected (skipped): ' + ch.skip.length +
            '\n\n' + ch.copy.slice(0, 50).join('\n');
        }
      } else {
        document.getElementById('updLog').textContent += '\n\n❌ ' + json.error;
      }
    } catch (e) {
      clearInterval(pollTimer);
      document.getElementById('updLog').textContent += '\n\n❌ Request failed: ' + e.message +
        '\n(The update may still be running — watch the progress above and refresh the page.)';
    }
  }

  document.getElementById('btnDryRun')?.addEventListener('click', () => runUpdate(true));
  document.getElementById('btnUpdateNow')?.addEventListener('click', () => runUpdate(false));
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
