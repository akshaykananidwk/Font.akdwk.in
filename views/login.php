<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow auth-wrap">
  <h1>લોગિન</h1>
  <?php if (!empty($success)): ?><div class="alert alert-success">✓ <?= Helper::e($success) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-error">⚠ <?= Helper::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= Helper::e(App::url('/login')) ?>" class="std-form">
    <?= Security::csrfField() ?>
    <label for="lEmail">Email</label>
    <input id="lEmail" type="email" name="email" required autocomplete="email">
    <label for="lPass">Password</label>
    <input id="lPass" type="password" name="password" required autocomplete="current-password">
    <button class="btn" type="submit">લોગિન</button>
  </form>
  <p class="muted">એકાઉન્ટ નથી? <a href="<?= Helper::e(App::url('/register')) ?>">રજિસ્ટર કરો</a></p>
</div>
