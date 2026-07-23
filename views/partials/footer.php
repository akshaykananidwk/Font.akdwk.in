<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <h4><?= Helper::e((string)App::setting('site_name', APP_NAME)) ?></h4>
      <p class="muted">90+ legacy ફોન્ટને Unicode માં કન્વર્ટ કરો — મફત, ઝડપી અને સુરક્ષિત. તમારો ટેક્સ્ટ ક્યારેય સ્ટોર થતો નથી.</p>
    </div>
    <div>
      <h4>ટૂલ્સ</h4>
      <a href="<?= Helper::e(App::url('/gujarati-font-converter')) ?>">ગુજરાતી કન્વર્ટર</a>
      <a href="<?= Helper::e(App::url('/hindi-font-converter')) ?>">હિન્દી કન્વર્ટર</a>
      <a href="<?= Helper::e(App::url('/lmg-to-unicode-converter')) ?>">LMG → Unicode</a>
      <a href="<?= Helper::e(App::url('/api')) ?>">Developer API</a>
    </div>
    <div>
      <h4>માહિતી</h4>
      <a href="<?= Helper::e(App::url('/faq')) ?>">FAQ</a>
      <a href="<?= Helper::e(App::url('/font-installation-guide')) ?>">ફોન્ટ Installation</a>
      <a href="<?= Helper::e(App::url('/blog')) ?>">બ્લોગ</a>
      <a href="<?= Helper::e(App::url('/pricing')) ?>">ભાવ</a>
    </div>
    <div>
      <h4>કાનૂની</h4>
      <a href="<?= Helper::e(App::url('/privacy-policy')) ?>">પ્રાઇવસી પોલિસી</a>
      <a href="<?= Helper::e(App::url('/terms')) ?>">નિયમો</a>
      <a href="<?= Helper::e(App::url('/contact')) ?>">સંપર્ક</a>
    </div>
  </div>
  <div class="container footer-bottom">
    <p>© <?= date('Y') ?> <?= Helper::e((string)App::setting('site_name', APP_NAME)) ?> — v<?= APP_VERSION ?></p>
  </div>
</footer>
