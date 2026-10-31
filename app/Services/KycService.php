<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

final class KycService
{
    public function __construct(private readonly Database $database)
    {
    }

    public function profile(string $userId): array
    {
        $profile = $this->database->fetchOne('SELECT * FROM kyc_profiles WHERE user_id = ?', [$userId]);
        if (!$profile) {
            $this->database->execute('INSERT INTO kyc_profiles (user_id) VALUES (?)', [$userId]);
            $profile = $this->database->fetchOne('SELECT * FROM kyc_profiles WHERE user_id = ?', [$userId]);
        }
        return $profile ?: [];
    }

    public function submit(string $userId, array $input): void
    {
        if (\Wayfairs\Core\Context::isImpersonating()) {
            throw new \RuntimeException('KYC submission is disabled during impersonation.');
        }
        $required = ['date_of_birth', 'country_code', 'address_line1', 'city', 'region', 'postal_code', 'id_type', 'id_last4'];
        foreach ($required as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                throw new \RuntimeException('Complete every required KYC field.');
            }
        }
        $last4 = preg_replace('/[^0-9A-Za-z]/', '', (string) $input['id_last4']);
        if (strlen((string) $last4) < 4) {
            throw new \RuntimeException('Provide at least the last four characters of the identity document.');
        }
        $this->database->execute(
            'UPDATE kyc_profiles SET status = ?, date_of_birth = ?, country_code = ?, address_line1 = ?, address_line2 = ?, city = ?, region = ?, postal_code = ?, id_type = ?, id_last4 = ?, submission_reference = ?, submitted_at = ?, updated_at = ? WHERE user_id = ?',
            [
                'pending',
                (string) $input['date_of_birth'],
                strtoupper(substr((string) $input['country_code'], 0, 2)),
                (string) $input['address_line1'],
                trim((string) ($input['address_line2'] ?? '')) ?: null,
                (string) $input['city'],
                (string) $input['region'],
                (string) $input['postal_code'],
                (string) $input['id_type'],
                substr((string) $last4, -4),
                Security::reference('KYC'),
                gmdate('Y-m-d H:i:s.u'),
                gmdate('Y-m-d H:i:s.u'),
                $userId,
            ]
        );
    }

    public function decide(string $userId, string $status, string $reason, string $adminId): void
    {
        if (!in_array($status, ['verified', 'rejected', 'needs_resubmission', 'expired'], true)) {
            throw new \RuntimeException('Invalid KYC decision.');
        }
        if (strlen(trim($reason)) < 10) {
            throw new \RuntimeException('Enter a review reason of at least 10 characters.');
        }
        $this->database->execute(
            'UPDATE kyc_profiles SET status = ?, reviewed_by = ?, reviewed_at = ?, review_reason = ?, updated_at = ? WHERE user_id = ?',
            [$status, $adminId, gmdate('Y-m-d H:i:s.u'), $reason, gmdate('Y-m-d H:i:s.u'), $userId]
        );
    }
}
