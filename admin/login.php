<?php
/**
 * Admin login — lockout + rate limit સાથે.
 */
$SKIP_AUTH = true;
$PAGE_TITLE = 'Admin Login';
require __DIR__ . '/includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = 'Session expired — please try again.';
    } elseif (!Security::rateLimit(Helper::clientIp(), 'admin_login', 10, 900)) {
        $error = 'Too many attempts — please try again after 15 minutes.';
    } else {
        $result = Auth::adminLogin(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''));
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        $error = $result['error'] ?? 'Login failed';
    }
}

if (Auth::isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">🔤</div>
    <h1>Admin Login</h1>
    <?php if ($error !== ''): ?>
      <div class="admin-alert alert-err">⚠ <?= $e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= Security::csrfField() ?>
      <label for="aUser">Username</label>
      <input id="aUser" type="text" name="username" required autocomplete="username" autofocus>
      <label for="aPass">Password</label>
      <input id="aPass" type="password" name="password" required autocomplete="current-password">
      <button type="submit" class="abtn abtn-primary abtn-block">Login</button>
    </form>
  </div>
</div>
</body>
</html>
