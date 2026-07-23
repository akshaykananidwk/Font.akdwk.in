<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow center error-page">
  <h1>500</h1>
  <p class="intro">Something went wrong.</p>
  <p>The error has been logged — please try again shortly.</p>
  <p style="margin-top:20px"><a class="btn" href="<?= Helper::e(App::url('/')) ?>">🏠 Home page</a></p>
</div>
