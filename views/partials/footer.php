<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <h4><?= Helper::e((string)App::setting('site_name', APP_NAME)) ?></h4>
      <p class="muted">Convert 90+ legacy fonts to Unicode — free, fast and secure. Your text is never stored.</p>
    </div>
    <div>
      <h4>Tools</h4>
      <a href="<?= Helper::e(App::url('/gujarati-font-converter')) ?>">Gujarati Converter</a>
      <a href="<?= Helper::e(App::url('/hindi-font-converter')) ?>">Hindi Converter</a>
      <a href="<?= Helper::e(App::url('/lmg-to-unicode-converter')) ?>">LMG → Unicode</a>
      <a href="<?= Helper::e(App::url('/api')) ?>">Developer API</a>
    </div>
    <div>
      <h4>Information</h4>
      <a href="<?= Helper::e(App::url('/faq')) ?>">FAQ</a>
      <a href="<?= Helper::e(App::url('/font-installation-guide')) ?>">Font Installation</a>
      <a href="<?= Helper::e(App::url('/blog')) ?>">Blog</a>
      <a href="<?= Helper::e(App::url('/pricing')) ?>">Pricing</a>
    </div>
    <div>
      <h4>Legal</h4>
      <a href="<?= Helper::e(App::url('/privacy-policy')) ?>">Privacy Policy</a>
      <a href="<?= Helper::e(App::url('/terms')) ?>">Terms</a>
      <a href="<?= Helper::e(App::url('/contact')) ?>">Contact</a>
    </div>
  </div>
  <div class="container footer-bottom">
    <p>© <?= date('Y') ?> <?= Helper::e((string)App::setting('site_name', APP_NAME)) ?> — v<?= APP_VERSION ?></p>
  </div>
</footer>
