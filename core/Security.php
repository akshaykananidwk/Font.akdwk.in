<?php
/**
 * CSRF, XSS clean, rate limiting, honeypot.
 */

defined('BASE_PATH') or die('Direct access denied');

class Security
{
    /**
     * CSRF token generate/મેળવો (session આધારિત).
     */
    public static function csrfToken(): string
    {
        Session::start();
        $token = Session::get('_csrf_token');
        if (!$token) {
            $token = Helper::randomToken(32);
            Session::set('_csrf_token', $token);
        }
        return $token;
    }

    /** CSRF hidden input field. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . Helper::e(self::csrfToken()) . '">';
    }

    /**
     * CSRF token verify — POST request માં ફરજિયાત call કરો.
     */
    public static function verifyCsrf(?string $token = null): bool
    {
        $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $stored = Session::get('_csrf_token');
        return is_string($token) && is_string($stored) && $token !== '' && hash_equals($stored, $token);
    }

    /**
     * Input sanitize — control chars કાઢો, valid UTF-8 જ રાખો.
     * (Output escaping Helper::e() થી થાય; આ ફક્ત input normalization છે.)
     */
    public static function cleanInput(string $input): string
    {
        // Invalid UTF-8 sequences હટાવો
        $input = (string)mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        // Null bytes + મોટા ભાગના control chars (tab/newline સિવાય)
        return (string)preg_replace('/[\x00\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
    }

    /**
     * DB-આધારિત rate limiting (sliding window).
     *
     * @param string $identifier IP અથવા api key
     * @param string $endpoint   endpoint નામ
     * @param int    $maxHits    window માં મહત્તમ hits
     * @param int    $windowSec  window લંબાઈ (સેકન્ડ)
     * @return bool true = allowed, false = limit વટાવી
     */
    public static function rateLimit(string $identifier, string $endpoint, int $maxHits, int $windowSec = 60): bool
    {
        try {
            $db = Database::getInstance();
            $table = $db->table('rate_limits');
            $windowStart = date('Y-m-d H:i:s', time() - $windowSec);

            // જૂની windows સાફ કરો (lazy cleanup)
            $db->query("DELETE FROM `{$table}` WHERE window_start < ?", [date('Y-m-d H:i:s', time() - $windowSec * 10)]);

            $row = $db->fetch(
                "SELECT id, hits FROM `{$table}` WHERE identifier = ? AND endpoint = ? AND window_start >= ? LIMIT 1",
                [$identifier, $endpoint, $windowStart]
            );
            if ($row === null) {
                $db->insert('rate_limits', [
                    'identifier'   => $identifier,
                    'endpoint'     => $endpoint,
                    'hits'         => 1,
                    'window_start' => date('Y-m-d H:i:s'),
                ]);
                return true;
            }
            if ((int)$row['hits'] >= $maxHits) {
                return false;
            }
            $db->query("UPDATE `{$table}` SET hits = hits + 1 WHERE id = ?", [$row['id']]);
            return true;
        } catch (Throwable $e) {
            Logger::error('Rate limit check failed: ' . $e->getMessage());
            return true; // DB error પર block ન કરો
        }
    }

    /**
     * Honeypot field verify — ભરાયેલું હોય તો bot.
     */
    public static function checkHoneypot(string $fieldName = 'website_url'): bool
    {
        return empty($_POST[$fieldName]);
    }

    /** Security headers મોકલો (PHP-level; .htaccess માં પણ છે). */
    public static function sendHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
