<?php
/**
 * બ્લોગ મેનેજર — posts + categories.
 */
$PAGE_TITLE = 'બ્લોગ';
require __DIR__ . '/includes/header.php';

$db = Database::getInstance();
$postsTable = $db->table('blog_posts');
$catsTable = $db->table('blog_categories');
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'save_post') {
            $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
            $data = [
                'slug'             => Helper::slugify((string)($_POST['slug'] ?? '')),
                'title'            => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255),
                'excerpt'          => (string)($_POST['excerpt'] ?? ''),
                'content'          => (string)($_POST['content'] ?? ''),
                'meta_title'       => mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 320),
                'focus_keyword'    => mb_substr(trim((string)($_POST['focus_keyword'] ?? '')), 0, 100),
                'category_id'      => (int)($_POST['category_id'] ?? 0) ?: null,
                'tags'             => mb_substr(trim((string)($_POST['tags'] ?? '')), 0, 500),
                'status'           => $status,
                'author_id'        => (int)Session::get('admin_id'),
            ];
            if ($data['slug'] === '' || $data['title'] === '') {
                throw new RuntimeException('Slug અને title જરૂરી છે.');
            }
            if ($status === 'published') {
                $existing = $id > 0 ? $db->fetch("SELECT published_at FROM `{$postsTable}` WHERE id = ?", [$id]) : null;
                if (!$existing || $existing['published_at'] === null) {
                    $data['published_at'] = date('Y-m-d H:i:s');
                }
            }
            if ($id > 0) {
                $db->update('blog_posts', $data, 'id = ?', [$id]);
                $msg = 'Post update થયો.';
            } else {
                $db->insert('blog_posts', $data);
                $msg = 'નવો post બન્યો.';
            }
        } elseif ($action === 'delete_post' && $id > 0) {
            $db->query("DELETE FROM `{$postsTable}` WHERE id = ?", [$id]);
            $msg = 'Post delete થયો.';
        } elseif ($action === 'save_category') {
            $name = mb_substr(trim((string)($_POST['cat_name'] ?? '')), 0, 100);
            if ($name === '') {
                throw new RuntimeException('Category નામ જરૂરી.');
            }
            $db->insert('blog_categories', ['name' => $name, 'slug' => Helper::slugify($name)]);
            $msg = 'Category બની.';
        }
        Auth::logAdminActivity((int)Session::get('admin_id'), $action, 'blog', ['id' => $id]);
    } catch (Throwable $ex) {
        $err = $ex->getMessage();
    }
}

$posts = $db->fetchAll("SELECT id, slug, title, status, views, published_at FROM `{$postsTable}` ORDER BY id DESC LIMIT 100");
$cats = $db->fetchAll("SELECT * FROM `{$catsTable}` ORDER BY name");
$editPost = null;
if (($editId = (int)($_GET['edit'] ?? 0)) > 0) {
    $editPost = $db->fetch("SELECT * FROM `{$postsTable}` WHERE id = ?", [$editId]);
}
?>
<?php if ($msg): ?><div class="admin-alert alert-ok">✓ <?= $e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="admin-alert alert-err">⚠ <?= $e($err) ?></div><?php endif; ?>

<div class="admin-card">
  <h2><?= $editPost ? 'Post Edit' : 'નવો Post' ?></h2>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save_post">
    <input type="hidden" name="id" value="<?= (int)($editPost['id'] ?? 0) ?>">
    <div class="row-2">
      <div><label>Title</label><input type="text" name="title" value="<?= $e($editPost['title'] ?? '') ?>" required></div>
      <div><label>Slug</label><input type="text" name="slug" value="<?= $e($editPost['slug'] ?? '') ?>" required></div>
    </div>
    <label>Excerpt</label>
    <textarea name="excerpt" rows="2"><?= $e($editPost['excerpt'] ?? '') ?></textarea>
    <label>Content (HTML)</label>
    <textarea name="content" rows="14" class="json-editor"><?= $e($editPost['content'] ?? '') ?></textarea>
    <div class="row-2">
      <div>
        <label>Category</label>
        <select name="category_id">
          <option value="0">— કોઈ નહીં —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)($editPost['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Tags (comma separated)</label><input type="text" name="tags" value="<?= $e($editPost['tags'] ?? '') ?>"></div>
    </div>
    <div class="row-2">
      <div><label>Meta Title</label><input type="text" name="meta_title" value="<?= $e($editPost['meta_title'] ?? '') ?>"></div>
      <div><label>Focus Keyword</label><input type="text" name="focus_keyword" value="<?= $e($editPost['focus_keyword'] ?? '') ?>"></div>
    </div>
    <label>Meta Description</label>
    <textarea name="meta_description" rows="2" maxlength="320"><?= $e($editPost['meta_description'] ?? '') ?></textarea>
    <label>Status</label>
    <select name="status">
      <option value="draft" <?= ($editPost['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
      <option value="published" <?= ($editPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
    </select>
    <button class="abtn abtn-primary" type="submit">સેવ</button>
    <?php if ($editPost): ?><a class="abtn" href="blog.php">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="admin-grid-2">
  <div class="admin-card">
    <h2>બધા Posts</h2>
    <table class="atable">
      <tr><th>Title</th><th>Status</th><th>Views</th><th></th></tr>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><?= $e($p['title']) ?><br><small class="amuted">/blog/<?= $e($p['slug']) ?></small></td>
        <td><span class="abadge ab-<?= $p['status'] === 'published' ? 'ok' : 'warn' ?>"><?= $e($p['status']) ?></span></td>
        <td class="num"><?= number_format((int)$p['views']) ?></td>
        <td>
          <a class="abtn abtn-xs" href="?edit=<?= (int)$p['id'] ?>">Edit</a>
          <form method="post" class="inline" onsubmit="return confirm('Delete?')"><?= Security::csrfField() ?>
            <input type="hidden" name="action" value="delete_post"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="abtn abtn-xs abtn-danger" type="submit">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="admin-card">
    <h2>Categories</h2>
    <form method="post" class="filter-row">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="save_category">
      <input type="text" name="cat_name" placeholder="નવી category..." required>
      <button class="abtn" type="submit">+ ઉમેરો</button>
    </form>
    <table class="atable">
      <?php foreach ($cats as $c): ?>
        <tr><td><?= $e($c['name']) ?></td><td><code><?= $e($c['slug']) ?></code></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
