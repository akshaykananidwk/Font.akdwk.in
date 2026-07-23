<?php
/**
 * Application bootstrap + config/settings access.
 * બધા entry points (index.php, admin, api, cron) આ class થી શરૂ થાય.
 */

defined('BASE_PATH') or die('Direct access denied');

class App
{
    private static ?array $config = null;
    private static array $settings = [];
    private static bool $settingsLoaded = false;

    /**
     * Autoloader + basic environment setup.
     */
    public static function bootstrap(): void
    {
        mb_internal_encoding('UTF-8');

        spl_autoload_register(function (string $class): void {
            $paths = [
                CORE_PATH . '/' . $class . '.php',
                ENGINE_PATH . '/' . $class . '.php',
                CONTROLLERS_PATH . '/' . $class . '.php',
            ];
            foreach ($paths as $path) {
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        });

        $config = self::config();
        if ($config !== null) {
            date_default_timezone_set($config['site']['timezone'] ?? 'Asia/Kolkata');
            if (!empty($config['debug'])) {
                error_reporting(E_ALL);
                ini_set('display_errors', '1');
            } else {
                error_reporting(E_ALL);
                ini_set('display_errors', '0');
                ini_set('log_errors', '1');
                ini_set('error_log', LOGS_PATH . '/php_errors.log');
            }
        }
    }

    /**
     * config.php લોડ કરો (installed ન હોય તો null).
     */
    public static function config(): ?array
    {
        if (self::$config === null) {
            $file = CONFIG_PATH . '/config.php';
            if (is_file($file)) {
                self::$config = require $file;
            }
        }
        return self::$config;
    }

    /** Installation થઈ ગયું છે? */
    public static function isInstalled(): bool
    {
        return is_file(INSTALL_LOCK_FILE) && self::config() !== null;
    }

    /** Maintenance mode ચાલુ છે? */
    public static function isMaintenanceMode(): bool
    {
        return is_file(MAINTENANCE_FLAG);
    }

    /**
     * DB settings ટેબલમાંથી setting મેળવો (cache સાથે).
     *
     * @param mixed $default setting ન મળે તો
     */
    public static function setting(string $key, mixed $default = null): mixed
    {
        if (!self::$settingsLoaded) {
            try {
                $db = Database::getInstance();
                $rows = $db->fetchAll("SELECT setting_key, setting_value FROM `" . $db->table('settings') . "`");
                foreach ($rows as $row) {
                    self::$settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Throwable $e) {
                // DB ઉપલબ્ધ ન હોય તો defaults વાપરો
                Logger::error('Settings load failed: ' . $e->getMessage());
            }
            self::$settingsLoaded = true;
        }
        return self::$settings[$key] ?? $default;
    }

    /** Setting સેવ કરો (upsert). */
    public static function setSetting(string $key, string $value, string $group = 'general'): void
    {
        $db = Database::getInstance();
        $table = $db->table('settings');
        $db->query(
            "INSERT INTO `{$table}` (setting_key, setting_value, setting_group)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)",
            [$key, $value, $group]
        );
        self::$settings[$key] = $value;
    }

    /** Site base URL (trailing slash વગર). */
    public static function url(string $path = ''): string
    {
        $base = rtrim(self::config()['site']['url'] ?? '', '/');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}
