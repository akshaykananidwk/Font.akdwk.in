<?php
defined('BASE_PATH') or die('Direct access denied');
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$menu = [
    'index.php'          => ['📊', 'ડેશબોર્ડ'],
    'fonts.php'          => ['🔤', 'ફોન્ટ મેનેજર'],
    'mapping-editor.php' => ['🗺️', 'Mapping Editor'],
    'users.php'          => ['👥', 'યુઝર્સ'],
    'plans.php'          => ['💳', 'પ્લાન'],
    'api-keys.php'       => ['🔑', 'API Keys'],
    'pages.php'          => ['📄', 'પેજીસ (CMS)'],
    'blog.php'           => ['✍️', 'બ્લોગ'],
    'seo.php'            => ['🔍', 'SEO'],
    'logs.php'           => ['📋', 'લોગ્સ'],
    'backup.php'         => ['💾', 'બેકઅપ'],
    'settings.php'       => ['⚙️', 'સેટિંગ્સ'],
    'update.php'         => ['🔄', 'અપડેટ'],
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
