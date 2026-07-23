<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container narrow">
  <nav class="breadcrumbs"><a href="<?= Helper::e(App::url('/')) ?>">Home</a> <span>›</span> <span>Contact</span></nav>
  <h1>Contact Us</h1>
  <p class="intro">Questions, suggestions, or a mapping error — write to us and we'll respond quickly.</p>

  <?php if (!empty($sent)): ?>
    <div class="alert alert-success">✓ We have received your message. Thank you!</div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error">⚠ <?= Helper::e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= Helper::e(App::url('/contact')) ?>" class="std-form">
    <?= Security::csrfField() ?>
    <?php /* Honeypot — bots fill this in, humans don't see it */ ?>
    <div class="hp-field" aria-hidden="true"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>
    <div class="form-row">
      <div><label for="cName">Name *</label><input id="cName" type="text" name="name" required maxlength="100"></div>
      <div><label for="cEmail">Email *</label><input id="cEmail" type="email" name="email" required></div>
    </div>
    <div class="form-row">
      <div><label for="cPhone">Phone (optional)</label><input id="cPhone" type="text" name="phone" maxlength="20"></div>
      <div><label for="cSubject">Subject</label><input id="cSubject" type="text" name="subject" maxlength="255"></div>
    </div>
    <label for="cMessage">Message *</label>
    <textarea id="cMessage" name="message" required rows="6" maxlength="5000"></textarea>
    <button class="btn" type="submit">Send Message</button>
  </form>
</div>
