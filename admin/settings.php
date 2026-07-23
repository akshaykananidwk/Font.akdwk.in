<?php
/**
 * સેટિંગ્સ — demo limits, SMTP, maintenance mode, API.
 */
$PAGE_TITLE = 'સેટિંગ્સ';
require __DIR__ . '/includes/header.php';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_general') {
            App::setSetting('site_name', mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 100), 'general');
            App::setSetting('demo_char_limit', (string)max(10, (int)($_POST['demo_char_limit'] ?? 200)), 'general');
            App::setSetting('demo_attempt_limit', (string)max(1, (int)($_POST['demo_attempt_limit'] ?? 20)), 'general');
            App::setSetting('rate_limit_per_min', (string)max(5, (int)($_POST['rate_limit_per_min'] ?? 30)), 'general');
            App::setSetting('api_enabled', !empty($_POST['api_enabled']) ? '1' : '0', 'api');
            $msg = 'General settings સેવ થયા.';
        } elseif ($action === 'save_smtp') {
            App::setSetting('smtp_enabled', !empty($_POST['smtp_enabled']) ? '1' : '0', 'email');
            App::setSetting('email_from', trim((string)($_POST['email_from'] ?? '')), 'email');
            App::setSetting('smtp_host', trim((string)($_POST['smtp_host'] ?? '')), 'email');
            App::setSetting('smtp_port', (string)(int)($_POST['smtp_port'] ?? 587), 'email');
            App::setSetting('smtp_user', trim((string)($_POST['smtp_user'] ?? '')), 'email');
            if ((string)($_POST['smtp_pass'] ?? '') !== '') {
                App::setSetting('smtp_pass', (string)$_POST['smtp_pass'], 'email');
            }
            App::setSetting('smtp_encryption', in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? (string)$_POST['smtp_encryption'] : 'tls', 'email');
            $msg = 'Email settings સેવ થયા.';
        } elseif ($action === 'toggle_maintenance') {
            if (App::isMaintenanceMode()) {
                @unlink(MAINTENANCE_FLAG);
                $msg = 'Maintenance mode બંધ થયો.';
            } else {
                file_put_contents(MAINTENANCE_FLAG, date('c'));
                $msg = 'Maintenance mode ચાલુ થયો (admin session ને અસર નહીં).';
            }
        } elseif ($action === 'test_email') {
            $to = App::config()['site']['admin_email'] ?? '';
            $ok = $to !== '' && Mailer::sendTemplate($to, 'Test Email — ' . App::setting('site_name', APP_NAME), 'આ test email છે. Settings બરાબર છે! ✓');
            $msg = $ok ? "Test email મોકલાયો ({$to})." : 'Email મોકલી શકાયો નહીં — logs જુઓ.';
        } elseif ($action === 'clear_cache') {
            $n = Cache::clear();
            $msg = "Cache clear થયું ({$n} ફાઇલ).";
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'settings', []);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>General</h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save_general">
      <label>Site Name</label>
      <input type="text" name="site_name" value="<?= $e((string)App::setting('site_name', '')) ?>">
      <div class="row-2">
        <div><label>Demo Char Limit</label><input type="number" name="demo_char_limit" value="<?= (int)App::setting('demo_char_limit', 200) ?>"></div>
        <div><label>Demo Attempts/દિવસ</label><input type="number" name="demo_attempt_limit" value="<?= (int)App::setting('demo_attempt_limit', 20) ?>"></div>
      </div>
      <label>Rate Limit (requests/min/IP)</label>
      <input type="number" name="rate_limit_per_min" value="<?= (int)App::setting('rate_limit_per_min', 30) ?>">
      <label><input type="checkbox" name="api_enabled" value="1" <?= App::setting('api_enabled', '1') === '1' ? 'checked' : '' ?>> API ચાલુ</label>
      <button class="abtn abtn-primary" type="submit">સેવ</button>
    </form>
    <hr class="asep">
    <form method="post" class="inline">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="toggle_maintenance">
      <button class="abtn <?= App::isMaintenanceMode() ? 'abtn-primary' : 'abtn-danger' ?>" type="submit">
        <?= App::isMaintenanceMode() ? '▶ Maintenance બંધ કરો' : '🔧 Maintenance ચાલુ કરો' ?>
      </button>
    </form>
    <form method="post" class="inline">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="clear_cache">
      <button class="abtn" type="submit">🗑 Cache Clear</button>
    </form>
  </div>

  <div class="admin-card">
    <h2>Email / SMTP</h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save_smtp">
      <label><input type="checkbox" name="smtp_enabled" value="1" <?= App::setting('smtp_enabled', '0') === '1' ? 'checked' : '' ?>> SMTP વાપરો (નહીં તો PHP mail())</label>
      <label>From Email</label>
      <input type="email" name="email_from" value="<?= $e((string)App::setting('email_from', '')) ?>">
      <div class="row-2">
        <div><label>SMTP Host</label><input type="text" name="smtp_host" value="<?= $e((string)App::setting('smtp_host', '')) ?>"></div>
        <div><label>Port</label><input type="number" name="smtp_port" value="<?= (int)App::setting('smtp_port', 587) ?>"></div>
      </div>
      <div class="row-2">
        <div><label>Username</label><input type="text" name="smtp_user" value="<?= $e((string)App::setting('smtp_user', '')) ?>"></div>
        <div><label>Password (ખાલી = જૂનો રહે)</label><input type="password" name="smtp_pass" value=""></div>
      </div>
      <label>Encryption</label>
      <select name="smtp_encryption">
        <?php foreach (['tls', 'ssl', 'none'] as $enc): ?>
          <option value="<?= $enc ?>" <?= App::setting('smtp_encryption', 'tls') === $enc ? 'selected' : '' ?>><?= strtoupper($enc) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="abtn abtn-primary" type="submit">સેવ</button>
    </form>
    <hr class="asep">
    <form method="post" class="inline">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="test_email">
      <button class="abtn" type="submit">✉ Test Email મોકલો</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
