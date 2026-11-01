<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use RuntimeException;
use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

final class OtpService
{
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly Database $database,
        private readonly Mailer $mailer,
        private readonly EmailTemplates $templates,
    ) {
    }

    public function issue(string $userId, string $purpose, string $ip): string
    {
        $user = $this->database->fetchOne('SELECT first_name, email FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            throw new RuntimeException('Account not found.');
        }
        $this->database->execute("UPDATE otp_codes SET consumed_at = ? WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL", [gmdate('Y-m-d H:i:s.u'), $userId, $purpose]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $id = Security::uuid();
        $now = gmdate('Y-m-d H:i:s.u');
        $expires = gmdate('Y-m-d H:i:s.u', time() + self::TTL_MINUTES * 60);
        $this->database->execute(
            'INSERT INTO otp_codes (id, user_id, purpose, code_hash, expires_at, created_at, ip) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $userId, $purpose, Security::hashToken($code), $expires, $now, $ip]
        );
        $rendered = $this->templates->render('otp', [
            'first_name' => (string) ($user['first_name'] ?? ''),
            'code' => $code,
            'minutes' => self::TTL_MINUTES,
            'site_url' => '',
        ]);
        $subject = $rendered['subject'] ?? 'Your Wayfairs verification code';
        $body = $rendered['body'] ?? "Your verification code is {$code}. It expires in " . self::TTL_MINUTES . " minutes.";
        $result = $this->mailer->send((string) $user['email'], $subject, $body);
        if (!$result['ok']) {
            error_log('[wayfairs] otp email failed: ' . ($result['error'] ?? 'unknown'));
        }
        return $id;
    }

    public function verify(string $userId, string $purpose, string $code): bool
    {
        $row = $this->database->fetchOne(
            "SELECT * FROM otp_codes WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > ? ORDER BY created_at DESC LIMIT 1",
            [$userId, $purpose, gmdate('Y-m-d H:i:s.u')]
        );
        if (!$row) {
            return false;
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }
        $this->database->execute('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [(string) $row['id']]);
        if (!hash_equals((string) $row['code_hash'], Security::hashToken(trim($code)))) {
            return false;
        }
        $this->database->execute('UPDATE otp_codes SET consumed_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s.u'), (string) $row['id']]);
        return true;
    }

    public function isEnabled(): bool
    {
        $row = $this->database->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'feature.otp'");
        return $row !== null && (string) $row['setting_value'] === '1';
    }
}