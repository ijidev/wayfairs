<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Flash
{
    private const COOKIE = 'wayfairs_flash';
    private const TTL_SECONDS = 120;

    public static function put(string $type, string $message): void
    {
        self::cookie(self::encode($type, $message, time()), time() + self::TTL_SECONDS);
    }

    public static function consume(): ?array
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        self::cookie('', time() - 3600);
        return self::decode($raw);
    }

    /**
     * Build a signed flash cookie value. Exposed so the signing/verification
     * boundary can be covered by tests.
     */
    public static function encode(string $type, string $message, ?int $time = null): string
    {
        $payload = json_encode([
            'type' => $type,
            'message' => $message,
            'time' => $time ?? time(),
        ], JSON_THROW_ON_ERROR);
        $key = base64_decode((string) Config::instance()->get('app_key', ''), true);
        if (!is_string($key) || strlen($key) !== 32) {
            return '';
        }
        return base64_encode($payload . '.' . hash_hmac('sha256', $payload, $key));
    }

    /**
     * Verify and decode a flash cookie value. The separator is located from the
     * end because the JSON payload itself may contain periods (e.g. a sentence
     * ending in "." previously broke every such flash message).
     */
    public static function decode(string $raw): ?array
    {
        $decoded = base64_decode($raw, true);
        if (!is_string($decoded)) {
            return null;
        }
        $position = strrpos($decoded, '.');
        if ($position === false) {
            return null;
        }
        $payload = substr($decoded, 0, $position);
        $signature = substr($decoded, $position + 1);
        $key = base64_decode((string) Config::instance()->get('app_key', ''), true);
        if (!is_string($key) || !hash_equals(hash_hmac('sha256', $payload, $key), $signature)) {
            return null;
        }
        $value = json_decode($payload, true);
        return is_array($value) && (int) ($value['time'] ?? 0) >= time() - 180 ? $value : null;
    }

    private static function cookie(string $value, int $expires): void
    {
        $config = Config::instance();
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => $config->cookiePath(),
            'secure' => (bool) $config->get('session.secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
