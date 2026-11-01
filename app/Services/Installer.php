<?php

declare(strict_types=1);

namespace Wayfairs\Services;

use Wayfairs\Core\Database;
use Wayfairs\Core\Security;

final class Installer
{
    public function install(array $input): void
    {
        $email = mb_strtolower(trim((string) ($input['admin_email'] ?? '')), 'UTF-8');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Enter a valid administrator email address.');
        }
        if (strlen((string) ($input['admin_password'] ?? '')) < 12) {
            throw new \RuntimeException('Administrator password must be at least 12 characters.');
        }
        $driver = (string) ($input['db_driver'] ?? '');
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new \RuntimeException('Choose a supported database driver.');
        }
        $config = [
            'installed' => false,
            'environment' => 'production',
            'base_path' => trim((string) ($input['base_path'] ?? ''), " \t\n\r\0\x0B/"),
            'app_key' => base64_encode(random_bytes(32)),
            'database' => $driver === 'sqlite'
                ? ['driver' => 'sqlite', 'path' => WAYFAIRS_ROOT . '/storage/wayfairs.sqlite']
                : [
                    'driver' => 'mysql',
                    'host' => trim((string) ($input['db_host'] ?? '127.0.0.1')),
                    'port' => (int) ($input['db_port'] ?? 3306),
                    'database' => trim((string) ($input['db_name'] ?? '')),
                    'username' => trim((string) ($input['db_user'] ?? '')),
                    'password' => (string) ($input['db_password'] ?? ''),
                    'charset' => 'utf8mb4',
                ],
            'session' => [
                'cookie' => 'wayfairs_session',
                'lifetime_minutes' => 720,
                'secure' => $this->isHttps(),
            ],
        ];
        \Wayfairs\Core\Config::write($config);
        \Wayfairs\Core\Config::reset();
        $database = new Database($config['database']);
        (new \Wayfairs\Core\Migrator($database))->migrate();
        $this->seedSettings($database, $input);
        $this->seedAdministrator($database, $input, $email);
        $this->seedAssets($database, $input);
        $final = $config;
        $final['installed'] = true;
        \Wayfairs\Core\Config::write($final);
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    private function seedSettings(Database $database, array $input): void
    {
        $settings = [
            ['site.name', 'Wayfairs', 1],
            ['site.tagline', 'Your money. Multiple currencies. One place.', 1],
            ['site.support_email', trim((string) ($input['support_email'] ?? '')), 1],
            ['site.support_phone', trim((string) ($input['support_phone'] ?? '')), 1],
            ['site.address', trim((string) ($input['address'] ?? '')), 1],
            ['site.primary_color', '#0f8b6d', 1],
            ['site.accent_color', '#f0b429', 1],
            ['site.deposit_instructions_fiat', 'Use your bank transfer reference exactly as shown. Deposits are reviewed and credited only after observed receipt.', 1],
            ['site.deposit_instructions_crypto', 'Send only to the assigned address and network. Deposits are reviewed after the required confirmation policy.', 1],
            ['site.withdrawal_notice', 'Withdrawals are processed after administrative review and external settlement. Keep your destination details current.', 1],
            ['site.kyc_notice', 'Identity verification is required before deposits, withdrawals, and transfers above configured limits.', 1],
            ['site.footer', 'Multi-currency accounts, international payments, and invoices in one place.', 1],
        ];
        foreach ($settings as [$key, $value, $public]) {
            $database->execute('INSERT INTO site_settings (setting_key, setting_value, is_public, updated_by) VALUES (?, ?, ?, NULL)', [$key, $value, $public]);
        }
    }

    private function seedAdministrator(Database $database, array $input, string $email): void
    {
        $id = Security::uuid();
        $database->execute(
            'INSERT INTO users (id, role, email, email_normalized, password_hash, first_name, last_name, status, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                'admin',
                $email,
                $email,
                Security::passwordHash((string) $input['admin_password']),
                trim((string) ($input['admin_first_name'] ?? 'Wayfairs')),
                trim((string) ($input['admin_last_name'] ?? 'Administrator')),
                'active',
                gmdate('Y-m-d H:i:s.u'),
            ]
        );
        $database->execute('INSERT INTO kyc_profiles (user_id, status) VALUES (?, ?)', [$id, 'verified']);
    }

    private function seedAssets(Database $database, array $input): void
    {
        $assets = [
            ['USD', 'US Dollar', 'fiat', '$', 2, 0, 0, 0, 0, null],
            ['BTC', 'Bitcoin', 'crypto', '₿', 8, 0, 0, 0, 0, 'bitcoin'],
            ['ETH', 'Ethereum', 'crypto', 'Ξ', 18, 0, 0, 0, 0, 'ethereum'],
        ];
        foreach ($assets as [$code, $name, $type, $symbol, $scale, $minDeposit, $minWithdrawal, $withdrawalFee, $transferFee, $providerReference]) {
            $assetId = Security::uuid();
            $systemLedgerId = Security::uuid();
            $database->execute(
                'INSERT INTO assets (id, code, name, asset_type, symbol, scale, min_deposit_minor, min_withdrawal_minor, withdrawal_fee_minor, transfer_fee_minor, usd_provider_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$assetId, $code, $name, $type, $symbol, $scale, $minDeposit, $minWithdrawal, $withdrawalFee, $transferFee, $providerReference]
            );
            $database->execute(
                'INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
                [$systemLedgerId, $assetId, 'system', 'SYSTEM:' . $code, 'System ' . $code . ' control', 'asset', 'debit']
            );
            $database->execute(
                'INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
                [Security::uuid(), $assetId, 'system', 'SYSTEM:' . $code . ':REVENUE', 'System ' . $code . ' revenue', 'revenue', 'credit']
            );
        }
    }
}
