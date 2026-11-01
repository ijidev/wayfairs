<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;

/**
 * Administrator switches for money movement that depends on server capability.
 * A switch is off unless an administrator has turned it on.
 */
final class Features
{
    public const BANK_TRANSFERS = 'feature.bank_transfers';

    public static function enabled(Database $database, string $key): bool
    {
        $row = $database->fetchOne('SELECT setting_value FROM site_settings WHERE setting_key = ?', [$key]);
        return $row !== null && (string) $row['setting_value'] === '1';
    }
}
