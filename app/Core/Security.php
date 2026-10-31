<?php

declare(strict_types=1);

namespace Wayfairs\Core;

use RuntimeException;

final class Security
{
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function reference(string $prefix): string
    {
        return strtoupper($prefix) . '-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function passwordHash(string $password): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algorithm);
        if (!is_string($hash)) {
            throw new RuntimeException('Could not hash password.');
        }
        return $hash;
    }

    public static function encrypt(array $value, string $encodedKey): string
    {
        $key = base64_decode($encodedKey, true);
        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Application encryption key is invalid.');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public static function decrypt(string $ciphertext, string $encodedKey): array
    {
        $key = base64_decode($encodedKey, true);
        $packed = base64_decode($ciphertext, true);
        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || !is_string($packed)) {
            throw new RuntimeException('Encrypted data is invalid.');
        }
        $nonce = substr($packed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($packed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if (!is_string($plain)) {
            throw new RuntimeException('Encrypted data could not be opened.');
        }
        $decoded = json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
