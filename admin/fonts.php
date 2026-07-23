<?php
/**
 * Font Manager — CRUD, enable/disable, popular toggle, live test box.
 */

/*
 * AJAX live-test must return pure JSON, so it runs BEFORE includes/header.php
 * (which would otherwise print the <!DOCTYPE html> admin layout first and break res.json()).
 */
if (($_POST['action'] ?? '') === 'live_test') {
    define('BASE_PATH', dirname(__DIR__));
    require_once BASE_PATH . '/config/constants.php';
    require_once CORE_PATH . '/App.php';
    App::bootstrap();
    header('Content-Type: application/json; charset=utf-8');
    if (!Auth::isAdminLoggedIn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
    if (!Security::verifyCsrf()) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    try {
        $result = FontConverter::convertBySlug(
            (string)($_POST['slug'] ?? ''),
            (string)($_POST['text'] ?? ''),
            (string)($_POST['direction'] ?? 'legacy_to_unicode')
        );
        echo json_encode(['success' => true, 'output' => $result['converted_text'], 'ms' => $result['processing_time_ms']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['success' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$PAGE_TITLE = 'Font Manager';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$fontsTable = $db->table('fonts');
$langTable = $db->table('languages');
$msg = '';
$err = '';

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'toggle_active' && $id > 0) {
            $db->query("UPDATE `{$fontsTable}` SET is_active = 1 - is_active WHERE id = ?", [$id]);
            $msg = 'Status changed.';
        } elseif ($action === 'toggle_popular' && $id > 0) {
            $db->query("UPDATE `{$fontsTable}` SET is_popular = 1 - is_popular WHERE id = ?", [$id]);
            $msg = 'Popular flag changed.';
        } elseif ($action === 'delete' && $id > 0) {
            $db->query("DELETE FROM `{$fontsTable}` WHERE id = ?", [$id]);
            $msg = 'Font deleted (the mapping file remains on disk).';
        } elseif ($action === 'save') {
            $data = [
                'language_id'  => (int)($_POST['language_id'] ?? 1),
                'font_name'    => mb_substr(trim((string)($_POST['font_name'] ?? '')), 0, 100),
                'font_slug'    => Helper::slugify((string)($_POST['font_slug'] ?? '')),
                'mapping_file' => trim((string)($_POST['mapping_file'] ?? '')),
                'font_family'  => mb_substr(trim((string)($_POST['font_family'] ?? '')), 0, 100),
                'sort_order'   => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($data['font_name'] === '' || $data['font_slug'] === '' || $data['mapping_file'] === '') {
                throw new RuntimeException('Name, slug, and mapping file are required.');
            }
            if (preg_match('#\.\.|^/#', $data['mapping_file'])) {
                throw new RuntimeException('Invalid mapping path.');
            }
            if ($id > 0) {
                $db->update('fonts', $data, 'id = ?', [$id]);
                $msg = 'Font updated.';
            } else {
                $db->insert('fonts', $data);
                $msg = 'New font added.';
            }
            Auth::logAdminActivity((int)Session::get('admin_id'), 'save_font', 'fonts', $data);
        }
        Cache::clear();
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$languages = $db->fetchAll("SELECT * FROM `{$langTable}` ORDER BY sort_order");
$langFilter = (int)($_GET['lang'] ?? 0);
$where = $langFilter > 0 ? 'WHERE f.language_id = ' . $langFilter : '';
$fonts = $db->fetchAll(
    "SELECT f.*, l.name AS lang_name FROM `{$fontsTable}` f
     JOIN `{$langTable}` l ON l.id = f.language_id {$where}
     ORDER BY f.language_id, f.sort_order"
);
$editFont = null;
if (($editId = (int)($_GET['edit'] ?? 0)) > 0) {
    $editFont = $db->fetch("SELECT * FROM `{$fontsTable}` WHERE id = ?", [$editId]);
}
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2><?= $editFont ? 'Edit Font' : 'Add New Font' ?></h2>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($editFont['id'] ?? 0) ?>">
      <label>Language</label>
      <select name="language_id">
        <?php foreach ($languages as $lang): ?>
          <option value="<?= (int)$lang['id'] ?>" <?= (int)($editFont['language_id'] ?? 1) === (int)$lang['id'] ? 'selected' : '' ?>><?= $e($lang['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Font Name</label>
      <input type="text" name="font_name" value="<?= $e($editFont['font_name'] ?? '') ?>" required>
      <label>Slug (for URL)</label>
      <input type="text" name="font_slug" value="<?= $e($editFont['font_slug'] ?? '') ?>" required>
      <label>Mapping File (e.g. gujarati/lmg.json)</label>
      <input type="text" name="mapping_file" value="<?= $e($editFont['mapping_file'] ?? '') ?>" required>
      <label>Font Family (for preview)</label>
      <input type="text" name="font_family" value="<?= $e($editFont['font_family'] ?? '') ?>">
      <label>Sort Order</label>
      <input type="number" name="sort_order" value="<?= (int)($editFont['sort_order'] ?? 0) ?>">
      <button class="abtn abtn-primary" type="submit">Save</button>
      <?php if ($editFont): ?><a class="abtn" href="fonts.php">Cancel</a><?php endif; ?>
    </form>
  </div>

  <div class="admin-card">
    <h2>⚡ Live Test</h2>
    <p class="amuted">Instantly test any font's mapping.</p>
    <label>Font slug</label>
    <input type="text" id="testSlug" value="lmg">
    <label>Direction</label>
    <select id="testDirection">
      <option value="legacy_to_unicode">Legacy → Unicode</option>
      <option value="unicode_to_legacy">Unicode → Legacy</option>
    </select>
    <label>Input</label>
    <textarea id="testInput" rows="3">kml ikrN kay©</textarea>
    <button class="abtn abtn-primary" id="btnLiveTest" type="button">▶ Test</button>
    <label>Output</label>
    <pre class="test-output" id="testOutput">—</pre>
  </div>
</div>

<div class="admin-card">
  <h2>All Fonts (<?= count($fonts) ?>)</h2>
  <form method="get" class="filter-row">
    <select name="lang" onchange="this.form.submit()">
      <option value="0">All Languages</option>
      <?php foreach ($languages as $lang): ?>
        <option value="<?= (int)$lang['id'] ?>" <?= $langFilter === (int)$lang['id'] ? 'selected' : '' ?>><?= $e($lang['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="table-scroll">
    <table class="atable">
      <tr><th>ID</th><th>Name</th><th>Language</th><th>Slug</th><th>Mapping</th><th>Conversions</th><th>Popular</th><th>Active</th><th></th></tr>
      <?php foreach ($fonts as $f): ?>
      <tr>
        <td><?= (int)$f['id'] ?></td>
        <td><?= $e($f['font_name']) ?></td>
        <td><?= $e($f['lang_name']) ?></td>
        <td><code><?= $e($f['font_slug']) ?></code></td>
        <td><small><?= $e($f['mapping_file']) ?></small></td>
        <td class="num"><?= number_format((int)$f['conversion_count']) ?></td>
        <td>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="toggle_popular"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="link-btn" type="submit"><?= (int)$f['is_popular'] === 1 ? '⭐' : '☆' ?></button>
          </form>
        </td>
        <td>
          <form method="post" class="inline"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="link-btn" type="submit"><?= (int)$f['is_active'] === 1 ? '✅' : '⛔' ?></button>
          </form>
        </td>
        <td>
          <a class="abtn abtn-xs" href="?edit=<?= (int)$f['id'] ?>">Edit</a>
          <a class="abtn abtn-xs" href="mapping-editor.php?file=<?= urlencode($f['mapping_file']) ?>">Mapping</a>
          <form method="post" class="inline" onsubmit="return confirm('Delete this font?')"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="abtn abtn-xs abtn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<script>
document.getElementById('btnLiveTest').addEventListener('click', async () => {
  const out = document.getElementById('testOutput');
  out.textContent = '⏳ ...';
  const body = new URLSearchParams({
    action: 'live_test',
    csrf_token: document.querySelector('meta[name="csrf-token"]').content,
    slug: document.getElementById('testSlug').value.trim(),
    direction: document.getElementById('testDirection').value,
    text: document.getElementById('testInput').value
  });
  try {
    const res = await fetch('fonts.php', { method: 'POST', body });
    const json = await res.json();
    out.textContent = json.success ? json.output + '\n\n(' + json.ms + 'ms)' : '✗ ' + json.error;
  } catch (e) { out.textContent = '✗ Request failed'; }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
