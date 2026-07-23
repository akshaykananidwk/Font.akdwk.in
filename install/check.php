<?php
/**
 * Server requirement checker — installer Step 1 માટે.
 * standalone પણ ચાલે: /install/check.php
 */

if (!defined('INSTALLER_RUNNING')) {
    define('BASE_PATH', dirname(__DIR__));
    if (is_file(BASE_PATH . '/install.lock')) {
        http_response_code(403);
        die('Installation is locked. Delete install.lock to re-run (NOT recommended on production).');
    }
}

/**
 * બધી requirement checks ચલાવો.
 *
 * @return array<int, array{name: string, required: bool, ok: bool, note: string}>
 */
function gfc_run_checks(): array
{
    $base = dirname(__DIR__);
    $checks = [];

    $checks[] = [
        'name' => 'PHP >= 8.1',
        'required' => true,
        'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'note' => 'Current: ' . PHP_VERSION,
    ];

    foreach (['pdo_mysql', 'mbstring', 'json', 'curl', 'zip', 'openssl', 'fileinfo'] as $ext) {
        $checks[] = [
            'name' => "Extension: {$ext}",
            'required' => true,
            'ok' => extension_loaded($ext),
            'note' => extension_loaded($ext) ? 'Loaded' : "php-{$ext} install કરો",
        ];
    }

    $checks[] = [
        'name' => 'Extension: intl (optional)',
        'required' => false,
        'ok' => extension_loaded('intl'),
        'note' => extension_loaded('intl') ? 'Loaded — Unicode normalization ઉપલબ્ધ' : 'ભલામણ છે પણ ફરજિયાત નથી',
    ];

    $writables = ['/config', '/storage', '/storage/cache', '/storage/logs', '/storage/backups', '/storage/temp', '/storage/uploads'];
    foreach ($writables as $dir) {
        $path = $base . $dir;
        $ok = is_dir($path) ? is_writable($path) : @mkdir($path, 0755, true);
        $checks[] = [
            'name' => "Writable: {$dir}",
            'required' => true,
            'ok' => (bool)$ok,
            'note' => $ok ? 'OK' : "chmod 755/775 {$dir} કરો",
        ];
    }

    $urlFopen = (bool)ini_get('allow_url_fopen') || extension_loaded('curl');
    $checks[] = [
        'name' => 'allow_url_fopen અથવા cURL',
        'required' => true,
        'ok' => $urlFopen,
        'note' => $urlFopen ? 'OK' : 'GitHub update માટે જરૂરી',
    ];

    return $checks;
}

/** બધી ફરજિયાત checks પાસ છે? */
function gfc_checks_pass(array $checks): bool
{
    foreach ($checks as $c) {
        if ($c['required'] && !$c['ok']) {
            return false;
        }
    }
    return true;
}

// Standalone JSON mode
if (!defined('INSTALLER_RUNNING')) {
    header('Content-Type: application/json; charset=utf-8');
    $checks = gfc_run_checks();
    echo json_encode(['pass' => gfc_checks_pass($checks), 'checks' => $checks], JSON_UNESCAPED_UNICODE);
}
