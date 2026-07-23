<?php
defined('BASE_PATH') or die('Direct access denied');
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$menu = [
    'index.php'          => ['📊', 'Dashboard'],
    'fonts.php'          => ['🔤', 'Font Manager'],
    'mapping-editor.php' => ['🗺️', 'Mapping Editor'],
    'users.php'          => ['👥', 'Users'],
    'plans.php'          => ['💳', 'Plans'],
    'api-keys.php'       => ['🔑', 'API Keys'],
    'pages.php'          => ['📄', 'Pages (CMS)'],
    'blog.php'           => ['✍️', 'Blog'],
    'seo.php'            => ['🔍', 'SEO'],
    'logs.php'           => ['📋', 'Logs'],
    'backup.php'         => ['💾', 'Backup'],
    'settings.php'       => ['⚙️', 'Settings'],
    'update.php'         => ['🔄', 'Update'],
];
?>
<aside class="admin-sidebar">
  <div class="sidebar-logo">🔤 GFC Admin</div>
  <nav>
    <?php foreach ($menu as $file => [$icon, $label]): ?>
      <a href="<?= Helper::e($file) ?>" class="<?= $current === $file ? 'active' : '' ?>">
        <span class="mi"><?= $icon ?></span> <?= Helper::e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-version">v<?= APP_VERSION ?></div>
</aside>
