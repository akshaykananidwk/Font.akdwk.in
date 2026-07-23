<?php
/**
 * Visual Mapping Editor — character pairs table, test, CSV import/export.
 */
$PAGE_TITLE = 'Mapping Editor';
require __DIR__ . '/includes/header.php';

$msg = '';
$err = '';
$file = (string)($_GET['file'] ?? ($_POST['file'] ?? ''));
// Path traversal રક્ષણ
if ($file !== '' && (preg_match('#\.\.|^/|\\\\#', $file) || !str_ends_with($file, '.json'))) {
    $file = '';
    $err = 'અમાન્ય ફાઇલ path.';
}
$fullPath = $file !== '' ? MAPPINGS_PATH . '/' . $file : '';

// ---- CSV export ----
if ($file !== '' && ($_GET['export'] ?? '') === 'csv' && is_file($fullPath)) {
    $data = json_decode((string)file_get_contents($fullPath), true) ?: [];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($file, '.json') . '_mapping.csv"');
    echo "\xEF\xBB\xBF"; // BOM — Excel માટે
    $out = fopen('php://output', 'w');
    fputcsv($out, ['legacy', 'unicode', 'group']);
    foreach (($data['mappings'] ?? []) as $group => $pairs) {
        foreach ((array)$pairs as $legacy => $unicode) {
            fputcsv($out, [$legacy, $unicode, $group]);
        }
    }
    fclose($out);
    exit;
}

// ---- POST: save / import ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf() && $file !== '') {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_json') {
            $json = (string)($_POST['json_content'] ?? '');
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('અમાન્ય JSON: ' . json_last_error_msg());
            }
            if (!isset($decoded['mappings']) || !is_array($decoded['mappings'])) {
                throw new RuntimeException('"mappings" object જરૂરી છે.');
            }
            file_put_contents($fullPath, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
            Cache::clear();
            Auth::logAdminActivity((int)Session::get('admin_id'), 'save_mapping', 'mapping-editor', ['file' => $file]);
            $msg = 'Mapping સેવ થયું અને cache clear થયું.';
        } elseif ($action === 'import_csv' && !empty($_FILES['csv_file']['tmp_name'])) {
            $up = $_FILES['csv_file'];
            if ($up['size'] > 2 * 1024 * 1024) {
                throw new RuntimeException('CSV 2MB થી નાની હોવી જોઈએ.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($up['tmp_name']);
            if (!in_array($mime, ['text/plain', 'text/csv', 'application/csv'], true)) {
                throw new RuntimeException('ફક્ત CSV ફાઇલ ચાલે (મળ્યું: ' . $mime . ').');
            }
            $data = is_file($fullPath)
                ? (json_decode((string)file_get_contents($fullPath), true) ?: [])
                : json_decode((string)file_get_contents(MAPPINGS_PATH . '/_template.json'), true);
            $fp = fopen($up['tmp_name'], 'r');
            $count = 0;
            $header = fgetcsv($fp); // header skip
            while (($row = fgetcsv($fp)) !== false) {
                if (count($row) < 2 || (string)$row[0] === '') {
                    continue;
                }
                $legacy = (string)$row[0];
                $unicode = (string)$row[1];
                $group = (string)($row[2] ?? '') ?: (mb_strlen($legacy) . '_char');
                if (!preg_match('/^\d+_char$/', $group)) {
                    $group = mb_strlen($legacy) . '_char';
                }
                $data['mappings'][$group][$legacy] = $unicode;
                $count++;
            }
            fclose($fp);
            file_put_contents($fullPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
            Cache::clear();
            $msg = "{$count} pairs import થયા.";
        }
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$mappingFiles = MappingLoader::listMappingFiles();
$content = '';
$pairCount = 0;
if ($file !== '' && is_file($fullPath)) {
    $content = (string)file_get_contents($fullPath);
    $decoded = json_decode($content, true) ?: [];
    foreach (($decoded['mappings'] ?? []) as $pairs) {
        $pairCount += count((array)$pairs);
    }
}
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-card">
  <h2>Mapping ફાઇલ પસંદ કરો</h2>
  <form method="get" class="filter-row">
    <select name="file" onchange="this.form.submit()">
      <option value="">— પસંદ કરો —</option>
      <?php foreach ($mappingFiles as $mf): ?>
        <option value="<?= $e($mf['file']) ?>" <?= $file === $mf['file'] ? 'selected' : '' ?>><?= $e($mf['file']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($file !== '' && $content !== ''): ?>
<div class="admin-card">
  <h2><?= $e($file) ?> <small class="amuted">(<?= $pairCount ?> pairs<?= $pairCount === 0 ? ' — STUB, data ઉમેરો' : '' ?>)</small></h2>
  <p class="amuted">
    JSON structure: <code>mappings.1_char</code> વગેરે groups માં <code>"legacy": "unicode"</code> જોડીઓ.
    Pre-base િ glyph ની value ફક્ત <code>"િ"</code> અને reph glyph ની value ફક્ત <code>"ર્"</code> રાખો — engine આપમેળે reorder કરે છે.
    <a href="?file=<?= urlencode($file) ?>&export=csv">⬇ CSV Export</a>
  </p>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save_json">
    <input type="hidden" name="file" value="<?= $e($file) ?>">
    <textarea name="json_content" class="json-editor" rows="24" spellcheck="false"><?= $e($content) ?></textarea>
    <button class="abtn abtn-primary" type="submit">💾 સેવ + Cache Clear</button>
  </form>
</div>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>📥 CSV Import</h2>
    <p class="amuted">Columns: <code>legacy, unicode, group</code> (group ખાલી હોય તો length પ્રમાણે આપમેળે).</p>
    <form method="post" enctype="multipart/form-data">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="import_csv">
      <input type="hidden" name="file" value="<?= $e($file) ?>">
      <input type="file" name="csv_file" accept=".csv,text/csv" required>
      <button class="abtn abtn-primary" type="submit">Import</button>
    </form>
  </div>
  <div class="admin-card">
    <h2>⚡ ઝડપી Test</h2>
    <label>Input (legacy)</label>
    <textarea id="meTestInput" rows="2"></textarea>
    <button class="abtn abtn-primary" type="button" id="meTestBtn">▶ Legacy → Unicode</button>
    <pre class="test-output" id="meTestOut">—</pre>
  </div>
</div>
<script>
document.getElementById('meTestBtn')?.addEventListener('click', async () => {
  const out = document.getElementById('meTestOut');
  out.textContent = '⏳';
  // slug શોધો: mapping file વાપરતો કોઈ font
  const body = new URLSearchParams({
    action: 'live_test',
    csrf_token: document.querySelector('meta[name="csrf-token"]').content,
    slug: <?= json_encode(basename($file, '.json') === 'lmg' ? 'lmg' : str_replace('_', '-', basename($file, '.json'))) ?>,
    direction: 'legacy_to_unicode',
    text: document.getElementById('meTestInput').value
  });
  try {
    const res = await fetch('fonts.php', { method: 'POST', body });
    const json = await res.json();
    out.textContent = json.success ? json.output : '✗ ' + json.error;
  } catch (e) { out.textContent = '✗ failed'; }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
