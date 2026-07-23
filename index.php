<?php
/**
 * Front Controller — બધી public requests અહીંથી route થાય છે.
 */

define('BASE_PATH', __DIR__);
require BASE_PATH . '/config/constants.php';
require CORE_PATH . '/App.php';

App::bootstrap();

// Installed નથી? → installer પર
if (!App::isInstalled()) {
    if (is_dir(BASE_PATH . '/install')) {
        header('Location: install/');
        exit;
    }
    http_response_code(500);
    die('Site is not installed and /install folder is missing.');
}

// Maintenance mode (admin session સિવાય)
if (App::isMaintenanceMode()) {
    Session::start();
    if (Session::get('admin_id') === null) {
        http_response_code(503);
        header('Retry-After: 600');
        $viewFile = VIEWS_PATH . '/errors/maintenance.php';
        if (is_file($viewFile)) {
            include $viewFile;
        } else {
            echo '<h1>થોડી વારમાં પાછા આવીએ છીએ…</h1><p>Site maintenance ચાલુ છે.</p>';
        }
        exit;
    }
}

Security::sendHeaders();

$router = new Router();

// ---- મુખ્ય કન્વર્ટર ----
$router->get('/', [HomeController::class, 'index']);
$router->post('/convert', [ConverterController::class, 'convert']);
$router->get('/demo-status', [ConverterController::class, 'demoStatus']);

// ---- ભાષા-વાર SEO landing pages ----
$router->get('/gujarati-font-converter', fn() => HomeController::language('gu'));
$router->get('/hindi-font-converter', fn() => HomeController::language('hi'));
$router->get('/marathi-font-converter', fn() => HomeController::language('mr'));
$router->get('/nepali-font-converter', fn() => HomeController::language('ne'));

// ---- ફોન્ટ-વાર SEO pages (94 auto-generated) ----
$router->get('/{slug}-to-unicode-converter', fn($p) => HomeController::fontPage($p['slug']));

// ---- Static/CMS pages ----
$router->get('/faq', fn() => PageController::show('faq'));
$router->get('/privacy-policy', fn() => PageController::show('privacy-policy'));
$router->get('/terms', fn() => PageController::show('terms'));
$router->get('/font-installation-guide', fn() => PageController::show('font-installation-guide'));
$router->get('/pricing', [PageController::class, 'pricing']);
$router->get('/contact', [PageController::class, 'contactForm']);
$router->post('/contact', [PageController::class, 'contactSubmit']);
$router->get('/page/{slug}', fn($p) => PageController::show($p['slug']));

// ---- Blog ----
$router->get('/blog', [BlogController::class, 'index']);
$router->get('/blog/{slug}', fn($p) => BlogController::single($p['slug']));

// ---- API docs ----
$router->get('/api', [PageController::class, 'apiDocs']);

// ---- Auth ----
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/dashboard', [AuthController::class, 'dashboard']);
$router->post('/dashboard/api-key', [AuthController::class, 'generateApiKey']);

// ---- SEO ----
$router->get('/sitemap.xml', [PageController::class, 'sitemap']);
$router->get('/robots.txt', [PageController::class, 'robots']);

$router->setNotFound(function (): void {
    View::render('errors/404', ['metaTitle' => '404 — પેજ મળ્યું નથી']);
});

try {
    $router->dispatch();
} catch (Throwable $e) {
    Logger::error('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty(App::config()['debug'])) {
        throw $e;
    }
    View::render('errors/500', ['metaTitle' => 'Server Error']);
}
