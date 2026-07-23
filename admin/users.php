<?php
/**
 * યુઝર મેનેજર — CRUD, plan assign, subscription extend, ban.
 */
$PAGE_TITLE = 'યુઝર્સ';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$usersTable = $db->table('users');
$plansTable = $db->table('plans');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'assign_plan' && $id > 0) {
            $planId = (int)($_POST['plan_id'] ?? 0) ?: null;
            $days = max(0, (int)($_POST['days'] ?? 30));
            $data = ['plan_id' => $planId];
            if ($planId !== null && $days > 0) {
                $user = $db->fetch("SELECT subscription_end FROM `{$usersTable}` WHERE id = ?", [$id]);
                // હાલનું subscription ચાલુ હોય તો extend, નહીં તો આજથી
                $base = ($user && $user['subscription_end'] && strtotime($user['subscription_end']) > time())
                    ? strtotime($user['subscription_end'])
                    : time();
                $data['subscription_start'] = date('Y-m-d H:i:s');
                $data['subscription_end'] = date('Y-m-d H:i:s', $base + $days * 86400);
                $data['status'] = 'active';
            }
            $db->update('users', $data, 'id = ?', [$id]);
            $db->insert('subscriptions', [
                'user_id'   => $id,
                'plan_id'   => $planId,
                'status'    => 'completed',
                'payment_method' => 'manual_admin',
                'starts_at' => $data['subscription_start'] ?? null,
                'expires_at' => $data['subscription_end'] ?? null,
            ]);
            $msg = 'Plan assign/extend થયો.';
        } elseif ($action === 'toggle_status' && $id > 0) {
            $db->query(
                "UPDATE `{$usersTable}` SET status = IF(status = 'suspended', 'active', 'suspended') WHERE id = ?",
                [$id]
            );
            $msg = 'User status બદલાયો.';
        } elseif ($action === 'delete' && $id > 0) {
            $db->query("DELETE FROM `{$usersTable}` WHERE id = ?", [$id]);
            $msg = 'User delete થયો.';
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'users', ['user_id' => $id]);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE u.name LIKE ? OR u.email LIKE ?";
    $params = ['%' . $search . '%', '%' . $search . '%'];
}
$users = $db->fetchAll(
    "SELECT u.*, p.plan_name FROM `{$usersTable}` u
     LEFT JOIN `{$plansTable}` p ON p.id = u.plan_id {$where}
     ORDER BY u.created_at DESC LIMIT 200",
    $params
);
$plans = $db->fetchAll("SELECT * FROM `{$plansTable}` WHERE is_active = 1 ORDER BY sort_order");
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-card">
  <form method="get" class="filter-row">
    <input type="search" name="q" value="<?= $e($search) ?>" placeholder="નામ કે email શોધો...">
    <button class="abtn" type="submit">શોધો</button>
  </form>
  <div class="table-scroll">
    <table class="atable">
      <tr><th>ID</th><th>નામ / Email</th><th>Plan</th><th>Subscription</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><strong><?= $e($u['name']) ?></strong><br><small><?= $e($u['email']) ?></small></td>
        <td><?= $e($u['plan_name'] ?? '—') ?></td>
        <td>
          <?php if ($u['subscription_end']): ?>
            <?= date('d M Y', strtotime($u['subscription_end'])) ?> સુધી
            <?= strtotime($u['subscription_end']) > time() ? '✓' : '(expired)' ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><span class="abadge ab-<?= $u['status'] === 'active' ? 'ok' : 'err' ?>"><?= $e($u['status']) ?></span></td>
        <td>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="assign_plan"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <select name="plan_id">
              <option value="0">— plan —</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$u['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= $e($p['plan_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="days" value="30" style="width:64px" title="દિવસ">
            <button class="abtn abtn-xs" type="submit">Assign</button>
          </form>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="abtn abtn-xs" type="submit"><?= $u['status'] === 'suspended' ? 'Unban' : 'Ban' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('User delete કરવો?')"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="abtn abtn-xs abtn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
