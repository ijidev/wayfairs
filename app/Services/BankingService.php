<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Config;
use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Money;
use Wayfairs\Core\Security;

final class BankingService
{
    public function __construct(public readonly Database $database, public readonly LedgerService $ledger)
    {
    }

    public function assetByCode(string $code): array
    {
        $asset = $this->database->fetchOne('SELECT * FROM assets WHERE code = ? AND enabled = 1', [strtoupper(trim($code))]);
        if (!$asset) {
            throw new \RuntimeException('That asset is not available.');
        }
        return $asset;
    }

    public function accountFor(string $userId, string $assetCode): array
    {
        $asset = $this->assetByCode($assetCode);
        $account = $this->database->fetchOne(
            'SELECT a.*, la.code AS ledger_code FROM accounts a JOIN ledger_accounts la ON la.id = a.ledger_account_id WHERE a.user_id = ? AND a.asset_id = ?',
            [$userId, $asset['id']]
        ) ?? $this->createAccount($userId, $asset);
        return $account + ['asset' => $asset];
    }

    public function allAccounts(string $userId): array
    {
        $accounts = [];
        foreach ($this->database->fetchAll("SELECT code FROM assets WHERE enabled = 1 ORDER BY CASE WHEN code = 'USD' THEN 0 ELSE 1 END, asset_type, code") as $asset) {
            $accounts[] = $this->accountFor($userId, (string) $asset['code']);
        }
        return $accounts;
    }

    public function submitDeposit(string $userId, string $assetCode, string $amount, string $remark, string $idempotencyToken): array
    {
        $account = $this->accountFor($userId, $assetCode);
        $asset = $account['asset'];
        $this->guard($userId, $account, (string) $asset['asset_type'] === 'crypto' ? 'crypto_deposit' : 'fiat_deposit');
        if (!(int) $asset['deposit_enabled']) {
            throw new \RuntimeException('Deposits are not open for this asset.');
        }
        $minor = $this->positiveAmount($amount, (int) $asset['scale']);
        if ($minor < (int) $asset['min_deposit_minor']) {
            throw new \RuntimeException('Deposit is below the minimum for this asset.');
        }
        $entryId = $this->ledger->createEntry('deposit', 'Deposit request', $userId, $this->idempotencyKey('deposit', $userId, $idempotencyToken));
        $existing = $this->database->fetchOne('SELECT * FROM deposit_requests WHERE journal_entry_id = ?', [$entryId]);
        if ($existing) {
            return $existing;
        }
        $depositId = Security::uuid();
        $transactionId = Security::uuid();
        $remark = mb_substr(trim($remark), 0, 500);
        $this->database->transaction(function (Database $db) use ($userId, $account, $asset, $minor, $remark, $entryId, $depositId, $transactionId): void {
            $db->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, description, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$transactionId, $userId, $account['id'], $entryId, Security::reference('DEP'), 'deposit', 'credit', $minor, 0, 'pending', 'Deposit awaiting verification', json_encode(['user_remark' => $remark ?: null], JSON_THROW_ON_ERROR)]
            );
            $db->execute(
                'INSERT INTO deposit_requests (id, user_id, account_id, transaction_id, deposit_type, amount_minor, user_remark, status, journal_entry_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$depositId, $userId, $account['id'], $transactionId, (string) $asset['asset_type'] === 'crypto' ? 'crypto' : 'fiat', $minor, $remark ?: null, 'pending', $entryId]
            );
        });
        return $this->database->fetchOne('SELECT * FROM deposit_requests WHERE id = ?', [$depositId]);
    }

    public function verifyDeposit(string $depositId, string $adminId, bool $approve, string $remark, ?string $externalReference): void
    {
        $this->assertNotImpersonating('Deposit decisions');
        $deposit = $this->database->fetchOne('SELECT * FROM deposit_requests WHERE id = ?', [$depositId]);
        if (!$deposit || $deposit['status'] !== 'pending') {
            throw new \RuntimeException('This deposit is no longer pending.');
        }
        $account = $this->database->fetchOne('SELECT * FROM accounts WHERE id = ?', [(string) $deposit['account_id']]);
        $remark = mb_substr(trim($remark), 0, 500);
        $this->database->transaction(function (Database $db) use ($deposit, $account, $adminId, $approve, $remark, $externalReference): void {
            if ($approve) {
                $this->ledger->post([
                    ['ledger_account_id' => $this->systemAccountId((string) $account['asset_id'], 'asset'), 'side' => 'debit', 'amount_minor' => (int) $deposit['amount_minor']],
                    ['ledger_account_id' => (string) $account['ledger_account_id'], 'side' => 'credit', 'amount_minor' => (int) $deposit['amount_minor']],
                ], $adminId, (string) $deposit['journal_entry_id']);
                $db->execute("UPDATE transactions SET status = 'completed', completed_at = ?, updated_at = ? WHERE id = ?", [$this->now(), $this->now(), (string) $deposit['transaction_id']]);
                $db->execute("UPDATE deposit_requests SET status = 'completed', reviewed_by = ?, reviewed_at = ?, review_reason = ?, external_reference = ?, updated_at = ? WHERE id = ?", [$adminId, $this->now(), $remark ?: null, $externalReference ?: null, $this->now(), (string) $deposit['id']]);
                return;
            }
            $db->execute("UPDATE transactions SET status = 'failed', updated_at = ? WHERE id = ?", [$this->now(), (string) $deposit['transaction_id']]);
            $db->execute("UPDATE deposit_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, review_reason = ?, updated_at = ? WHERE id = ?", [$adminId, $this->now(), $remark ?: null, $this->now(), (string) $deposit['id']]);
        });
    }

    public function submitWithdrawal(string $userId, string $assetCode, string $amount, array $destination, string $idempotencyToken, string $remark = ''): array
    {
        $account = $this->accountFor($userId, $assetCode);
        $asset = $account['asset'];
        $this->guard($userId, $account, (string) $asset['asset_type'] === 'crypto' ? 'crypto_withdrawal' : 'fiat_withdrawal');
        if (!(int) $asset['withdrawal_enabled']) {
            throw new \RuntimeException('Withdrawals are not open for this asset.');
        }
        $isCrypto = (string) $asset['asset_type'] === 'crypto';
        $requiredFields = $isCrypto ? ['network', 'address'] : ['bank_name', 'account_number'];
        foreach ($requiredFields as $field) {
            if (trim((string) ($destination[$field] ?? '')) === '') {
                throw new \RuntimeException('Complete the ' . ($isCrypto ? 'wallet' : 'bank') . ' destination details.');
            }
        }
        $destination = array_intersect_key(
            $destination,
            array_flip($isCrypto ? ['network', 'address', 'memo'] : ['bank_name', 'account_name', 'account_number', 'routing', 'swift'])
        );
        return $this->createExternalRequest($userId, $account, $asset, $amount, $destination, $idempotencyToken, 'withdrawal', $remark);
    }

    private function prepareBankTransfer(string $userId, string $assetCode, string $amount, array $destination, string $remark, string $route): array
    {
        if (!Features::enabled($this->database, Features::BANK_TRANSFERS)) {
            throw new \RuntimeException('Bank transfers are not available on this account yet.');
        }
        Security::assertServerReady();
        $account = $this->accountFor($userId, $assetCode);
        $asset = $account['asset'];
        $this->guard($userId, $account, 'fiat_withdrawal');
        if ((string) $asset['asset_type'] !== 'fiat') {
            throw new \RuntimeException('Bank transfers require a fiat currency account.');
        }

        $clean = $this->cleanBankDestination($destination, $route);
        return ['account' => $account, 'asset' => $asset, 'destination' => $clean, 'remark' => $this->cleanText($remark, 500)];
    }

    /**
     * Validates and normalises beneficiary banking details. Shared by transfers,
     * bill payments, and saved beneficiaries so every route applies the same rules.
     */
    public function cleanBankDestination(array $destination, string $route): array
    {
        $beneficiaryName = $this->cleanText((string) ($destination['beneficiary_name'] ?? ''), 160);
        $bankName = $this->cleanText((string) ($destination['bank_name'] ?? ''), 160);
        $country = $this->compact((string) ($destination['country_code'] ?? ''));
        if ($beneficiaryName === '' || $bankName === '' || $country === '') {
            throw new \RuntimeException('Complete the beneficiary country, name, and bank.');
        }
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \RuntimeException('Choose a valid two-letter beneficiary country.');
        }
        // The country catalog comes from PHP's intl extension. When that extension
        // is unavailable the catalog is empty, so accept any two-letter country
        // code instead of blocking every beneficiary.
        $catalog = \function_exists('wayfairs_countries') ? \wayfairs_countries() : [];
        if ($catalog !== [] && !array_key_exists($country, $catalog)) {
            throw new \RuntimeException('Choose a recognized beneficiary country.');
        }

        $iban = $this->compact((string) ($destination['iban'] ?? ''));
        $accountNumber = $this->compactAccount((string) ($destination['account_number'] ?? ''));
        $route = strtolower(trim($route));
        if (!in_array($route, ['iban', 'local'], true)) {
            $route = $iban !== '' ? 'iban' : 'local';
        }
        // Honour the route the customer selected and ignore stale input from the
        // other route so a leftover value cannot block a valid transfer.
        if ($route === 'iban' && $iban === '' && $accountNumber !== '') {
            $route = 'local';
        } elseif ($route === 'local' && $accountNumber === '' && $iban !== '') {
            $route = 'iban';
        }

        $clean = [
            'country_code' => $country,
            'beneficiary_name' => $beneficiaryName,
            'bank_name' => $bankName,
        ];
        foreach (['beneficiary_address' => 300, 'bank_address' => 300] as $field => $limit) {
            $value = $this->cleanText((string) ($destination[$field] ?? ''), $limit);
            if ($value !== '') {
                $clean[$field] = $value;
            }
        }

        if ($route === 'iban') {
            if ($iban === '') {
                throw new \RuntimeException('Enter the beneficiary IBAN.');
            }
            if (strlen($iban) < 15 || strlen($iban) > 34 || !preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
                throw new \RuntimeException('Check the IBAN. It should start with a two-letter country code, two check digits, then the account details.');
            }
            $clean['iban'] = $iban;
            $swift = $this->compact((string) ($destination['swift_bic'] ?? ''));
            if ($swift !== '') {
                if (!preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $swift)) {
                    throw new \RuntimeException('Check the SWIFT/BIC. It should be 8 or 11 letters and digits.');
                }
                $clean['swift_bic'] = $swift;
            }
        } else {
            if ($accountNumber === '') {
                throw new \RuntimeException('Enter the beneficiary account number.');
            }
            if (strlen($accountNumber) < 4 || strlen($accountNumber) > 40) {
                throw new \RuntimeException('Check the account number. It should be between 4 and 40 letters and digits.');
            }
            $clean['account_number'] = $accountNumber;
            $routing = $this->compact((string) ($destination['routing_code'] ?? ''));
            if ($routing !== '') {
                $clean['routing_code'] = $routing;
            }
        }

        return $clean;
    }

    public function previewBankTransfer(string $userId, string $assetCode, string $amount, array $destination, string $remark = '', string $route = ''): array
    {
        $prepared = $this->prepareBankTransfer($userId, $assetCode, $amount, $destination, $remark, $route);
        $account = $prepared['account'];
        $asset = $prepared['asset'];
        $amountMinor = $this->positiveAmount($amount, (int) $asset['scale']);
        if ($amountMinor < (int) $asset['min_withdrawal_minor']) {
            throw new \RuntimeException('Transfer is below the minimum for this asset.');
        }
        $fee = (int) $asset['withdrawal_fee_minor'];
        $total = $amountMinor + $fee;
        $available = $this->ledger->accountBalance($account['id']);
        if ($available < $total) {
            throw new \RuntimeException('Insufficient available balance for this request and its fee.');
        }
        return [
            'asset' => $asset,
            'amount_minor' => $amountMinor,
            'fee_minor' => $fee,
            'total_minor' => $total,
            'available_minor' => $available,
            'available_after_minor' => $available - $total,
            'destination' => $prepared['destination'],
            'remark' => $prepared['remark'],
        ];
    }

    public function submitBankTransfer(string $userId, string $assetCode, string $amount, array $destination, string $idempotencyToken, string $remark = '', string $route = '', string $purpose = 'transfer'): array
    {
        $prepared = $this->prepareBankTransfer($userId, $assetCode, $amount, $destination, $remark, $route);
        return $this->createExternalRequest($userId, $prepared['account'], $prepared['asset'], $amount, $prepared['destination'], $idempotencyToken, 'bank_transfer', $prepared['remark'], $purpose);
    }


    private function createExternalRequest(string $userId, array $account, array $asset, string $amount, array $destination, string $idempotencyToken, string $kind, string $remark, string $purpose = 'transfer'): array
    {
        $purpose = in_array($purpose, ['transfer', 'bill'], true) ? $purpose : 'transfer';
        if (!(int) $asset['withdrawal_enabled']) {
            throw new \RuntimeException(($kind === 'bank_transfer' ? 'Bank transfers' : 'Withdrawals') . ' are not open for this asset.');
        }
        $idempotencyKey = $this->idempotencyKey($kind, $userId, $idempotencyToken);
        $existing = $this->database->fetchOne(
            'SELECT wr.* FROM withdrawal_requests wr JOIN journal_entries je ON je.id = wr.journal_entry_id WHERE je.idempotency_key = ?',
            [$idempotencyKey]
        );
        if ($existing) {
            return $existing;
        }
        $amountMinor = $this->positiveAmount($amount, (int) $asset['scale']);
        if ($amountMinor < (int) $asset['min_withdrawal_minor']) {
            throw new \RuntimeException(($kind === 'bank_transfer' ? 'Transfer' : 'Withdrawal') . ' is below the minimum for this asset.');
        }
        $fee = (int) $asset['withdrawal_fee_minor'];
        $total = $amountMinor + $fee;
        if ($this->ledger->accountBalance($account['id']) < $total) {
            throw new \RuntimeException('Insufficient available balance for this request and its fee.');
        }
        $label = $kind === 'bank_transfer' ? ($purpose === 'bill' ? 'Bill payment' : 'Bank transfer') : 'Withdrawal';
        $entryId = $this->ledger->createEntry('withdrawal', $label . ' request', $userId, $idempotencyKey);
        $withdrawalId = Security::uuid();
        $holdId = Security::uuid();
        $transactionId = Security::uuid();
        $encrypted = Security::encrypt($destination, (string) Config::instance()->get('app_key', ''));
        $summary = $this->destinationSummary($destination);
        $remark = $this->cleanText($remark, 500);
        $this->database->transaction(function (Database $db) use ($userId, $account, $amountMinor, $fee, $total, $withdrawalId, $holdId, $transactionId, $entryId, $encrypted, $summary, $asset, $kind, $label, $remark, $purpose): void {
            $db->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, description, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$transactionId, $userId, $account['id'], $entryId, Security::reference($kind === 'bank_transfer' ? 'BTF' : 'WDR'), 'withdrawal', 'debit', $amountMinor, $fee, 'pending', $label . ' pending', json_encode(['request_kind' => $kind, 'user_remark' => $remark ?: null], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]
            );
            $db->execute('INSERT INTO holds (id, account_id, source_type, source_id, amount_minor) VALUES (?, ?, ?, ?, ?)', [$holdId, $account['id'], 'withdrawal', $withdrawalId, $total]);
            $db->execute(
                'INSERT INTO withdrawal_requests (id, user_id, account_id, transaction_id, hold_id, withdrawal_type, request_kind, payment_purpose, amount_minor, fee_minor, destination_ciphertext, destination_summary, user_remark, status, journal_entry_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$withdrawalId, $userId, $account['id'], $transactionId, $holdId, (string) $asset['asset_type'] === 'crypto' ? 'crypto' : 'fiat', $kind, $purpose, $amountMinor, $fee, $encrypted, $summary, $remark ?: null, 'pending', $entryId]
            );
        });
        return $this->database->fetchOne('SELECT * FROM withdrawal_requests WHERE id = ?', [$withdrawalId]);
    }

    public function approveWithdrawal(string $withdrawalId, string $adminId, bool $approve, string $remark, ?string $externalReference): void
    {
        $this->assertNotImpersonating('Withdrawal decisions');
        $request = $this->database->fetchOne('SELECT * FROM withdrawal_requests WHERE id = ?', [$withdrawalId]);
        if (!$request || !in_array((string) $request['status'], ['pending', 'approved'], true)) {
            throw new \RuntimeException('This withdrawal is no longer actionable.');
        }
        $account = $this->database->fetchOne('SELECT * FROM accounts WHERE id = ?', [(string) $request['account_id']]);
        $remark = mb_substr(trim($remark), 0, 500);
        $this->database->transaction(function (Database $db) use ($request, $account, $adminId, $approve, $remark, $externalReference): void {
            if ($approve) {
                $amount = (int) $request['amount_minor'];
                $fee = (int) $request['fee_minor'];
                $this->ledger->post([
                    ['ledger_account_id' => (string) $account['ledger_account_id'], 'side' => 'debit', 'amount_minor' => $amount],
                    ['ledger_account_id' => $this->systemAccountId((string) $account['asset_id'], 'asset'), 'side' => 'credit', 'amount_minor' => $amount],
                ], $adminId, (string) $request['journal_entry_id']);
                if ($fee > 0) {
                    $feeEntry = $this->ledger->createEntry('fee', 'Withdrawal fee', $adminId);
                    $this->ledger->post([
                        ['ledger_account_id' => (string) $account['ledger_account_id'], 'side' => 'debit', 'amount_minor' => $fee],
                        ['ledger_account_id' => $this->systemAccountId((string) $account['asset_id'], 'revenue'), 'side' => 'credit', 'amount_minor' => $fee],
                    ], $adminId, $feeEntry);
                }
                $db->execute("UPDATE holds SET status = 'captured', released_at = ? WHERE id = ?", [$this->now(), (string) $request['hold_id']]);
                $db->execute("UPDATE transactions SET status = 'completed', completed_at = ?, updated_at = ? WHERE id = ?", [$this->now(), $this->now(), (string) $request['transaction_id']]);
                $db->execute("UPDATE withdrawal_requests SET status = 'completed', reviewed_by = ?, reviewed_at = ?, review_reason = ?, external_reference = ?, updated_at = ? WHERE id = ?", [$adminId, $this->now(), $remark ?: null, $externalReference ?: null, $this->now(), (string) $request['id']]);
                return;
            }
            $db->execute("UPDATE holds SET status = 'released', released_at = ? WHERE id = ?", [$this->now(), (string) $request['hold_id']]);
            $db->execute("UPDATE transactions SET status = 'failed', updated_at = ? WHERE id = ?", [$this->now(), (string) $request['transaction_id']]);
            $db->execute("UPDATE withdrawal_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, review_reason = ?, updated_at = ? WHERE id = ?", [$adminId, $this->now(), $remark ?: null, $this->now(), (string) $request['id']]);
        });
    }

    public function internalTransfer(string $senderId, string $recipientEmail, string $assetCode, string $amount, ?string $note, string $idempotencyToken): array
    {
        $senderAccount = $this->accountFor($senderId, $assetCode);
        $this->guard($senderId, $senderAccount, 'internal_transfer');
        $asset = $senderAccount['asset'];
        $recipient = $this->database->fetchOne('SELECT * FROM users WHERE email_normalized = ?', [mb_strtolower(trim($recipientEmail), 'UTF-8')]);
        if (!$recipient || $recipient['status'] !== 'active') {
            throw new \RuntimeException('The recipient account is not available.');
        }
        if ((string) $recipient['id'] === $senderId) {
            throw new \RuntimeException('You cannot send money to your own account.');
        }
        $recipientAccount = $this->accountFor((string) $recipient['id'], $assetCode);
        $amountMinor = $this->positiveAmount($amount, (int) $asset['scale']);
        $fee = (int) $asset['transfer_fee_minor'];
        if ($this->ledger->accountBalance($senderAccount['id']) < $amountMinor + $fee) {
            throw new \RuntimeException('Insufficient available balance for this transfer.');
        }
        $entryId = $this->ledger->createEntry('transfer', 'Internal transfer', $senderId, $this->idempotencyKey('transfer', $senderId, $idempotencyToken));
        $existing = $this->database->fetchOne('SELECT * FROM transfers WHERE journal_entry_id = ?', [$entryId]);
        if ($existing) {
            return $existing;
        }
        $transferId = Security::uuid();
        $senderTransactionId = Security::uuid();
        $recipientTransactionId = Security::uuid();
        $note = trim((string) $note) ?: null;
        $this->database->transaction(function (Database $db) use ($senderId, $senderAccount, $recipient, $recipientAccount, $asset, $amountMinor, $fee, $entryId, $transferId, $senderTransactionId, $recipientTransactionId, $note): void {
            $postings = [
                ['ledger_account_id' => (string) $senderAccount['ledger_account_id'], 'side' => 'debit', 'amount_minor' => $amountMinor],
                ['ledger_account_id' => (string) $recipientAccount['ledger_account_id'], 'side' => 'credit', 'amount_minor' => $amountMinor],
            ];
            if ($fee > 0) {
                $postings[] = ['ledger_account_id' => (string) $senderAccount['ledger_account_id'], 'side' => 'debit', 'amount_minor' => $fee];
                $postings[] = ['ledger_account_id' => $this->systemAccountId((string) $asset['id'], 'revenue'), 'side' => 'credit', 'amount_minor' => $fee];
            }
            $this->ledger->post($postings, $senderId, $entryId);
            $db->execute(
                'INSERT INTO transfers (id, public_reference, sender_user_id, sender_account_id, recipient_user_id, recipient_account_id, asset_id, amount_minor, fee_minor, note, status, journal_entry_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$transferId, Security::reference('TRF'), $senderId, $senderAccount['id'], $recipient['id'], $recipientAccount['id'], $asset['id'], $amountMinor, $fee, $note, 'completed', $entryId]
            );
            $db->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, counterparty_label, description, metadata, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$senderTransactionId, $senderId, $senderAccount['id'], $entryId, Security::reference('TRF'), 'transfer_out', 'debit', $amountMinor, $fee, 'completed', (string) $recipient['email_normalized'], $note ?? 'Transfer sent', json_encode(['transfer_id' => $transferId], JSON_THROW_ON_ERROR), $this->now()]
            );
            $db->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, counterparty_label, description, metadata, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$recipientTransactionId, (string) $recipient['id'], $recipientAccount['id'], $entryId, Security::reference('TRF'), 'transfer_in', 'credit', $amountMinor, 0, 'completed', $senderId, $note ?? 'Transfer received', json_encode(['transfer_id' => $transferId], JSON_THROW_ON_ERROR), $this->now()]
            );
        });
        return $this->database->fetchOne('SELECT * FROM transfers WHERE id = ?', [$transferId]);
    }

    public function adminAdjustBalance(string $adminId, string $userId, string $accountId, string $direction, string $amount, string $remark, string $idempotencyToken): array
    {
        $this->assertNotImpersonating('Administrative balance adjustments');
        $admin = $this->database->fetchOne("SELECT id FROM users WHERE id = ? AND role = 'admin' AND status = 'active'", [$adminId]);
        if (!$admin) {
            throw new \RuntimeException('An active administrator account is required.');
        }
        if (!in_array($direction, ['credit', 'debit'], true)) {
            throw new \RuntimeException('Choose credit or debit.');
        }
        $remark = trim($remark);
        if (mb_strlen($remark) < 10) {
            throw new \RuntimeException('Enter an adjustment note of at least 10 characters.');
        }
        $account = $this->database->fetchOne(
            "SELECT a.*, s.code AS asset_code, s.scale AS asset_scale, s.id AS resolved_asset_id, u.role AS user_role
             FROM accounts a JOIN assets s ON s.id = a.asset_id JOIN users u ON u.id = a.user_id
             WHERE a.id = ? AND a.user_id = ?",
            [$accountId, $userId]
        );
        if (!$account || (string) $account['user_role'] !== 'user') {
            throw new \RuntimeException('Customer currency account was not found.');
        }
        if ((string) $account['status'] === 'closed') {
            throw new \RuntimeException('A closed currency account cannot be adjusted.');
        }
        $key = $this->idempotencyKey('admin_adjustment', $adminId . ':' . $userId, $idempotencyToken);
        $existing = $this->database->fetchOne(
            "SELECT t.* FROM transactions t JOIN journal_entries je ON je.id = t.journal_entry_id WHERE je.idempotency_key = ? AND t.user_id = ? AND t.account_id = ? AND t.transaction_type = 'adjustment'",
            [$key, $userId, $accountId]
        );
        if ($existing) {
            return $existing;
        }
        $minor = $this->positiveAmount($amount, (int) $account['asset_scale']);
        if ($direction === 'debit' && $this->ledger->accountBalance($accountId) < $minor) {
            throw new \RuntimeException('Debit exceeds the customer available balance.');
        }
        $entryId = $this->ledger->createEntry('adjustment', mb_substr($remark, 0, 500), $adminId, $key);
        $system = $this->systemAccountId((string) $account['resolved_asset_id'], 'asset');
        $postings = $direction === 'credit'
            ? [
                ['ledger_account_id' => $system, 'side' => 'debit', 'amount_minor' => $minor],
                ['ledger_account_id' => (string) $account['ledger_account_id'], 'side' => 'credit', 'amount_minor' => $minor],
            ]
            : [
                ['ledger_account_id' => (string) $account['ledger_account_id'], 'side' => 'debit', 'amount_minor' => $minor],
                ['ledger_account_id' => $system, 'side' => 'credit', 'amount_minor' => $minor],
            ];
        $transactionId = Security::uuid();
        $this->database->transaction(function (Database $database) use ($postings, $adminId, $entryId, $transactionId, $userId, $accountId, $direction, $minor, $remark): void {
            $this->ledger->post($postings, $adminId, $entryId);
            $database->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, counterparty_label, description, metadata, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$transactionId, $userId, $accountId, $entryId, Security::reference('ADJ'), 'adjustment', $direction, $minor, 0, 'completed', 'Wayfairs administration', mb_substr($remark, 0, 500), json_encode(['admin_user_id' => $adminId], JSON_THROW_ON_ERROR), $this->now()]
            );
        });
        return $this->database->fetchOne('SELECT * FROM transactions WHERE id = ?', [$transactionId]) ?? throw new \RuntimeException('Adjustment did not persist.');
    }

    public function createInvoice(string $issuerId, string $assetCode, string $amount, string $title, ?string $description, ?string $dueAt): array
    {
        $this->guard($issuerId, $this->accountFor($issuerId, $assetCode), 'invoice');
        $asset = $this->assetByCode($assetCode);
        $amountMinor = $this->positiveAmount($amount, (int) $asset['scale']);
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException('Give the invoice a title.');
        }
        $due = $dueAt !== null && trim($dueAt) !== '' && strtotime($dueAt) !== false ? gmdate('Y-m-d H:i:s', strtotime($dueAt)) : null;
        $id = Security::uuid();
        $this->database->execute(
            'INSERT INTO invoices (id, public_code, issuer_user_id, asset_id, amount_minor, title, description, due_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, Security::reference('INV'), $issuerId, $asset['id'], $amountMinor, mb_substr($title, 0, 200), trim((string) $description) ?: null, $due]
        );
        return $this->database->fetchOne('SELECT * FROM invoices WHERE id = ?', [$id]);
    }

    public function payInvoice(string $payerId, string $invoiceCode, string $idempotencyToken): array
    {
        $invoice = $this->database->fetchOne('SELECT * FROM invoices WHERE public_code = ?', [strtoupper(trim($invoiceCode))]);
        if (!$invoice || $invoice['status'] !== 'open') {
            throw new \RuntimeException('This invoice is not open for payment.');
        }
        if ((string) $invoice['issuer_user_id'] === $payerId) {
            throw new \RuntimeException('You cannot pay your own invoice.');
        }
        if ($invoice['due_at'] !== null && strtotime((string) $invoice['due_at']) < time()) {
            throw new \RuntimeException('This invoice has expired.');
        }
        $asset = $this->database->fetchOne('SELECT code, scale FROM assets WHERE id = ?', [(string) $invoice['asset_id']]);
        $issuer = $this->database->fetchOne('SELECT email_normalized FROM users WHERE id = ?', [(string) $invoice['issuer_user_id']]);
        return $this->database->transaction(function (Database $db) use ($payerId, $invoice, $asset, $issuer, $idempotencyToken): array {
            $transfer = $this->internalTransfer(
                $payerId,
                (string) $issuer['email_normalized'],
                (string) $asset['code'],
                Money::format((int) $invoice['amount_minor'], (int) $asset['scale']),
                'Invoice ' . $invoice['public_code'] . ': ' . $invoice['title'],
                $idempotencyToken
            );
            $db->execute("UPDATE invoices SET status = 'paid', paid_at = ?, paid_by_user_id = ?, transfer_id = ?, updated_at = ? WHERE id = ?", [$this->now(), $payerId, $transfer['id'], $this->now(), (string) $invoice['id']]);
            return $db->fetchOne('SELECT * FROM invoices WHERE id = ?', [(string) $invoice['id']]);
        });
    }

    public function history(string $userId, ?string $accountId = null, int $limit = 100): array
    {
        $sql = 'SELECT t.*, a.account_number, aset.id AS asset_id, aset.code AS asset_code, aset.symbol AS asset_symbol, aset.scale AS asset_scale, aset.asset_type, aset.usd_provider_reference FROM transactions t JOIN accounts a ON a.id = t.account_id JOIN assets aset ON aset.id = a.asset_id WHERE t.user_id = ?';
        $params = [$userId];
        if ($accountId !== null) {
            $sql .= ' AND t.account_id = ?';
            $params[] = $accountId;
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT ?';
        $params[] = max(1, min(200, $limit));
        return $this->database->fetchAll($sql, $params);
    }

    public function receipt(string $transactionId, string $userId): array
    {
        $row = $this->database->fetchOne(
            'SELECT t.*, a.account_number, aset.id AS asset_id, aset.code AS asset_code, aset.symbol AS asset_symbol, aset.scale AS asset_scale, aset.asset_type, aset.usd_provider_reference FROM transactions t JOIN accounts a ON a.id = t.account_id JOIN assets aset ON aset.id = a.asset_id WHERE t.id = ? AND t.user_id = ?',
            [$transactionId, $userId]
        );
        if (!$row) {
            throw new \RuntimeException('Receipt not found.');
        }
        return $row;
    }

    private function createAccount(string $userId, array $asset): array
    {
        $user = $this->database->fetchOne('SELECT first_name, last_name FROM users WHERE id = ?', [$userId]) ?? [];
        $accountId = Security::uuid();
        $ledgerId = Security::uuid();
        $number = 'WF' . gmdate('y') . strtoupper(bin2hex(random_bytes(5)));
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . ' ' . $asset['code'];
        $this->database->transaction(function (Database $db) use ($userId, $asset, $accountId, $ledgerId, $number, $name): void {
            $db->execute(
                'INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$ledgerId, $asset['id'], 'user', $userId, 'USER:' . $userId . ':' . $asset['code'], $name, 'liability', 'credit']
            );
            $db->execute(
                'INSERT INTO accounts (id, user_id, asset_id, ledger_account_id, account_number, status) VALUES (?, ?, ?, ?, ?, ?)',
                [$accountId, $userId, $asset['id'], $ledgerId, $number, 'active']
            );
        });
        return $this->database->fetchOne('SELECT a.*, la.code AS ledger_code FROM accounts a JOIN ledger_accounts la ON la.id = a.ledger_account_id WHERE a.id = ?', [$accountId]);
    }

    private function guard(string $userId, array $account, string $capability): void
    {
        $this->assertNotImpersonating('Money movement and security changes');
        $user = $this->database->fetchOne('SELECT status FROM users WHERE id = ?', [$userId]);
        if (!$user || $user['status'] !== 'active') {
            throw new \RuntimeException('This account is not active.');
        }
        if (($account['status'] ?? 'active') !== 'active') {
            throw new \RuntimeException('This asset account is frozen. Contact support.');
        }
        $kyc = $this->database->fetchOne('SELECT status FROM kyc_profiles WHERE user_id = ?', [$userId]);
        if (!$kyc || $kyc['status'] !== 'verified') {
            throw new \RuntimeException('Identity verification must be approved before this action.');
        }
        if (!(new Restrictions($this->database))->can($userId, $capability)) {
            throw new \RuntimeException('This action is restricted on your account. Contact support.');
        }
    }

    private function assertNotImpersonating(string $action): void
    {
        if (Context::isImpersonating()) {
            throw new \RuntimeException($action . ' are disabled during impersonation.');
        }
    }

    private function systemAccountId(string $assetId, string $accountClass): string
    {
        $row = $this->database->fetchOne("SELECT id FROM ledger_accounts WHERE asset_id = ? AND owner_type = 'system' AND account_class = ?", [$assetId, $accountClass]);
        if (!$row) {
            throw new \RuntimeException('The asset ledger is incomplete. Contact support.');
        }
        return (string) $row['id'];
    }

    private function positiveAmount(string $amount, int $scale): int
    {
        try {
            $minor = Money::parse($amount, $scale);
        } catch (\InvalidArgumentException $error) {
            throw new \RuntimeException($error->getMessage(), 0, $error);
        }
        if ($minor <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }
        return $minor;
    }

    /**
     * Accept pasted banking identifiers: uppercase, drop spaces, dashes, dots,
     * and any invisible character that a phone keyboard may add.
     */
    private function compact(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    private function compactAccount(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return strtoupper((string) preg_replace('/[^A-Za-z0-9.\-]/', '', $value));
    }

    /**
     * Trim free-text input to valid UTF-8 so JSON encoding, encryption, and
     * database writes cannot fail on control characters or broken encoding.
     */
    private function cleanText(string $value, int $limit): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        $filtered = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        if (is_string($filtered)) {
            $value = $filtered;
        }
        return mb_substr(trim($value), 0, $limit);
    }

    private function idempotencyKey(string $scope, string $userId, string $token): string
    {
        $token = trim($token) !== '' ? trim($token) : Security::token(16);
        return $scope . ':' . Security::hashToken($userId . ':' . $token);
    }

    private function destinationSummary(array $destination): string
    {
        $parts = [];
        foreach (['country_code', 'beneficiary_name', 'bank_name', 'account_name', 'iban', 'account_number', 'routing_code', 'swift_bic', 'network', 'address', 'memo'] as $field) {
            if (!empty($destination[$field])) {
                $value = (string) $destination[$field];
                $parts[] = $field . ':' . (in_array($field, ['iban', 'account_number', 'address'], true) ? '…' . mb_substr($value, -4) : mb_substr($value, 0, 30));
            }
        }
        return mb_substr(implode('; ', $parts), 0, 200);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s.u');
    }
}
