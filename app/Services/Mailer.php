<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use RuntimeException;
use Wayfairs\Core\Config;
use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

/**
 * Minimal SMTP client. No external library: it talks SMTP over a socket and
 * supports STARTTLS and AUTH LOGIN/PLAIN. Sending is best-effort; failures are
 * logged by the caller and never block the customer action.
 */
final class Mailer
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return array{ok:bool,error:?string} */
    public function send(string $to, string $subject, string $body): array
    {
        $config = $this->config();
        if ($config['host'] === '' || $config['from_email'] === '') {
            return ['ok' => false, 'error' => 'SMTP is not configured.'];
        }
        try {
            $this->deliver($config, $to, $subject, $body);
            return ['ok' => true, 'error' => null];
        } catch (RuntimeException $error) {
            return ['ok' => false, 'error' => $error->getMessage()];
        }
    }

    /** @return array{ok:bool,error:?string} */
    public function test(string $to): array
    {
        return $this->send($to, 'Wayfairs SMTP test', "This is a test message from your Wayfairs installation.\n\nIf you received it, SMTP is working.");
    }

    public function isConfigured(): bool
    {
        $config = $this->config();
        return $config['host'] !== '' && $config['from_email'] !== '';
    }

    private function config(): array
    {
        $get = fn (string $key, mixed $default = null) => $this->database->fetchOne('SELECT setting_value FROM site_settings WHERE setting_key = ?', [$key])['setting_value'] ?? $default;
        $password = (string) $get('smtp.password', '');
        if ($password !== '' && str_starts_with($password, 'enc:')) {
            try {
                $decrypted = Security::decrypt(substr($password, 4), (string) Config::instance()->get('app_key', ''));
                $password = is_array($decrypted) ? (string) ($decrypted['v'] ?? '') : (string) $decrypted;
            } catch (RuntimeException) {
                $password = '';
            }
        }
        return [
            'host' => (string) $get('smtp.host', ''),
            'port' => (int) $get('smtp.port', 587),
            'username' => (string) $get('smtp.username', ''),
            'password' => $password,
            'encryption' => (string) $get('smtp.encryption', 'tls'),
            'from_email' => (string) $get('smtp.from_email', ''),
            'from_name' => (string) $get('smtp.from_name', 'Wayfairs'),
        ];
    }

    private function deliver(array $config, string $to, string $subject, string $body): void
    {
        $host = $config['host'];
        $port = $config['port'] > 0 ? $config['port'] : 587;
        $encryption = $config['encryption'];
        $verifyRow = $this->database->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'smtp.verify_peer'");
        $verifyPeer = (string) ($verifyRow['setting_value'] ?? '1');
        $verifyPeer = $verifyPeer !== '0';
        $sslOptions = [
            'verify_peer' => $verifyPeer,
            'verify_peer_name' => $verifyPeer,
            'allow_self_signed' => !$verifyPeer,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ];
        $context = stream_context_create(['ssl' => $sslOptions]);
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            throw new RuntimeException('Could not connect to the SMTP server: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($socket, 15);
        $this->expect($socket, 220);
        $this->command($socket, 'EHLO wayfairs', 250);
        if ($encryption === 'tls') {
            $this->command($socket, 'STARTTLS', 220);
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                $last = error_get_last();
                $detail = is_array($last) ? ($last['message'] ?? '') : '';
                throw new RuntimeException('STARTTLS failed.' . ($detail !== '' ? ' ' . $detail : ''));
            }
            $this->command($socket, 'EHLO wayfairs', 250);
        }
        if ($config['username'] !== '') {
            $this->command($socket, 'AUTH LOGIN', 334);
            $this->command($socket, base64_encode($config['username']), 334);
            $this->command($socket, base64_encode($config['password']), 235);
        }
        $this->command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', 250);
        $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        $this->command($socket, 'DATA', 354);
        $headers = "From: " . $this->formatAddress($config['from_name'], $config['from_email']) . "\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $this->encodeSubject($subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message = $headers . $this->dotStuff($body) . "\r\n.\r\n";
        fwrite($socket, $message);
        $this->expect($socket, 250);
        $this->command($socket, 'QUIT', 221);
        fclose($socket);
    }

    private function command($socket, string $command, int|array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    private function expect($socket, int|array $expected): void
    {
        $expected = is_array($expected) ? $expected : [$expected];
        $response = '';
        while (true) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP connection closed unexpectedly.');
            }
            $response .= $line;
            if (preg_match('/^\d{3} /', $line) === 1) {
                break;
            }
        }
        $code = (int) substr(trim($response), 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }

    private function formatAddress(string $name, string $email): string
    {
        $name = trim($name);
        return $name === '' ? '<' . $email . '>' : '"' . str_replace('"', '', $name) . '" <' . $email . '>';
    }

    private function encodeSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject) !== 1) {
            return $subject;
        }
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private function dotStuff(string $body): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $body);
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '.')) {
                $lines[$index] = '.' . $line;
            }
        }
        return implode("\r\n", $lines);
    }
}