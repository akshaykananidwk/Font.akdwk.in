<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<?php include VIEWS_PATH . '/partials/head.php'; ?>
</head>
<body>
<?php include VIEWS_PATH . '/partials/header.php'; ?>
<main id="main">
<?= $content ?? '' ?>
</main>
<?php include VIEWS_PATH . '/partials/footer.php'; ?>
<?php include VIEWS_PATH . '/partials/schema.php'; ?>
<script src="<?= Helper::e(App::url('/assets/js/converter.js')) ?>?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>
