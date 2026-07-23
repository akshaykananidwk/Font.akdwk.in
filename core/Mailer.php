<?php
/**
 * Email — PHP mail() અથવા SMTP (settings પ્રમાણે).
 * Self-contained SMTP client — કોઈ Composer dependency નહીં.
 */

defined('BASE_PATH') or die('Direct access denied');

class Mailer
{
    /**
     * Email મોકલો. Settings માં smtp_enabled હોય તો SMTP, નહીં તો mail().
     *
     * @return bool મોકલાયું કે નહીં
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $fromEmail = App::setting('email_from', App::config()['site']['admin_email'] ?? 'noreply@localhost');
        $fromName = App::setting('site_name', APP_NAME);

        try {
            if (App::setting('smtp_enabled', '0') === '1') {
                return self::sendSmtp($to, $subject, $htmlBody, $fromEmail, $fromName);
            }
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <{$fromEmail}>",
            ];
            return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
        } catch (Throwable $e) {
            Logger::error('Mail send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Minimal SMTP client (AUTH LOGIN, STARTTLS/SSL support).
     */
    private static function sendSmtp(string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName): bool
    {
        $host = App::setting('smtp_host', '');
        $port = (int)App::setting('smtp_port', '587');
        $user = App::setting('smtp_user', '');
        $pass = App::setting('smtp_pass', '');
        $encryption = App::setting('smtp_encryption', 'tls'); // tls / ssl / none

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $fp = @stream_socket_client("{$remote}:{$port}", $errno, $errstr, 15);
        if (!$fp) {
            Logger::error("SMTP connect failed: {$errstr}");
            return false;
        }

        $read = function () use ($fp): string {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $command) use ($fp, $read): string {
            fwrite($fp, $command . "\r\n");
            return $read();
        };

        try {
            $read();
            $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            if ($encryption === 'tls') {
                $resp = $cmd('STARTTLS');
                if (!str_starts_with($resp, '220')) {
                    throw new RuntimeException('STARTTLS failed');
                }
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            }
            if ($user !== '') {
                $cmd('AUTH LOGIN');
                $cmd(base64_encode($user));
                $resp = $cmd(base64_encode($pass));
                if (!str_starts_with($resp, '235')) {
                    throw new RuntimeException('SMTP auth failed');
                }
            }
            $cmd("MAIL FROM:<{$fromEmail}>");
            $cmd("RCPT TO:<{$to}>");
            $cmd('DATA');
            $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n"
                . "To: <{$to}>\r\n"
                . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n";
            $resp = $cmd($headers . "\r\n" . $htmlBody . "\r\n.");
            $cmd('QUIT');
            fclose($fp);
            return str_starts_with($resp, '250');
        } catch (Throwable $e) {
            Logger::error('SMTP error: ' . $e->getMessage());
            @fclose($fp);
            return false;
        }
    }

    /**
     * Template આધારિત email — સાદું branded HTML wrapper.
     */
    public static function sendTemplate(string $to, string $subject, string $bodyHtml): bool
    {
        $siteName = Helper::e((string)App::setting('site_name', APP_NAME));
        $html = <<<HTML
<!DOCTYPE html>
<html><body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="background:#1a5276;padding:20px 30px;color:#fff;font-size:20px;font-weight:bold;">{$siteName}</td></tr>
<tr><td style="padding:30px;color:#333;font-size:15px;line-height:1.6;">{$bodyHtml}</td></tr>
<tr><td style="padding:16px 30px;background:#f4f4f7;color:#888;font-size:12px;">© {$siteName}</td></tr>
</table></td></tr></table></body></html>
HTML;
        return self::send($to, $subject, $html);
    }
}
