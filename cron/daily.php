<?php
/**
 * Daily cron — 0 3 * * * php /path/to/cron/daily.php
 *
 * કામ: demo reset (સફાઈ), subscription expiry check + reminder emails,
 *      log cleanup, જૂના rate-limit rows સાફ.
 */
require __DIR__ . '/bootstrap.php';

$db = Database::getInstance();
echo "[" . date('c') . "] Daily cron શરૂ\n";

// 1. Demo usage — expired reset rows સાફ (lazy reset ઉપરાંત સફાઈ)
$demoTable = $db->table('demo_usage');
$n = $db->query("DELETE FROM `{$demoTable}` WHERE reset_at IS NOT NULL AND reset_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->rowCount();
echo "Demo rows cleaned: {$n}\n";

// 2. Subscription expiry — status update + reminder emails (7/3/1 દિવસ)
$usersTable = $db->table('users');
$db->query("UPDATE `{$usersTable}` SET status = 'expired' WHERE status = 'active' AND subscription_end IS NOT NULL AND subscription_end < NOW()");
foreach ([7, 3, 1] as $days) {
    $users = $db->fetchAll(
        "SELECT name, email, subscription_end FROM `{$usersTable}`
         WHERE status = 'active' AND subscription_end IS NOT NULL
         AND DATE(subscription_end) = DATE(DATE_ADD(NOW(), INTERVAL ? DAY))",
        [$days]
    );
    foreach ($users as $user) {
        Mailer::sendTemplate(
            $user['email'],
            "તમારું subscription {$days} દિવસમાં પૂરું થાય છે",
            'નમસ્તે ' . Helper::e($user['name']) . ',<br><br>તમારું subscription '
            . date('d M Y', strtotime($user['subscription_end'])) . ' ના રોજ પૂરું થાય છે. '
            . 'સેવા ચાલુ રાખવા renew કરો.<br><br><a href="' . App::url('/pricing') . '">Renew કરો</a>'
        );
        echo "Reminder ({$days}d): {$user['email']}\n";
    }
}

// 3. Log cleanup
$deleted = Logger::cleanup(6);
echo "Log files cleaned: {$deleted}\n";

// 4. જૂના rate_limits + api_logs trim
$rlTable = $db->table('rate_limits');
$db->query("DELETE FROM `{$rlTable}` WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)");
$alTable = $db->table('api_logs');
$db->query("DELETE FROM `{$alTable}` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$clTable = $db->table('conversions_log');
$db->query("DELETE FROM `{$clTable}` WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");

echo "[" . date('c') . "] Daily cron પૂરું\n";
