<?php
/**
 * GitHub One-Click Update — settings, check, update now (live progress), history.
 * ફક્ત super_admin; update શરૂ કરતાં પહેલા password re-verify.
 */
$REQUIRE_SUPER = true;
$PAGE_TITLE = 'અપડેટ';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$msg = '';
$err = '';
$checkResult = null;

// ---- AJAX: progress polling ----
if (($_GET['action'] ?? '') === 'progress') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(Updater::readProgress() ?? ['state' => 'idle'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- AJAX: run update (long request; UI progress poll કરે છે) ----
if (($_POST['action'] ?? '') === 'run_update') {
    header('Content-Type: application/json; charset=utf-8');
    if (!Security::verifyCsrf()) {
        echo json_encode(['success' => false, 'error' => 'CSRF token અમાન્ય']);
        exit;
    }
    if (!Auth::reverifyAdminPassword((string)($_POST['admin_password'] ?? ''))) {
        echo json_encode(['success' => false, 'error' => 'Admin password ખોટો છે']);
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
            $msg = 'Update settings સેવ થયા (token encrypted).';
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
      <label>Personal Access Token (private repo માટે — encrypted store થાય છે)</label>
      <input type="password" name="github_token" value="" placeholder="<?= $settings['token'] !== '' ? '•••••• (સેવ થયેલો છે — ખાલી રાખો તો બદલાય નહીં)' : 'ghp_...' ?>" autocomplete="new-password">
      <label>Auto-check</label>
      <select name="frequency">
        <?php foreach (['manual' => 'Manual', 'daily' => 'Daily (cron)', 'weekly' => 'Weekly (cron)'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $settings['frequency'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <button class="abtn abtn-primary" type="submit">સેવ</button>
    </form>
    <p class="amuted">એકવાર સેવ કર્યા પછી ફરી ક્યારેય ફાઇલ upload કરવાની જરૂર નથી — બધું અહીંથી થાય છે.</p>
  </div>

  <div class="admin-card">
    <h2>🔍 Update Check</h2>
    <p>હાલનું વર્ઝન: <strong>v<?= $e($localVersion['version'] ?? APP_VERSION) ?></strong></p>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="check">
      <button class="abtn abtn-primary" type="submit" <?= $settings['repo'] === '' ? 'disabled' : '' ?>>Check for Update</button>
    </form>

    <?php if ($checkResult !== null): ?>
      <hr class="asep">
      <?php if ($checkResult['is_latest']): ?>
        <div class="admin-alert alert-ok">✓ તમે નવીનતમ વર્ઝન પર છો (v<?= $e($checkResult['current_version']) ?>)</div>
      <?php else: ?>
        <div class="admin-alert alert-warn">🆕 નવું વર્ઝન ઉપલબ્ધ: v<?= $e((string)$checkResult['remote_version']) ?></div>
      <?php endif; ?>
      <table class="atable">
        <tr><td>Commit</td><td><code><?= $e($checkResult['commit_hash']) ?></code></td></tr>
        <tr><td>Message</td><td><?= $e(mb_substr($checkResult['commit_message'], 0, 140)) ?></td></tr>
        <tr><td>Author</td><td><?= $e($checkResult['commit_author']) ?></td></tr>
        <tr><td>Date</td><td><?= $e($checkResult['commit_date']) ?></td></tr>
        <tr><td>નવી Migrations</td><td><?= $checkResult['new_migrations'] ? $e(implode(', ', $checkResult['new_migrations'])) : 'નથી' ?></td></tr>
      </table>
      <?php if ($checkResult['changelog']): ?>
        <details><summary>Changelog</summary><pre class="test-output"><?= $e($checkResult['changelog']) ?></pre></details>
      <?php endif; ?>

      <hr class="asep">
      <h3>Update ચલાવો</h3>
      <label>Admin Password (ફરી confirm)</label>
      <input type="password" id="updPassword" autocomplete="current-password">
      <div style="margin-top:10px">
        <button class="abtn" id="btnDryRun" type="button">🔬 Dry Run (ફક્ત preview)</button>
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
    <p><a class="abtn abtn-xs" href="update.php">બંધ કરો</a></p>
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
    <?php if (!$history): ?><tr><td colspan="6" class="amuted">હજી કોઈ update નથી થયું.</td></tr><?php endif; ?>
  </table>
  <p class="amuted">Manual rollback માટે <a href="backup.php">બેકઅપ પેજ</a> વાપરો.</p>
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
          document.getElementById('updLog').textContent = p.log.join('\n');
          const el = document.getElementById('updLog');
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
    const pw = document.getElementById('updPassword').value;
    if (!pw) { alert('Admin password આપો.'); return; }
    if (!dryRun && !confirm('ખરેખર update કરવું છે? Backup આપમેળે લેવાશે અને ભૂલ આવે તો rollback થશે.')) return;
    startPolling();
    document.getElementById('updLog').textContent = '⏳ Update શરૂ થાય છે...';
    const body = new URLSearchParams({ action: 'run_update', csrf_token: csrf, admin_password: pw });
    if (dryRun) body.append('dry_run', '1');
    try {
      const res = await fetch('update.php', { method: 'POST', body });
      const json = await res.json();
      clearInterval(pollTimer);
      if (json.success) {
        document.getElementById('updProgressBar').style.width = '100%';
        document.getElementById('updLog').textContent += '\n\n✅ ' + (dryRun ? 'Dry run પૂરું.' : 'Update સફળ! Page refresh કરો.');
        if (json.result && json.result.changes) {
          const ch = json.result.changes;
          document.getElementById('updLog').textContent +=
            '\n\nબદલાશે: ' + ch.copy.length + ' | નવી: ' + ch.new.length + ' | Protected skip: ' + ch.skip.length +
            '\n\n' + ch.copy.slice(0, 50).join('\n');
        }
      } else {
        document.getElementById('updLog').textContent += '\n\n❌ ' + json.error;
      }
    } catch (e) {
      clearInterval(pollTimer);
      document.getElementById('updLog').textContent += '\n\n❌ Request ફેલ: ' + e.message + '\n(Update કદાચ ચાલુ છે — progress ઉપર જુઓ, page refresh કરો)';
    }
  }

  document.getElementById('btnDryRun')?.addEventListener('click', () => runUpdate(true));
  document.getElementById('btnUpdateNow')?.addEventListener('click', () => runUpdate(false));
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
