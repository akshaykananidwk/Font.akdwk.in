<?php
/**
 * Blog — list + single post.
 */

defined('BASE_PATH') or die('Direct access denied');

class BlogController
{
    /** GET /blog */
    public static function index(): void
    {
        $db = Database::getInstance();
        $posts = $db->table('blog_posts');
        $cats = $db->table('blog_categories');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        $search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

        $where = "status = 'published'";
        $params = [];
        if ($search !== '') {
            $where .= " AND (title LIKE ? OR excerpt LIKE ? OR tags LIKE ?)";
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        $total = (int)$db->fetchValue("SELECT COUNT(*) FROM `{$posts}` WHERE {$where}", $params);
        $rows = $db->fetchAll(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM `{$posts}` p LEFT JOIN `{$cats}` c ON c.id = p.category_id
             WHERE {$where} ORDER BY p.published_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        View::render('blog-list', [
            'posts'           => $rows,
            'total'           => $total,
            'page'            => $page,
            'totalPages'      => (int)ceil($total / $perPage),
            'search'          => $search,
            'metaTitle'       => 'બ્લોગ — Gujarati Font Converter',
            'metaDescription' => 'ગુજરાતી ટાઇપિંગ, ફોન્ટ કન્વર્ઝન અને Unicode વિશે ઉપયોગી લેખો.',
            'canonical'       => App::url('/blog'),
        ]);
    }

    /** GET /blog/{slug} */
    public static function single(string $slug): void
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $db = Database::getInstance();
        $posts = $db->table('blog_posts');
        $cats = $db->table('blog_categories');
        $post = $db->fetch(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM `{$posts}` p LEFT JOIN `{$cats}` c ON c.id = p.category_id
             WHERE p.slug = ? AND p.status = 'published' LIMIT 1",
            [$slug]
        );
        if ($post === null) {
            http_response_code(404);
            View::render('errors/404', ['metaTitle' => '404']);
            return;
        }

        $db->query("UPDATE `{$posts}` SET views = views + 1 WHERE id = ?", [$post['id']]);

        // Related posts — same category
        $related = $db->fetchAll(
            "SELECT slug, title, published_at FROM `{$posts}`
             WHERE status = 'published' AND id != ? AND (category_id = ? OR ? IS NULL)
             ORDER BY published_at DESC LIMIT 4",
            [$post['id'], $post['category_id'], $post['category_id']]
        );

        View::render('blog-single', [
            'post'            => $post,
            'related'         => $related,
            'metaTitle'       => $post['meta_title'] ?: $post['title'],
            'metaDescription' => $post['meta_description'] ?: mb_substr(strip_tags((string)$post['excerpt']), 0, 160),
            'canonical'       => App::url('/blog/' . $post['slug']),
            'ogImage'         => $post['featured_image'] ?: null,
            'isArticle'       => true,
        ]);
    }
}
