<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use RuntimeException;
use Wayfairs\Core\Config;
use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

/**
 * Saved beneficiaries. Administrators publish global beneficiaries for every
 * customer; customers save their own. Account identifiers are encrypted at rest and
 * matched through a keyed hash, so a lookup never needs the stored plain number.
 */
final class BeneficiaryService
{
    public function __construct(
        private readonly Database $database,
        private readonly BankingService $banking,
        private readonly Audit $audit,
    ) {
    }

    public function createGlobal(array $input, string $adminId): array
    {
        return $this->create($input, 'global', 'global', $adminId);
    }

    public function saveForUser(string $userId, array $input): array
    {
        return $this->create($input, 'user', $userId, $userId);
    }

    /**
     * Saves a beneficiary from a confirmed transfer. A beneficiary that is already
     * saved is left as it is, so confirming a transfer never fails on this step.
     */
    public function saveFromTransfer(string $userId, array $destination, string $route, string $nickname): void
    {
        $identifier = $this->identifierFrom($destination, $route);
        if ($identifier === '' || $this->activeFor($userId, $route, $identifier) !== null) {
            return;
        }
        $this->saveForUser($userId, array_merge($destination, ['route' => $route, 'nickname' => $nickname]));
    }

    /** Exact match on the identifier only. Returns no account details on a miss. */
    public function findForUser(string $userId, string $route, string $identifier): ?array
    {
        $normalized = $this->normalize($route, $identifier);
        if ($normalized === '') {
            return null;
        }
        $row = $this->database->fetchOne(
            "SELECT * FROM saved_beneficiaries WHERE identifier_hash = ? AND route = ? AND status = 'active' AND owner_key IN (?, 'global') ORDER BY CASE WHEN owner_key = ? THEN 0 ELSE 1 END LIMIT 1",
            [$this->hash($route, $normalized), $route, $userId, $userId]
        );
        return $row ? $this->view($row) : null;
    }

    public function listFor(string $userId): array
    {
        return array_map(
            fn (array $row): array => $this->summary($row),
            $this->database->fetchAll(
                "SELECT * FROM saved_beneficiaries WHERE status = 'active' AND owner_key IN (?, 'global') ORDER BY scope ASC, nickname ASC LIMIT 200",
                [$userId]
            )
        );
    }

    public function listAllGlobal(): array
    {
        return array_map(
            fn (array $row): array => $this->summary($row),
            $this->database->fetchAll("SELECT * FROM saved_beneficiaries WHERE scope = 'global' ORDER BY status ASC, nickname ASC LIMIT 200")
        );
    }

    public function archive(string $beneficiaryId, string $actorId, bool $isAdmin): void
    {
        if (Context::isImpersonating()) {
            throw new RuntimeException('Beneficiary changes are disabled during impersonation.');
        }
        $this->database->transaction(function (Database $db) use ($beneficiaryId, $actorId, $isAdmin): void {
            $row = $db->fetchOne('SELECT * FROM saved_beneficiaries WHERE id = ?', [$beneficiaryId]);
            if (!$row || $row['status'] !== 'active') {
                throw new RuntimeException('That beneficiary was not found.');
            }
            if (!($row['scope'] === 'global' ? $isAdmin : ($isAdmin === false && (string) $row['owner_user_id'] === $actorId))) {
                throw new RuntimeException('That beneficiary was not found.');
            }
            $db->execute("UPDATE saved_beneficiaries SET status = 'archived', updated_at = ? WHERE id = ?", [gmdate('Y-m-d H:i:s.u'), $beneficiaryId]);
            if ($row['scope'] === 'global') {
                $this->audit->record('beneficiary.archived', 'beneficiary', $beneficiaryId, null, ['nickname' => $row['nickname']]);
            }
        });
    }

    private function create(array $input, string $scope, string $ownerKey, string $actorId): array
    {
        $route = (string) ($input['route'] ?? '');
        if (!in_array($route, ['iban', 'local'], true)) {
            throw new RuntimeException('Choose an IBAN or local account for this beneficiary.');
        }
        $clean = $this->banking->cleanBankDestination($input, $route);
        $identifier = $this->identifierFrom($clean, $route);
        $normalized = $this->normalize($route, $identifier);
        if ($this->activeFor($ownerKey, $route, $identifier) !== null) {
            throw new RuntimeException('This beneficiary is already saved.');
        }
        $nickname = $this->nickname((string) ($input['nickname'] ?? ''), (string) $clean['beneficiary_name']);
        $details = array_intersect_key($clean, array_flip(['iban', 'account_number', 'swift_bic', 'routing_code', 'bank_address', 'beneficiary_address']));
        $id = Security::uuid();
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->execute(
            'INSERT INTO saved_beneficiaries (id, owner_key, scope, owner_user_id, route, nickname, country_code, beneficiary_name, bank_name, identifier_hash, identifier_last4, details_ciphertext, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $ownerKey,
                $scope,
                $scope === 'user' ? $ownerKey : null,
                $route,
                $nickname,
                (string) $clean['country_code'],
                (string) $clean['beneficiary_name'],
                (string) $clean['bank_name'],
                $this->hash($route, $normalized),
                substr($normalized, -4),
                Security::encrypt($details, (string) Config::instance()->get('app_key', '')),
                'active',
                $actorId,
                $now,
                $now,
            ]
        );
        if ($scope === 'global') {
            $this->audit->record('beneficiary.created', 'beneficiary', $id, null, ['route' => $route, 'country_code' => $clean['country_code']]);
        }
        return $this->summary($this->database->fetchOne('SELECT * FROM saved_beneficiaries WHERE id = ?', [$id]));
    }

    private function activeFor(string $ownerKey, string $route, string $identifier): ?array
    {
        return $this->database->fetchOne(
            "SELECT * FROM saved_beneficiaries WHERE owner_key = ? AND identifier_hash = ? AND status = 'active'",
            [$ownerKey, $this->hash($route, $this->normalize($route, $identifier))]
        );
    }

    private function identifierFrom(array $destination, string $route): string
    {
        return (string) ($route === 'iban' ? ($destination['iban'] ?? '') : ($destination['account_number'] ?? ''));
    }

    private function normalize(string $route, string $identifier): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $identifier));
    }

    private function hash(string $route, string $normalized): string
    {
        $key = base64_decode((string) Config::instance()->get('app_key', ''), true);
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Application encryption key is invalid.');
        }
        return hash_hmac('sha256', $route . ':' . $normalized, $key);
    }

    private function nickname(string $nickname, string $fallback): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $nickname));
        if ($value === '') {
            $value = $fallback;
        }
        return mb_substr($value, 0, 80);
    }

    private function details(array $row): array
    {
        return Security::decrypt((string) $row['details_ciphertext'], (string) Config::instance()->get('app_key', ''));
    }

    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'scope' => (string) $row['scope'],
            'route' => (string) $row['route'],
            'nickname' => (string) $row['nickname'],
            'country_code' => (string) $row['country_code'],
            'beneficiary_name' => (string) $row['beneficiary_name'],
            'bank_name' => (string) $row['bank_name'],
            'identifier_last4' => (string) $row['identifier_last4'],
            'status' => (string) $row['status'],
            'owner_user_id' => $row['owner_user_id'] !== null ? (string) $row['owner_user_id'] : null,
        ];
    }

    /** Fields a transfer form may pre-fill. The account identifier is never returned. */
    private function view(array $row): array
    {
        $details = $this->details($row);
        return [
            'id' => (string) $row['id'],
            'scope' => (string) $row['scope'],
            'nickname' => (string) $row['nickname'],
            'country_code' => (string) $row['country_code'],
            'beneficiary_name' => (string) $row['beneficiary_name'],
            'bank_name' => (string) $row['bank_name'],
            'bank_address' => (string) ($details['bank_address'] ?? ''),
            'beneficiary_address' => (string) ($details['beneficiary_address'] ?? ''),
            'swift_bic' => (string) ($details['swift_bic'] ?? ''),
            'routing_code' => (string) ($details['routing_code'] ?? ''),
        ];
    }
}
