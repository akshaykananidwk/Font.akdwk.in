<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container">
  <nav class="breadcrumbs"><a href="<?= Helper::e(App::url('/')) ?>">હોમ</a> <span>›</span> <span>ભાવ</span></nav>
  <h1>સાદા અને પારદર્શક ભાવ</h1>
  <p class="intro">200 અક્ષર સુધી હંમેશ માટે મફત. વધુ જોઈએ તો સસ્તા plans — ગમે ત્યારે cancel કરો.</p>
  <div class="plans-grid">
    <?php foreach ($plans as $plan): ?>
      <?php $features = json_decode((string)$plan['features'], true) ?: []; ?>
      <div class="plan-card <?= $plan['plan_name'] === 'Pro' ? 'plan-featured' : '' ?>">
        <?php if ($plan['plan_name'] === 'Pro'): ?><div class="plan-badge">લોકપ્રિય</div><?php endif; ?>
        <h3><?= Helper::e($plan['plan_name']) ?></h3>
        <div class="plan-price">
          <?php if ((float)$plan['price'] == 0): ?>
            મફત
          <?php else: ?>
            ₹<?= number_format((float)$plan['price']) ?><span>/<?= (int)$plan['duration_days'] ?> દિવસ</span>
          <?php endif; ?>
        </div>
        <ul class="plan-features">
          <?php foreach ($features as $feat): ?>
            <li>✓ <?= Helper::e($feat) ?></li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= Helper::e(App::url((float)$plan['price'] == 0 ? '/' : '/contact')) ?>" class="btn">
          <?= (float)$plan['price'] == 0 ? 'હમણાં વાપરો' : 'ખરીદવા સંપર્ક કરો' ?>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted center">Payment gateway integration ટૂંક સમયમાં — હાલ plan ખરીદવા <a href="<?= Helper::e(App::url('/contact')) ?>">સંપર્ક કરો</a>.</p>
</div>
