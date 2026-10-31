<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

final class LedgerService
{
    public function __construct(private readonly Database $database)
    {
    }

    public function balanceMinor(string $accountId): int
    {
        $row = $this->database->fetchOne(
            "SELECT COALESCE(SUM(CASE WHEN p.side = 'debit' THEN p.amount_minor ELSE 0 END),0) AS debits, COALESCE(SUM(CASE WHEN p.side = 'credit' THEN p.amount_minor ELSE 0 END),0) AS credits, la.normal_side FROM postings p JOIN ledger_accounts la ON la.id = p.ledger_account_id WHERE p.ledger_account_id = (SELECT ledger_account_id FROM accounts WHERE id = ?) GROUP BY la.normal_side",
            [$accountId]
        );
        if (!$row) {
            return 0;
        }
        return (int) ($row['normal_side'] === 'debit' ? (int) $row['debits'] - (int) $row['credits'] : (int) $row['credits'] - (int) $row['debits']);
    }

    public function accountBalance(string $accountId): int
    {
        $base = $this->balanceMinor($accountId);
        $row = $this->database->fetchOne("SELECT COALESCE(SUM(amount_minor),0) AS held FROM holds WHERE account_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > ?)", [$accountId, gmdate('Y-m-d H:i:s.u')]);
        return $base - (int) ($row['held'] ?? 0);
    }

    public function createEntry(string $type, string $description, string $initiatedBy, ?string $idempotencyKey = null, ?string $relatedEntryId = null): string
    {
        if ($idempotencyKey) {
            $existing = $this->database->fetchOne('SELECT id FROM journal_entries WHERE idempotency_key = ?', [$idempotencyKey]);
            if ($existing) {
                return (string) $existing['id'];
            }
        }
        $id = Security::uuid();
        $this->database->execute(
            'INSERT INTO journal_entries (id, public_reference, entry_type, status, description, idempotency_key, initiated_by, related_entry_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, Security::reference('JE'), $type, 'draft', $description, $idempotencyKey, $initiatedBy, $relatedEntryId]
        );
        return $id;
    }

    public function post(array $postings, ?string $approvedBy = null, string $entryId = ''): string
    {
        if (count($postings) < 2) {
            throw new \RuntimeException('A journal entry must contain at least two postings.');
        }
        $byAsset = [];
        foreach ($postings as $posting) {
            $account = $this->database->fetchOne('SELECT la.asset_id, la.normal_side FROM ledger_accounts la WHERE la.id = ?', [(string) $posting['ledger_account_id']]);
            if (!$account) {
                throw new \RuntimeException('Unknown ledger account.');
            }
            $byAsset[$account['asset_id']][$posting['side']] = ($byAsset[$account['asset_id']][$posting['side']] ?? 0) + (int) $posting['amount_minor'];
        }
        foreach ($byAsset as $sides) {
            if (($sides['debit'] ?? 0) !== ($sides['credit'] ?? 0)) {
                throw new \RuntimeException('Journal entry does not balance for one or more assets.');
            }
        }
        return $this->database->transaction(function (Database $database) use ($postings, $approvedBy, $entryId): string {
            $entry = $database->fetchOne('SELECT status FROM journal_entries WHERE id = ?', [$entryId]);
            if (!$entry || $entry['status'] !== 'draft') {
                throw new \RuntimeException('Journal entry is not editable.');
            }
            foreach ($postings as $posting) {
                $database->execute('INSERT INTO postings (id, journal_entry_id, ledger_account_id, side, amount_minor) VALUES (?, ?, ?, ?, ?)', [
                    Security::uuid(),
                    $entryId,
                    (string) $posting['ledger_account_id'],
                    (string) $posting['side'],
                    (int) $posting['amount_minor'],
                ]);
            }
            $database->execute('UPDATE journal_entries SET status = ?, approved_by = ?, posted_at = ? WHERE id = ?', ['posted', $approvedBy, gmdate('Y-m-d H:i:s.u'), $entryId]);
            return $entryId;
        });
    }

    public function reverse(string $entryId, string $description, string $initiatedBy): string
    {
        $entry = $this->database->fetchOne('SELECT * FROM journal_entries WHERE id = ?', [$entryId]);
        if (!$entry || $entry['status'] !== 'posted') {
            throw new \RuntimeException('Only posted entries can be reversed.');
        }
        $postings = $this->database->fetchAll('SELECT ledger_account_id, side, amount_minor FROM postings WHERE journal_entry_id = ?', [$entryId]);
        $reversed = array_map(static function (array $posting): array {
            return [
                'ledger_account_id' => $posting['ledger_account_id'],
                'side' => $posting['side'] === 'debit' ? 'credit' : 'debit',
                'amount_minor' => (int) $posting['amount_minor'],
            ];
        }, $postings);
        $newEntry = $this->createEntry('reversal', $description, $initiatedBy, null, $entryId);
        $this->post($reversed, $initiatedBy, $newEntry);
        $this->database->execute('UPDATE journal_entries SET status = ? WHERE id = ?', ['reversed', $entryId]);
        return $newEntry;
    }
}
