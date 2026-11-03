<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use RuntimeException;
use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Money;
use Wayfairs\Core\Security;
use Wayfairs\Services\UsdRateService;

final class SwapService
{
    public function __construct(
        private readonly Database $database,
        private readonly BankingService $banking,
        private readonly LedgerService $ledger,
        private readonly Audit $audit,
    ) {
    }

    public function isEnabled(): bool
    {
        $row = $this->database->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'swap.enabled'");
        return $row !== null && (string) $row['setting_value'] === '1';
    }

    public function feePercent(): float
    {
        $row = $this->database->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'swap.fee_percent'");
        $value = (float) ($row['setting_value'] ?? 0.5);
        return max(0.0, min(10.0, $value));
    }

    /**
     * Preview a swap without writing anything.
     *
     * @return array{from_asset:array,to_asset:array,from_amount_minor:int,to_amount_minor:int,fee_minor:int,fee_percent:float,from_rate:string,to_rate:string,available_minor:int}
     */
    public function preview(string $userId, string $fromCode, string $toCode, string $amount): array
    {
        if ($fromCode === $toCode) {
            throw new RuntimeException('Choose two different currencies.');
        }
        $fromAccount = $this->banking->accountFor($userId, $fromCode);
        $toAccount = $this->banking->accountFor($userId, $toCode);
        $fromAsset = $fromAccount['asset'];
        $toAsset = $toAccount['asset'];
        if ((string) $fromAsset['asset_type'] !== 'fiat' || (string) $toAsset['asset_type'] !== 'fiat') {
            throw new RuntimeException('Swaps are available between fiat currency accounts only.');
        }
        $this->banking->guardPublic($userId, $fromAccount, 'internal_transfer');
        $amountMinor = $this->banking->positiveAmountPublic($amount, (int) $fromAsset['scale']);
        if ($amountMinor <= 0) {
            throw new RuntimeException('Enter an amount greater than zero.');
        }
        $feePercent = $this->feePercent();
        $feeMinor = (int) round($amountMinor * $feePercent / 100.0);
        $netMinor = $amountMinor - $feeMinor;
        if ($netMinor <= 0) {
            throw new RuntimeException('The amount is too small to cover the swap fee.');
        }
        $rateService = new UsdRateService($this->database);
        $rates = $rateService->ratesForAssets([$fromAsset, $toAsset]);
        $fromRate = $rates[(string) $fromAsset['code']]['rate'] ?? null;
        $toRate = $rates[(string) $toAsset['code']]['rate'] ?? null;
        if (!is_string($fromRate) || !is_string($toRate)) {
            throw new RuntimeException('Exchange rates are not available right now. Try again later.');
        }
        // to_amount = net * from_rate / to_rate
        $toAmountMinor = (int) Money::convert($netMinor, (int) $fromAsset['scale'], $fromRate, (int) $toAsset['scale']);
        // apply the to rate by dividing? Money::convert multiplies by rate. We need net*fromRate/toRate.
        // Redo manually using the rates as decimals.
        $toAmountMinor = $this->convert($netMinor, (int) $fromAsset['scale'], $fromRate, (int) $toAsset['scale'], $toRate);
        if ($toAmountMinor <= 0) {
            throw new RuntimeException('The converted amount is too small.');
        }
        $available = $this->ledger->accountBalance($fromAccount['id']);
        if ($available < $amountMinor) {
            throw new RuntimeException('Insufficient available balance for this swap.');
        }
        return [
            'from_asset' => $fromAsset,
            'to_asset' => $toAsset,
            'from_account' => $fromAccount,
            'to_account' => $toAccount,
            'from_amount_minor' => $amountMinor,
            'to_amount_minor' => $toAmountMinor,
            'fee_minor' => $feeMinor,
            'fee_percent' => $feePercent,
            'from_rate' => $fromRate,
            'to_rate' => $toRate,
            'available_minor' => $available,
            'available_after_minor' => $available - $amountMinor,
        ];
    }

    private function convert(int $amountMinor, int $fromScale, string $fromRate, int $toScale, string $toRate): int
    {
        // amount in from minor -> major: /10^fromScale ; * fromRate (USD per from unit) -> USD major
        // / toRate -> to major ; *10^toScale -> to minor
        // Use Money::convert twice: first to USD minor at scale 2, then from USD to to.
        $usdMinor = Money::convert($amountMinor, $fromScale, $fromRate, 2);
        // Now convert USD minor back to to asset: to_minor = usdMinor * 10^toScale / (toRate * 10^2)? 
        // Money::convert expects amount in source minor, source scale, rate (target per source), target scale.
        // We want to_amount = usdMajor / toRate. Reuse by inverting rate.
        $inverted = (string) (1 / (float) $toRate);
        // Use a decimal string with enough precision
        $inverted = rtrim(rtrim(sprintf('%.12f', 1 / (float) $toRate), '0'), '.');
        return Money::convert($usdMinor, 2, $inverted, $toScale);
    }

    public function execute(string $userId, string $fromCode, string $toCode, string $amount, string $idempotencyToken): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Currency swaps are not available on this account yet.');
        }
        $preview = $this->preview($userId, $fromCode, $toCode, $amount);
        $fromAccount = $preview['from_account'];
        $toAccount = $preview['to_account'];
        $fromAsset = $preview['from_asset'];
        $toAsset = $preview['to_asset'];
        $idemKey = 'swap:' . Security::hashToken($userId . ':' . trim($idempotencyToken !== '' ? $idempotencyToken : Security::token(16)));
        $existing = $this->database->fetchOne('SELECT * FROM swaps WHERE idempotency_key = ?', [$idemKey]);
        if ($existing) {
            return $existing;
        }
        $swapId = Security::uuid();
        $fromEntryId = $this->ledger->createEntry('adjustment', 'Currency swap (out)', $userId, $idemKey);
        $toEntryId = $this->ledger->createEntry('adjustment', 'Currency swap (in)', $userId, $idemKey . ':in');
        $fromTxId = Security::uuid();
        $toTxId = Security::uuid();
        $now = gmdate('Y-m-d H:i:s.u');
        $this->database->transaction(function (Database $db) use ($userId, $fromAccount, $toAccount, $fromAsset, $toAsset, $preview, $fromEntryId, $toEntryId, $swapId, $fromTxId, $toTxId, $idemKey, $now): void {
            // From side: customer pays amount + fee, revenue receives fee, system clearing balances.
            $this->ledger->post([
                ['ledger_account_id' => (string) $fromAccount['ledger_account_id'], 'side' => 'debit', 'amount_minor' => $preview['from_amount_minor'] + $preview['fee_minor']],
                ['ledger_account_id' => $this->systemAccountId((string) $fromAsset['id'], 'revenue'), 'side' => 'credit', 'amount_minor' => $preview['fee_minor']],
                ['ledger_account_id' => $this->systemAccountId((string) $fromAsset['id'], 'asset'), 'side' => 'credit', 'amount_minor' => $preview['from_amount_minor']],
            ], $userId, $fromEntryId);
            // To side: system clearing pays out, customer receives converted amount.
            $this->ledger->post([
                ['ledger_account_id' => $this->systemAccountId((string) $toAsset['id'], 'asset'), 'side' => 'debit', 'amount_minor' => $preview['to_amount_minor']],
                ['ledger_account_id' => (string) $toAccount['ledger_account_id'], 'side' => 'credit', 'amount_minor' => $preview['to_amount_minor']],
            ], $userId, $toEntryId);
            $db->execute(
                'INSERT INTO swaps (id, user_id, from_account_id, to_account_id, from_asset_id, to_asset_id, from_amount_minor, to_amount_minor, fee_minor, from_rate, to_rate, idempotency_key, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$swapId, $userId, $fromAccount['id'], $toAccount['id'], $fromAsset['id'], $toAsset['id'], $preview['from_amount_minor'], $preview['to_amount_minor'], $preview['fee_minor'], $preview['from_rate'], $preview['to_rate'], $idemKey, 'completed', $now]
            );
            $db->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, counterparty_label, description, metadata, completed_at, swap_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$fromTxId, $userId, $fromAccount['id'], $fromEntryId, Security::reference('SWP'), 'adjustment', 'debit', $preview['from_amount_minor'], $preview['fee_minor'], 'completed', (string) $toAsset['code'], 'Swap to ' . (string) $toAsset['code'], json_encode(['kind' => 'swap', 'swap_id' => $swapId, 'from' => (string) $fromAsset['code'], 'to' => (string) $toAsset['code'], 'to_amount_minor' => $preview['to_amount_minor']], JSON_THROW_ON_ERROR), $now, $swapId]
            );
            $db->execute(
                'INSERT INTO transactions (id, user_id, account_id, journal_entry_id, public_reference, transaction_type, direction, amount_minor, fee_minor, status, counterparty_label, description, metadata, completed_at, swap_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$toTxId, $userId, $toAccount['id'], $toEntryId, Security::reference('SWP'), 'adjustment', 'credit', $preview['to_amount_minor'], 0, 'completed', (string) $fromAsset['code'], 'Swap from ' . (string) $fromAsset['code'], json_encode(['kind' => 'swap', 'swap_id' => $swapId, 'from' => (string) $fromAsset['code'], 'to' => (string) $toAsset['code'], 'from_amount_minor' => $preview['from_amount_minor']], JSON_THROW_ON_ERROR), $now, $swapId]
            );
        });
        return $this->database->fetchOne('SELECT * FROM swaps WHERE id = ?', [$swapId]);
    }

    // Expose positiveAmount for routes (it's private in BankingService).
    public function positiveAmountPublic(string $amount, int $scale): int
    {
        return $this->banking->positiveAmountPublic($amount, $scale);
    }

    private function systemAccountId(string $assetId, string $accountClass): string
    {
        $row = $this->database->fetchOne("SELECT id FROM ledger_accounts WHERE asset_id = ? AND owner_type = 'system' AND account_class = ?", [$assetId, $accountClass]);
        if (!$row) {
            throw new RuntimeException('The asset ledger is incomplete. Contact support.');
        }
        return (string) $row['id'];
    }
}