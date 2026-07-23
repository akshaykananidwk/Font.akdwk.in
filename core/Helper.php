<?php
/**
 * Common helper functions.
 */

defined('BASE_PATH') or die('Direct access denied');

class Helper
{
    /**
     * XSS-safe output. બધા echo પર આ વાપરો.
     */
    public static function e(?string $str): string
    {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** Client IP મેળવો (proxy headers ને trust ન કરો — ફક્ત REMOTE_ADDR). */
    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** URL-safe slug બનાવો. */
    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\-]+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }

    /** Random hex token. */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** JSON response મોકલીને exit કરો. */
    public static function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Redirect કરીને exit. */
    public static function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    /** Bytes ને human readable બનાવો. */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = (float)$bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return round($val, 2) . ' ' . $units[$i];
    }

    /**
     * AES-256-GCM encryption (GitHub token વગેરે માટે).
     */
    public static function encrypt(string $plaintext, ?string $key = null): string
    {
        $key = $key ?? (App::config()['security']['encryption_key'] ?? '');
        $keyBin = hash('sha256', $key, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * AES-256-GCM decryption. ફેલ થાય તો ખાલી string.
     */
    public static function decrypt(string $encoded, ?string $key = null): string
    {
        $key = $key ?? (App::config()['security']['encryption_key'] ?? '');
        $keyBin = hash('sha256', $key, true);
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    /** ડિરેક્ટરી recursive delete (path BASE_PATH ની અંદર જ હોવો જોઈએ). */
    public static function rrmdir(string $dir): bool
    {
        $real = realpath($dir);
        if ($real === false || !str_starts_with($real, (string)realpath(BASE_PATH))) {
            return false; // safety: પ્રોજેક્ટ બહાર delete નહીં
        }
        if (!is_dir($real)) {
            return @unlink($real);
        }
        foreach (scandir($real) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::rrmdir($real . '/' . $item);
        }
        return @rmdir($real);
    }

    /** Multibyte-safe character count. */
    public static function charCount(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }
}
