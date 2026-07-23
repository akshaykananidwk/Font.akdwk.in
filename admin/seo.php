<?php
/**
 * SEO મેનેજર — meta defaults, robots.txt, 301 redirects, analytics IDs.
 */
$PAGE_TITLE = 'SEO';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$redirectsTable = $db->table('seo_redirects');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_meta') {
            App::setSetting('default_meta_title', mb_substr((string)($_POST['default_meta_title'] ?? ''), 0, 255), 'seo');
            App::setSetting('default_meta_description', mb_substr((string)($_POST['default_meta_description'] ?? ''), 0, 320), 'seo');
            App::setSetting('google_analytics_id', mb_substr(trim((string)($_POST['google_analytics_id'] ?? '')), 0, 30), 'seo');
            App::setSetting('google_site_verification', mb_substr(trim((string)($_POST['google_site_verification'] ?? '')), 0, 100), 'seo');
            App::setSetting('bing_site_verification', mb_substr(trim((string)($_POST['bing_site_verification'] ?? '')), 0, 100), 'seo');
            $msg = 'SEO defaults saved.';
        } elseif ($action === 'save_robots') {
            App::setSetting('robots_txt', (string)($_POST['robots_txt'] ?? ''), 'seo');
            $msg = 'robots.txt saved.';
        } elseif ($action === 'add_redirect') {
            $from = '/' . ltrim(trim((string)($_POST['from_url'] ?? '')), '/');
            $to = trim((string)($_POST['to_url'] ?? ''));
            if ($from === '/' || $to === '') {
                throw new RuntimeException('Both URLs are required.');
            }
            $db->insert('seo_redirects', [
                'from_url'      => mb_substr($from, 0, 190),
                'to_url'        => mb_substr($to, 0, 500),
                'redirect_type' => ($_POST['redirect_type'] ?? '301') === '302' ? '302' : '301',
            ]);
            $msg = 'Redirect added.';
        } elseif ($action === 'delete_redirect') {
            $db->query("DELETE FROM `{$redirectsTable}` WHERE id = ?", [(int)($_POST['id'] ?? 0)]);
            $msg = 'Redirect deleted.';
        } elseif ($action === 'regen_sitemap') {
            // Sitemap ડાયનેમિક જ છે — cache clear પૂરતું
            Cache::clear();
            $msg = 'Sitemap cache cleared — /sitemap.xml will now be generated fresh.';
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'seo', []);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$redirects = $db->fetchAll("SELECT * FROM `{$redirectsTable}` ORDER BY id DESC LIMIT 100");
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>Global Meta Defaults</h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save_meta">
      <label>Default Meta Title</label>
      <input type="text" name="default_meta_title" value="<?= $e((string)App::setting('default_meta_title', '')) ?>">
      <label>Default Meta Description</label>
      <textarea name="default_meta_description" rows="3"><?= $e((string)App::setting('default_meta_description', '')) ?></textarea>
      <label>Google Analytics 4 ID (G-XXXX)</label>
      <input type="text" name="google_analytics_id" value="<?= $e((string)App::setting('google_analytics_id', '')) ?>">
      <label>Google Site Verification</label>
      <input type="text" name="google_site_verification" value="<?= $e((string)App::setting('google_site_verification', '')) ?>">
      <label>Bing Verification</label>
      <input type="text" name="bing_site_verification" value="<?= $e((string)App::setting('bing_site_verification', '')) ?>">
      <button class="abtn abtn-primary" type="submit">Save</button>
    </form>
  </div>

  <div class="admin-card">
    <h2>robots.txt Editor</h2>
    <p class="amuted">Leave empty to generate the default.</p>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save_robots">
      <textarea name="robots_txt" rows="8" class="json-editor"><?= $e((string)App::setting('robots_txt', '')) ?></textarea>
      <button class="abtn abtn-primary" type="submit">Save</button>
    </form>
    <hr class="asep">
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="regen_sitemap">
      <button class="abtn" type="submit">🔄 Sitemap Regenerate</button>
      <a class="abtn" href="<?= $e(App::url('/sitemap.xml')) ?>" target="_blank" rel="noopener">👁 View Sitemap</a>
    </form>
  </div>
</div>

<div class="admin-card">
  <h2>301/302 Redirects</h2>
  <form method="post" class="filter-row">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="add_redirect">
    <input type="text" name="from_url" placeholder="/old-url" required>
    <input type="text" name="to_url" placeholder="/new-url or https://..." required>
    <select name="redirect_type"><option value="301">301</option><option value="302">302</option></select>
    <button class="abtn abtn-primary" type="submit">+ Add</button>
  </form>
  <table class="atable">
    <tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th></th></tr>
    <?php foreach ($redirects as $r): ?>
    <tr>
      <td><code><?= $e($r['from_url']) ?></code></td>
      <td><code><?= $e($r['to_url']) ?></code></td>
      <td><?= $e($r['redirect_type']) ?></td>
      <td class="num"><?= number_format((int)$r['hits']) ?></td>
      <td>
        <form method="post" class="inline"><?= Security::csrfField() ?>
          <input type="hidden" name="action" value="delete_redirect"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="abtn abtn-xs abtn-danger" type="submit">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
