<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container">
  <nav class="breadcrumbs"><a href="<?= Helper::e(App::url('/')) ?>">Home</a> <span>›</span> <span>Blog</span></nav>
  <h1>Blog</h1>
  <form method="get" action="<?= Helper::e(App::url('/blog')) ?>" class="blog-search">
    <input type="search" name="q" value="<?= Helper::e($search ?? '') ?>" placeholder="Search articles...">
    <button class="btn btn-sm-h" type="submit">Search</button>
  </form>

  <?php if (empty($posts)): ?>
    <p class="muted">No articles yet. Coming soon!</p>
  <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($posts as $post): ?>
        <article class="blog-card">
          <h2><a href="<?= Helper::e(App::url('/blog/' . $post['slug'])) ?>"><?= Helper::e($post['title']) ?></a></h2>
          <p class="muted small">
            <?= $post['published_at'] ? date('d M Y', strtotime($post['published_at'])) : '' ?>
            <?php if (!empty($post['category_name'])): ?> · <?= Helper::e($post['category_name']) ?><?php endif; ?>
          </p>
          <p><?= Helper::e(mb_substr(strip_tags((string)($post['excerpt'] ?: $post['content'])), 0, 180)) ?>…</p>
          <a class="read-more" href="<?= Helper::e(App::url('/blog/' . $post['slug'])) ?>">Read more →</a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if (($totalPages ?? 1) > 1): ?>
      <nav class="pagination" aria-label="Pages">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
