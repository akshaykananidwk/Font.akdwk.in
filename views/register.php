<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow auth-wrap">
  <h1>Register</h1>
  <?php if (!empty($error)): ?><div class="alert alert-error">⚠ <?= Helper::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= Helper::e(App::url('/register')) ?>" class="std-form">
    <?= Security::csrfField() ?>
    <div class="hp-field" aria-hidden="true"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>
    <label for="rName">Name</label>
    <input id="rName" type="text" name="name" required maxlength="100" autocomplete="name">
    <label for="rEmail">Email</label>
    <input id="rEmail" type="email" name="email" required autocomplete="email">
    <label for="rPass">Password (at least 8 characters)</label>
    <input id="rPass" type="password" name="password" required minlength="8" autocomplete="new-password">
    <button class="btn" type="submit">Create account</button>
  </form>
  <p class="muted">Already have an account? <a href="<?= Helper::e(App::url('/login')) ?>">Login</a></p>
</div>
