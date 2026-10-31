<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

final class Audit
{
    public function __construct(private readonly Database $database)
    {
    }

    public function record(string $action, string $resourceType, ?string $resourceId = null, ?string $reason = null, array $metadata = []): void
    {
        $user = \Wayfairs\Core\Context::user();
        $this->database->execute(
            'INSERT INTO audit_events (id, actor_user_id, effective_user_id, action, resource_type, resource_id, request_id, reason, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Security::uuid(),
                $user['id'] ?? null,
                \Wayfairs\Core\Context::adminId(),
                $action,
                $resourceType,
                $resourceId,
                \Wayfairs\Core\Context::requestId(),
                $reason,
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]
        );
    }
}
