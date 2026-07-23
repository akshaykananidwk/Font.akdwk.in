<?php
/**
 * પ્લાન મેનેજર — CRUD.
 */
$PAGE_TITLE = 'Plans';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$plansTable = $db->table('plans');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'save') {
            $features = array_values(array_filter(array_map('trim', explode("\n", (string)($_POST['features'] ?? '')))));
            $data = [
                'plan_name'       => mb_substr(trim((string)($_POST['plan_name'] ?? '')), 0, 100),
                'price'           => (float)($_POST['price'] ?? 0),
                'currency'        => mb_substr(trim((string)($_POST['currency'] ?? 'INR')), 0, 10),
                'duration_days'   => max(1, (int)($_POST['duration_days'] ?? 30)),
                'char_limit'      => max(0, (int)($_POST['char_limit'] ?? 0)),
                'daily_limit'     => max(0, (int)($_POST['daily_limit'] ?? 0)),
                'api_access'      => (int)!empty($_POST['api_access']),
                'api_daily_limit' => max(0, (int)($_POST['api_daily_limit'] ?? 0)),
                'features'        => json_encode($features, JSON_UNESCAPED_UNICODE),
                'sort_order'      => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($data['plan_name'] === '') {
                throw new RuntimeException('Plan name is required.');
            }
            if ($id > 0) {
                $db->update('plans', $data, 'id = ?', [$id]);
                $msg = 'Plan updated.';
            } else {
                $db->insert('plans', $data);
                $msg = 'New plan created.';
            }
        } elseif ($action === 'toggle' && $id > 0) {
            $db->query("UPDATE `{$plansTable}` SET is_active = 1 - is_active WHERE id = ?", [$id]);
            $msg = 'Plan status changed.';
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'plans', ['plan_id' => $id]);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$plans = $db->fetchAll("SELECT * FROM `{$plansTable}` ORDER BY sort_order");
$editPlan = null;
if (($editId = (int)($_GET['edit'] ?? 0)) > 0) {
    $editPlan = $db->fetch("SELECT * FROM `{$plansTable}` WHERE id = ?", [$editId]);
}
$editFeatures = $editPlan ? implode("\n", json_decode((string)$editPlan['features'], true) ?: []) : '';
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2><?= $editPlan ? 'Edit Plan' : 'New Plan' ?></h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($editPlan['id'] ?? 0) ?>">
      <label>Name</label><input type="text" name="plan_name" value="<?= $e($editPlan['plan_name'] ?? '') ?>" required>
      <div class="row-2">
        <div><label>Price</label><input type="number" step="0.01" name="price" value="<?= $e($editPlan['price'] ?? '0') ?>"></div>
        <div><label>Currency</label><input type="text" name="currency" value="<?= $e($editPlan['currency'] ?? 'INR') ?>"></div>
      </div>
      <div class="row-2">
        <div><label>Duration (days)</label><input type="number" name="duration_days" value="<?= (int)($editPlan['duration_days'] ?? 30) ?>"></div>
        <div><label>Char Limit (0=unlimited)</label><input type="number" name="char_limit" value="<?= (int)($editPlan['char_limit'] ?? 0) ?>"></div>
      </div>
      <div class="row-2">
        <div><label>Daily Limit (0=unlimited)</label><input type="number" name="daily_limit" value="<?= (int)($editPlan['daily_limit'] ?? 0) ?>"></div>
        <div><label>API Daily Limit</label><input type="number" name="api_daily_limit" value="<?= (int)($editPlan['api_daily_limit'] ?? 0) ?>"></div>
      </div>
      <label><input type="checkbox" name="api_access" value="1" <?= (int)($editPlan['api_access'] ?? 0) === 1 ? 'checked' : '' ?>> API Access</label>
      <label>Features (one per line)</label>
      <textarea name="features" rows="4"><?= $e($editFeatures) ?></textarea>
      <label>Sort Order</label><input type="number" name="sort_order" value="<?= (int)($editPlan['sort_order'] ?? 0) ?>">
      <button class="abtn abtn-primary" type="submit">Save</button>
      <?php if ($editPlan): ?><a class="abtn" href="plans.php">Cancel</a><?php endif; ?>
    </form>
  </div>

  <div class="admin-card">
    <h2>All Plans</h2>
    <table class="atable">
      <tr><th>Name</th><th>Price</th><th>API</th><th>Active</th><th></th></tr>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td><?= $e($p['plan_name']) ?></td>
        <td>₹<?= number_format((float)$p['price']) ?>/<?= (int)$p['duration_days'] ?>d</td>
        <td><?= (int)$p['api_access'] === 1 ? '✓ ' . (int)$p['api_daily_limit'] . '/day' : '—' ?></td>
        <td>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="link-btn" type="submit"><?= (int)$p['is_active'] === 1 ? '✅' : '⛔' ?></button>
          </form>
        </td>
        <td><a class="abtn abtn-xs" href="?edit=<?= (int)$p['id'] ?>">Edit</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
