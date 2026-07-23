<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container">
  <h1>ડેશબોર્ડ</h1>
  <?php if (!empty($flash)): ?><div class="alert alert-success"><?= Helper::e($flash) ?></div><?php endif; ?>

  <div class="dash-grid">
    <div class="dash-card">
      <h3>👤 એકાઉન્ટ</h3>
      <p><strong><?= Helper::e($user['name']) ?></strong><br><?= Helper::e($user['email']) ?></p>
      <p class="muted small">સભ્ય બન્યા: <?= date('d M Y', strtotime($user['created_at'])) ?></p>
    </div>
    <div class="dash-card">
      <h3>💳 પ્લાન</h3>
      <?php if ($plan): ?>
        <p><strong><?= Helper::e($plan['plan_name']) ?></strong></p>
        <?php if ($hasActivePlan && $user['subscription_end']): ?>
          <p class="muted small">માન્ય: <?= date('d M Y', strtotime($user['subscription_end'])) ?> સુધી</p>
        <?php elseif ((float)$plan['price'] > 0): ?>
          <p class="muted small warn-txt">Subscription સક્રિય નથી</p>
        <?php endif; ?>
      <?php else: ?>
        <p>Free Demo</p>
      <?php endif; ?>
      <a href="<?= Helper::e(App::url('/pricing')) ?>" class="btn btn-sm-h">પ્લાન બદલો</a>
    </div>
    <div class="dash-card">
      <h3>📊 આજનો ઉપયોગ</h3>
      <p class="big-number"><?= number_format($todayConversions) ?></p>
      <p class="muted small">કન્વર્ઝન આજે</p>
    </div>
  </div>

  <section class="dash-section">
    <h2 class="h-small">API Keys</h2>
    <?php if ($plan && (int)$plan['api_access'] === 1 && $hasActivePlan): ?>
      <form method="post" action="<?= Helper::e(App::url('/dashboard/api-key')) ?>" class="inline-form">
        <?= Security::csrfField() ?>
        <input type="text" name="key_name" placeholder="Key નું નામ (દા.ત. My App)" maxlength="100">
        <button class="btn btn-sm-h" type="submit">+ નવી API Key</button>
      </form>
    <?php else: ?>
      <p class="muted">API access માટે <a href="<?= Helper::e(App::url('/pricing')) ?>">Pro કે Business plan</a> જરૂરી છે.</p>
    <?php endif; ?>

    <?php if (!empty($apiKeys)): ?>
      <div class="table-wrap">
        <table class="data-table">
          <tr><th>નામ</th><th>Key</th><th>આજની Calls</th><th>Daily Limit</th><th>Status</th><th>છેલ્લે વપરાઈ</th></tr>
          <?php foreach ($apiKeys as $key): ?>
          <tr>
            <td><?= Helper::e($key['name']) ?></td>
            <td><code class="api-key-code"><?= Helper::e($key['api_key']) ?></code></td>
            <td><?= (int)($key['calls_date'] === date('Y-m-d') ? $key['calls_today'] : 0) ?></td>
            <td><?= (int)$key['daily_limit'] ?></td>
            <td><span class="badge badge-<?= $key['status'] === 'active' ? 'ok' : 'off' ?>"><?= Helper::e($key['status']) ?></span></td>
            <td><?= $key['last_used_at'] ? date('d M H:i', strtotime($key['last_used_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
    <p class="muted small">API docs: <a href="<?= Helper::e(App::url('/api')) ?>"><?= Helper::e(App::url('/api')) ?></a></p>
  </section>
</div>
