<?php

declare(strict_types=1);

namespace Wayfairs\Core;

final class Context
{
    private static ?array $user = null;
    private static ?string $requestId = null;
    private static ?string $sessionToken = null;

    public static function setUser(?array $user): void
    {
        self::$user = $user;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function requestId(): string
    {
        return self::$requestId ?? Security::uuid();
    }

    public static function setRequestId(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public static function sessionToken(): ?string
    {
        return self::$sessionToken;
    }

    public static function setSessionToken(?string $token): void
    {
        self::$sessionToken = $token;
    }

    public static function adminId(): ?string
    {
        return self::$user['original_admin_id'] ?? null;
    }

    public static function isImpersonating(): bool
    {
        return self::$user !== null && !empty(self::$user['original_admin_id']);
    }

    public static function canMutateSensitive(): bool
    {
        return !self::isImpersonating();
    }
}
