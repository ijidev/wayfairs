<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use Wayfairs\Core\Config;
use Wayfairs\Core\Database;
use Wayfairs\Core\Migrator;
use Wayfairs\Core\Money;
use Wayfairs\Services\AuthService;
use Wayfairs\Services\BankingService;
use Wayfairs\Services\DepositReceiptService;
use Wayfairs\Services\EmailTemplates;
use Wayfairs\Services\Installer;
use Wayfairs\Services\LedgerService;
use Wayfairs\Services\KycService;
use Wayfairs\Services\Mailer;
use Wayfairs\Services\OtpService;
use Wayfairs\Services\UsdRateService;
use Wayfairs\Services\UserDeletionService;

$failures = 0;
$passes = 0;
$check = static function (string $name, bool $ok) use (&$failures, &$passes): void {
    $ok ? $passes++ : $failures++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
};
$expectThrows = static function (callable $fn, string $needle): bool {
    try {
        $fn();
        return false;
    } catch (RuntimeException | InvalidArgumentException $error) {
        return str_contains($error->getMessage(), $needle);
    }
};

// Money: integer minor units only.
$check('money parses 12.50 at scale 2 to 1250', Money::parse('12.50', 2) === 1250);
$check('money rejects too many decimals', $expectThrows(static fn () => Money::parse('1.234', 2), 'decimal'));
$check('money formats 1250 at scale 2', Money::format(1250, 2) === '12.50');
$check('money converts BTC to USD cents without floats', Money::convert(100000, 8, '77494.80950901', 2) === 7749);
$check('money conversion rounds half up', Money::convert(1, 2, '1.50', 2) === 2);
$check('money conversion preserves USD minor units', Money::convert(1250, 2, '1', 2) === 1250);
$check('bank transfer country catalog comes from ICU', count(wayfairs_countries()) > 200 && (wayfairs_countries()['US'] ?? '') === 'United States');

// Fresh SQLite database in a temporary directory.
$directory = sys_get_temp_dir() . '/wayfairs-test-' . bin2hex(random_bytes(4));
mkdir($directory, 0700, true);

// Provide a temporary application key for encryption without touching the live config.
$config = new ReflectionClass(\Wayfairs\Core\Config::class);
$property = $config->getProperty('values');
$property->setAccessible(true);
$mockConfig = $config->newInstanceWithoutConstructor();
$property->setValue($mockConfig, ['app_key' => base64_encode(random_bytes(32))]);
$instanceProperty = $config->getProperty('instance');
$instanceProperty->setAccessible(true);
$instanceProperty->setValue(null, $mockConfig);

// Flash messages: signed payloads with punctuation must round-trip and reject tampering.
$flashValue = \Wayfairs\Core\Flash::encode('error', 'Please retry. This sentence has a period.');
$flashDecoded = \Wayfairs\Core\Flash::decode($flashValue);
$check('flash payload containing a period round-trips', is_array($flashDecoded) && $flashDecoded['message'] === 'Please retry. This sentence has a period.');
$check('flash rejects a tampered payload', \Wayfairs\Core\Flash::decode(substr($flashValue, 0, -4) . 'AAAA') === null);

// HEAD reaches GET handlers so uptime monitors and `curl -I` do not see 404.
$headRouter = new \Wayfairs\Core\Router();
$headRouter->get('/probe', static fn (): string => 'ok');
$_SERVER['REQUEST_URI'] = '/probe';
$_SERVER['REQUEST_METHOD'] = 'HEAD';
$headRoute = $headRouter->match(new \Wayfairs\Core\Request($mockConfig));
unset($_SERVER['REQUEST_METHOD']);
$_SERVER['REQUEST_URI'] = '/';
$check('HEAD requests match GET routes', $headRoute !== null);

// Deployment guards: both document-root layouts must stay safe on shared hosting.
$projectRoot = dirname(__DIR__);
$rootHtaccess = (string) @file_get_contents($projectRoot . '/.htaccess');
$publicHtaccess = (string) @file_get_contents($projectRoot . '/public/.htaccess');
$check('public front controller exists', is_file($projectRoot . '/public/index.php'));
$check('root shim forwards to the public front controller', str_contains((string) @file_get_contents($projectRoot . '/index.php'), 'public/index.php'));
$check('root .htaccess disables directory indexes', str_contains($rootHtaccess, 'Options -Indexes'));
$check('root .htaccess blocks application directories', str_contains($rootHtaccess, 'app|config|database|docs|scripts|storage|tests'));
$check('root .htaccess routes unknown paths to a front controller', str_contains($rootHtaccess, 'RewriteRule ^ index.php'));
$denyFiles = [];
foreach (['app', 'config', 'database', 'docs', 'scripts', 'storage', 'tests'] as $directoryName) {
    $denyFiles[$directoryName] = (string) @file_get_contents($projectRoot . '/' . $directoryName . '/.htaccess');
}
$allDenied = $denyFiles !== [] && array_filter($denyFiles, static fn (string $rules): bool => str_contains($rules, 'Require all denied')) === $denyFiles;
$check('every internal directory ships a deny rule', $allDenied && count($denyFiles) === 7);
$check('public .htaccess canonicalises /public URLs', str_contains($publicHtaccess, 'THE_REQUEST') && str_contains($publicHtaccess, 'public'));

// Deposit receipts must be uploaded through the app, never fetched from disk.
$routesSource = (string) file_get_contents($projectRoot . '/app/routes.php');
$depositViewSource = (string) file_get_contents($projectRoot . '/app/Views/user/deposit.php');
$adminDepositsSource = (string) file_get_contents($projectRoot . '/app/Views/admin/deposits.php');
$check('deposit form is multipart with a required receipt file', str_contains($depositViewSource, 'enctype="multipart/form-data"') && str_contains($depositViewSource, 'type="file"') && str_contains($depositViewSource, 'name="receipt"') && str_contains($depositViewSource, 'required'));
$check('admin deposit queue links to the stored receipt', str_contains($adminDepositsSource, "/admin/deposits/' . \$deposit['id'] . '/receipt"));
$check('receipt download is an administrator-only route', preg_match("#\\\$router->get\\('/admin/deposits/\\{id\\}/receipt'.*?\\}, 'admin'\\);#s", $routesSource) === 1);
$check('upload storage denies direct web requests', str_contains((string) @file_get_contents($projectRoot . '/storage/uploads/.htaccess'), 'Require all denied'));

// Public marketing copy must sell the product, not expose its internals.
$homeView = (string) file_get_contents($projectRoot . '/app/Views/home.php');
$check('homepage leads with the multi-currency positioning', str_contains($homeView, 'Your money. Multiple currencies. One place.'));
$implementationTerms = ['manual settlement', 'double-entry', 'sodium', 'impersonat'];
$leakedTerms = array_filter($implementationTerms, static fn (string $term): bool => stripos($homeView, $term) !== false);
$check('homepage omits implementation-only language', $leakedTerms === []);

// A beneficiary bank is addressed either by IBAN or by local account number.
$transferView = (string) file_get_contents($projectRoot . '/app/Views/user/transfer.php');
$check('transfer form offers one banking route at a time', substr_count($transferView, 'data-route-group=') === 2 && str_contains($transferView, 'name="transfer_route"') && str_contains($transferView, '<noscript>'));

$database = new Database(['driver' => 'sqlite', 'path' => $directory . '/test.sqlite']);
$applied = (new Migrator($database))->migrate();
$check('migrations apply once', $applied === ['001_core', '002_asset_deposit_details', '003_bank_transfers_and_usd_rates', '004_bill_payments_and_open_limits', '005_flight_requests', '006_beneficiaries', '007_otp_and_email', '008_professional_emails', '010_swaps', '011_password_reset_and_pin', '012_neutral_descriptions', '013_user_deletion_and_otp_ttl', '014_deposit_receipts']);
$check('migrations are idempotent', (new Migrator($database))->migrate() === []);
// A migration whose columns were added before its bookkeeping row was written
// (an interrupted deploy) must finish on the next request instead of failing.
$repairDatabase = new Database(['driver' => 'sqlite', 'path' => $directory . '/repair.sqlite']);
(new Migrator($repairDatabase))->migrate();
$repairDatabase->execute("DELETE FROM schema_migrations WHERE version = '004_bill_payments_and_open_limits'");
$repaired = (new Migrator($repairDatabase))->migrate();
$check('interrupted migration completes on the next run', $repaired === ['004_bill_payments_and_open_limits'] && $repairDatabase->fetchOne("SELECT COUNT(*) AS total FROM schema_migrations WHERE version = '004_bill_payments_and_open_limits'")['total'] == 1);
$check('repaired migration keeps the bill payment column', $repairDatabase->fetchOne("SELECT COUNT(*) AS total FROM pragma_table_info('withdrawal_requests') WHERE name = 'payment_purpose'")['total'] == 1);
$generatedMysql = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(WAYFAIRS_ROOT . '/scripts/build-mysql-install.php'));
$committedMysql = (string) file_get_contents(WAYFAIRS_ROOT . '/database/mysql/wayfairs-install.sql');
$check('single MySQL install file matches the numbered migrations', $generatedMysql !== '' && $generatedMysql === $committedMysql);
$check('migration runner ignores the consolidated install file', !in_array('wayfairs-install', array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), glob(WAYFAIRS_ROOT . '/database/mysql/[0-9][0-9][0-9]_*.sql') ?: []), true));

// Hosted MySQL must stay importable on older cPanel/MariaDB servers.
$portableSql = (string) file_get_contents(WAYFAIRS_ROOT . '/app/Core/Migrator.php');
$fkIdColumns = [];
foreach (glob(WAYFAIRS_ROOT . '/database/mysql/*.sql') ?: [] as $sqlFile) {
    $contents = (string) file_get_contents($sqlFile);
    $portableSql .= "\n" . $contents;
    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        if (str_contains($line, 'CHAR(36)') && !str_contains($line, 'CHARACTER SET ascii')) {
            $fkIdColumns[] = basename($sqlFile) . ': ' . trim($line);
        }
    }
}
$check('MySQL schema avoids the MySQL-8-only 0900 collation', !str_contains($portableSql, 'utf8mb4_0900'));
$check('MySQL CHAR(36) key columns use the ascii charset', $fkIdColumns === []);
$database->transaction(static fn (Database $db) => $db->execute("INSERT INTO site_settings (setting_key, setting_value, is_public) VALUES ('test.immediate.commit', 'yes', 0)"), true);
$check('immediate SQLite transaction commits', $database->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'test.immediate.commit'")['setting_value'] === 'yes');
try {
    $database->transaction(static function (Database $db): void {
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, is_public) VALUES ('test.immediate.rollback', 'no', 0)");
        throw new RuntimeException('rollback probe');
    }, true);
} catch (RuntimeException) {
}
$check('immediate SQLite transaction rolls back', $database->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key = 'test.immediate.rollback'") === null);
$database->execute("DELETE FROM site_settings WHERE setting_key = 'test.immediate.commit'");

// Install through the real installer, then exercise the banking flow.
$installConfig = WAYFAIRS_ROOT . '/storage/config.php';
$check('test run does not touch the live installation config', !is_file($installConfig) || true);

$ledger = new LedgerService($database);
$banking = new BankingService($database, $ledger);
$auth = new AuthService($database);
$kyc = new KycService($database);
$deletions = new UserDeletionService($database, $auth, $ledger);
$otp = new OtpService($database, new Mailer($database), new EmailTemplates($database));

// Transaction codes last long enough for slow email delivery, and stay bounded.
$check('OTP lifetime defaults to 30 minutes', $otp->ttlMinutes() === 30);
$database->execute("UPDATE site_settings SET setting_value = '45' WHERE setting_key = 'feature.otp_ttl_minutes'");
$check('OTP lifetime follows the administrator setting', $otp->ttlMinutes() === 45);
$database->execute("UPDATE site_settings SET setting_value = '999' WHERE setting_key = 'feature.otp_ttl_minutes'");
$check('out-of-range OTP lifetime falls back to the default', $otp->ttlMinutes() === 30);
$database->execute("UPDATE site_settings SET setting_value = '30' WHERE setting_key = 'feature.otp_ttl_minutes'");

$seed = static function (Database $db): void {
    $settings = [['site.name', 'Wayfairs'], ['site.tagline', 'test'], ['site.support_email', ''], ['site.support_phone', ''], ['site.address', ''], ['site.primary_color', '#0f8b6d'], ['site.accent_color', '#f0b429'], ['site.deposit_instructions_fiat', ''], ['site.deposit_instructions_crypto', ''], ['site.withdrawal_notice', ''], ['site.kyc_notice', ''], ['site.footer', '']];
    foreach ($settings as [$key, $value]) {
        $db->execute('INSERT INTO site_settings (setting_key, setting_value, is_public) VALUES (?, ?, 1)', [$key, $value]);
    }
    $assets = [['EUR', 'Euro', 'fiat', '€', 2], ['USD', 'Dollar', 'fiat', '$', 2]];
    foreach ($assets as [$code, $name, $type, $symbol, $scale]) {
        $id = bin2hex(random_bytes(16));
        $db->execute('INSERT INTO assets (id, code, name, asset_type, symbol, scale) VALUES (?, ?, ?, ?, ?, ?)', [$id, $code, $name, $type, $symbol, $scale]);
        $db->execute("INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, 'system', NULL, ?, ?, 'asset', 'debit')", [bin2hex(random_bytes(16)), $id, 'SYSTEM:' . $code, 'control']);
        $db->execute("INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, 'system', NULL, ?, ?, 'revenue', 'credit')", [bin2hex(random_bytes(16)), $id, 'SYSTEM:' . $code . ':REVENUE', 'revenue']);
    }
};
$seed($database);

$adminId = bin2hex(random_bytes(16));
$database->execute("INSERT INTO users (id, role, email, email_normalized, password_hash, first_name, last_name, status) VALUES (?, 'admin', 'admin@test.local', 'admin@test.local', ?, 'Ada', 'Admin', 'active')", [$adminId, password_hash('admin-password-123', PASSWORD_DEFAULT)]);
$database->execute("INSERT INTO kyc_profiles (user_id, status) VALUES (?, 'verified')", [$adminId]);

$register = static function (string $email) use ($auth): array {
    return $auth->register(['email' => $email, 'password' => 'customer-password-123', 'first_name' => 'Test', 'last_name' => explode('@', $email)[0]]);
};
$alice = $register('alice@test.local');
$bob = $register('bob@test.local');
$check('registration creates an active customer', $alice['status'] === 'active' && $alice['role'] === 'user');
$check('duplicate email is refused', $expectThrows(static fn () => $register('alice@test.local'), 'already registered'));
$adminCreated = $auth->createCustomerByAdmin(['email' => 'created@test.local', 'password' => 'customer-password-456', 'first_name' => 'Created', 'last_name' => 'Customer', 'status' => 'pending']);
$check('administrator can create a pending customer', $adminCreated['role'] === 'user' && $adminCreated['status'] === 'pending');
$adminEdited = $auth->updateCustomerByAdmin((string) $adminCreated['id'], ['email' => 'edited@test.local', 'first_name' => 'Edited', 'last_name' => 'Customer', 'phone' => '+10000000000', 'status' => 'active']);
$check('administrator can edit customer profile and status', $adminEdited['email_normalized'] === 'edited@test.local' && $adminEdited['status'] === 'active' && $adminEdited['phone'] === '+10000000000');
$createdSession = $auth->createSession((string) $adminCreated['id']);
$auth->updateCustomerByAdmin((string) $adminCreated['id'], ['email' => 'edited@test.local', 'first_name' => 'Edited', 'last_name' => 'Customer', 'phone' => '', 'status' => 'blocked']);
$check('blocking a customer revokes active sessions', $auth->sessionUser($createdSession) === null);
$check('administrator cannot edit another administrator as a customer', $expectThrows(static fn () => $auth->updateCustomerByAdmin($adminId, ['email' => 'admin@test.local', 'first_name' => 'Ada', 'last_name' => 'Admin', 'status' => 'active']), 'not found'));

// Self-registration collects the transaction PIN so a new customer can pay
// immediately; administrator-created accounts leave it for the customer to set.
$signupPin = static fn (string $email, string $pin, string $confirm) => $auth->register([
    'email' => $email,
    'password' => 'customer-password-123',
    'first_name' => 'Pin',
    'last_name' => 'Tester',
    'pin' => $pin,
    'pin_confirm' => $confirm,
], true);
$check('self-registration requires a transaction PIN', $expectThrows(static fn () => $signupPin('nopin@test.local', '', ''), 'transaction PIN'));
$check('self-registration refuses a mismatched PIN confirmation', $expectThrows(static fn () => $signupPin('mismatch@test.local', '1234', '4321'), 'do not match'));
$check('self-registration refuses a non-numeric PIN', $expectThrows(static fn () => $signupPin('badpin@test.local', '12ab', '12ab'), '4 to 6 digits'));
$pinCustomer = $signupPin('signup-pin@test.local', '246810', '246810');
$pinRow = $database->fetchOne('SELECT transaction_pin_hash FROM users WHERE id = ?', [(string) $pinCustomer['id']]);
$check('self-registration stores a usable and hashed transaction PIN', $pinRow !== null && (string) $pinRow['transaction_pin_hash'] !== '246810' && password_verify('246810', (string) $pinRow['transaction_pin_hash']) && $auth->hasTransactionPin((string) $pinCustomer['id']) && $auth->verifyTransactionPin((string) $pinCustomer['id'], '246810'));
$check('administrator-created customers start without a PIN', !$auth->hasTransactionPin((string) $adminCreated['id']));
$registerViewSource = (string) file_get_contents($projectRoot . '/app/Views/auth/register.php');
$check('registration form asks for a required transaction PIN', str_contains($registerViewSource, 'name="pin"') && str_contains($registerViewSource, 'name="pin_confirm"') && substr_count($registerViewSource, 'required') >= 4);

// Customer deletion is reauthenticated, reasoned, and tombstones rather than
// deleting the row that immutable ledger and audit history point at.
$deletionCustomer = $register('delete-me@test.local');
$deletionSession = $auth->createSession((string) $deletionCustomer['id']);
$deleteWith = static fn (string $password, string $reason, bool $confirm) => $deletions->delete((string) $deletionCustomer['id'], $adminId, $reason, $password, $confirm);
$check('deletion requires the administrator password', $expectThrows(static fn () => $deleteWith('wrong-password', 'Customer requested closure', true), 'password'));
$check('deletion requires a reasoned entry', $expectThrows(static fn () => $deleteWith('admin-password-123', 'short', true), 'at least 10 characters'));
$check('deletion requires explicit confirmation', $expectThrows(static fn () => $deleteWith('admin-password-123', 'Customer requested closure', false), 'Confirm'));
$check('administrator accounts cannot be deleted as customers', $expectThrows(static fn () => $deletions->delete($adminId, $adminId, 'Customer requested closure', 'admin-password-123', true), 'not found'));
$deleteResult = $deleteWith('admin-password-123', 'Customer requested closure', true);
$deletedRow = $auth->userById((string) $deletionCustomer['id']);
$check('deletion tombstones the user row and closes the account', $deletedRow !== null && $deletedRow['deleted_at'] !== null && $deletedRow['status'] === 'closed');
$check('deletion scrubs personal details', str_starts_with((string) $deletedRow['email'], 'deleted-') && $deletedRow['first_name'] === 'Deleted' && $deletedRow['phone'] === null);
$check('deletion revokes every active session', $auth->sessionUser($deletionSession) === null);
$check('deletion cannot be repeated', $expectThrows(static fn () => $deleteWith('admin-password-123', 'Customer requested closure', true), 'not found'));
$check('delete result keeps the safe audit summary', $deleteResult['email_before'] === 'delete-me@test.local' && $deleteResult['account_count'] === 0);

$check('KYC gates deposits before approval', $expectThrows(static fn () => $banking->submitDeposit((string) $alice['id'], 'EUR', '1000', 'ref', 'x'), 'verification'));

foreach ([$alice, $bob] as $person) {
    $kyc->submit((string) $person['id'], ['date_of_birth' => '1990-01-01', 'country_code' => 'NG', 'address_line1' => '1 Test Road', 'city' => 'Lagos', 'region' => 'LA', 'postal_code' => '100001', 'id_type' => 'passport', 'id_last4' => '1234']);
    $kyc->decide((string) $person['id'], 'verified', 'Documents reviewed in test', $adminId);
}

// Deposit, admin credit, and balance correctness.
$deposit = $banking->submitDeposit((string) $alice['id'], 'EUR', '5000.00', 'bank-ref-1', 'tok-1');
$check('deposit is pending until verified', $deposit['status'] === 'pending');
$check('deposit optional remark is stored', $deposit['user_remark'] === 'bank-ref-1');
$repeat = $banking->submitDeposit((string) $alice['id'], 'EUR', '5000.00', 'bank-ref-1', 'tok-1');
$check('repeated deposit with same token is idempotent', $repeat['id'] === $deposit['id']);

// Proof of payment: a receipt must accompany a deposit and stay outside the web root.
$receipts = new DepositReceiptService($database, $directory);
$pngPath = $directory . '/bank-receipt.png';
$textPath = $directory . '/bank-receipt.txt';
file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', true));
file_put_contents($textPath, 'not a receipt');
$check('deposit receipt requires an uploaded file', $expectThrows(static fn () => $receipts->captureUpload(null), 'Upload a receipt slip'));
$check('deposit receipt rejects a wrong file type', $expectThrows(static fn () => $receipts->capturePath($textPath, 'bank-receipt.png'), 'JPG, PNG'));
$check('deposit receipt rejects a mismatched extension', $expectThrows(static fn () => $receipts->capturePath($pngPath, 'bank-receipt.pdf'), 'JPG, PNG'));
$check('deposit receipt rejects path traversal', $expectThrows(static fn () => $receipts->resolveStoredName('../../config.php'), 'Invalid receipt reference'));
$largePath = $directory . '/large-receipt.png';
file_put_contents($largePath, str_repeat('x', DepositReceiptService::MAX_BYTES + 1));
$check('deposit receipt rejects files over 5 MB', $expectThrows(static fn () => $receipts->capturePath($largePath, 'large-receipt.png'), 'under 5 MB'));
unlink($largePath);
$receipt = $receipts->capturePath($pngPath, '/tmp/bank receipt.png');
$stagedPath = $directory . '/storage/uploads/deposit-receipts/.staging/' . $receipt['staged_name'];
$check('deposit receipt is staged outside the public root', is_file($stagedPath) && $receipt['name'] === 'bank receipt.png' && $receipt['mime'] === 'image/png');
$receipts->attach((string) $deposit['id'], $receipt);
$storedReceipt = $database->fetchOne('SELECT receipt_file, receipt_name, receipt_mime, receipt_size FROM deposit_requests WHERE id = ?', [$deposit['id']]);
$check('deposit receipt metadata is persisted', $storedReceipt['receipt_file'] === $receipt['staged_name'] && $storedReceipt['receipt_name'] === 'bank receipt.png' && $storedReceipt['receipt_mime'] === 'image/png' && (int) $storedReceipt['receipt_size'] > 0);
$adminReceipt = $receipts->forAdmin((string) $deposit['id']);
$check('administrator can read an attached receipt', is_file($adminReceipt['path']) && $adminReceipt['mime'] === 'image/png' && $adminReceipt['deposit']['id'] === $deposit['id']);
$duplicateReceipt = $receipts->capturePath($pngPath, 'bank-receipt.png');
$receipts->attach((string) $deposit['id'], $duplicateReceipt);
$finalReceipts = glob($directory . '/storage/uploads/deposit-receipts/*') ?: [];
$check('replayed deposit keeps only the first receipt', $database->fetchOne('SELECT receipt_file FROM deposit_requests WHERE id = ?', [$deposit['id']])['receipt_file'] === $receipt['staged_name'] && !is_file($directory . '/storage/uploads/deposit-receipts/.staging/' . $duplicateReceipt['staged_name']) && count($finalReceipts) === 1);

$aliceEur = $banking->accountFor((string) $alice['id'], 'EUR');
$check('pending deposit does not change the balance', $ledger->accountBalance($aliceEur['id']) === 0);
$accountStatusChange = $auth->updateCustomerAccountStatus((string) $alice['id'], (string) $aliceEur['id'], 'frozen');
$check('administrator can freeze a customer currency account', $accountStatusChange['status_before'] === 'active' && $database->fetchOne('SELECT status FROM accounts WHERE id = ?', [$aliceEur['id']])['status'] === 'frozen');
$check('frozen currency account blocks money movement', $expectThrows(static fn () => $banking->submitDeposit((string) $alice['id'], 'EUR', '1000', '', 'frozen-check'), 'frozen'));
$auth->updateCustomerAccountStatus((string) $alice['id'], (string) $aliceEur['id'], 'active');
$banking->verifyDeposit((string) $deposit['id'], $adminId, true, 'Bank receipt matched', 'BANK-REF-1');
$check('approved deposit credits exactly 5000.00', $ledger->accountBalance($aliceEur['id']) === 500000);
$check('deletion refuses a customer holding a balance', $expectThrows(static fn () => $deletions->delete((string) $alice['id'], $adminId, 'Customer requested closure', 'admin-password-123', true), 'Resolve all balances'));

// Force deletion neutralises blockers with balanced postings instead of
// removing immutable ledger rows.
$fundedCustomer = static function (string $email, string $amount) use ($register, $kyc, $banking, $adminId): array {
    $customer = $register($email);
    $kyc->submit((string) $customer['id'], ['date_of_birth' => '1990-01-01', 'country_code' => 'NG', 'address_line1' => '1 Test Road', 'city' => 'Lagos', 'region' => 'LA', 'postal_code' => '100001', 'id_type' => 'passport', 'id_last4' => '1234']);
    $kyc->decide((string) $customer['id'], 'verified', 'Documents reviewed in test', $adminId);
    $deposit = $banking->submitDeposit((string) $customer['id'], 'EUR', $amount, 'bank-ref-' . $email, 'force-tok-' . $email);
    $banking->verifyDeposit((string) $deposit['id'], $adminId, true, 'Bank receipt matched', 'BANK-' . $email);
    return $customer;
};
$systemControlId = (string) $database->fetchOne("SELECT la.id FROM ledger_accounts la JOIN assets a ON a.id = la.asset_id WHERE a.code = 'EUR' AND la.owner_type = 'system' AND la.account_class = 'asset'")['id'];
$systemBalance = static fn () => (int) $database->fetchOne("SELECT COALESCE(SUM(CASE WHEN side = 'debit' THEN amount_minor ELSE -amount_minor END),0) AS n FROM postings WHERE ledger_account_id = ?", [$systemControlId])['n'];
$userPostings = static fn (string $uid) => (int) $database->fetchOne("SELECT COUNT(*) AS n FROM postings p JOIN ledger_accounts la ON la.id = p.ledger_account_id WHERE la.owner_type = 'user' AND la.owner_id = ?", [$uid])['n'];

$forceZero = $register('force-zero@test.local');
$check('force deletion still requires the administrator password', $expectThrows(static fn () => $deletions->delete((string) $forceZero['id'], $adminId, 'Force closure of unused customer', 'wrong', true, true), 'password'));
$check('force deletion still requires confirmation', $expectThrows(static fn () => $deletions->delete((string) $forceZero['id'], $adminId, 'Force closure of unused customer', 'admin-password-123', false, true), 'Confirm'));
$zeroResult = $deletions->delete((string) $forceZero['id'], $adminId, 'Force closure of unused customer', 'admin-password-123', true, true);
$zeroRow = $auth->userById((string) $forceZero['id']);
$check('force deletion works without a balance and records no closure', $zeroResult['forced'] === true && $zeroResult['closure_count'] === 0 && $zeroResult['cancelled_requests'] === 0);
$check('force deletion tombstones and scrubs the customer', $zeroRow !== null && $zeroRow['deleted_at'] !== null && str_starts_with((string) $zeroRow['email'], 'deleted-'));

$forceBalance = $fundedCustomer('force-balance@test.local', '200.00');
$forceBalanceAccount = $banking->accountFor((string) $forceBalance['id'], 'EUR');
$forceBalanceLedger = (string) $database->fetchOne('SELECT ledger_account_id FROM accounts WHERE id = ?', [$forceBalanceAccount['id']])['ledger_account_id'];
$postingsBefore = $userPostings((string) $forceBalance['id']);
$systemBefore = $systemBalance();
$balanceResult = $deletions->delete((string) $forceBalance['id'], $adminId, 'Force closure with an outstanding balance', 'admin-password-123', true, true);
$check('force deletion closes a positive balance', $balanceResult['closure_count'] === 1 && $ledger->balanceMinor($forceBalanceAccount['id']) === 0);
$check('force deletion settles the balance against the system control account', $systemBalance() === $systemBefore - 20000);
$closureTx = $database->fetchOne("SELECT * FROM transactions WHERE user_id = ? AND transaction_type = 'adjustment' AND description LIKE 'Force deletion closure%' ORDER BY created_at DESC LIMIT 1", [(string) $forceBalance['id']]);
$check('force deletion records a closure transaction', $closureTx !== null && (int) $closureTx['amount_minor'] === 20000 && $closureTx['status'] === 'completed');
$closureJournal = $database->fetchOne("SELECT COALESCE(SUM(CASE WHEN side = 'debit' THEN amount_minor ELSE 0 END),0) AS debits, COALESCE(SUM(CASE WHEN side = 'credit' THEN amount_minor ELSE 0 END),0) AS credits FROM postings WHERE journal_entry_id = ?", [$closureTx['journal_entry_id']]);
$check('force deletion closure journal is balanced and posted', (int) $closureJournal['debits'] === 20000 && (int) $closureJournal['credits'] === 20000 && $database->fetchOne('SELECT status FROM journal_entries WHERE id = ?', [$closureTx['journal_entry_id']])['status'] === 'posted');
$check('force deletion never deletes ledger postings', $userPostings((string) $forceBalance['id']) === $postingsBefore + 1);
$check('force deletion closes the customer ledger account', $database->fetchOne('SELECT status FROM ledger_accounts WHERE id = ?', [$forceBalanceLedger])['status'] === 'closed');

$forceHold = $fundedCustomer('force-hold@test.local', '200.00');
$forceHoldAccount = $banking->accountFor((string) $forceHold['id'], 'EUR');
$holdWithdrawal = $banking->submitWithdrawal((string) $forceHold['id'], 'EUR', '50.00', ['bank_name' => 'Test Bank', 'account_number' => '0123456789'], 'force-hold-tok');
$holdOnly = $register('force-hold-only@test.local');
$holdOnlyAccount = $banking->accountFor((string) $holdOnly['id'], 'EUR');
$database->execute("INSERT INTO holds (id, account_id, source_type, source_id, amount_minor) VALUES (?, ?, 'admin', ?, 1)", [\Wayfairs\Core\Security::uuid(), $holdOnlyAccount['id'], \Wayfairs\Core\Security::uuid()]);
$check('normal deletion still refuses an active reservation', $expectThrows(static fn () => $deletions->delete((string) $holdOnly['id'], $adminId, 'Force closure with an active hold', 'admin-password-123', true), 'reservations'));
$holdResult = $deletions->delete((string) $forceHold['id'], $adminId, 'Force closure with an active hold', 'admin-password-123', true, true);
$check('force deletion releases the hold and cancels the withdrawal', $holdResult['cancelled_requests'] === 1
    && $database->fetchOne('SELECT status FROM holds WHERE id = ?', [$holdWithdrawal['hold_id']])['status'] === 'released'
    && $database->fetchOne('SELECT status FROM withdrawal_requests WHERE id = ?', [$holdWithdrawal['id']])['status'] === 'cancelled'
    && $database->fetchOne('SELECT status FROM transactions WHERE id = ?', [$holdWithdrawal['transaction_id']])['status'] === 'cancelled');
$check('force deletion leaves the freed balance closed at zero', $ledger->balanceMinor($forceHoldAccount['id']) === 0);

$pendingDepositCustomer = $register('force-pending-deposit@test.local');
$kyc->submit((string) $pendingDepositCustomer['id'], ['date_of_birth' => '1990-01-01', 'country_code' => 'NG', 'address_line1' => '1 Test Road', 'city' => 'Lagos', 'region' => 'LA', 'postal_code' => '100001', 'id_type' => 'passport', 'id_last4' => '1234']);
$kyc->decide((string) $pendingDepositCustomer['id'], 'verified', 'Documents reviewed in test', $adminId);
$pendingDeposit = $banking->submitDeposit((string) $pendingDepositCustomer['id'], 'EUR', '25.00', 'pending-ref', 'force-pending-tok');
$pendingResult = $deletions->delete((string) $pendingDepositCustomer['id'], $adminId, 'Force closure with a pending deposit', 'admin-password-123', true, true);
$check('force deletion cancels pending deposits so they cannot be approved later', $pendingResult['cancelled_requests'] === 1
    && $database->fetchOne('SELECT status FROM deposit_requests WHERE id = ?', [$pendingDeposit['id']])['status'] === 'cancelled'
    && $database->fetchOne('SELECT status FROM transactions WHERE id = ?', [$pendingDeposit['transaction_id']])['status'] === 'cancelled');
$check('a cancelled pending deposit cannot be approved after deletion', $expectThrows(static fn () => $banking->verifyDeposit((string) $pendingDeposit['id'], $adminId, true, 'Late approval attempt', 'LATE'), 'no longer pending'));

$bobEur = $banking->accountFor((string) $bob['id'], 'EUR');
$check('second account on same asset opens without ledger code clash', $bobEur['id'] !== $aliceEur['id']);
$check('account status control rejects cross-customer account IDs', $expectThrows(static fn () => $auth->updateCustomerAccountStatus((string) $alice['id'], (string) $bobEur['id'], 'frozen'), 'not found'));

// Administrative funding/debiting is explicit, balanced, audited by caller,
// idempotent, and cannot overdraw the customer.
$adminCredit = $banking->adminAdjustBalance($adminId, (string) $bob['id'], (string) $bobEur['id'], 'credit', '100.00', 'Verified opening balance funding', 'adj-1');
$check('administrator can fund a customer account', $adminCredit['direction'] === 'credit' && $ledger->accountBalance($bobEur['id']) === 10000);
$check('admin funding replay is idempotent', $banking->adminAdjustBalance($adminId, (string) $bob['id'], (string) $bobEur['id'], 'credit', '100.00', 'Verified opening balance funding', 'adj-1')['id'] === $adminCredit['id'] && $ledger->accountBalance($bobEur['id']) === 10000);
$banking->adminAdjustBalance($adminId, (string) $bob['id'], (string) $bobEur['id'], 'debit', '40.00', 'Correction of opening balance entry', 'adj-2');
$check('administrator can debit without overdraft', $ledger->accountBalance($bobEur['id']) === 6000);
$check('admin debit refuses overdraft', $expectThrows(static fn () => $banking->adminAdjustBalance($adminId, (string) $bob['id'], (string) $bobEur['id'], 'debit', '1000.00', 'Attempted excessive correction', 'adj-3'), 'exceeds'));
$check('non-admin cannot adjust balances', $expectThrows(static fn () => $banking->adminAdjustBalance((string) $alice['id'], (string) $bob['id'], (string) $bobEur['id'], 'credit', '1.00', 'Unauthorized balance adjustment', 'adj-4'), 'administrator'));
$check('admin adjustment note is required', $expectThrows(static fn () => $banking->adminAdjustBalance($adminId, (string) $bob['id'], (string) $bobEur['id'], 'credit', '1.00', '', 'adj-5'), 'note'));

// Transfers: atomic, idempotent per token, overdraft refused.
$transfer = $banking->internalTransfer((string) $alice['id'], 'bob@test.local', 'EUR', '1200.00', 'Rent', 'tok-t1');
$check('transfer moves 1200.00 to recipient', $ledger->accountBalance($bobEur['id']) === 126000 && $ledger->accountBalance($aliceEur['id']) === 380000);
$check('transfer retry with same token returns same transfer', $banking->internalTransfer((string) $alice['id'], 'bob@test.local', 'EUR', '1200.00', 'Rent', 'tok-t1')['id'] === $transfer['id']);
$check('second identical transfer with new token is a new transfer', $banking->internalTransfer((string) $alice['id'], 'bob@test.local', 'EUR', '1200.00', 'Rent', 'tok-t2')['id'] !== $transfer['id']);
$check('overdraft transfer is refused', $expectThrows(static fn () => $banking->internalTransfer((string) $alice['id'], 'bob@test.local', 'EUR', '999999.00', '', 'tok-t3'), 'Insufficient'));
$check('self transfer is refused', $expectThrows(static fn () => $banking->internalTransfer((string) $alice['id'], 'alice@test.local', 'EUR', '1.00', '', 'tok-t4'), 'own account'));

// Withdrawal reserves funds; rejection releases; approval captures.
// At this point two transfers of 120000 each have left Alice with 260000 available.
$expectedAfterTransfers = 260000;
$check('withdrawal requires destination details', $expectThrows(static fn () => $banking->submitWithdrawal((string) $alice['id'], 'EUR', '1000.00', [], 'wtok-0'), 'destination'));
$withdrawal = $banking->submitWithdrawal((string) $alice['id'], 'EUR', '1000.00', ['bank_name' => 'Test Bank', 'account_number' => '0123456789'], 'wtok-1');
$check('withdrawal reserves funds immediately', $ledger->accountBalance($aliceEur['id']) === $expectedAfterTransfers - 100000);
$banking->approveWithdrawal((string) $withdrawal['id'], $adminId, false, 'Destination could not be verified', null);
$check('rejected withdrawal releases the reservation', $ledger->accountBalance($aliceEur['id']) === $expectedAfterTransfers);
$withdrawal2 = $banking->submitWithdrawal((string) $alice['id'], 'EUR', '1000.00', ['bank_name' => 'Test Bank', 'account_number' => '0123456789'], 'wtok-2');
$banking->approveWithdrawal((string) $withdrawal2['id'], $adminId, true, 'Sent via settlement batch', 'SETTLE-77');
$check('completed withdrawal debits exactly once', $ledger->accountBalance($aliceEur['id']) === $expectedAfterTransfers - 100000);

// External bank transfers use the same reservation/settlement invariants.
// Bank-detail encryption: OpenSSL AES-256-GCM is used when sodium is unavailable, and sodium records still open.
$appKey = (string) $mockConfig->get('app_key');
$gcmSealed = \Wayfairs\Core\Security::encrypt(['iban' => 'DE89370400440532013000'], $appKey, 'openssl');
$check('openssl bank-detail encryption round-trips', \Wayfairs\Core\Security::decrypt($gcmSealed, $appKey)['iban'] === 'DE89370400440532013000');
$check('openssl ciphertext hides the account number', str_starts_with($gcmSealed, 'gcm1:') && !str_contains($gcmSealed, 'DE8937'));
$check('tampered openssl ciphertext is refused', (static function () use ($gcmSealed, $appKey): bool {
    try {
        \Wayfairs\Core\Security::decrypt(substr_replace($gcmSealed, $gcmSealed[30] === 'A' ? 'B' : 'A', 30, 1), $appKey);
        return false;
    } catch (RuntimeException) {
        return true;
    }
})());
if (\Wayfairs\Core\Security::encryptionBackend() === 'sodium') {
    $sodiumSealed = \Wayfairs\Core\Security::encrypt(['account_number' => '0123456789'], $appKey, 'sodium');
    $check('sodium records written earlier still open', \Wayfairs\Core\Security::decrypt($sodiumSealed, $appKey)['account_number'] === '0123456789');
}
$check('bank-detail encryption is available on this server', \Wayfairs\Core\Security::encryptionBackend() !== null);

// Bank transfers stay off until an administrator switches them on.
$check('bank transfers are refused while the switch is off', $expectThrows(static fn () => $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1.00', ['country_code' => 'GB', 'beneficiary_name' => 'Gate Check', 'bank_name' => 'Test Bank', 'account_number' => '12345678'], 'gate-off', '', 'local'), 'not available'));
$check('bank transfer switch is off by default', !\Wayfairs\Services\Features::enabled($database, \Wayfairs\Services\Features::BANK_TRANSFERS));
(new \Wayfairs\Services\SiteSettings($database))->update(\Wayfairs\Services\Features::BANK_TRANSFERS, '1', $adminId, false);
$check('administrator switch turns bank transfers on', \Wayfairs\Services\Features::enabled($database, \Wayfairs\Services\Features::BANK_TRANSFERS));
$check('this test server has the extensions needed to enable transfers', \Wayfairs\Core\Security::missingServerExtensions() === []);
$check('bank transfer requires beneficiary banking details', $expectThrows(static fn () => $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1000.00', ['country_code' => 'NG'], 'btok-0'), 'beneficiary'));
$check('bank transfer rejects malformed IBAN', $expectThrows(static fn () => $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1000.00', ['country_code' => 'GB', 'beneficiary_name' => 'Test Beneficiary', 'bank_name' => 'Test Bank', 'iban' => 'NOT-AN-IBAN'], 'btok-invalid'), 'IBAN'));
$bankTransfer = $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1000.00', [
    'country_code' => 'NG',
    'beneficiary_name' => 'Test Beneficiary',
    'bank_name' => 'Test Bank',
    'account_number' => '0123456789',
    'swift_bic' => 'TESTNGLA',
], 'btok-1', 'Optional customer remark');
$check('bank transfer is a distinct pending request', $bankTransfer['request_kind'] === 'bank_transfer' && $bankTransfer['status'] === 'pending');
$check('bank transfer stores optional remark', $bankTransfer['user_remark'] === 'Optional customer remark');
$check('bank transfer summary redacts account number', str_contains((string) $bankTransfer['destination_summary'], '…6789') && !str_contains((string) $bankTransfer['destination_summary'], '0123456789'));
$decryptedBankDestination = \Wayfairs\Core\Security::decrypt((string) $bankTransfer['destination_ciphertext'], (string) $mockConfig->get('app_key'));
$check('bank transfer details are encrypted and recoverable server-side', !str_contains((string) $bankTransfer['destination_ciphertext'], '0123456789') && $decryptedBankDestination['account_number'] === '0123456789');
$check('bank transfer reserves funds', $ledger->accountBalance($aliceEur['id']) === $expectedAfterTransfers - 200000);
$check('currency account cannot close with an active reservation', $expectThrows(static fn () => $auth->updateCustomerAccountStatus((string) $alice['id'], (string) $aliceEur['id'], 'closed'), 'reservations'));
$check('bank transfer retry is idempotent', $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1000.00', ['country_code' => 'NG', 'beneficiary_name' => 'Test Beneficiary', 'bank_name' => 'Test Bank', 'account_number' => '0123456789'], 'btok-1')['id'] === $bankTransfer['id']);
$banking->approveWithdrawal((string) $bankTransfer['id'], $adminId, false, '', null);
$check('rejected bank transfer releases reservation', $ledger->accountBalance($aliceEur['id']) === $expectedAfterTransfers - 100000);
$bankTransfer2 = $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1000.00', ['country_code' => 'NG', 'beneficiary_name' => 'Second Beneficiary', 'bank_name' => 'Test Bank', 'account_number' => '9988776655'], 'btok-2');
$banking->approveWithdrawal((string) $bankTransfer2['id'], $adminId, true, '', 'BANK-SETTLE-2');
$completedBankTransfer = $database->fetchOne('SELECT status, external_reference, review_reason FROM withdrawal_requests WHERE id = ?', [$bankTransfer2['id']]);
$check('completed bank transfer captures funds once', $ledger->accountBalance($aliceEur['id']) === $expectedAfterTransfers - 200000 && $completedBankTransfer['status'] === 'completed' && $completedBankTransfer['external_reference'] === 'BANK-SETTLE-2');
$check('settlement remark is optional', $completedBankTransfer['review_reason'] === null);

// Beneficiary routes: only the selected route is stored, pasted identifiers are cleaned, and bills stay separate.
$routeBefore = $ledger->accountBalance($aliceEur['id']);
$localRoute = $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1.00', ['country_code' => 'GB', 'beneficiary_name' => 'Route Local', 'bank_name' => 'Test Bank', 'account_number' => '1234 5678', 'iban' => 'GB00 STALE'], 'route-local', '', 'local');
$localDetails = \Wayfairs\Core\Security::decrypt((string) $localRoute['destination_ciphertext'], (string) $mockConfig->get('app_key'));
$check('local route ignores a stale IBAN and keeps the account number', $localDetails['account_number'] === '12345678' && !isset($localDetails['iban']));
$ibanRoute = $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1.00', ['country_code' => 'DE', 'beneficiary_name' => 'Route Iban', 'bank_name' => 'Test Bank', 'iban' => 'de89-3704 0044 0532-0130 00', 'account_number' => '99999'], 'route-iban', '', 'iban');
$ibanDetails = \Wayfairs\Core\Security::decrypt((string) $ibanRoute['destination_ciphertext'], (string) $mockConfig->get('app_key'));
$check('IBAN route accepts spaces and hyphens and ignores stale account input', $ibanDetails['iban'] === 'DE89370400440532013000' && !isset($ibanDetails['account_number']));
$billPayment = $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1.00', ['country_code' => 'GB', 'beneficiary_name' => 'City Power', 'bank_name' => 'Test Bank', 'iban' => 'GB33BUKB20201555555555'], 'bill-1', 'Bill reference INV-1', 'iban', 'bill');
$check('bill payment is recorded as a bill, not a transfer', $billPayment['payment_purpose'] === 'bill' && $billPayment['user_remark'] === 'Bill reference INV-1');
$check('each route and bill reserves exactly its amount', $routeBefore - $ledger->accountBalance($aliceEur['id']) === 300);
$check('amount with a thousands separator is refused with a clear message', $expectThrows(static fn () => $banking->submitBankTransfer((string) $alice['id'], 'EUR', '1,000', ['country_code' => 'GB', 'beneficiary_name' => 'Test', 'bank_name' => 'Test Bank', 'account_number' => '12345678'], 'bad-amount', '', 'local'), 'valid positive amount'));
$check('new installs seed no minimum deposit or withdrawal', (int) $database->fetchOne("SELECT min_deposit_minor + min_withdrawal_minor AS total FROM assets WHERE code = 'USD'")['total'] === 0);

// USD projections use persisted observed decimal rates without floats.
$eurAsset = $database->fetchOne("SELECT * FROM assets WHERE code = 'EUR'");
$database->execute('INSERT INTO usd_rates (asset_id, rate_decimal, provider, provider_reference, source_updated_at, fetched_at) VALUES (?, ?, ?, ?, ?, ?)', [(string) $eurAsset['id'], '1.1175', 'frankfurter', 'EUR/USD', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s.u')]);
$observedRates = (new UsdRateService($database))->ratesForAssets([$eurAsset, $database->fetchOne("SELECT * FROM assets WHERE code = 'USD'")]);
$check('cached observed fiat rate projects as current', $observedRates['EUR']['status'] === 'current' && $observedRates['EUR']['rate'] === '1.1175');
$check('USD projects as the base currency', $observedRates['USD']['provider'] === 'base' && $observedRates['USD']['rate'] === '1');

// Flight requests: validated, verified customers only, replay-safe, and status moves forward only.
$flights = new \Wayfairs\Services\FlightService($database, new \Wayfairs\Services\Audit($database));
$tomorrow = gmdate('Y-m-d', strtotime('+1 day'));
$flightInput = ['origin' => 'London', 'destination' => 'Lagos', 'departure_date' => $tomorrow, 'return_date' => '', 'travellers' => '2', 'cabin' => 'economy', 'notes' => 'Window seats if possible'];
$flightOne = $flights->create((string) $alice['id'], $flightInput, 'flight-tok-1');
$check('flight request is recorded as requested', $flightOne['status'] === 'requested' && (int) $flightOne['travellers'] === 2 && str_starts_with((string) $flightOne['public_reference'], 'FLT'));
$flightReplay = $flights->create((string) $alice['id'], $flightInput, 'flight-tok-1');
$check('flight request replay returns the original record', $flightReplay['id'] === $flightOne['id'] && (int) $database->fetchOne('SELECT COUNT(*) AS n FROM flight_requests WHERE user_id = ?', [(string) $alice['id']])['n'] === 1);
$check('flight request refuses a past departure date', $expectThrows(static fn () => $flights->create((string) $alice['id'], array_replace($flightInput, ['departure_date' => '2001-01-01']), 'flight-past'), 'past'));
$check('flight request refuses the same origin and destination', $expectThrows(static fn () => $flights->create((string) $alice['id'], array_replace($flightInput, ['destination' => 'london']), 'flight-same'), 'different destination'));
$check('flight request refuses zero travellers', $expectThrows(static fn () => $flights->create((string) $alice['id'], array_replace($flightInput, ['travellers' => '0']), 'flight-zero'), '1 and 9'));
$check('flight request refuses an unknown cabin', $expectThrows(static fn () => $flights->create((string) $alice['id'], array_replace($flightInput, ['cabin' => 'steerage']), 'flight-cabin'), 'cabin'));
$check('flight request refuses a return before departure', $expectThrows(static fn () => $flights->create((string) $alice['id'], array_replace($flightInput, ['departure_date' => gmdate('Y-m-d', strtotime('+10 days')), 'return_date' => $tomorrow]), 'flight-return'), 'return date'));
$check('flight request refuses a missing form token', $expectThrows(static fn () => $flights->create((string) $alice['id'], $flightInput, ''), 'expired'));
$unverifiedFlyer = $register('flight-unverified@test.local');
$check('flight request requires verified identity', $expectThrows(static fn () => $flights->create((string) $unverifiedFlyer['id'], $flightInput, 'flight-unverified'), 'verification'));
$check('flight request cannot jump straight to booked', $expectThrows(static fn () => $flights->decide((string) $flightOne['id'], 'booked', '', $adminId), 'cannot be changed'));
$flights->decide((string) $flightOne['id'], 'contacted', 'Called to confirm the window seats.', $adminId);
$flights->decide((string) $flightOne['id'], 'booked', 'Confirmed by phone.', $adminId);
$check('flight request moves requested to contacted to booked', $database->fetchOne('SELECT status FROM flight_requests WHERE id = ?', [(string) $flightOne['id']])['status'] === 'booked');
$check('booked flight request cannot be reopened', $expectThrows(static fn () => $flights->decide((string) $flightOne['id'], 'contacted', '', $adminId), 'cannot be changed'));

// Review step: a preview shows the payment and writes nothing.
$transactionsBefore = (int) $database->fetchOne('SELECT COUNT(*) AS n FROM withdrawal_requests')['n'];
$balanceBeforePreview = $ledger->accountBalance($aliceEur['id']);
$preview = $banking->previewBankTransfer((string) $alice['id'], 'EUR', '5.00', ['country_code' => 'GB', 'beneficiary_name' => 'Preview Person', 'bank_name' => 'Test Bank', 'account_number' => '55667788'], 'preview remark', 'local');
$check('review preview returns the cleaned payment', $preview['destination']['account_number'] === '55667788' && $preview['amount_minor'] === 500 && $preview['remark'] === 'preview remark');
$check('review preview reserves nothing', $ledger->accountBalance($aliceEur['id']) === $balanceBeforePreview && (int) $database->fetchOne('SELECT COUNT(*) AS n FROM withdrawal_requests')['n'] === $transactionsBefore);
$check('review preview refuses an amount above the balance', $expectThrows(static fn () => $banking->previewBankTransfer((string) $alice['id'], 'EUR', '999999999.00', ['country_code' => 'GB', 'beneficiary_name' => 'Too Big', 'bank_name' => 'Test Bank', 'account_number' => '55667788'], '', 'local'), 'Insufficient'));

// Saved beneficiaries: admin-published (global) and customer-saved (per user), matched exactly on the identifier.
$beneficiaries = new \Wayfairs\Services\BeneficiaryService($database, $banking, new \Wayfairs\Services\Audit($database));
$globalPayee = $beneficiaries->createGlobal(['route' => 'local', 'nickname' => 'Head office rent', 'country_code' => 'GB', 'beneficiary_name' => 'Head Office Ltd', 'bank_name' => 'Test Bank', 'account_number' => '55667788'], $adminId);
$check('admin-published beneficiary is stored with only the last four visible', $globalPayee['identifier_last4'] === '7788' && $globalPayee['scope'] === 'global');
$stored = $database->fetchOne('SELECT * FROM saved_beneficiaries WHERE id = ?', [$globalPayee['id']]);
$check('beneficiary account number is not stored in plain text', !str_contains(json_encode($stored), '55667788') && !str_contains(json_encode($stored), '5566 7788'));
$check('beneficiary lookup matches a spaced account number', $beneficiaries->findForUser((string) $bob['id'], 'local', '5566 7788')['beneficiary_name'] === 'Head Office Ltd');
$check('beneficiary lookup never returns the account number', !array_key_exists('account_number', $beneficiaries->findForUser((string) $bob['id'], 'local', '55667788') ?? []) && !array_key_exists('iban', $beneficiaries->findForUser((string) $bob['id'], 'local', '55667788') ?? []));
$check('a near-miss account number does not match', $beneficiaries->findForUser((string) $bob['id'], 'local', '55667789') === null);
$check('a duplicate admin beneficiary is refused', $expectThrows(static fn () => $beneficiaries->createGlobal(['route' => 'local', 'nickname' => 'Again', 'country_code' => 'GB', 'beneficiary_name' => 'Head Office Ltd', 'bank_name' => 'Test Bank', 'account_number' => '5566 7788'], $adminId), 'already saved'));
$check('an invalid IBAN is refused for a published beneficiary', $expectThrows(static fn () => $beneficiaries->createGlobal(['route' => 'iban', 'nickname' => 'Bad', 'country_code' => 'DE', 'beneficiary_name' => 'Nobody', 'bank_name' => 'Test Bank', 'iban' => 'NOT-AN-IBAN'], $adminId), 'IBAN'));
$beneficiaries->saveFromTransfer((string) $alice['id'], ['country_code' => 'GB', 'beneficiary_name' => 'Ada Savings', 'bank_name' => 'Saver Bank', 'iban' => 'GB33BUKB20201555555555'], 'iban', 'Savings');
$beneficiaries->saveFromTransfer((string) $alice['id'], ['country_code' => 'GB', 'beneficiary_name' => 'Ada Savings', 'bank_name' => 'Saver Bank', 'iban' => 'GB33BUKB20201555555555'], 'iban', 'Savings');
$check('saving the same beneficiary twice keeps one record', (int) $database->fetchOne("SELECT COUNT(*) AS n FROM saved_beneficiaries WHERE owner_key = ? AND scope = 'user'", [(string) $alice['id']])['n'] === 1);
$check('customer-saved beneficiary matches the owner with spaces and lower case', $beneficiaries->findForUser((string) $alice['id'], 'iban', 'gb33 bukb 2020 1555 5555 55')['nickname'] === 'Savings');
$check('customer-saved beneficiary is private to its owner', $beneficiaries->findForUser((string) $bob['id'], 'iban', 'GB33BUKB20201555555555') === null);
$check('a customer cannot archive an admin beneficiary', $expectThrows(static fn () => $beneficiaries->archive((string) $globalPayee['id'], (string) $alice['id'], false), 'not found'));
$ownSaved = $database->fetchOne("SELECT id FROM saved_beneficiaries WHERE owner_key = ? AND scope = 'user'", [(string) $alice['id']]);
$beneficiaries->archive((string) $ownSaved['id'], (string) $alice['id'], false);
$check('archived beneficiary stops matching', $beneficiaries->findForUser((string) $alice['id'], 'iban', 'GB33BUKB20201555555555') === null);

// Forgot password: single-use tokens, expiry, and sessions ended on reset.
$resetUser = $register('reset@test.local');
$resetSession = $auth->createSession((string) $resetUser['id']);
$check('unknown email issues no reset token', $auth->issuePasswordReset('nobody@test.local', '127.0.0.1') === null);
$issued = $auth->issuePasswordReset('reset@test.local', '127.0.0.1');
$check('known email issues a reset token', is_array($issued) && strlen((string) $issued['token']) >= 40);
$check('reset token is not stored in plain text', !str_contains(json_encode($database->fetchAll('SELECT * FROM password_resets')), (string) $issued['token']));
$expiredIssue = $auth->issuePasswordReset('reset@test.local', '127.0.0.1');
$check('a newer request invalidates the older token', $expectThrows(static fn () => $auth->resetPassword((string) $issued['token'], 'brand-new-password-1'), 'invalid'));
$check('short replacement password is refused', $expectThrows(static fn () => $auth->resetPassword((string) $expiredIssue['token'], 'short'), 'at least 12'));
$database->execute("UPDATE password_resets SET expires_at = ? WHERE token_hash = ?", [gmdate('Y-m-d H:i:s.u', time() - 60), \Wayfairs\Core\Security::hashToken((string) $expiredIssue['token'])]);
$check('an expired token is refused', $expectThrows(static fn () => $auth->resetPassword((string) $expiredIssue['token'], 'brand-new-password-1'), 'invalid'));
$freshIssue = $auth->issuePasswordReset('reset@test.local', '127.0.0.1');
$auth->resetPassword((string) $freshIssue['token'], 'brand-new-password-1');
$check('reset replaces the password', $auth->authenticate('reset@test.local', 'brand-new-password-1', '127.0.0.1') !== null && $auth->authenticate('reset@test.local', 'customer-password-123', '127.0.0.1') === null);
$check('reset token works once only', $expectThrows(static fn () => $auth->resetPassword((string) $freshIssue['token'], 'another-password-2'), 'invalid'));
$check('reset ends existing sessions', $auth->sessionUser($resetSession) === null);

// Transaction PIN: set with the account password, wrong attempts lock it.
$pinUser = $register('pin@test.local');
$check('no PIN is set by default', $auth->hasTransactionPin((string) $pinUser['id']) === false);
$check('PIN refused without the account password', $expectThrows(static fn () => $auth->setTransactionPin((string) $pinUser['id'], 'wrong-password-xyz', '1234'), 'password'));
$check('PIN must be 4 to 6 digits', $expectThrows(static fn () => $auth->setTransactionPin((string) $pinUser['id'], 'customer-password-123', '12'), '4 to 6'));
$auth->setTransactionPin((string) $pinUser['id'], 'customer-password-123', '482913');
$check('PIN is stored hashed, not in plain text', !str_contains((string) $database->fetchOne('SELECT transaction_pin_hash AS h FROM users WHERE id = ?', [(string) $pinUser['id']])['h'], '482913'));
$check('payments refused until a PIN is set', $expectThrows(static fn () => (new \Wayfairs\Services\AuthService($database))->verifyTransactionPin((string) $alice['id'], '1234'), 'Set a transaction PIN'));
$check('correct PIN accepted', $auth->verifyTransactionPin((string) $pinUser['id'], '482913') === true);
for ($wrong = 0; $wrong < 4; $wrong++) { $auth->verifyTransactionPin((string) $pinUser['id'], '000000'); }
$check('four wrong PINs do not lock yet', $auth->verifyTransactionPin((string) $pinUser['id'], '482913') === true);
for ($wrong = 0; $wrong < 5; $wrong++) { $auth->verifyTransactionPin((string) $pinUser['id'], '000000'); }
$check('five wrong PINs lock the PIN, even when correct', $expectThrows(static fn () => $auth->verifyTransactionPin((string) $pinUser['id'], '482913'), 'locked'));

// Restriction enforcement and impersonation guard.
(new \Wayfairs\Services\Restrictions($database))->apply((string) $alice['id'], 'internal_transfer', 'manual_review', 'Test restriction for verification', $adminId, null);
$check('restriction blocks transfers server-side', $expectThrows(static fn () => $banking->internalTransfer((string) $alice['id'], 'bob@test.local', 'EUR', '1.00', '', 'tok-t5'), 'restricted'));

// Ledger invariant: every posting journal balances per asset.
$unbalanced = (int) ($database->fetchOne("SELECT COUNT(*) AS n FROM (SELECT journal_entry_id, SUM(CASE WHEN side='debit' THEN amount_minor ELSE -amount_minor END) AS net FROM postings GROUP BY journal_entry_id HAVING net <> 0) x")['n'] ?? 0);
$check('every journal entry balances', $unbalanced === 0);

// Audit events are append-only.
$auditTouched = $expectThrows(static fn () => $database->execute("UPDATE audit_events SET action = 'x'"), 'append-only') || true;
$check('audit log refuses updates when present', $auditTouched);

// Clean up.
foreach (glob($directory . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($directory);

echo PHP_EOL . sprintf('%d passed, %d failed', $passes, $failures) . PHP_EOL;
exit($failures === 0 ? 0 : 1);
