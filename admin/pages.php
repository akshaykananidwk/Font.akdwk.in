<?php
/**
 * CMS — static pages editor (SEO fields સાથે).
 */
$PAGE_TITLE = 'પેજીસ (CMS)';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$pagesTable = $db->table('pages');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'save') {
            $data = [
                'slug'             => Helper::slugify((string)($_POST['slug'] ?? '')),
                'title'            => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255),
                'content'          => (string)($_POST['content'] ?? ''),
                'meta_title'       => mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 320),
                'meta_keywords'    => mb_substr(trim((string)($_POST['meta_keywords'] ?? '')), 0, 500),
                'canonical_url'    => mb_substr(trim((string)($_POST['canonical_url'] ?? '')), 0, 255),
                'is_indexed'       => (int)!empty($_POST['is_indexed']),
                'status'           => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            ];
            if ($data['slug'] === '' || $data['title'] === '') {
                throw new RuntimeException('Slug અને title જરૂરી છે.');
            }
            if ($id > 0) {
                $db->update('pages', $data, 'id = ?', [$id]);
                $msg = 'Page update થયું.';
            } else {
                $db->insert('pages', $data);
                $msg = 'નવું page બન્યું.';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $db->query("DELETE FROM `{$pagesTable}` WHERE id = ?", [$id]);
            $msg = 'Page delete થયું.';
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'pages', ['page_id' => $id]);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$pages = $db->fetchAll("SELECT id, slug, title, status, updated_at FROM `{$pagesTable}` ORDER BY sort_order, id");
$editPage = null;
if (($editId = (int)($_GET['edit'] ?? 0)) > 0) {
    $editPage = $db->fetch("SELECT * FROM `{$pagesTable}` WHERE id = ?", [$editId]);
}
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-card">
  <h2><?= $editPage ? 'Page Edit: ' . $e($editPage['title']) : 'નવું Page' ?></h2>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editPage['id'] ?? 0) ?>">
    <div class="row-2">
      <div><label>Title</label><input type="text" name="title" value="<?= $e($editPage['title'] ?? '') ?>" required></div>
      <div><label>Slug</label><input type="text" name="slug" value="<?= $e($editPage['slug'] ?? '') ?>" required></div>
    </div>
    <label>Content (HTML)</label>
    <textarea name="content" rows="14" class="json-editor"><?= $e($editPage['content'] ?? '') ?></textarea>
    <div class="row-2">
      <div><label>Meta Title</label><input type="text" name="meta_title" value="<?= $e($editPage['meta_title'] ?? '') ?>"></div>
      <div><label>Canonical URL</label><input type="text" name="canonical_url" value="<?= $e($editPage['canonical_url'] ?? '') ?>"></div>
    </div>
    <label>Meta Description (150-160 chars)</label>
    <textarea name="meta_description" rows="2" maxlength="320"><?= $e($editPage['meta_description'] ?? '') ?></textarea>
    <label>Meta Keywords</label>
    <input type="text" name="meta_keywords" value="<?= $e($editPage['meta_keywords'] ?? '') ?>">
    <div class="row-2">
      <div><label><input type="checkbox" name="is_indexed" value="1" <?= (int)($editPage['is_indexed'] ?? 1) === 1 ? 'checked' : '' ?>> Search engines માં index થાય</label></div>
      <div>
        <label>Status</label>
        <select name="status">
          <option value="published" <?= ($editPage['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
          <option value="draft" <?= ($editPage['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
      </div>
    </div>
    <button class="abtn abtn-primary" type="submit">સેવ</button>
    <?php if ($editPage): ?><a class="abtn" href="pages.php">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="admin-card">
  <h2>બધા Pages</h2>
  <table class="atable">
    <tr><th>Slug</th><th>Title</th><th>Status</th><th>Updated</th><th></th></tr>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><code>/<?= $e($p['slug']) ?></code></td>
      <td><?= $e($p['title']) ?></td>
      <td><span class="abadge ab-<?= $p['status'] === 'published' ? 'ok' : 'warn' ?>"><?= $e($p['status']) ?></span></td>
      <td><small><?= $e($p['updated_at']) ?></small></td>
      <td>
        <a class="abtn abtn-xs" href="?edit=<?= (int)$p['id'] ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Page delete કરવું?')"><?= Security::csrfField() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="abtn abtn-xs abtn-danger" type="submit">Del</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
