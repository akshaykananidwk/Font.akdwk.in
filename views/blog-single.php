<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow">
  <nav class="breadcrumbs">
    <a href="<?= Helper::e(App::url('/')) ?>">હોમ</a> <span>›</span>
    <a href="<?= Helper::e(App::url('/blog')) ?>">બ્લોગ</a> <span>›</span>
    <span><?= Helper::e($post['title']) ?></span>
  </nav>
  <article class="page-content">
    <h1><?= Helper::e($post['title']) ?></h1>
    <p class="muted small">
      <?= $post['published_at'] ? date('d M Y', strtotime($post['published_at'])) : '' ?>
      <?php if (!empty($post['category_name'])): ?> · <?= Helper::e($post['category_name']) ?><?php endif; ?>
      · <?= number_format((int)$post['views']) ?> views
    </p>
    <?php if (!empty($post['featured_image'])): ?>
      <img src="<?= Helper::e($post['featured_image']) ?>" alt="<?= Helper::e($post['title']) ?>" class="featured-img" loading="lazy" width="800" height="420">
    <?php endif; ?>
    <?= $post['content'] /* admin-authored trusted HTML */ ?>
  </article>

  <?php if (!empty($related)): ?>
  <aside class="related-posts">
    <h2 class="h-small">સંબંધિત લેખ</h2>
    <ul>
      <?php foreach ($related as $r): ?>
        <li><a href="<?= Helper::e(App::url('/blog/' . $r['slug'])) ?>"><?= Helper::e($r['title']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
  <?php endif; ?>
</div>
