<?php
/**
 * Cron jobs common bootstrap — CLI થી જ ચાલે.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/constants.php';
require CORE_PATH . '/App.php';
App::bootstrap();
if (!App::isInstalled()) {
    fwrite(STDERR, "Site not installed\n");
    exit(1);
}
