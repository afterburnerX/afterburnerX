<?php

declare(strict_types=1);

namespace App;

/**
 * Sends plain-text email either via a minimal hand-rolled SMTP client
 * (if SMTP_HOST is set) or PHP's native mail() as a fallback. No
 * PHPMailer/Symfony Mailer dependency, in keeping with the rest of the
 * app - this only needs to speak enough SMTP to authenticate and hand
 * off one message, not be a general-purpose mail library.
 */
class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $host = env('SMTP_HOST');

        if ($host) {
            return self::sendViaSmtp($host, $to, $subject, $body);
        }

        return self::sendViaMailFunction($to, $subject, $body);
    }

    /**
     * RFC 5321 dot-stuffing: a line consisting of (or starting with) a
     * single dot would otherwise be read by the SMTP server as the
     * end-of-DATA marker, so leading dots on any line get doubled.
     */
    public static function dotStuff(string $body): string
    {
        return (string) preg_replace('/^\./m', '..', $body);
    }

    private static function truthy(?string $value): bool
    {
        return $value !== null && !in_array(strtolower($value), ['', '0', 'false', 'no'], true);
    }

    private static function fromAddress(): string
    {
        $configured = env('MAIL_FROM');
        if ($configured) {
            return $configured;
        }

        $host = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);

        return 'no-reply@' . ($host ?: 'localhost');
    }

    private static function sendViaMailFunction(string $to, string $subject, string $body): bool
    {
        $from = self::fromAddress();
        $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        $ok = @mail($to, $subject, $body, $headers);
        if (!$ok) {
            error_log("Mailer: mail() failed sending to {$to}");
        }

        return $ok;
    }

    private static function sendViaSmtp(string $host, string $to, string $subject, string $body): bool
    {
        $port = (int) env('SMTP_PORT', '587');
        $user = env('SMTP_USER');
        $pass = env('SMTP_PASS');
        $encryption = strtolower((string) env('SMTP_ENCRYPTION', 'tls')); // tls|ssl|none
        $from = self::fromAddress();
        $timeout = 15;

        // Verifying the server's TLS certificate is the secure default; the
        // escape hatch exists for relays behind a private/internal CA, not
        // to be routinely disabled.
        $verifyPeer = self::truthy(env('SMTP_VERIFY_PEER', 'true'));
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'allow_self_signed' => !$verifyPeer,
            ],
        ]);

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            "{$transport}{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$socket) {
            error_log("Mailer: SMTP connect to {$host}:{$port} failed: {$errstr} ({$errno})");
            return false;
        }
        stream_set_timeout($socket, $timeout);

        $read = static function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                // A space (not '-') in the 4th column marks the last line of a multi-line SMTP reply.
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };

        $write = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };

        $expectCode = static function (string $response, array $codes): bool {
            return in_array((int) substr($response, 0, 3), $codes, true);
        };

        $fail = static function (string $context, string $response) use ($socket): bool {
            error_log("Mailer: SMTP {$context} failed: " . trim($response));
            fclose($socket);
            return false;
        };

        $banner = $read();
        if (!$expectCode($banner, [220])) {
            return $fail('connect', $banner);
        }

        $localHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

        $write("EHLO {$localHost}");
        $response = $read();
        if (!$expectCode($response, [250])) {
            return $fail('EHLO', $response);
        }

        if ($encryption === 'tls') {
            $write('STARTTLS');
            $response = $read();
            if (!$expectCode($response, [220])) {
                return $fail('STARTTLS', $response);
            }
            // Silenced because a failed handshake (e.g. an untrusted
            // certificate) raises a warning we already report ourselves
            // via $fail() with the underlying OpenSSL reason.
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $reason = error_get_last()['message'] ?? 'TLS handshake failed';
                return $fail('TLS negotiation', $reason);
            }
            $write("EHLO {$localHost}");
            $response = $read();
            if (!$expectCode($response, [250])) {
                return $fail('EHLO after STARTTLS', $response);
            }
        }

        if ($user && $pass) {
            $write('AUTH LOGIN');
            $response = $read();
            if (!$expectCode($response, [334])) {
                return $fail('AUTH LOGIN', $response);
            }

            $write(base64_encode($user));
            $response = $read();
            if (!$expectCode($response, [334])) {
                return $fail('SMTP username', $response);
            }

            $write(base64_encode($pass));
            $response = $read();
            if (!$expectCode($response, [235])) {
                return $fail('SMTP authentication', $response);
            }
        }

        $write("MAIL FROM:<{$from}>");
        $response = $read();
        if (!$expectCode($response, [250])) {
            return $fail('MAIL FROM', $response);
        }

        $write("RCPT TO:<{$to}>");
        $response = $read();
        if (!$expectCode($response, [250, 251])) {
            return $fail('RCPT TO', $response);
        }

        $write('DATA');
        $response = $read();
        if (!$expectCode($response, [354])) {
            return $fail('DATA', $response);
        }

        $headers = implode("\r\n", [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Date: ' . date('r'),
        ]);
        $write($headers . "\r\n\r\n" . self::dotStuff($body) . "\r\n.");
        $response = $read();
        $sent = $expectCode($response, [250]);
        if (!$sent) {
            error_log('Mailer: message not accepted: ' . trim($response));
        }

        $write('QUIT');
        fclose($socket);

        return $sent;
    }
}
