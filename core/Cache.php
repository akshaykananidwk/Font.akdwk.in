<?php
/**
 * File-based cache — mapping tables + settings માટે.
 * var_export/include વાપરે છે જેથી OPcache નો ફાયદો મળે.
 */

defined('BASE_PATH') or die('Direct access denied');

class Cache
{
    /**
     * Cache માંથી value મેળવો.
     *
     * @param int $maxAge સેકન્ડમાં TTL (0 = કાયમ valid)
     */
    public static function get(string $key, int $maxAge = 0): mixed
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }
        if ($maxAge > 0 && (time() - filemtime($file)) > $maxAge) {
            @unlink($file);
            return null;
        }
        $data = @include $file;
        return $data === false ? null : $data;
    }

    /**
     * Cache માં value મૂકો (PHP array/scalar — var_export થાય).
     */
    public static function set(string $key, mixed $value): bool
    {
        $file = self::path($key);
        $content = "<?php\nreturn " . var_export($value, true) . ";\n";
        $ok = @file_put_contents($file, $content, LOCK_EX) !== false;
        if ($ok && function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        return $ok;
    }

    /** એક key delete કરો. */
    public static function delete(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** આખું cache સાફ કરો. */
    public static function clear(): int
    {
        $count = 0;
        foreach (glob(CACHE_PATH . '/*.cache.php') ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return $count;
    }

    /** Key → ફાઇલ path. */
    private static function path(string $key): string
    {
        return CACHE_PATH . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.cache.php';
    }
}
