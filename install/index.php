<?php
/**
 * 6-Step Installation Wizard.
 *
 * Step 1: Server requirements   Step 2: License
 * Step 3: Database              Step 4: Site settings
 * Step 5: Admin account         Step 6: Finish
 *
 * install.lock હોય તો સંપૂર્ણ block (403).
 */

define('BASE_PATH', dirname(__DIR__));
define('INSTALLER_RUNNING', true);

if (is_file(BASE_PATH . '/install.lock')) {
    http_response_code(403);
    die('<h1>403 — Already Installed</h1><p>This site is already installed. For security, the installer is locked.</p>');
}

require BASE_PATH . '/config/constants.php';
require __DIR__ . '/check.php';

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');
mb_internal_encoding('UTF-8');

$step = max(1, min(6, (int)($_GET['step'] ?? 1)));
$errors = [];

/** Wizard state session માં રહે છે. */
function wiz(string $key, mixed $default = null): mixed
{
    return $_SESSION['wizard'][$key] ?? $default;
}

function wizSet(string $key, mixed $value): void
{
    $_SESSION['wizard'][$key] = $value;
}

/** DB connection test (AJAX + Step 3 validation). */
function testDbConnection(array $db): array
{
    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        // DB ન હોય તો બનાવવાની કોશિશ
        $dbName = str_replace('`', '', $db['name']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        return ['success' => true, 'message' => 'Connection successful — database ready'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
    }
}

// ---- AJAX: test db ----
if (($_POST['action'] ?? '') === 'test_db') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(testDbConnection([
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'port' => trim($_POST['db_port'] ?? '3306'),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => $_POST['db_pass'] ?? '',
    ]), JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- POST handling per step ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            $checks = gfc_run_checks();
            if (gfc_checks_pass($checks)) {
                header('Location: ?step=2');
                exit;
            }
            $errors[] = 'બધી ફરજિયાત requirements પૂરી નથી. ઉપરની સૂચનાઓ પ્રમાણે fix કરો.';
            break;

        case 2:
            if (!empty($_POST['accept_terms'])) {
                wizSet('terms_accepted', true);
                header('Location: ?step=3');
                exit;
            }
            $errors[] = 'આગળ વધવા શરતો સ્વીકારવી જરૂરી છે.';
            break;

        case 3:
            $db = [
                'host'   => trim($_POST['db_host'] ?? 'localhost'),
                'port'   => trim($_POST['db_port'] ?? '3306'),
                'name'   => trim($_POST['db_name'] ?? ''),
                'user'   => trim($_POST['db_user'] ?? ''),
                'pass'   => $_POST['db_pass'] ?? '',
                'prefix' => trim($_POST['db_prefix'] ?? 'gfc_'),
            ];
            if ($db['name'] === '' || $db['user'] === '') {
                $errors[] = 'Database name અને username જરૂરી છે.';
                break;
            }
            if (!preg_match('/^[a-zA-Z0-9_]*$/', $db['prefix'])) {
                $errors[] = 'Table prefix માં ફક્ત letters, numbers, underscore ચાલે.';
                break;
            }
            $test = testDbConnection($db);
            if (!$test['success']) {
                $errors[] = $test['message'];
                break;
            }
            // Schema + seed run કરો (transaction માં જ્યાં શક્ય)
            try {
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $schema = str_replace('{{prefix}}', $db['prefix'], (string)file_get_contents(__DIR__ . '/schema.sql'));
                $pdo->exec($schema);
                // Seed transaction માં (DDL auto-commits, DML rollback-able)
                $seed = str_replace('{{prefix}}', $db['prefix'], (string)file_get_contents(__DIR__ . '/seed.sql'));
                $pdo->beginTransaction();
                try {
                    $pdo->exec($seed);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                wizSet('db', $db);
                header('Location: ?step=4');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Schema/seed run કરવામાં ભૂલ: ' . $e->getMessage();
            }
            break;

        case 4:
            $siteName = trim($_POST['site_name'] ?? '');
            $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            if ($siteName === '' || $siteUrl === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Site name, valid URL અને valid admin email જરૂરી છે.';
                break;
            }
            wizSet('site', [
                'name'        => $siteName,
                'url'         => $siteUrl,
                'timezone'    => $_POST['timezone'] ?? 'Asia/Kolkata',
                'language'    => $_POST['language'] ?? 'gu',
                'admin_email' => $adminEmail,
            ]);
            header('Location: ?step=5');
            exit;

        case 5:
            $username = trim($_POST['admin_username'] ?? '');
            $email = trim($_POST['admin_email2'] ?? '');
            $pass = $_POST['admin_password'] ?? '';
            $confirm = $_POST['admin_password_confirm'] ?? '';
            if (!preg_match('/^[a-zA-Z0-9_]{3,60}$/', $username)) {
                $errors[] = 'Username: 3-60 chars, ફક્ત letters/numbers/underscore.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email આપો.';
            }
            if (strlen($pass) < 8) {
                $errors[] = 'Password ઓછામાં ઓછો 8 characters જોઈએ.';
            }
            if ($pass !== $confirm) {
                $errors[] = 'Passwords મળતા નથી.';
            }
            if (!$errors) {
                wizSet('admin', ['username' => $username, 'email' => $email, 'password' => $pass]);
                // ---- FINALIZE ----
                try {
                    $db = wiz('db');
                    $site = wiz('site');
                    if (!$db || !$site) {
                        throw new RuntimeException('Wizard state ખોવાયો — Step 3 થી ફરી શરૂ કરો.');
                    }
                    // 1. Admin account બનાવો
                    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $hash = defined('PASSWORD_ARGON2ID')
                        ? password_hash($pass, PASSWORD_ARGON2ID)
                        : password_hash($pass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO `{$db['prefix']}admins` (username, email, password_hash, full_name, role, status)
                         VALUES (?, ?, ?, ?, 'super_admin', 'active')
                         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)"
                    );
                    $stmt->execute([$username, $email, $hash, $username]);

                    // migrations ટેબલમાં baseline નોંધો (update વખતે ફરી ન ચાલે)
                    foreach (glob(BASE_PATH . '/migrations/*.sql') ?: [] as $mig) {
                        $stmt = $pdo->prepare(
                            "INSERT IGNORE INTO `{$db['prefix']}migrations` (migration_file, batch) VALUES (?, 0)"
                        );
                        $stmt->execute([basename($mig)]);
                    }

                    // 2. config.php લખો
                    $sample = (string)file_get_contents(BASE_PATH . '/config/config.sample.php');
                    $config = str_replace(
                        ['{{DB_HOST}}', '{{DB_PORT}}', '{{DB_NAME}}', '{{DB_USER}}', '{{DB_PASS}}', '{{DB_PREFIX}}',
                         '{{SITE_URL}}', '{{SITE_NAME}}', '{{TIMEZONE}}', '{{LANGUAGE}}', '{{ADMIN_EMAIL}}', '{{ENCRYPTION_KEY}}'],
                        [addslashes($db['host']), addslashes($db['port']), addslashes($db['name']), addslashes($db['user']),
                         addslashes($db['pass']), addslashes($db['prefix']), addslashes($site['url']), addslashes($site['name']),
                         addslashes($site['timezone']), addslashes($site['language']), addslashes($site['admin_email']),
                         bin2hex(random_bytes(32))],
                        $sample
                    );
                    if (file_put_contents(CONFIG_PATH . '/config.php', $config, LOCK_EX) === false) {
                        throw new RuntimeException('config.php લખી શકાયું નહીં — /config writable છે?');
                    }

                    // 3. install.lock બનાવો
                    file_put_contents(BASE_PATH . '/install.lock', date('Y-m-d H:i:s') . "\n");

                    header('Location: ?step=6');
                    exit;
                } catch (Throwable $e) {
                    $errors[] = 'Installation ભૂલ: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Step guards — આગળના step પર કૂદકો ન મરાય
if ($step >= 3 && !wiz('terms_accepted')) {
    $step = 2;
}
if ($step >= 4 && $step < 6 && !wiz('db')) {
    $step = min($step, 3);
}
if ($step === 6 && !is_file(BASE_PATH . '/install.lock')) {
    $step = 1;
}

$checks = $step === 1 ? gfc_run_checks() : [];
$progress = (int)(($step / 6) * 100);

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

$autoUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');
?>
<!DOCTYPE html>
<html lang="gu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation — Gujarati Font Converter</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#eef2f7;color:#2c3e50;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wizard{background:#fff;border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,.12);width:100%;max-width:680px;overflow:hidden}
.head{background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;padding:26px 32px}
.head h1{font-size:1.35rem;font-weight:600}
.head p{opacity:.85;font-size:.85rem;margin-top:4px}
.progress{height:6px;background:#d6e4f0}
.progress-bar{height:100%;background:#27ae60;transition:width .4s;width:<?= $progress ?>%}
.steps{display:flex;gap:4px;padding:14px 32px 0;font-size:.72rem;color:#95a5a6;flex-wrap:wrap}
.steps span{padding:4px 10px;border-radius:20px;background:#f4f6f8}
.steps span.active{background:#1a5276;color:#fff}
.steps span.done{background:#d5f5e3;color:#1e8449}
.body{padding:26px 32px 32px}
h2{font-size:1.1rem;margin-bottom:16px;color:#1a5276}
table{width:100%;border-collapse:collapse;font-size:.85rem}
td,th{padding:8px 10px;border-bottom:1px solid #eee;text-align:left}
.ok{color:#27ae60;font-weight:700}
.fail{color:#c0392b;font-weight:700}
.warn-txt{color:#e67e22}
label{display:block;font-size:.82rem;font-weight:600;margin:14px 0 4px}
input[type=text],input[type=password],input[type=email],select{width:100%;padding:10px 12px;border:1px solid #cfd8e3;border-radius:8px;font-size:.9rem}
input:focus,select:focus{outline:2px solid #2980b9;border-color:transparent}
.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btns{display:flex;justify-content:space-between;margin-top:24px;gap:10px}
.btn{display:inline-block;padding:11px 26px;border-radius:8px;border:0;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none}
.btn-primary{background:#1a5276;color:#fff}
.btn-primary:disabled{background:#95a5a6;cursor:not-allowed}
.btn-light{background:#eef2f7;color:#2c3e50}
.btn-test{background:#8e44ad;color:#fff;margin-top:10px}
.error-box{background:#fdecea;border:1px solid #f5b7b1;color:#943126;padding:12px 16px;border-radius:8px;font-size:.85rem;margin-bottom:16px}
.info-box{background:#eaf2f8;border:1px solid #aed6f1;color:#1a5276;padding:12px 16px;border-radius:8px;font-size:.85rem;margin-bottom:16px}
.success-big{text-align:center;padding:20px 0}
.success-big .tick{font-size:3.2rem}
.license{background:#f8f9fa;border:1px solid #e3e6ea;border-radius:8px;padding:16px;font-size:.82rem;max-height:220px;overflow-y:auto;line-height:1.6}
.check-row{margin-top:14px;font-size:.88rem}
.pw-meter{height:6px;border-radius:3px;background:#eee;margin-top:6px;overflow:hidden}
.pw-meter i{display:block;height:100%;width:0;background:#c0392b;transition:all .3s}
code{background:#f4f6f8;padding:2px 6px;border-radius:4px;font-size:.82rem}
#dbTestResult{font-size:.84rem;margin-top:8px}
@media(max-width:560px){.row{grid-template-columns:1fr}.body,.head{padding-left:20px;padding-right:20px}}
</style>
</head>
<body>
<div class="wizard">
  <div class="head">
    <h1>🔤 Gujarati Font Converter — Installation</h1>
    <p>Step <?= $step ?> of 6</p>
  </div>
  <div class="progress"><div class="progress-bar"></div></div>
  <div class="steps">
    <?php
    $names = [1 => 'સર્વર ચેક', 2 => 'શરતો', 3 => 'ડેટાબેઝ', 4 => 'સાઇટ', 5 => 'એડમિન', 6 => 'પૂર્ણ'];
    foreach ($names as $n => $label) {
        $cls = $n === $step ? 'active' : ($n < $step ? 'done' : '');
        echo "<span class=\"{$cls}\">{$n}. {$label}</span>";
    }
    ?>
  </div>
  <div class="body">
    <?php foreach ($errors as $err): ?>
      <div class="error-box">⚠ <?= h($err) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 1): ?>
      <h2>Step 1 — સર્વર Requirements</h2>
      <table>
        <tr><th>Requirement</th><th>Status</th><th>નોંધ</th></tr>
        <?php foreach ($checks as $c): ?>
        <tr>
          <td><?= h($c['name']) ?></td>
          <td class="<?= $c['ok'] ? 'ok' : ($c['required'] ? 'fail' : 'warn-txt') ?>">
            <?= $c['ok'] ? '✓' : '✗' ?>
          </td>
          <td><?= h($c['note']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <form method="post">
        <div class="btns">
          <a class="btn btn-light" href="?step=1">↻ ફરી ચેક કરો</a>
          <button class="btn btn-primary" <?= gfc_checks_pass($checks) ? '' : 'disabled' ?>>આગળ →</button>
        </div>
      </form>

    <?php elseif ($step === 2): ?>
      <h2>Step 2 — લાઇસન્સ / શરતો</h2>
      <div class="license">
        <p><strong>Gujarati Font Converter</strong></p>
        <p>આ સોફ્ટવેર "as-is" ધોરણે અપાય છે, કોઈ પણ પ્રકારની warranty વગર. Install કરીને તમે સ્વીકારો છો કે:</p>
        <p>• સોફ્ટવેરના ઉપયોગથી થતા કોઈ પણ નુકસાન માટે developer જવાબદાર નથી.<br>
        • Legacy ફોન્ટના glyph-mappings જે-તે ફોન્ટના માલિકોના specimen પરથી verify કરવાની જવાબદારી તમારી છે.<br>
        • Third-party fonts ના copyright જે-તે માલિકોના છે.<br>
        • Database backups નિયમિત લેવાની જવાબદારી તમારી છે.</p>
      </div>
      <form method="post">
        <div class="check-row">
          <label style="display:flex;gap:8px;align-items:center;font-weight:400">
            <input type="checkbox" name="accept_terms" value="1"> હું ઉપરની શરતો સ્વીકારું છું
          </label>
        </div>
        <div class="btns">
          <a class="btn btn-light" href="?step=1">← પાછળ</a>
          <button class="btn btn-primary">આગળ →</button>
        </div>
      </form>

    <?php elseif ($step === 3): ?>
      <h2>Step 3 — ડેટાબેઝ સેટિંગ્સ</h2>
      <div class="info-box">MySQL 8.0 / MariaDB 10.5+ database credentials આપો. Database ન હોય તો બનાવવાની કોશિશ થશે.</div>
      <form method="post" id="dbForm">
        <div class="row">
          <div><label>Host</label><input type="text" name="db_host" value="<?= h(wiz('db')['host'] ?? 'localhost') ?>"></div>
          <div><label>Port</label><input type="text" name="db_port" value="<?= h(wiz('db')['port'] ?? '3306') ?>"></div>
        </div>
        <label>Database Name</label>
        <input type="text" name="db_name" value="<?= h(wiz('db')['name'] ?? '') ?>" required>
        <div class="row">
          <div><label>Username</label><input type="text" name="db_user" value="<?= h(wiz('db')['user'] ?? '') ?>" required></div>
          <div><label>Password</label><input type="password" name="db_pass" value=""></div>
        </div>
        <label>Table Prefix</label>
        <input type="text" name="db_prefix" value="<?= h(wiz('db')['prefix'] ?? 'gfc_') ?>">
        <button type="button" class="btn btn-test" id="btnTestDb">⚡ Test Connection</button>
        <div id="dbTestResult"></div>
        <div class="btns">
          <a class="btn btn-light" href="?step=2">← પાછળ</a>
          <button class="btn btn-primary" id="btnDbNext" disabled>Install Tables →</button>
        </div>
      </form>
      <script>
      document.getElementById('btnTestDb').addEventListener('click', async () => {
        const form = document.getElementById('dbForm');
        const data = new FormData(form);
        data.append('action', 'test_db');
        const el = document.getElementById('dbTestResult');
        el.innerHTML = '⏳ Testing...';
        try {
          const res = await fetch('?step=3', { method: 'POST', body: data });
          const json = await res.json();
          el.innerHTML = json.success
            ? '<span class="ok">✓ ' + json.message + '</span>'
            : '<span class="fail">✗ ' + json.message + '</span>';
          document.getElementById('btnDbNext').disabled = !json.success;
        } catch (e) {
          el.innerHTML = '<span class="fail">✗ Request failed</span>';
        }
      });
      </script>

    <?php elseif ($step === 4): ?>
      <h2>Step 4 — સાઇટ સેટિંગ્સ</h2>
      <form method="post">
        <label>Site Name</label>
        <input type="text" name="site_name" value="<?= h(wiz('site')['name'] ?? 'Gujarati Font Converter') ?>" required>
        <label>Site URL (auto-detected)</label>
        <input type="text" name="site_url" value="<?= h(wiz('site')['url'] ?? $autoUrl) ?>" required>
        <div class="row">
          <div>
            <label>Default Language</label>
            <select name="language">
              <option value="gu" <?= (wiz('site')['language'] ?? 'gu') === 'gu' ? 'selected' : '' ?>>ગુજરાતી</option>
              <option value="en" <?= (wiz('site')['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
            </select>
          </div>
          <div>
            <label>Timezone</label>
            <select name="timezone">
              <?php foreach (['Asia/Kolkata', 'Asia/Kathmandu', 'UTC', 'America/New_York', 'Europe/London'] as $tz): ?>
              <option value="<?= h($tz) ?>" <?= (wiz('site')['timezone'] ?? 'Asia/Kolkata') === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <label>Admin Email</label>
        <input type="email" name="admin_email" value="<?= h(wiz('site')['admin_email'] ?? '') ?>" required>
        <div class="btns">
          <a class="btn btn-light" href="?step=3">← પાછળ</a>
          <button class="btn btn-primary">આગળ →</button>
        </div>
      </form>

    <?php elseif ($step === 5): ?>
      <h2>Step 5 — એડમિન એકાઉન્ટ</h2>
      <form method="post">
        <div class="row">
          <div><label>Username</label><input type="text" name="admin_username" required minlength="3"></div>
          <div><label>Email</label><input type="email" name="admin_email2" value="<?= h(wiz('site')['admin_email'] ?? '') ?>" required></div>
        </div>
        <label>Password (min 8 chars)</label>
        <input type="password" name="admin_password" id="pw" required minlength="8">
        <div class="pw-meter"><i id="pwBar"></i></div>
        <label>Confirm Password</label>
        <input type="password" name="admin_password_confirm" required minlength="8">
        <div class="btns">
          <a class="btn btn-light" href="?step=4">← પાછળ</a>
          <button class="btn btn-primary">Install પૂર્ણ કરો ✓</button>
        </div>
      </form>
      <script>
      document.getElementById('pw').addEventListener('input', function () {
        let s = 0;
        if (this.value.length >= 8) s++;
        if (/[A-Z]/.test(this.value)) s++;
        if (/[0-9]/.test(this.value)) s++;
        if (/[^A-Za-z0-9]/.test(this.value)) s++;
        const bar = document.getElementById('pwBar');
        bar.style.width = (s * 25) + '%';
        bar.style.background = s <= 1 ? '#c0392b' : (s <= 2 ? '#e67e22' : (s === 3 ? '#f1c40f' : '#27ae60'));
      });
      </script>

    <?php else: ?>
      <h2>Step 6 — Installation પૂર્ણ! 🎉</h2>
      <div class="success-big">
        <div class="tick">✅</div>
        <p style="margin:10px 0 4px;font-weight:600">Gujarati Font Converter સફળતાપૂર્વક install થયું</p>
      </div>
      <div class="error-box">
        <strong>⚠ સુરક્ષા — અગત્યનું:</strong> હવે <code>/install</code> ફોલ્ડર server પરથી <strong>delete</strong> કરો
        (અથવા rename કરો). <code>install.lock</code> ફાઇલ delete ન કરશો.
      </div>
      <div class="info-box">
        <p><strong>Admin Panel:</strong> <a href="../admin/login.php">/admin/login.php</a></p>
        <p><strong>Username:</strong> <?= h(wiz('admin')['username'] ?? '(તમે બનાવેલું)') ?></p>
        <p><strong>આગળનાં પગલાં:</strong></p>
        <p>1. Admin → Settings માં SMTP + Analytics configure કરો<br>
        2. Admin → Update માં GitHub repo જોડો (auto-update માટે)<br>
        3. Admin → Fonts માં mapping data ચકાસો</p>
      </div>
      <div class="btns">
        <a class="btn btn-light" href="../">🏠 વેબસાઇટ જુઓ</a>
        <a class="btn btn-primary" href="../admin/login.php">Admin Login →</a>
      </div>
      <?php unset($_SESSION['wizard']); ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
