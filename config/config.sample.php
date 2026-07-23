<?php
/**
 * Configuration template.
 * Installer આ ફાઇલ ની નકલ કરીને config.php બનાવે છે — config.php ક્યારેય repo/update માં overwrite ન થાય.
 */

defined('BASE_PATH') or die('Direct access denied');

return [
    'db' => [
        'host'     => '{{DB_HOST}}',
        'port'     => '{{DB_PORT}}',
        'name'     => '{{DB_NAME}}',
        'user'     => '{{DB_USER}}',
        'pass'     => '{{DB_PASS}}',
        'prefix'   => '{{DB_PREFIX}}',
        'charset'  => 'utf8mb4',
    ],

    'site' => [
        'url'      => '{{SITE_URL}}',
        'name'     => '{{SITE_NAME}}',
        'timezone' => '{{TIMEZONE}}',
        'language' => '{{LANGUAGE}}',
        'admin_email' => '{{ADMIN_EMAIL}}',
        // Admin panel path — /admin બદલવું હોય તો અહીં (દા.ત. 'mypanel')
        'admin_path' => 'admin',
    ],

    'security' => [
        // installer random 64-char key generate કરે છે — GitHub token encryption માટે
        'encryption_key' => '{{ENCRYPTION_KEY}}',
        'session_name'   => 'gfc_session',
    ],

    'debug' => false,

    // GitHub update વખતે આ ફાઇલો/ફોલ્ડર ક્યારેય overwrite ન થાય
    'protected_paths' => [
        'config/config.php',
        '.env',
        'install.lock',
        'storage/uploads/',
        'storage/logs/',
        'storage/backups/',
        'storage/cache/',
        'assets/uploads/',
        'engine/mappings/custom/',
        'sitemap.xml',
        'robots.txt',
    ],
];
