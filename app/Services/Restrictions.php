<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

final class Restrictions
{
    public function __construct(private readonly Database $database)
    {
    }

    public function can(string $userId, string $capability): bool
    {
        $row = $this->database->fetchOne(
            "SELECT COUNT(*) AS total FROM capability_restrictions WHERE user_id = ? AND capability = ? AND released_at IS NULL AND starts_at <= ? AND (expires_at IS NULL OR expires_at > ?)",
            [$userId, $capability, gmdate('Y-m-d H:i:s.u'), gmdate('Y-m-d H:i:s.u')]
        );
        return (int) ($row['total'] ?? 0) === 0;
    }

    public function active(string $userId): array
    {
        return $this->database->fetchAll(
            "SELECT * FROM capability_restrictions WHERE user_id = ? AND released_at IS NULL AND starts_at <= ? AND (expires_at IS NULL OR expires_at > ?) ORDER BY created_at DESC",
            [$userId, gmdate('Y-m-d H:i:s.u'), gmdate('Y-m-d H:i:s.u')]
        );
    }

    public function apply(string $userId, string $capability, string $reasonCode, string $reason, string $createdBy, ?string $expiresAt = null): void
    {
        if (\Wayfairs\Core\Context::isImpersonating()) {
            throw new \RuntimeException('Capability changes are disabled during impersonation.');
        }
        $this->database->execute(
            'INSERT INTO capability_restrictions (id, user_id, capability, reason_code, reason, starts_at, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [Security::uuid(), $userId, $capability, $reasonCode, $reason, gmdate('Y-m-d H:i:s.u'), $expiresAt, $createdBy]
        );
    }

    public function release(string $restrictionId, string $reason, string $releasedBy): void
    {
        if (\Wayfairs\Core\Context::isImpersonating()) {
            throw new \RuntimeException('Capability changes are disabled during impersonation.');
        }
        $this->database->execute(
            'UPDATE capability_restrictions SET released_at = ?, released_by = ?, release_reason = ? WHERE id = ? AND released_at IS NULL',
            [gmdate('Y-m-d H:i:s.u'), $releasedBy, $reason, $restrictionId]
        );
    }
}
