<?php
/**
 * Admin logout.
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/constants.php';
require_once CORE_PATH . '/App.php';
App::bootstrap();

$adminId = Session::get('admin_id');
if ($adminId !== null) {
    Auth::logAdminActivity((int)$adminId, 'logout', 'auth', []);
}
Session::destroy();
header('Location: login.php');
exit;
