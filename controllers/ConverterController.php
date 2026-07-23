<?php
/**
 * AJAX conversion endpoint + demo limit tracking.
 */

defined('BASE_PATH') or die('Direct access denied');

class ConverterController
{
    /**
     * POST /convert — AJAX conversion.
     * Response: JSON { success, data | error }
     */
    public static function convert(): void
    {
        if (!Security::verifyCsrf()) {
            Helper::jsonResponse(['success' => false, 'error' => 'Invalid CSRF token. Page refresh કરો.'], 403);
        }

        $ip = Helper::clientIp();
        $ratePerMin = (int)App::setting('rate_limit_per_min', DEFAULT_RATE_LIMIT_PER_MIN);
        if (!Security::rateLimit($ip, 'convert', $ratePerMin, 60)) {
            Helper::jsonResponse(['success' => false, 'error' => 'ઘણી બધી requests. એક મિનિટ પછી પ્રયત્ન કરો.'], 429);
        }

        $text = Security::cleanInput((string)($_POST['text'] ?? ''));
        $fontSlug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['font'] ?? ''));
        $direction = (string)($_POST['direction'] ?? 'legacy_to_unicode');
        $preserveHtml = !empty($_POST['preserve_html']);

        if ($text === '') {
            Helper::jsonResponse(['success' => false, 'error' => 'ટેક્સ્ટ ખાલી છે.'], 400);
        }
        if ($fontSlug === '') {
            Helper::jsonResponse(['success' => false, 'error' => 'ફોન્ટ પસંદ કરો.'], 400);
        }

        $charCount = Helper::charCount($text);
        $user = Auth::currentUser();
        $isPaid = Auth::userHasActivePlan($user);

        // ---- Demo limit (paid users માટે નહીં) ----
        if (!$isPaid) {
            $charLimit = (int)App::setting('demo_char_limit', DEFAULT_DEMO_CHAR_LIMIT);
            $attemptLimit = (int)App::setting('demo_attempt_limit', DEFAULT_DEMO_ATTEMPT_LIMIT);

            if ($charCount > $charLimit) {
                Helper::jsonResponse([
                    'success' => false,
                    'error'   => "Demo મર્યાદા: {$charLimit} અક્ષર. તમારો ટેક્સ્ટ {$charCount} અક્ષરનો છે. અમર્યાદિત ઉપયોગ માટે plan લો.",
                    'upgrade' => true,
                ], 403);
            }

            $demo = self::demoUsage($ip);
            if ($demo['attempts_used'] >= $attemptLimit) {
                Helper::jsonResponse([
                    'success' => false,
                    'error'   => "આજની {$attemptLimit} demo પ્રયાસની મર્યાદા પૂરી થઈ. 24 કલાક પછી reset થશે, અથવા plan લો.",
                    'upgrade' => true,
                ], 403);
            }
            self::incrementDemoUsage($ip);
        }

        try {
            $result = FontConverter::convertBySlug($fontSlug, $text, $direction, ['preserve_html' => $preserveHtml]);
        } catch (RuntimeException $e) {
            Helper::jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (InvalidArgumentException $e) {
            Helper::jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        // Log — ટેક્સ્ટ ક્યારેય store થતો નથી, ફક્ત char count
        try {
            Database::getInstance()->insert('conversions_log', [
                'ip_address' => $ip,
                'font_id'    => $result['font_id'],
                'direction'  => $direction,
                'char_count' => $charCount,
                'user_id'    => $user['id'] ?? null,
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            Logger::error('Conversion log failed: ' . $e->getMessage());
        }

        $response = ['success' => true, 'data' => $result];
        if (!$isPaid) {
            $demo = self::demoUsage($ip);
            $attemptLimit = (int)App::setting('demo_attempt_limit', DEFAULT_DEMO_ATTEMPT_LIMIT);
            $response['demo'] = [
                'attempts_used' => $demo['attempts_used'],
                'attempts_left' => max(0, $attemptLimit - $demo['attempts_used']),
                'char_limit'    => (int)App::setting('demo_char_limit', DEFAULT_DEMO_CHAR_LIMIT),
            ];
        }
        Helper::jsonResponse($response);
    }

    /**
     * GET /demo-status — counter UI માટે.
     */
    public static function demoStatus(): void
    {
        $ip = Helper::clientIp();
        $user = Auth::currentUser();
        if (Auth::userHasActivePlan($user)) {
            Helper::jsonResponse(['success' => true, 'unlimited' => true]);
        }
        $demo = self::demoUsage($ip);
        $attemptLimit = (int)App::setting('demo_attempt_limit', DEFAULT_DEMO_ATTEMPT_LIMIT);
        Helper::jsonResponse([
            'success'       => true,
            'unlimited'     => false,
            'char_limit'    => (int)App::setting('demo_char_limit', DEFAULT_DEMO_CHAR_LIMIT),
            'attempts_used' => $demo['attempts_used'],
            'attempts_left' => max(0, $attemptLimit - $demo['attempts_used']),
        ]);
    }

    /**
     * IP નું demo usage મેળવો — 24 કલાકે lazy reset.
     *
     * @return array{attempts_used: int}
     */
    private static function demoUsage(string $ip): array
    {
        try {
            $db = Database::getInstance();
            $table = $db->table('demo_usage');
            $row = $db->fetch("SELECT * FROM `{$table}` WHERE ip_address = ? LIMIT 1", [$ip]);
            if ($row === null) {
                return ['attempts_used' => 0];
            }
            // Lazy 24-hour reset
            if ($row['reset_at'] !== null && strtotime($row['reset_at']) <= time()) {
                $db->update('demo_usage', [
                    'attempts_used' => 0,
                    'reset_at'      => null,
                ], 'ip_address = ?', [$ip]);
                return ['attempts_used' => 0];
            }
            return ['attempts_used' => (int)$row['attempts_used']];
        } catch (Throwable $e) {
            Logger::error('Demo usage read failed: ' . $e->getMessage());
            return ['attempts_used' => 0];
        }
    }

    /** Demo attempt વધારો (upsert). */
    private static function incrementDemoUsage(string $ip): void
    {
        try {
            $db = Database::getInstance();
            $table = $db->table('demo_usage');
            $db->query(
                "INSERT INTO `{$table}` (ip_address, attempts_used, first_attempt_at, last_attempt_at, reset_at)
                 VALUES (?, 1, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
                 ON DUPLICATE KEY UPDATE
                   attempts_used = attempts_used + 1,
                   last_attempt_at = NOW(),
                   reset_at = IF(reset_at IS NULL, DATE_ADD(NOW(), INTERVAL 24 HOUR), reset_at)",
                [$ip]
            );
        } catch (Throwable $e) {
            Logger::error('Demo usage increment failed: ' . $e->getMessage());
        }
    }
}
