<?php

declare(strict_types=1);

namespace Wayfairs\Core;

/**
 * Short-lived server-side payment drafts. They live in the session store, never in
 * hidden form fields, and expire so a stale review cannot be confirmed later.
 */
final class Draft
{
    private const MAX_AGE_SECONDS = 1800;

    public static function put(string $name, array $data): void
    {
        $_SESSION['wayfairs_drafts'][$name] = ['data' => $data, 'at' => time()];
    }

    public static function get(string $name): ?array
    {
        $entry = $_SESSION['wayfairs_drafts'][$name] ?? null;
        if (!is_array($entry) || !is_array($entry['data'] ?? null) || time() - (int) ($entry['at'] ?? 0) > self::MAX_AGE_SECONDS) {
            self::forget($name);
            return null;
        }
        return $entry['data'];
    }

    public static function take(string $name): ?array
    {
        $data = self::get($name);
        self::forget($name);
        return $data;
    }

    public static function forget(string $name): void
    {
        unset($_SESSION['wayfairs_drafts'][$name]);
    }
}
