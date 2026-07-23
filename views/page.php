<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow">
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="<?= Helper::e(App::url('/')) ?>">Home</a> <span>›</span> <span><?= Helper::e($page['title']) ?></span>
  </nav>
  <article class="page-content">
    <h1><?= Helper::e($page['title']) ?></h1>
    <?= $page['content'] /* admin-authored trusted HTML */ ?>
  </article>
</div>
