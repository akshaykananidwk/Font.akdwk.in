<?php
/**
 * Authentication — admin + user login, password hashing, lockout.
 */

defined('BASE_PATH') or die('Direct access denied');

class Auth
{
    /**
     * Password hash બનાવો (Argon2id ઉપલબ્ધ હોય તો, નહીં તો bcrypt).
     */
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Admin login attempt.
     *
     * @return array{success: bool, error?: string}
     */
    public static function adminLogin(string $username, string $password): array
    {
        $db = Database::getInstance();
        $table = $db->table('admins');
        $admin = $db->fetch(
            "SELECT * FROM `{$table}` WHERE username = ? AND status = 'active' LIMIT 1",
            [$username]
        );

        if ($admin === null) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Lockout check
        if ($admin['locked_until'] !== null && strtotime($admin['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($admin['locked_until']) - time()) / 60);
            return ['success' => false, 'error' => "Account locked. Try again in {$mins} minute(s)."];
        }

        if (!password_verify($password, $admin['password_hash'])) {
            $attempts = (int)$admin['login_attempts'] + 1;
            $data = ['login_attempts' => $attempts];
            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                $data['locked_until'] = date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60);
                $data['login_attempts'] = 0;
            }
            $db->update('admins', $data, 'id = ?', [$admin['id']]);
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // સફળ login
        $db->update('admins', [
            'login_attempts' => 0,
            'locked_until'   => null,
            'last_login'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [$admin['id']]);

        Session::regenerate();
        Session::set('admin_id', (int)$admin['id']);
        Session::set('admin_username', $admin['username']);
        Session::set('admin_role', $admin['role']);

        self::logAdminActivity((int)$admin['id'], 'login', 'auth', []);
        return ['success' => true];
    }

    /** Admin logged in છે? */
    public static function isAdminLoggedIn(): bool
    {
        return Session::get('admin_id') !== null;
    }

    /** Admin ન હોય તો login page પર મોકલો. */
    public static function requireAdmin(): void
    {
        if (!self::isAdminLoggedIn()) {
            $adminPath = App::config()['site']['admin_path'] ?? 'admin';
            Helper::redirect(App::url($adminPath . '/login.php'));
        }
    }

    /** Super admin role ફરજિયાત (update વગેરે માટે). */
    public static function requireSuperAdmin(): void
    {
        self::requireAdmin();
        if (Session::get('admin_role') !== 'super_admin') {
            http_response_code(403);
            die('403 — Super admin access required');
        }
    }

    /** Admin ના password ને ફરી verify કરો (sensitive actions પહેલાં). */
    public static function reverifyAdminPassword(string $password): bool
    {
        $adminId = Session::get('admin_id');
        if ($adminId === null) {
            return false;
        }
        $db = Database::getInstance();
        $table = $db->table('admins');
        $admin = $db->fetch("SELECT password_hash FROM `{$table}` WHERE id = ? LIMIT 1", [$adminId]);
        return $admin !== null && password_verify($password, $admin['password_hash']);
    }

    /** Admin activity log entry. */
    public static function logAdminActivity(int $adminId, string $action, string $module, array $details): void
    {
        try {
            Database::getInstance()->insert('admin_activity_log', [
                'admin_id'   => $adminId,
                'action'     => $action,
                'module'     => $module,
                'details'    => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip_address' => Helper::clientIp(),
            ]);
        } catch (Throwable $e) {
            Logger::error('Admin activity log failed: ' . $e->getMessage());
        }
    }

    /**
     * User (subscriber) login.
     *
     * @return array{success: bool, error?: string}
     */
    public static function userLogin(string $email, string $password): array
    {
        $db = Database::getInstance();
        $table = $db->table('users');
        $user = $db->fetch("SELECT * FROM `{$table}` WHERE email = ? LIMIT 1", [$email]);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        if ($user['status'] === 'suspended') {
            return ['success' => false, 'error' => 'Account suspended. Contact support.'];
        }

        $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
        Session::set('user_name', $user['name']);
        return ['success' => true];
    }

    /** હાલનો logged-in user (અથવા null). */
    public static function currentUser(): ?array
    {
        $userId = Session::get('user_id');
        if ($userId === null) {
            return null;
        }
        $db = Database::getInstance();
        $table = $db->table('users');
        return $db->fetch("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$userId]);
    }

    /** User પાસે active paid subscription છે? */
    public static function userHasActivePlan(?array $user = null): bool
    {
        $user = $user ?? self::currentUser();
        return $user !== null
            && $user['status'] === 'active'
            && $user['plan_id'] !== null
            && $user['subscription_end'] !== null
            && strtotime($user['subscription_end']) > time();
    }
}
