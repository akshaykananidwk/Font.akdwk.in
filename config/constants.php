<?php
/**
 * Global constants — paths, version, defaults.
 * આ ફાઇલ installer પછી પણ બદલાતી નથી; site-specific settings config.php માં જાય છે.
 */

defined('BASE_PATH') or die('Direct access denied');

define('APP_NAME', 'Gujarati Font Converter');
define('APP_VERSION', '1.0.0');
define('DB_SCHEMA_VERSION', 1);

// ---- Paths ----
define('CONFIG_PATH', BASE_PATH . '/config');
define('CORE_PATH', BASE_PATH . '/core');
define('ENGINE_PATH', BASE_PATH . '/engine');
define('MAPPINGS_PATH', ENGINE_PATH . '/mappings');
define('CONTROLLERS_PATH', BASE_PATH . '/controllers');
define('VIEWS_PATH', BASE_PATH . '/views');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CACHE_PATH', STORAGE_PATH . '/cache');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('BACKUPS_PATH', STORAGE_PATH . '/backups');
define('TEMP_PATH', STORAGE_PATH . '/temp');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('LANG_PATH', BASE_PATH . '/lang');
define('MIGRATIONS_PATH', BASE_PATH . '/migrations');

// ---- Defaults (DB settings આને override કરે) ----
define('DEFAULT_DEMO_CHAR_LIMIT', 200);
define('DEFAULT_DEMO_ATTEMPT_LIMIT', 20);
define('DEFAULT_RATE_LIMIT_PER_MIN', 30);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);
define('SESSION_TIMEOUT_MINUTES', 60);
define('MAX_BACKUPS_KEPT', 5);

// ---- Engine internal markers (Private Use Area) ----
// Legacy reph glyph → આ placeholder → reorder પછી "ર્" બને છે.
define('ENGINE_REPH_MARKER', "\u{F001}");
// Legacy "િ" (pre-base i matra) glyph → આ placeholder → reorder પછી "િ" બને છે.
define('ENGINE_IMATRA_MARKER', "\u{F002}");

define('INSTALL_LOCK_FILE', BASE_PATH . '/install.lock');
define('MAINTENANCE_FLAG', STORAGE_PATH . '/maintenance.flag');
define('UPDATE_LOCK_FILE', STORAGE_PATH . '/update.lock');
