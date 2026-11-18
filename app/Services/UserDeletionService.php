<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use RuntimeException;
use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

/**
 * Removes a customer from active use without deleting the user row: ledger
 * postings, transactions, and audit events keep their foreign-key owner. Access
 * is revoked and personal data is scrubbed, so the record is only a tombstone.
 *
 * Force mode closes outstanding balances with a balanced journal posting to the
 * system control account instead of removing ledger rows. Postings are never
 * deleted or rewritten: the books must still reconcile after a deletion.
 */
final class UserDeletionService
{
    public function __construct(
        private readonly Database $database,
        private readonly AuthService $auth,
        private readonly LedgerService $ledger,
    ) {
    }

    /**
     * @return array{id: string, email_before: string, account_count: int, forced: bool, closure_count: int, cancelled_requests: int}
     */
    public function delete(
        string $userId,
        string $adminId,
        string $reason,
        string $adminPassword,
        bool $confirmed = false,
        bool $force = false
    ): array {
        $admin = $this->auth->userById($adminId);
        if (!$admin || (string) $admin['role'] !== 'admin' || (string) $admin['status'] !== 'active') {
            throw new RuntimeException('Only an active administrator can delete a customer.');
        }
        if (!password_verify($adminPassword, (string) $admin['password_hash'])) {
            throw new RuntimeException('Administrator password verification failed.');
        }
        if (!$confirmed) {
            throw new RuntimeException('Confirm that this deletion cannot be undone.');
        }
        $reason = trim($reason);
        if (strlen($reason) < 10) {
            throw new RuntimeException('Enter a deletion reason of at least 10 characters.');
        }

        $customer = $this->database->fetchOne('SELECT * FROM users WHERE id = ? AND role = ?', [$userId, 'user']);
        if (!$customer || $customer['deleted_at'] !== null) {
            throw new RuntimeException('Customer account was not found.');
        }

        $accounts = $this->database->fetchAll('SELECT id, ledger_account_id, asset_id, status FROM accounts WHERE user_id = ?', [$userId]);
        $balances = [];
        foreach ($accounts as $account) {
            $accountId = (string) $account['id'];
            $balances[$accountId] = $this->ledger->balanceMinor($accountId);
            if ($force) {
                continue;
            }
            if ($balances[$accountId] !== 0) {
                throw new RuntimeException('Resolve all balances before deleting this customer. Use the ledger adjustment flow.');
            }
            $held = $this->database->fetchOne(
                "SELECT COUNT(*) AS total FROM holds WHERE account_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > ?)",
                [$accountId, gmdate('Y-m-d H:i:s.u')]
            );
            if ((int) ($held['total'] ?? 0) > 0) {
                throw new RuntimeException('Resolve active reservations before deleting this customer.');
            }
        }

        $now = gmdate('Y-m-d H:i:s.u');
        $tombstoneEmail = 'deleted-' . $userId . '@invalid.local';
        $detail = mb_substr('Force deletion closure: ' . $reason, 0, 500);
        $closureNote = mb_substr('Cancelled during forced customer deletion: ' . $reason, 0, 500);
        $closureCount = 0;
        $cancelledRequests = 0;

        $this->database->transaction(function (Database $db) use (
            $userId,
            $adminId,
            $now,
            $tombstoneEmail,
            $detail,
            $closureNote,
            $accounts,
            $balances,
            $force,
            &$closureCount,
            &$cancelledRequests
        ): void {
            if ($force) {
                // A pending request must not be approvable after access is revoked.
                $withdrawals = $db->fetchAll(
                    "SELECT id, transaction_id, hold_id FROM withdrawal_requests WHERE user_id = ? AND status IN ('pending','approved','processing')",
                    [$userId]
                );
                foreach ($withdrawals as $request) {
                    $db->execute(
                        "UPDATE withdrawal_requests SET status = 'cancelled', reviewed_by = ?, reviewed_at = ?, review_reason = ?, updated_at = ? WHERE id = ?",
                        [$adminId, $now, $closureNote, $now, (string) $request['id']]
                    );
                    $db->execute("UPDATE transactions SET status = 'cancelled', updated_at = ? WHERE id = ?", [$now, (string) $request['transaction_id']]);
                    $db->execute("UPDATE holds SET status = 'released', released_at = ? WHERE id = ?", [$now, (string) $request['hold_id']]);
                    $cancelledRequests++;
                }
                $deposits = $db->fetchAll(
                    "SELECT id, transaction_id FROM deposit_requests WHERE user_id = ? AND status IN ('pending','reviewing')",
                    [$userId]
                );
                foreach ($deposits as $deposit) {
                    $db->execute(
                        "UPDATE deposit_requests SET status = 'cancelled', reviewed_by = ?, reviewed_at = ?, review_reason = ?, updated_at = ? WHERE id = ?",
                        [$adminId, $now, $closureNote, $now, (string) $deposit['id']]
                    );
                    $db->execute("UPDATE transactions SET status = 'cancelled', updated_at = ? WHERE id = ?", [$now, (string) $deposit['transaction_id']]);
                    $cancelledRequests++;
                }
                // Release any hold not tied to a request as well (defensive cleanup).
                $db->execute(
                    "UPDATE holds SET status = 'released', released_at = ? WHERE status = 'active' AND account_id IN (SELECT id FROM accounts WHERE user_id = ?)",
                    [$now, $userId]
                );

                // Move each remaining balance to the system asset control account.
                // A positive customer liability is debited; a debit balance is
                // credited back. Both cases balance within the same asset.
                foreach ($accounts as $account) {
                    $accountId = (string) $account['id'];
                    $balance = (int) ($balances[$accountId] ?? 0);
                    if ($balance === 0) {
                        continue;
                    }
                    $amount = abs($balance);
                    $customerLedger = (string) $account['ledger_account_id'];
                    $systemLedger = $this->systemAssetAccount((string) $account['asset_id']);
                    $postings = $balance > 0
                        ? [
                            ['ledger_account_id' => $customerLedger, 'side' => 'debit', 'amount_minor' => $amount],
                            ['ledger_account_id' => $systemLedger, 'side' => 'credit', 'amount_minor' => $amount],
                        ]
                        : [
                            ['ledger_account_id' => $systemLedger, 'side' => 'debit', 'amount_minor' => $amount],
                            ['ledger_account_id' => $customerLedger, 'side' => 'credit', 'amount_minor' => $amount],
                        ];
                    $key = hash('sha256', 'force_delete_closure:' . $userId . ':' . $accountId);
                    $entryId = $this->ledger->createEntry('adjustment', $detail, $adminId, $key);
                    $this->ledger->post($postings, $adminId, $entryId);
                    $db->execute(
                        'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, counterparty_label, description, metadata, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
                        [
                            Security::uuid(),
                            $userId,
                            $accountId,
                            $entryId,
                            Security::reference('CLS'),
                            'adjustment',
                            $balance > 0 ? 'debit' : 'credit',
                            $amount,
                            'completed',
                            'Wayfairs administration',
                            $detail,
                            json_encode([
                                'admin_user_id' => $adminId,
                                'forced' => true,
                                'balance_before_minor' => $balance,
                            ], JSON_THROW_ON_ERROR),
                            $now,
                        ]
                    );
                    $closureCount++;
                }
            }

            $db->execute('UPDATE sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [$now, $userId]);
            $db->execute('UPDATE otp_codes SET consumed_at = ? WHERE user_id = ? AND consumed_at IS NULL', [$now, $userId]);
            $db->execute('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [$now, $userId]);
            $db->execute("UPDATE accounts SET status = 'closed', updated_at = ? WHERE user_id = ?", [$now, $userId]);
            $db->execute(
                "UPDATE ledger_accounts SET status = 'closed' WHERE id IN (SELECT ledger_account_id FROM accounts WHERE user_id = ?)",
                [$userId]
            );
            $db->execute(
                "UPDATE users SET email = ?, email_normalized = ?, first_name = 'Deleted', last_name = 'Customer', phone = NULL, status = 'closed', deleted_at = ?, updated_at = ? WHERE id = ? AND role = 'user'",
                [$tombstoneEmail, $tombstoneEmail, $now, $now, $userId]
            );
            $db->execute(
                "UPDATE kyc_profiles SET date_of_birth = NULL, country_code = NULL, address_line1 = NULL, address_line2 = NULL, city = NULL, region = NULL, postal_code = NULL, id_last4 = NULL, submission_reference = NULL, updated_at = ? WHERE user_id = ?",
                [$now, $userId]
            );
        });

        return [
            'id' => $userId,
            'email_before' => (string) $customer['email'],
            'account_count' => count($accounts),
            'forced' => $force,
            'closure_count' => $closureCount,
            'cancelled_requests' => $cancelledRequests,
        ];
    }

    private function systemAssetAccount(string $assetId): string
    {
        $row = $this->database->fetchOne(
            "SELECT id FROM ledger_accounts WHERE asset_id = ? AND owner_type = 'system' AND account_class = 'asset'",
            [$assetId]
        );
        if (!$row) {
            throw new RuntimeException('The asset ledger is incomplete. Contact support.');
        }
        return (string) $row['id'];
    }
}
