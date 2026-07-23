<?php
/**
 * REST API v1 — convert / fonts / usage / batch endpoints.
 * .htaccess આ ફાઇલ પર /api/v1/{convert|fonts|usage|batch} route કરે છે.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/config/constants.php';
require CORE_PATH . '/App.php';

App::bootstrap();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** API error response + log. */
function apiError(int $code, string $message, array $extra = []): never
{
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!App::isInstalled()) {
    apiError(503, 'Service not installed');
}
if (App::setting('api_enabled', '1') !== '1') {
    apiError(503, 'API is disabled');
}

$startTime = microtime(true);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$endpoint = 'convert';
foreach (['fonts', 'usage', 'batch', 'convert'] as $ep) {
    if (str_contains($uri, '/api/v1/' . $ep)) {
        $endpoint = $ep;
        break;
    }
}

$db = Database::getInstance();
$ip = Helper::clientIp();

// ---- API key authentication ----
$apiKeyRaw = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($apiKeyRaw === '') {
    apiError(401, 'Missing X-API-Key header');
}
$keysTable = $db->table('api_keys');
$keyRow = $db->fetch("SELECT * FROM `{$keysTable}` WHERE api_key = ? LIMIT 1", [$apiKeyRaw]);
if ($keyRow === null) {
    apiError(401, 'Invalid API key');
}
if ($keyRow['status'] !== 'active') {
    apiError(403, 'API key is ' . $keyRow['status']);
}
if ($keyRow['expires_at'] !== null && strtotime($keyRow['expires_at']) < time()) {
    apiError(403, 'API key expired — renew your plan');
}
// Allowed IPs check
if (!empty($keyRow['allowed_ips'])) {
    $allowed = json_decode((string)$keyRow['allowed_ips'], true);
    if (is_array($allowed) && $allowed !== [] && !in_array($ip, $allowed, true)) {
        apiError(403, 'IP not allowed for this key');
    }
}

// ---- Daily limit (calls_date સાથે daily reset) ----
$today = date('Y-m-d');
if ($keyRow['calls_date'] !== $today) {
    $db->update('api_keys', ['calls_today' => 0, 'calls_date' => $today], 'id = ?', [$keyRow['id']]);
    $keyRow['calls_today'] = 0;
}
$dailyLimit = (int)$keyRow['daily_limit'];
if ($dailyLimit > 0 && (int)$keyRow['calls_today'] >= $dailyLimit) {
    header('Retry-After: ' . (strtotime('tomorrow') - time()));
    apiError(429, 'Daily API limit reached', [
        'usage' => ['calls_today' => (int)$keyRow['calls_today'], 'daily_limit' => $dailyLimit, 'remaining' => 0],
    ]);
}

/** API call log + counter update. */
function apiLog(Database $db, array $keyRow, string $endpoint, int $chars, int $code, float $startTime): void
{
    try {
        $db->query(
            "UPDATE `" . $db->table('api_keys') . "`
             SET calls_today = calls_today + 1, total_calls = total_calls + 1, last_used_at = NOW()
             WHERE id = ?",
            [$keyRow['id']]
        );
        $db->insert('api_logs', [
            'api_key_id'       => $keyRow['id'],
            'endpoint'         => $endpoint,
            'method'           => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            'ip_address'       => Helper::clientIp(),
            'request_chars'    => $chars,
            'response_code'    => $code,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);
    } catch (Throwable $e) {
        Logger::error('API log failed: ' . $e->getMessage(), 'api');
    }
}

/** Usage array for responses. */
function usageArray(Database $db, array $keyRow): array
{
    $row = $db->fetch("SELECT calls_today, daily_limit FROM `" . $db->table('api_keys') . "` WHERE id = ?", [$keyRow['id']]);
    $callsToday = (int)($row['calls_today'] ?? 0);
    $limit = (int)($row['daily_limit'] ?? 0);
    return [
        'calls_today' => $callsToday,
        'daily_limit' => $limit,
        'remaining'   => $limit > 0 ? max(0, $limit - $callsToday) : null,
    ];
}

// ================= GET endpoints =================
if ($endpoint === 'fonts') {
    $fonts = $db->fetchAll(
        "SELECT f.font_name, f.font_slug, l.code AS language, f.is_popular
         FROM `" . $db->table('fonts') . "` f
         JOIN `" . $db->table('languages') . "` l ON l.id = f.language_id
         WHERE f.is_active = 1 ORDER BY l.id, f.sort_order"
    );
    apiLog($db, $keyRow, 'fonts', 0, 200, $startTime);
    echo json_encode(['success' => true, 'data' => $fonts, 'usage' => usageArray($db, $keyRow)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($endpoint === 'usage') {
    apiLog($db, $keyRow, 'usage', 0, 200, $startTime);
    echo json_encode(['success' => true, 'usage' => usageArray($db, $keyRow)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================= POST endpoints =================
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiError(400, 'Use POST for this endpoint');
}
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    apiError(400, 'Invalid JSON body');
}

if ($endpoint === 'batch') {
    // Batch — plan માં api_access + batch feature જોઈએ (Business)
    $plansTable = $db->table('plans');
    $usersTable = $db->table('users');
    $user = $db->fetch("SELECT * FROM `{$usersTable}` WHERE id = ?", [$keyRow['user_id']]);
    $plan = $user && $user['plan_id'] ? $db->fetch("SELECT * FROM `{$plansTable}` WHERE id = ?", [$user['plan_id']]) : null;
    if (!$plan || (int)$plan['api_daily_limit'] < 10000) {
        apiError(403, 'Batch API requires Business plan');
    }
    $texts = $body['texts'] ?? null;
    $font = preg_replace('/[^a-z0-9\-]/', '', (string)($body['font'] ?? ''));
    $direction = (string)($body['direction'] ?? 'legacy_to_unicode');
    if (!is_array($texts) || $texts === [] || count($texts) > 100) {
        apiError(400, 'texts must be an array of 1-100 strings');
    }
    $results = [];
    $totalChars = 0;
    try {
        foreach ($texts as $t) {
            $t = (string)$t;
            $totalChars += Helper::charCount($t);
            $r = FontConverter::convertBySlug($font, $t, $direction);
            $results[] = $r['converted_text'];
        }
    } catch (Throwable $e) {
        apiLog($db, $keyRow, 'batch', $totalChars, 400, $startTime);
        apiError(400, $e->getMessage());
    }
    apiLog($db, $keyRow, 'batch', $totalChars, 200, $startTime);
    echo json_encode([
        'success' => true,
        'data'    => ['converted_texts' => $results, 'count' => count($results), 'char_count' => $totalChars],
        'usage'   => usageArray($db, $keyRow),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- convert ----
$text = (string)($body['text'] ?? '');
$font = preg_replace('/[^a-z0-9\-]/', '', (string)($body['font'] ?? ''));
$direction = (string)($body['direction'] ?? 'legacy_to_unicode');

if ($text === '') {
    apiError(400, 'text is required');
}
if ($font === '') {
    apiError(400, 'font is required');
}
if (!in_array($direction, ['legacy_to_unicode', 'unicode_to_legacy'], true)) {
    apiError(400, 'direction must be legacy_to_unicode or unicode_to_legacy');
}

try {
    $result = FontConverter::convertBySlug($font, $text, $direction, [
        'preserve_html' => !empty($body['preserve_html']),
    ]);
} catch (Throwable $e) {
    apiLog($db, $keyRow, 'convert', Helper::charCount($text), 400, $startTime);
    apiError(400, $e->getMessage());
}

// Conversion log (text ક્યારેય store થતો નથી)
try {
    $db->insert('conversions_log', [
        'ip_address' => $ip,
        'font_id'    => $result['font_id'],
        'direction'  => $direction,
        'char_count' => $result['char_count'],
        'user_id'    => $keyRow['user_id'],
        'api_key_id' => $keyRow['id'],
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'api'), 0, 255),
    ]);
} catch (Throwable $e) {
    Logger::error('API conversion log failed: ' . $e->getMessage(), 'api');
}

apiLog($db, $keyRow, 'convert', $result['char_count'], 200, $startTime);
echo json_encode([
    'success' => true,
    'data'    => [
        'converted_text'     => $result['converted_text'],
        'char_count'         => $result['char_count'],
        'font'               => $result['font'],
        'direction'          => $direction,
        'processing_time_ms' => $result['processing_time_ms'],
    ],
    'usage'   => usageArray($db, $keyRow),
], JSON_UNESCAPED_UNICODE);
