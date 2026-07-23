<?php
/**
 * Update check cron — auto-INSTALL નહીં, ફક્ત alert email.
 * 0 6 * * * php /path/to/cron/check_update.php
 * frequency setting 'manual' હોય તો કંઈ નહીં કરે.
 */
require __DIR__ . '/bootstrap.php';

$settings = Updater::getSettings();
if ($settings['frequency'] === 'manual' || $settings['repo'] === '') {
    echo "Auto-check off (frequency: {$settings['frequency']})\n";
    exit(0);
}
// weekly હોય તો ફક્ત રવિવારે
if ($settings['frequency'] === 'weekly' && (int)date('w') !== 0) {
    echo "Weekly check — આજે નહીં\n";
    exit(0);
}

try {
    $check = Updater::checkForUpdate();
    if ($check['update_available']) {
        $adminEmail = App::config()['site']['admin_email'] ?? '';
        if ($adminEmail !== '') {
            Mailer::sendTemplate(
                $adminEmail,
                '🆕 નવું update ઉપલબ્ધ: v' . $check['remote_version'],
                'તમારી સાઇટ માટે નવું વર્ઝન ઉપલબ્ધ છે.<br>'
                . 'હાલનું: v' . Helper::e($check['current_version'])
                . ' → નવું: v' . Helper::e((string)$check['remote_version'])
                . '<br>Commit: ' . Helper::e($check['commit_hash'])
                . ' — ' . Helper::e(mb_substr($check['commit_message'], 0, 100))
                . '<br><br>Admin → Update માંથી one-click install કરો.'
            );
            echo "Update alert email મોકલાયો\n";
        }
    } else {
        echo "Latest version પર છો (v{$check['current_version']})\n";
    }
} catch (Throwable $e) {
    echo "Check ફેલ: " . $e->getMessage() . "\n";
    exit(1);
}
