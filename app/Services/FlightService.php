<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

/**
 * Flight booking requests. Nothing is booked, priced, or charged here: a request
 * records the trip so operations can confirm availability and cost with the
 * customer. Status changes come only from an administrator.
 */
final class FlightService
{
    public const CABINS = ['economy', 'premium_economy', 'business', 'first'];
    public const STATUSES = ['requested', 'contacted', 'booked', 'declined', 'cancelled'];

    private const TRANSITIONS = [
        'requested' => ['contacted', 'declined', 'cancelled'],
        'contacted' => ['booked', 'declined', 'cancelled'],
        'booked' => ['cancelled'],
        'declined' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly Database $database, private readonly Audit $audit)
    {
    }

    public function create(string $userId, array $input, string $idempotencyToken): array
    {
        $user = $this->database->fetchOne('SELECT status FROM users WHERE id = ?', [$userId]);
        if (!$user || $user['status'] !== 'active') {
            throw new RuntimeException('This account is not active.');
        }
        $kyc = $this->database->fetchOne('SELECT status FROM kyc_profiles WHERE user_id = ?', [$userId]);
        if (!$kyc || $kyc['status'] !== 'verified') {
            throw new RuntimeException('Identity verification must be approved before requesting a flight.');
        }

        $token = trim($idempotencyToken);
        if ($token === '') {
            throw new RuntimeException('That request has expired. Refresh the page and try again.');
        }
        $idempotencyKey = 'flight:' . Security::hashToken($userId . ':' . $token);
        $existing = $this->database->fetchOne('SELECT * FROM flight_requests WHERE idempotency_key = ?', [$idempotencyKey]);
        if ($existing) {
            return $existing;
        }

        $origin = $this->place($input['origin'] ?? '', 'departure city or airport');
        $destination = $this->place($input['destination'] ?? '', 'destination city or airport');
        if (mb_strtolower($origin, 'UTF-8') === mb_strtolower($destination, 'UTF-8')) {
            throw new RuntimeException('Choose a different destination from the departure city.');
        }
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $departure = $this->date($input['departure_date'] ?? '', 'departure date');
        if ($departure < $today) {
            throw new RuntimeException('The departure date cannot be in the past.');
        }
        $return = null;
        $returnInput = trim((string) ($input['return_date'] ?? ''));
        if ($returnInput !== '') {
            $return = $this->date($returnInput, 'return date');
            if ($return < $departure) {
                throw new RuntimeException('The return date cannot be before the departure date.');
            }
            if ($return > $departure->modify('+365 days')) {
                throw new RuntimeException('Return dates more than a year after departure are not accepted.');
            }
        }
        $travellers = filter_var($input['travellers'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9]]);
        if ($travellers === false) {
            throw new RuntimeException('Choose between 1 and 9 travellers.');
        }
        $cabin = (string) ($input['cabin'] ?? '');
        if (!in_array($cabin, self::CABINS, true)) {
            throw new RuntimeException('Choose a cabin class.');
        }
        $notes = mb_substr(trim((string) ($input['notes'] ?? '')), 0, 500) ?: null;

        $id = Security::uuid();
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->execute(
            'INSERT INTO flight_requests (id, user_id, public_reference, idempotency_key, origin, destination, departure_date, return_date, travellers, cabin, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $userId, Security::reference('FLT'), $idempotencyKey, $origin, $destination, $departure->format('Y-m-d'), $return?->format('Y-m-d'), $travellers, $cabin, $notes, 'requested', $now, $now]
        );
        return $this->database->fetchOne('SELECT * FROM flight_requests WHERE id = ?', [$id]);
    }

    public function forUser(string $userId): array
    {
        return $this->database->fetchAll('SELECT * FROM flight_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [$userId]);
    }

    public function forAdmin(): array
    {
        return $this->database->fetchAll(
            'SELECT f.*, u.email AS customer_email, u.first_name, u.last_name FROM flight_requests f JOIN users u ON u.id = f.user_id ORDER BY CASE f.status WHEN \'requested\' THEN 0 WHEN \'contacted\' THEN 1 ELSE 2 END, f.created_at DESC LIMIT 200'
        );
    }

    public function decide(string $requestId, string $status, string $adminNote, string $adminId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Choose a valid status.');
        }
        $this->database->transaction(function (Database $db) use ($requestId, $status, $adminNote, $adminId): void {
            $row = $db->fetchOne('SELECT * FROM flight_requests WHERE id = ?', [$requestId]);
            if (!$row) {
                throw new RuntimeException('That flight request was not found.');
            }
            $current = (string) $row['status'];
            if (!in_array($status, self::TRANSITIONS[$current] ?? [], true)) {
                throw new RuntimeException('A ' . $current . ' request cannot be changed to ' . $status . '.');
            }
            $db->execute(
                'UPDATE flight_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND status = ?',
                [$status, mb_substr(trim($adminNote), 0, 500) ?: null, $adminId, gmdate('Y-m-d H:i:s.u'), gmdate('Y-m-d H:i:s.u'), $requestId, $current]
            );
            $this->audit->record('flight_request.' . $status, 'flight_request', $requestId, null, ['from' => $current, 'to' => $status]);
        });
    }

    private function place(mixed $value, string $label): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
        if (!mb_check_encoding($clean, 'UTF-8') || $clean === '') {
            throw new RuntimeException('Enter the ' . $label . '.');
        }
        if (mb_strlen($clean, 'UTF-8') < 2 || mb_strlen($clean, 'UTF-8') > 120) {
            throw new RuntimeException('The ' . $label . ' should be between 2 and 120 characters.');
        }
        return $clean;
    }

    private function date(mixed $value, string $label): DateTimeImmutable
    {
        $text = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $text) {
            throw new RuntimeException('Enter the ' . $label . ' as a real calendar date.');
        }
        return $parsed;
    }
}
