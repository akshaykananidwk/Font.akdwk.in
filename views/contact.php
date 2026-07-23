<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow">
  <nav class="breadcrumbs"><a href="<?= Helper::e(App::url('/')) ?>">હોમ</a> <span>›</span> <span>સંપર્ક</span></nav>
  <h1>સંપર્ક કરો</h1>
  <p class="intro">પ્રશ્ન, સૂચન કે mapping ની ભૂલ — અમને લખો, અમે જલ્દી જવાબ આપીશું.</p>

  <?php if (!empty($sent)): ?>
    <div class="alert alert-success">✓ તમારો સંદેશ મળી ગયો છે. આભાર!</div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error">⚠ <?= Helper::e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= Helper::e(App::url('/contact')) ?>" class="std-form">
    <?= Security::csrfField() ?>
    <?php /* Honeypot — bots ભરી દે છે, માણસને દેખાતું નથી */ ?>
    <div class="hp-field" aria-hidden="true"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>
    <div class="form-row">
      <div><label for="cName">નામ *</label><input id="cName" type="text" name="name" required maxlength="100"></div>
      <div><label for="cEmail">Email *</label><input id="cEmail" type="email" name="email" required></div>
    </div>
    <div class="form-row">
      <div><label for="cPhone">ફોન (વૈકલ્પિક)</label><input id="cPhone" type="text" name="phone" maxlength="20"></div>
      <div><label for="cSubject">વિષય</label><input id="cSubject" type="text" name="subject" maxlength="255"></div>
    </div>
    <label for="cMessage">સંદેશ *</label>
    <textarea id="cMessage" name="message" required rows="6" maxlength="5000"></textarea>
    <button class="btn" type="submit">સંદેશ મોકલો</button>
  </form>
</div>
