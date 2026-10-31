<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;

final class SiteSettings
{
    private array $cache = [];

    public function __construct(private readonly Database $database)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $row = $this->database->fetchOne('SELECT setting_value, is_public FROM site_settings WHERE setting_key = ?', [$key]);
        $value = $row ? ($row['setting_value'] ?? $default) : $default;
        $this->cache[$key] = $value;
        return $value;
    }

    public function publicSettings(): array
    {
        $rows = $this->database->fetchAll('SELECT setting_key, setting_value FROM site_settings WHERE is_public = 1');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    public function update(string $key, string $value, string $updatedBy, bool $public = true): void
    {
        $this->database->transaction(function (Database $database) use ($key, $value, $updatedBy, $public): void {
            $exists = $database->fetchOne('SELECT setting_key FROM site_settings WHERE setting_key = ?', [$key]);
            if ($exists) {
                $database->execute('UPDATE site_settings SET setting_value = ?, is_public = ?, updated_by = ?, updated_at = ? WHERE setting_key = ?', [
                    $value,
                    $public ? 1 : 0,
                    $updatedBy,
                    gmdate('Y-m-d H:i:s.u'),
                    $key,
                ]);
            } else {
                $database->execute('INSERT INTO site_settings (setting_key, setting_value, is_public, updated_by) VALUES (?, ?, ?, ?)', [
                    $key,
                    $value,
                    $public ? 1 : 0,
                    $updatedBy,
                ]);
            }
            $database->execute(
                'INSERT INTO audit_events (id, actor_user_id, effective_user_id, action, resource_type, resource_id, request_id, reason, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    \Wayfairs\Core\Security::uuid(),
                    $updatedBy,
                    null,
                    'site_setting.updated',
                    'site_setting',
                    $key,
                    \Wayfairs\Core\Context::requestId(),
                    null,
                    json_encode(['value' => $value], JSON_THROW_ON_ERROR),
                ]
            );
        });
        $this->cache[$key] = $value;
    }

    public function updateMany(array $values, string $updatedBy, bool $public = true): void
    {
        foreach ($values as $key => $value) {
            $this->update((string) $key, (string) $value, $updatedBy, $public);
        }
    }
}
