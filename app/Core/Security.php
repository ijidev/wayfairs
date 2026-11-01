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

    private const GCM_PREFIX = 'gcm1:';
    private const GCM_CIPHER = 'aes-256-gcm';

    /**
     * Bank-detail encryption uses libsodium when present. Servers without it use
     * OpenSSL AES-256-GCM with the same 32-byte application key.
     *
     * @return 'sodium'|'openssl'|null
     */
    public static function encryptionBackend(): ?string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            return 'sodium';
        }
        if (function_exists('openssl_encrypt') && in_array(self::GCM_CIPHER, openssl_get_cipher_methods(true), true)) {
            return 'openssl';
        }
        return null;
    }

    /**
     * @return list<string> PHP extensions the bank-detail encryption needs but
     *                      this server does not provide.
     */
    public static function missingServerExtensions(): array
    {
        $missing = [];
        if (self::encryptionBackend() === null) {
            $missing[] = 'sodium or openssl';
        }
        if (!function_exists('mb_substr')) {
            $missing[] = 'mbstring';
        }
        return $missing;
    }

    public static function assertServerReady(): void
    {
        $missing = self::missingServerExtensions();
        if ($missing !== []) {
            throw new RuntimeException('This server is missing the PHP ' . implode(', ', $missing) . ' extension, so bank details cannot be stored securely. Ask your host to enable it.');
        }
    }

    public static function encrypt(array $value, string $encodedKey, ?string $backend = null): string
    {
        $key = base64_decode($encodedKey, true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('Application encryption key is invalid.');
        }
        $backend ??= self::encryptionBackend();
        $plain = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($backend === 'sodium' && function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        if ($backend === 'openssl' && function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, self::GCM_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if (!is_string($cipher) || strlen($tag) !== 16) {
                throw new RuntimeException('Bank details could not be encrypted. Try again.');
            }
            return self::GCM_PREFIX . base64_encode($iv . $tag . $cipher);
        }
        throw new RuntimeException('This server cannot encrypt bank details. Ask your host to enable the sodium or openssl extension.');
    }

    public static function decrypt(string $ciphertext, string $encodedKey): array
    {
        $key = base64_decode($encodedKey, true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('Encrypted data is invalid.');
        }
        if (str_starts_with($ciphertext, self::GCM_PREFIX)) {
            $packed = base64_decode(substr($ciphertext, strlen(self::GCM_PREFIX)), true);
            if (!is_string($packed) || strlen($packed) < 28 || !function_exists('openssl_decrypt')) {
                throw new RuntimeException('Encrypted data is invalid.');
            }
            $iv = substr($packed, 0, 12);
            $tag = substr($packed, 12, 16);
            $plain = openssl_decrypt(substr($packed, 28), self::GCM_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        } else {
            if (!function_exists('sodium_crypto_secretbox_open')) {
                throw new RuntimeException('This record needs the PHP sodium extension to be opened. Ask your host to enable it.');
            }
            $packed = base64_decode($ciphertext, true);
            if (!is_string($packed)) {
                throw new RuntimeException('Encrypted data is invalid.');
            }
            $nonce = substr($packed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($packed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        }
        if (!is_string($plain)) {
            throw new RuntimeException('Encrypted data could not be opened.');
        }
        $decoded = json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
