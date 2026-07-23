<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow center error-page">
  <h1>404</h1>
  <p class="intro">Sorry — this page was not found.</p>
  <p>The URL may have changed or been typed incorrectly.</p>
  <form method="get" action="<?= Helper::e(App::url('/blog')) ?>" class="blog-search center-form">
    <input type="search" name="q" placeholder="Search the site...">
    <button class="btn btn-sm-h" type="submit">Search</button>
  </form>
  <p style="margin-top:20px">
    <a class="btn" href="<?= Helper::e(App::url('/')) ?>">🏠 Home page</a>
    <a class="btn btn-secondary" href="<?= Helper::e(App::url('/gujarati-font-converter')) ?>">Gujarati Converter</a>
  </p>
</div>
