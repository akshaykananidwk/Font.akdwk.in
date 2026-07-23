<?php
/**
 * CMS pages, pricing, contact, API docs, sitemap, robots.
 */

defined('BASE_PATH') or die('Direct access denied');

class PageController
{
    /**
     * CMS page બતાવો (pages ટેબલમાંથી).
     */
    public static function show(string $slug): void
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $db = Database::getInstance();
        $table = $db->table('pages');
        $page = $db->fetch(
            "SELECT * FROM `{$table}` WHERE slug = ? AND status = 'published' LIMIT 1",
            [$slug]
        );
        if ($page === null) {
            http_response_code(404);
            View::render('errors/404', ['metaTitle' => '404']);
            return;
        }
        View::render('page', [
            'page'            => $page,
            'metaTitle'       => $page['meta_title'] ?: $page['title'],
            'metaDescription' => $page['meta_description'] ?? '',
            'canonical'       => $page['canonical_url'] ?: App::url('/' . $slug),
            'noindex'         => (int)$page['is_indexed'] === 0,
            'isFaq'           => $slug === 'faq',
        ]);
    }

    /** GET /pricing */
    public static function pricing(): void
    {
        $db = Database::getInstance();
        $table = $db->table('plans');
        $plans = $db->fetchAll("SELECT * FROM `{$table}` WHERE is_active = 1 ORDER BY sort_order ASC");
        View::render('pricing', [
            'plans'           => $plans,
            'metaTitle'       => 'Pricing — Gujarati Font Converter',
            'metaDescription' => 'Affordable plans for the Gujarati Font Converter — from a free demo to unlimited API access. Starting at ₹99.',
            'canonical'       => App::url('/pricing'),
        ]);
    }

    /** GET /contact */
    public static function contactForm(): void
    {
        View::render('contact', [
            'metaTitle'       => 'Contact — Gujarati Font Converter',
            'metaDescription' => 'Contact us with questions, suggestions, or to report a font mapping error.',
            'canonical'       => App::url('/contact'),
            'sent'            => Session::flash('contact_sent'),
            'error'           => Session::flash('contact_error'),
        ]);
    }

    /** POST /contact */
    public static function contactSubmit(): void
    {
        if (!Security::verifyCsrf()) {
            Session::flash('contact_error', 'Session expired — please try again.');
            Helper::redirect(App::url('/contact'));
        }
        if (!Security::checkHoneypot()) {
            // Bot — ચૂપચાપ સફળતા બતાવો
            Session::flash('contact_sent', '1');
            Helper::redirect(App::url('/contact'));
        }
        if (!Security::rateLimit(Helper::clientIp(), 'contact', 5, 3600)) {
            Session::flash('contact_error', 'Too many messages. Please try again shortly.');
            Helper::redirect(App::url('/contact'));
        }

        $name = mb_substr(trim(Security::cleanInput((string)($_POST['name'] ?? ''))), 0, 100);
        $email = trim((string)($_POST['email'] ?? ''));
        $subject = mb_substr(trim(Security::cleanInput((string)($_POST['subject'] ?? ''))), 0, 255);
        $message = mb_substr(trim(Security::cleanInput((string)($_POST['message'] ?? ''))), 0, 5000);

        if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('contact_error', 'Name, a valid email and a message are required.');
            Helper::redirect(App::url('/contact'));
        }

        try {
            Database::getInstance()->insert('contacts', [
                'name'       => $name,
                'email'      => $email,
                'phone'      => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 20),
                'subject'    => $subject,
                'message'    => $message,
                'ip_address' => Helper::clientIp(),
            ]);
            // Admin ને જાણ + auto-reply
            $adminEmail = App::config()['site']['admin_email'] ?? '';
            if ($adminEmail !== '') {
                Mailer::sendTemplate($adminEmail, 'New contact message: ' . $subject, nl2br(Helper::e("From: {$name} <{$email}>\n\n{$message}")));
            }
            Mailer::sendTemplate($email, 'We received your message — ' . App::setting('site_name', APP_NAME), 'Hello ' . Helper::e($name) . ',<br><br>We have received your message and will reply soon.<br><br>Thank you!');
            Session::flash('contact_sent', '1');
        } catch (Throwable $e) {
            Logger::error('Contact save failed: ' . $e->getMessage());
            Session::flash('contact_error', 'Message could not be saved — please try again.');
        }
        Helper::redirect(App::url('/contact'));
    }

    /** GET /api — API documentation. */
    public static function apiDocs(): void
    {
        View::render('api-docs', [
            'metaTitle'       => 'API Documentation — Gujarati Font Converter',
            'metaDescription' => 'Integrate Gujarati font conversion into your app via our REST API. cURL, PHP, Python, JavaScript examples.',
            'canonical'       => App::url('/api'),
        ]);
    }

    /**
     * GET /sitemap.xml — ડાયનેમિક sitemap (fonts + pages + blog).
     */
    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $db = Database::getInstance();
        $urls = [];
        $urls[] = ['loc' => App::url('/'), 'priority' => '1.0', 'lastmod' => date('Y-m-d')];
        foreach (['gujarati-font-converter', 'hindi-font-converter', 'marathi-font-converter', 'nepali-font-converter', 'pricing', 'faq', 'contact', 'api', 'blog', 'font-installation-guide', 'privacy-policy', 'terms'] as $p) {
            $urls[] = ['loc' => App::url('/' . $p), 'priority' => '0.8', 'lastmod' => date('Y-m-d')];
        }
        try {
            foreach ($db->fetchAll("SELECT font_slug, created_at FROM `" . $db->table('fonts') . "` WHERE is_active = 1") as $f) {
                $urls[] = [
                    'loc'      => App::url('/' . $f['font_slug'] . '-to-unicode-converter'),
                    'priority' => '0.9',
                    'lastmod'  => date('Y-m-d', strtotime($f['created_at'])),
                ];
            }
            foreach ($db->fetchAll("SELECT slug, published_at FROM `" . $db->table('blog_posts') . "` WHERE status = 'published'") as $b) {
                $urls[] = [
                    'loc'      => App::url('/blog/' . $b['slug']),
                    'priority' => '0.7',
                    'lastmod'  => date('Y-m-d', strtotime($b['published_at'] ?? 'now')),
                ];
            }
        } catch (Throwable $e) {
            Logger::error('Sitemap generation: ' . $e->getMessage());
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            printf(
                "  <url><loc>%s</loc><lastmod>%s</lastmod><priority>%s</priority></url>\n",
                Helper::e($u['loc']),
                $u['lastmod'],
                $u['priority']
            );
        }
        echo '</urlset>';
        exit;
    }

    /** GET /robots.txt */
    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $custom = App::setting('robots_txt', '');
        if ($custom !== '') {
            echo $custom;
            exit;
        }
        $adminPath = App::config()['site']['admin_path'] ?? 'admin';
        echo "User-agent: *\n";
        echo "Disallow: /{$adminPath}/\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /install/\n";
        echo "Disallow: /storage/\n";
        echo "Disallow: /config/\n";
        echo "Disallow: /core/\n";
        echo "Disallow: /engine/\n";
        echo "\nSitemap: " . App::url('/sitemap.xml') . "\n";
        exit;
    }
}
