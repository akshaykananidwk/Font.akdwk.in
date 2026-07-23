<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow center error-page">
  <h1>404</h1>
  <p class="intro">માફ કરશો — આ પેજ મળ્યું નથી.</p>
  <p>કદાચ URL બદલાયું છે અથવા ખોટું ટાઇપ થયું છે.</p>
  <form method="get" action="<?= Helper::e(App::url('/blog')) ?>" class="blog-search center-form">
    <input type="search" name="q" placeholder="સાઇટ પર શોધો...">
    <button class="btn btn-sm-h" type="submit">શોધો</button>
  </form>
  <p style="margin-top:20px">
    <a class="btn" href="<?= Helper::e(App::url('/')) ?>">🏠 હોમ પેજ</a>
    <a class="btn btn-secondary" href="<?= Helper::e(App::url('/gujarati-font-converter')) ?>">ગુજરાતી કન્વર્ટર</a>
  </p>
</div>
