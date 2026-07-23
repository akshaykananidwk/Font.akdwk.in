<?php
/**
 * User (subscriber) auth — login, register, dashboard, API keys.
 */

defined('BASE_PATH') or die('Direct access denied');

class AuthController
{
    /** GET /login */
    public static function loginForm(): void
    {
        if (Auth::currentUser() !== null) {
            Helper::redirect(App::url('/dashboard'));
        }
        View::render('login', [
            'metaTitle' => 'Login — Gujarati Font Converter',
            'noindex'   => true,
            'error'     => Session::flash('auth_error'),
            'success'   => Session::flash('auth_success'),
        ]);
    }

    /** POST /login */
    public static function login(): void
    {
        if (!Security::verifyCsrf()) {
            Session::flash('auth_error', 'Session expired — please try again.');
            Helper::redirect(App::url('/login'));
        }
        if (!Security::rateLimit(Helper::clientIp(), 'user_login', 10, 900)) {
            Session::flash('auth_error', 'Too many attempts. Please try again after 15 minutes.');
            Helper::redirect(App::url('/login'));
        }
        $result = Auth::userLogin(trim((string)($_POST['email'] ?? '')), (string)($_POST['password'] ?? ''));
        if (!$result['success']) {
            Session::flash('auth_error', $result['error'] ?? 'Login failed');
            Helper::redirect(App::url('/login'));
        }
        Helper::redirect(App::url('/dashboard'));
    }

    /** GET /register */
    public static function registerForm(): void
    {
        if (Auth::currentUser() !== null) {
            Helper::redirect(App::url('/dashboard'));
        }
        View::render('register', [
            'metaTitle' => 'Register — Gujarati Font Converter',
            'noindex'   => true,
            'error'     => Session::flash('auth_error'),
        ]);
    }

    /** POST /register */
    public static function register(): void
    {
        if (!Security::verifyCsrf() || !Security::checkHoneypot()) {
            Session::flash('auth_error', 'Session expired — please try again.');
            Helper::redirect(App::url('/register'));
        }
        if (!Security::rateLimit(Helper::clientIp(), 'register', 5, 3600)) {
            Session::flash('auth_error', 'Too many attempts. Please try again later.');
            Helper::redirect(App::url('/register'));
        }

        $name = mb_substr(trim(Security::cleanInput((string)($_POST['name'] ?? ''))), 0, 100);
        $email = trim((string)($_POST['email'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            Session::flash('auth_error', 'Name, a valid email and an 8+ character password are required.');
            Helper::redirect(App::url('/register'));
        }

        $db = Database::getInstance();
        $table = $db->table('users');
        try {
            $existing = $db->fetch("SELECT id FROM `{$table}` WHERE email = ?", [$email]);
            if ($existing !== null) {
                Session::flash('auth_error', 'This email is already registered — please log in.');
                Helper::redirect(App::url('/login'));
            }
            $db->insert('users', [
                'name'          => $name,
                'email'         => $email,
                'password_hash' => Auth::hashPassword($pass),
                'plan_id'       => 1, // Free Demo plan
                'status'        => 'active',
                'verify_token'  => Helper::randomToken(24),
            ]);
            Mailer::sendTemplate($email, 'Welcome — ' . App::setting('site_name', APP_NAME), 'Hello ' . Helper::e($name) . ',<br><br>Your account has been created. Get a paid plan for unlimited conversions and API access.<br><br><a href="' . App::url('/pricing') . '">View plans</a>');
            Session::flash('auth_success', 'Account created! Please log in now.');
            Helper::redirect(App::url('/login'));
        } catch (Throwable $e) {
            Logger::error('Register failed: ' . $e->getMessage());
            Session::flash('auth_error', 'Registration failed — please try again.');
            Helper::redirect(App::url('/register'));
        }
    }

    /** GET /logout */
    public static function logout(): void
    {
        Session::destroy();
        Helper::redirect(App::url('/'));
    }

    /** GET /dashboard */
    public static function dashboard(): void
    {
        $user = Auth::currentUser();
        if ($user === null) {
            Helper::redirect(App::url('/login'));
        }
        $db = Database::getInstance();
        $plansTable = $db->table('plans');
        $keysTable = $db->table('api_keys');
        $convTable = $db->table('conversions_log');

        $plan = $user['plan_id'] ? $db->fetch("SELECT * FROM `{$plansTable}` WHERE id = ?", [$user['plan_id']]) : null;
        $apiKeys = $db->fetchAll("SELECT * FROM `{$keysTable}` WHERE user_id = ? ORDER BY created_at DESC", [$user['id']]);
        $todayConversions = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM `{$convTable}` WHERE user_id = ? AND created_at >= CURDATE()",
            [$user['id']]
        );

        View::render('dashboard', [
            'user'             => $user,
            'plan'             => $plan,
            'apiKeys'          => $apiKeys,
            'todayConversions' => $todayConversions,
            'hasActivePlan'    => Auth::userHasActivePlan($user),
            'metaTitle'        => 'Dashboard — Gujarati Font Converter',
            'noindex'          => true,
            'flash'            => Session::flash('dash_msg'),
        ]);
    }

    /** POST /dashboard/api-key — નવી API key generate. */
    public static function generateApiKey(): void
    {
        $user = Auth::currentUser();
        if ($user === null) {
            Helper::redirect(App::url('/login'));
        }
        if (!Security::verifyCsrf()) {
            Session::flash('dash_msg', 'Session expired.');
            Helper::redirect(App::url('/dashboard'));
        }

        $db = Database::getInstance();
        $plansTable = $db->table('plans');
        $plan = $user['plan_id'] ? $db->fetch("SELECT * FROM `{$plansTable}` WHERE id = ?", [$user['plan_id']]) : null;
        if (!$plan || (int)$plan['api_access'] !== 1 || !Auth::userHasActivePlan($user)) {
            Session::flash('dash_msg', 'A Pro or Business plan is required for API access.');
            Helper::redirect(App::url('/dashboard'));
        }

        $keysTable = $db->table('api_keys');
        $count = (int)$db->fetchValue("SELECT COUNT(*) FROM `{$keysTable}` WHERE user_id = ? AND status = 'active'", [$user['id']]);
        if ($count >= 5) {
            Session::flash('dash_msg', 'You can have a maximum of 5 active API keys.');
            Helper::redirect(App::url('/dashboard'));
        }

        $key = 'gfc_' . Helper::randomToken(28);
        $db->insert('api_keys', [
            'user_id'     => $user['id'],
            'api_key'     => $key,
            'api_secret'  => Helper::randomToken(32),
            'name'        => mb_substr(trim((string)($_POST['key_name'] ?? 'Default')), 0, 100) ?: 'Default',
            'daily_limit' => (int)$plan['api_daily_limit'],
            'calls_date'  => date('Y-m-d'),
            'expires_at'  => $user['subscription_end'],
        ]);
        Mailer::sendTemplate($user['email'], 'New API Key created — ' . App::setting('site_name', APP_NAME), 'Your new API key has been created. View it in your Dashboard. Did not create it? Contact us immediately.');
        Session::flash('dash_msg', 'New API key created: ' . $key);
        Helper::redirect(App::url('/dashboard'));
    }
}
