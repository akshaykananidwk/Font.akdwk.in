<?php
/**
 * લોગ્સ — conversion, API, admin activity, error logs (filter + CSV export).
 */
$PAGE_TITLE = 'Logs';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$type = (string)($_GET['type'] ?? 'conversions');
if (!in_array($type, ['conversions', 'api', 'admin', 'errors'], true)) {
    $type = 'conversions';
}

// ---- CSV export ----
if (($_GET['export'] ?? '') === 'csv' && $type !== 'errors') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_log_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($type === 'conversions') {
        $rows = $db->fetchAll("SELECT * FROM `" . $db->table('conversions_log') . "` ORDER BY id DESC LIMIT 5000");
    } elseif ($type === 'api') {
        $rows = $db->fetchAll("SELECT * FROM `" . $db->table('api_logs') . "` ORDER BY id DESC LIMIT 5000");
    } else {
        $rows = $db->fetchAll("SELECT * FROM `" . $db->table('admin_activity_log') . "` ORDER BY id DESC LIMIT 5000");
    }
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}

$rows = [];
$errorContent = '';
if ($type === 'conversions') {
    $rows = $db->fetchAll(
        "SELECT c.id, c.ip_address, f.font_name, c.direction, c.char_count, c.created_at
         FROM `" . $db->table('conversions_log') . "` c
         LEFT JOIN `" . $db->table('fonts') . "` f ON f.id = c.font_id
         ORDER BY c.id DESC LIMIT 100"
    );
} elseif ($type === 'api') {
    $rows = $db->fetchAll(
        "SELECT id, api_key_id, endpoint, ip_address, request_chars, response_code, response_time_ms, created_at
         FROM `" . $db->table('api_logs') . "` ORDER BY id DESC LIMIT 100"
    );
} elseif ($type === 'admin') {
    $rows = $db->fetchAll(
        "SELECT l.id, a.username, l.action, l.module, l.ip_address, l.created_at
         FROM `" . $db->table('admin_activity_log') . "` l
         LEFT JOIN `" . $db->table('admins') . "` a ON a.id = l.admin_id
         ORDER BY l.id DESC LIMIT 100"
    );
} else {
    $files = glob(LOGS_PATH . '/*.log') ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    if ($files) {
        $errorContent = implode('', array_slice(file($files[0]) ?: [], -200));
        $errorContent = basename($files[0]) . " (last 200 lines):\n\n" . $errorContent;
    }
}
?>
<div class="admin-card">
  <div class="filter-row">
    <?php foreach (['conversions' => 'Conversions', 'api' => 'API', 'admin' => 'Admin Activity', 'errors' => 'Error Files'] as $t => $label): ?>
      <a class="abtn <?= $type === $t ? 'abtn-primary' : '' ?>" href="?type=<?= $t ?>"><?= $e($label) ?></a>
    <?php endforeach; ?>
    <?php if ($type !== 'errors'): ?>
      <a class="abtn" href="?type=<?= $e($type) ?>&export=csv">⬇ CSV Export</a>
    <?php endif; ?>
  </div>

  <?php if ($type === 'errors'): ?>
    <pre class="test-output" style="max-height:520px;overflow:auto"><?= $e($errorContent ?: 'No log files.') ?></pre>
  <?php elseif (!$rows): ?>
    <p class="amuted">No records.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="atable">
        <tr><?php foreach (array_keys($rows[0]) as $col): ?><th><?= $e($col) ?></th><?php endforeach; ?></tr>
        <?php foreach ($rows as $row): ?>
          <tr><?php foreach ($row as $val): ?><td><?= $e(mb_substr((string)$val, 0, 60)) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
