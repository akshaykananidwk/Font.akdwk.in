<?php
defined('BASE_PATH') or die('Direct access denied');
/**
 * <head> — બધા meta tags, OG, Twitter, canonical, hreflang, critical CSS.
 */
$e = fn($s) => Helper::e((string)$s);
$title = $metaTitle ?? App::setting('default_meta_title', APP_NAME);
$desc = $metaDescription ?? App::setting('default_meta_description', '');
$canon = $canonical ?? App::url($_SERVER['REQUEST_URI'] ?? '/');
$og = $ogImage ?? App::url('/assets/images/og-default.png');
$gaId = App::setting('google_analytics_id', '');
$gsv = App::setting('google_site_verification', '');
$bsv = App::setting('bing_site_verification', '');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?></title>
<meta name="description" content="<?= $e($desc) ?>">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<link rel="canonical" href="<?= $e($canon) ?>">
<link rel="icon" href="<?= $e(App::url('/favicon.ico')) ?>">
<?php // hreflang — ભાષા-વાર converter pages ?>
<link rel="alternate" hreflang="gu" href="<?= $e(App::url('/gujarati-font-converter')) ?>">
<link rel="alternate" hreflang="hi" href="<?= $e(App::url('/hindi-font-converter')) ?>">
<link rel="alternate" hreflang="mr" href="<?= $e(App::url('/marathi-font-converter')) ?>">
<link rel="alternate" hreflang="ne" href="<?= $e(App::url('/nepali-font-converter')) ?>">
<link rel="alternate" hreflang="x-default" href="<?= $e(App::url('/')) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($desc) ?>">
<meta property="og:url" content="<?= $e($canon) ?>">
<meta property="og:image" content="<?= $e($og) ?>">
<meta property="og:site_name" content="<?= $e(App::setting('site_name', APP_NAME)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($title) ?>">
<meta name="twitter:description" content="<?= $e($desc) ?>">
<meta name="twitter:image" content="<?= $e($og) ?>">
<?php if ($gsv !== ''): ?>
<meta name="google-site-verification" content="<?= $e($gsv) ?>">
<?php endif; ?>
<?php if ($bsv !== ''): ?>
<meta name="msvalidate.01" content="<?= $e($bsv) ?>">
<?php endif; ?>
<meta name="csrf-token" content="<?= $e(Security::csrfToken()) ?>">
<meta name="base-url" content="<?= $e(App::url('/')) ?>">
<?php /* Critical CSS inline — LCP માટે */ ?>
<style>
:root{--c-primary:#1a5276;--c-primary-2:#2980b9;--c-accent:#27ae60;--c-bg:#f6f8fb;--c-card:#fff;--c-text:#233240;--c-muted:#6b7a8c;--c-border:#dde5ee;--c-danger:#c0392b}
[data-theme=dark]{--c-bg:#10161d;--c-card:#1a232e;--c-text:#e4ebf2;--c-muted:#8aa0b5;--c-border:#2a3947;--c-primary:#5dade2;--c-primary-2:#3498db}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans Gujarati','Shruti','Segoe UI',system-ui,sans-serif;background:var(--c-bg);color:var(--c-text);line-height:1.6}
.container{max-width:1100px;margin:0 auto;padding:0 16px}
.site-header{background:var(--c-card);border-bottom:1px solid var(--c-border);position:sticky;top:0;z-index:50}
.nav{display:flex;align-items:center;justify-content:space-between;height:58px;gap:12px}
.logo{font-weight:800;font-size:1.15rem;color:var(--c-primary);text-decoration:none;white-space:nowrap}
h1{font-size:1.6rem;margin:22px 0 8px;color:var(--c-primary)}
.converter-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
@media(max-width:760px){.converter-grid{grid-template-columns:1fr}}
textarea{width:100%;min-height:220px;padding:12px;border:1px solid var(--c-border);border-radius:10px;font-size:1.05rem;background:var(--c-card);color:var(--c-text);resize:vertical;font-family:inherit}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:8px;border:0;font-weight:600;font-size:.95rem;cursor:pointer;text-decoration:none;background:var(--c-primary);color:#fff}
</style>
<link rel="stylesheet" href="<?= $e(App::url('/assets/css/style.css')) ?>?v=<?= APP_VERSION ?>">
<?php if ($gaId !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $e($gaId) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= $e($gaId) ?>');</script>
<?php endif; ?>
<script>
/* Dark mode flash ટાળવા head માં જ theme લાગુ કરો */
(function(){try{var t=localStorage.getItem('gfc_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
