<?php
defined('BASE_PATH') or die('Direct access denied');
$user = Auth::currentUser();
?>
<header class="site-header">
  <div class="container nav">
    <a href="<?= Helper::e(App::url('/')) ?>" class="logo">🔤 <?= Helper::e((string)App::setting('site_name', APP_NAME)) ?></a>
    <nav class="main-nav" id="mainNav" aria-label="Main menu">
      <div class="dropdown">
        <button class="nav-link" aria-haspopup="true">Language ▾</button>
        <div class="dropdown-menu">
          <a href="<?= Helper::e(App::url('/gujarati-font-converter')) ?>">Gujarati</a>
          <a href="<?= Helper::e(App::url('/hindi-font-converter')) ?>">Hindi</a>
          <a href="<?= Helper::e(App::url('/marathi-font-converter')) ?>">Marathi</a>
          <a href="<?= Helper::e(App::url('/nepali-font-converter')) ?>">Nepali</a>
        </div>
      </div>
      <div class="dropdown">
        <button class="nav-link" aria-haspopup="true">Tools ▾</button>
        <div class="dropdown-menu">
          <a href="<?= Helper::e(App::url('/lmg-to-unicode-converter')) ?>">LMG → Unicode</a>
          <a href="<?= Helper::e(App::url('/shree-guj-0768-to-unicode-converter')) ?>">Shree Guj → Unicode</a>
          <a href="<?= Helper::e(App::url('/kruti-dev-010-to-unicode-converter')) ?>">Kruti Dev → Unicode</a>
          <a href="<?= Helper::e(App::url('/font-installation-guide')) ?>">Font Guide</a>
        </div>
      </div>
      <a class="nav-link" href="<?= Helper::e(App::url('/blog')) ?>">Blog</a>
      <a class="nav-link" href="<?= Helper::e(App::url('/api')) ?>">API</a>
      <a class="nav-link" href="<?= Helper::e(App::url('/pricing')) ?>">Pricing</a>
      <?php if ($user): ?>
        <a class="nav-link" href="<?= Helper::e(App::url('/dashboard')) ?>">Dashboard</a>
        <a class="nav-link" href="<?= Helper::e(App::url('/logout')) ?>">Logout</a>
      <?php else: ?>
        <a class="nav-link nav-cta" href="<?= Helper::e(App::url('/login')) ?>">Login</a>
      <?php endif; ?>
      <button id="themeToggle" class="theme-toggle" title="Dark mode" aria-label="Dark mode toggle">🌓</button>
    </nav>
    <button class="hamburger" id="hamburger" aria-label="Open menu">☰</button>
  </div>
</header>
