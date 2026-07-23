<?php
/**
 * Admin common bootstrap + HTML header.
 * દરેક admin પેજ સૌથી ઉપર આ require કરે: require __DIR__ . '/includes/header.php';
 * પેજે પહેલા $PAGE_TITLE સેટ કરવો; $REQUIRE_SUPER = true હોય તો super_admin ફરજિયાત.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/constants.php';
require_once CORE_PATH . '/App.php';
App::bootstrap();

if (!App::isInstalled()) {
    header('Location: ../install/');
    exit;
}

Security::sendHeaders();
header('X-Robots-Tag: noindex, nofollow');

if (empty($SKIP_AUTH)) {
    Auth::requireAdmin();
    if (!empty($REQUIRE_SUPER)) {
        Auth::requireSuperAdmin();
    }
}

$ADMIN_URL = App::url((App::config()['site']['admin_path'] ?? 'admin'));
$PAGE_TITLE = $PAGE_TITLE ?? 'Admin';
$e = fn($s) => Helper::e((string)$s);
?>
<!DOCTYPE html>
<html lang="gu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= $e(Security::csrfToken()) ?>">
<title><?= $e($PAGE_TITLE) ?> — Admin</title>
<link rel="stylesheet" href="<?= $e(App::url('/assets/css/admin.css')) ?>?v=<?= APP_VERSION ?>">
</head>
<body class="admin-body">
<?php if (empty($SKIP_AUTH)): ?>
<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
  <h1><?= $e($PAGE_TITLE) ?></h1>
  <div class="topbar-right">
    <a href="<?= $e(App::url('/')) ?>" target="_blank" rel="noopener">🌐 સાઇટ જુઓ</a>
    <span class="admin-user">👤 <?= $e((string)Session::get('admin_username')) ?></span>
    <a href="logout.php" class="btn-logout">લોગઆઉટ</a>
  </div>
</div>
<div class="admin-content">
<?php endif; ?>
