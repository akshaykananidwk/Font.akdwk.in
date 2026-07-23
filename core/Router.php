<?php
/**
 * Simple URL router — front controller (index.php) માટે.
 * Static routes + dynamic patterns ({slug} placeholders).
 */

defined('BASE_PATH') or die('Direct access denied');

class Router
{
    /** @var array<string, array{method: string, handler: callable}> */
    private array $routes = [];
    /** @var array<int, array{pattern: string, method: string, handler: callable}> */
    private array $dynamicRoutes = [];
    /** @var callable|null */
    private $notFoundHandler = null;

    /**
     * Route register કરો.
     *
     * @param string   $method  GET/POST
     * @param string   $path    '/faq' અથવા '/blog/{slug}'
     * @param callable $handler
     */
    public function add(string $method, string $path, callable $handler): void
    {
        if (str_contains($path, '{')) {
            // {slug} → named capture group
            $pattern = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $path);
            $this->dynamicRoutes[] = [
                'pattern' => '#^' . $pattern . '$#',
                'method'  => strtoupper($method),
                'handler' => $handler,
            ];
        } else {
            $this->routes[strtoupper($method) . ' ' . $path] = [
                'method'  => strtoupper($method),
                'handler' => $handler,
            ];
        }
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function setNotFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Request dispatch કરો.
     */
    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rtrim($uri, '/') ?: '/';
        // Sub-directory install support
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir)) ?: '/';
        }

        // 301 redirects (seo_redirects ટેબલ)
        $this->applySeoRedirect($uri);

        $key = $method . ' ' . $uri;
        if (isset($this->routes[$key])) {
            call_user_func($this->routes[$key]['handler']);
            return;
        }

        foreach ($this->dynamicRoutes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        if ($this->notFoundHandler !== null) {
            call_user_func($this->notFoundHandler);
        } else {
            echo '404 Not Found';
        }
    }

    /** seo_redirects ટેબલમાંથી redirect લાગુ કરો. */
    private function applySeoRedirect(string $uri): void
    {
        try {
            $db = Database::getInstance();
            $table = $db->table('seo_redirects');
            $redirect = $db->fetch(
                "SELECT * FROM `{$table}` WHERE from_url = ? AND is_active = 1 LIMIT 1",
                [$uri]
            );
            if ($redirect !== null) {
                $db->query("UPDATE `{$table}` SET hits = hits + 1 WHERE id = ?", [$redirect['id']]);
                Helper::redirect($redirect['to_url'], (int)$redirect['redirect_type']);
            }
        } catch (Throwable $e) {
            // DB ન હોય તો redirect skip
        }
    }
}
