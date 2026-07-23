<?php
/**
 * API Key management — generate, revoke, limits, usage.
 */
$PAGE_TITLE = 'API Keys';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$keysTable = $db->table('api_keys');
$usersTable = $db->table('users');
$apiLogsTable = $db->table('api_logs');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'generate') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $user = $db->fetch("SELECT id FROM `{$usersTable}` WHERE id = ?", [$userId]);
            if ($user === null) {
                throw new RuntimeException('User ID not found.');
            }
            $key = 'gfc_' . Helper::randomToken(28);
            $db->insert('api_keys', [
                'user_id'     => $userId,
                'api_key'     => $key,
                'api_secret'  => Helper::randomToken(32),
                'name'        => mb_substr(trim((string)($_POST['name'] ?? 'Admin issued')), 0, 100) ?: 'Admin issued',
                'daily_limit' => max(0, (int)($_POST['daily_limit'] ?? 1000)),
                'calls_date'  => date('Y-m-d'),
            ]);
            $msg = 'New key: ' . $key;
        } elseif ($action === 'revoke' && $id > 0) {
            $db->update('api_keys', ['status' => 'revoked'], 'id = ?', [$id]);
            $msg = 'Key revoked.';
        } elseif ($action === 'activate' && $id > 0) {
            $db->update('api_keys', ['status' => 'active'], 'id = ?', [$id]);
            $msg = 'Key reactivated.';
        } elseif ($action === 'set_limit' && $id > 0) {
            $db->update('api_keys', ['daily_limit' => max(0, (int)($_POST['daily_limit'] ?? 0))], 'id = ?', [$id]);
            $msg = 'Limit changed.';
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'api-keys', ['key_id' => $id]);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$keys = $db->fetchAll(
    "SELECT k.*, u.email FROM `{$keysTable}` k JOIN `{$usersTable}` u ON u.id = k.user_id ORDER BY k.created_at DESC LIMIT 100"
);
// છેલ્લા 7 દિવસનું usage
$usage = $db->fetchAll(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM `{$apiLogsTable}`
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY d"
);
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>Generate New Key</h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="generate">
      <label>User ID</label><input type="number" name="user_id" required>
      <label>Name</label><input type="text" name="name" value="Admin issued">
      <label>Daily Limit</label><input type="number" name="daily_limit" value="1000">
      <button class="abtn abtn-primary" type="submit">Generate</button>
    </form>
  </div>
  <div class="admin-card">
    <h2>API Usage — Last 7 Days</h2>
    <table class="atable">
      <?php foreach ($usage as $u): ?>
        <tr><td><?= $e($u['d']) ?></td><td class="num"><?= number_format((int)$u['c']) ?> calls</td></tr>
      <?php endforeach; ?>
      <?php if (!$usage): ?><tr><td class="amuted">No API calls yet</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<div class="admin-card">
  <h2>All Keys</h2>
  <div class="table-scroll">
    <table class="atable">
      <tr><th>Key</th><th>User</th><th>Name</th><th>Today/Limit</th><th>Total</th><th>Status</th><th></th></tr>
      <?php foreach ($keys as $k): ?>
      <tr>
        <td><code><?= $e(substr($k['api_key'], 0, 16)) ?>…</code></td>
        <td><small><?= $e($k['email']) ?></small></td>
        <td><?= $e($k['name']) ?></td>
        <td>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="set_limit"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
            <?= (int)($k['calls_date'] === date('Y-m-d') ? $k['calls_today'] : 0) ?> /
            <input type="number" name="daily_limit" value="<?= (int)$k['daily_limit'] ?>" style="width:80px">
            <button class="abtn abtn-xs" type="submit">Set</button>
          </form>
        </td>
        <td class="num"><?= number_format((int)$k['total_calls']) ?></td>
        <td><span class="abadge ab-<?= $k['status'] === 'active' ? 'ok' : 'err' ?>"><?= $e($k['status']) ?></span></td>
        <td>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="<?= $k['status'] === 'active' ? 'revoke' : 'activate' ?>">
            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
            <button class="abtn abtn-xs <?= $k['status'] === 'active' ? 'abtn-danger' : '' ?>" type="submit">
              <?= $k['status'] === 'active' ? 'Revoke' : 'Activate' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
