<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container">
  <nav class="breadcrumbs"><a href="<?= Helper::e(App::url('/')) ?>">Home</a> <span>›</span> <span>Pricing</span></nav>
  <h1>Simple, Transparent Pricing</h1>
  <p class="intro">Free forever up to 200 characters. Need more? Affordable plans — cancel anytime.</p>
  <div class="plans-grid">
    <?php foreach ($plans as $plan): ?>
      <?php $features = json_decode((string)$plan['features'], true) ?: []; ?>
      <div class="plan-card <?= $plan['plan_name'] === 'Pro' ? 'plan-featured' : '' ?>">
        <?php if ($plan['plan_name'] === 'Pro'): ?><div class="plan-badge">Popular</div><?php endif; ?>
        <h3><?= Helper::e($plan['plan_name']) ?></h3>
        <div class="plan-price">
          <?php if ((float)$plan['price'] == 0): ?>
            Free
          <?php else: ?>
            ₹<?= number_format((float)$plan['price']) ?><span>/<?= (int)$plan['duration_days'] ?> days</span>
          <?php endif; ?>
        </div>
        <ul class="plan-features">
          <?php foreach ($features as $feat): ?>
            <li>✓ <?= Helper::e($feat) ?></li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= Helper::e(App::url((float)$plan['price'] == 0 ? '/' : '/contact')) ?>" class="btn">
          <?= (float)$plan['price'] == 0 ? 'Use Now' : 'Contact to Buy' ?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted center">Payment gateway integration coming soon — for now, to buy a plan please <a href="<?= Helper::e(App::url('/contact')) ?>">contact us</a>.</p>
</div>
