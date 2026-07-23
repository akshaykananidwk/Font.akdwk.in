<?php
/**
 * File-based logger — error + activity logs.
 * Log rotation: દરેક મહિને નવી ફાઇલ.
 */

defined('BASE_PATH') or die('Direct access denied');

class Logger
{
    /**
     * Log line લખો.
     *
     * @param string $level   ERROR / INFO / WARN / DEBUG
     * @param string $message log message
     * @param string $channel ફાઇલ prefix (app, update, api, admin)
     */
    public static function log(string $level, string $message, string $channel = 'app'): void
    {
        $file = LOGS_PATH . '/' . $channel . '_' . date('Y-m') . '.log';
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        // @: logs folder writable ન હોય તો પણ site ક્રેશ ન થાય
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, string $channel = 'app'): void
    {
        self::log('ERROR', $message, $channel);
    }

    public static function info(string $message, string $channel = 'app'): void
    {
        self::log('INFO', $message, $channel);
    }

    public static function warn(string $message, string $channel = 'app'): void
    {
        self::log('WARN', $message, $channel);
    }

    /**
     * જૂની log ફાઇલો સાફ કરો (cron માટે).
     *
     * @param int $keepMonths કેટલા મહિના રાખવા
     */
    public static function cleanup(int $keepMonths = 6): int
    {
        $deleted = 0;
        $cutoff = strtotime("-{$keepMonths} months");
        foreach (glob(LOGS_PATH . '/*.log') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }
}
