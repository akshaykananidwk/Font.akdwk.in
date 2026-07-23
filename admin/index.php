<?php
/**
 * Admin Dashboard — stats, chart, top fonts, server status, update badge.
 */
$PAGE_TITLE = 'ડેશબોર્ડ';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$conv = $db->table('conversions_log');
$fonts = $db->table('fonts');
$users = $db->table('users');
$contacts = $db->table('contacts');
$apiLogs = $db->table('api_logs');

$stats = [
    'today'    => (int)$db->fetchValue("SELECT COUNT(*) FROM `{$conv}` WHERE created_at >= CURDATE()"),
    'week'     => (int)$db->fetchValue("SELECT COUNT(*) FROM `{$conv}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'month'    => (int)$db->fetchValue("SELECT COUNT(*) FROM `{$conv}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    'users'    => (int)$db->fetchValue("SELECT COUNT(*) FROM `{$users}`"),
    'active'   => (int)$db->fetchValue("SELECT COUNT(*) FROM `{$users}` WHERE status='active' AND subscription_end > NOW()"),
    'apiToday' => (int)$db->fetchValue("SELECT COUNT(*) FROM `{$apiLogs}` WHERE created_at >= CURDATE()"),
];

$topFonts = $db->fetchAll(
    "SELECT font_name, conversion_count FROM `{$fonts}` ORDER BY conversion_count DESC LIMIT 10"
);
$recentContacts = $db->fetchAll(
    "SELECT name, email, subject, status, created_at FROM `{$contacts}` ORDER BY created_at DESC LIMIT 10"
);

// છેલ્લા 14 દિવસનો chart data
$chartRows = $db->fetchAll(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM `{$conv}`
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at) ORDER BY d"
);
$chartData = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $chartData[$day] = 0;
}
foreach ($chartRows as $row) {
    $chartData[$row['d']] = (int)$row['c'];
}

// Server status
$dbSize = (float)$db->fetchValue(
    "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()"
);
$diskFree = (float)@disk_free_space(BASE_PATH);
?>
<div class="stat-grid">
  <div class="stat-card"><span class="stat-num"><?= number_format($stats['today']) ?></span><span class="stat-label">આજના કન્વર્ઝન</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($stats['week']) ?></span><span class="stat-label">આ અઠવાડિયે</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($stats['month']) ?></span><span class="stat-label">આ મહિને</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($stats['users']) ?></span><span class="stat-label">કુલ યુઝર</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($stats['active']) ?></span><span class="stat-label">Active સબસ્ક્રિપ્શન</span></div>
  <div class="stat-card"><span class="stat-num"><?= number_format($stats['apiToday']) ?></span><span class="stat-label">આજની API calls</span></div>
</div>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>છેલ્લા 14 દિવસના કન્વર્ઝન</h2>
    <div class="bar-chart" role="img" aria-label="Daily conversions chart">
      <?php $max = max(1, max($chartData)); ?>
      <?php foreach ($chartData as $day => $count): ?>
        <div class="bar-col" title="<?= $e($day) ?>: <?= $count ?>">
          <div class="bar" style="height:<?= (int)(($count / $max) * 100) ?>%"></div>
          <span class="bar-label"><?= date('d', strtotime($day)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-card">
    <h2>ટોપ 10 ફોન્ટ</h2>
    <table class="atable">
      <?php foreach ($topFonts as $i => $f): ?>
      <tr><td><?= $i + 1 ?>. <?= $e($f['font_name']) ?></td><td class="num"><?= number_format((int)$f['conversion_count']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>છેલ્લા 10 Contacts</h2>
    <?php if (!$recentContacts): ?><p class="amuted">કોઈ સંદેશ નથી.</p><?php endif; ?>
    <table class="atable">
      <?php foreach ($recentContacts as $c): ?>
      <tr>
        <td><strong><?= $e($c['name']) ?></strong><br><small><?= $e($c['email']) ?></small></td>
        <td><?= $e(mb_substr((string)$c['subject'], 0, 40)) ?></td>
        <td><span class="abadge ab-<?= $c['status'] === 'new' ? 'warn' : 'ok' ?>"><?= $e($c['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="admin-card">
    <h2>સર્વર Status</h2>
    <table class="atable">
      <tr><td>PHP Version</td><td><?= $e(PHP_VERSION) ?></td></tr>
      <tr><td>DB Size</td><td><?= Helper::formatBytes((int)$dbSize) ?></td></tr>
      <tr><td>Disk Free</td><td><?= Helper::formatBytes((int)$diskFree) ?></td></tr>
      <tr><td>App Version</td><td>v<?= APP_VERSION ?></td></tr>
      <tr><td>intl Extension</td><td><?= extension_loaded('intl') ? '✓' : '✗ (ભલામણ)' ?></td></tr>
      <tr><td>Maintenance</td><td><?= App::isMaintenanceMode() ? '🔧 ON' : '✓ OFF' ?></td></tr>
    </table>
    <p style="margin-top:10px"><a class="abtn" href="update.php">🔄 Update ચેક કરો</a></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
