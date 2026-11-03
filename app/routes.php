<?php

declare(strict_types=1);

use Wayfairs\Core\Context;
use Wayfairs\Core\Database;
use Wayfairs\Core\Draft;
use Wayfairs\Core\Flash;
use Wayfairs\Core\Money;
use Wayfairs\Core\Request;
use Wayfairs\Core\Response;
use Wayfairs\Core\Router;
use Wayfairs\Core\Security;
use Wayfairs\Core\View;
use Wayfairs\Services\Audit;
use Wayfairs\Services\AuthService;
use Wayfairs\Services\BankingService;
use Wayfairs\Services\BeneficiaryService;
use Wayfairs\Services\Features;
use Wayfairs\Services\EmailTemplates;
use Wayfairs\Services\Mailer;
use Wayfairs\Services\OtpService;
use Wayfairs\Services\FlightService;
use Wayfairs\Services\KycService;
use Wayfairs\Services\LedgerService;
use Wayfairs\Services\Restrictions;
use Wayfairs\Services\SiteSettings;
use Wayfairs\Services\UsdRateService;

return function (Router $router, Database $database): void {
    $auth = new AuthService($database);
    $settings = new SiteSettings($database);
    $ledger = new LedgerService($database);
    $banking = new BankingService($database, $ledger);
    $kyc = new KycService($database);
    $restrictions = new Restrictions($database);
    $audit = new Audit($database);
    $flights = new FlightService($database, $audit);
    $beneficiaries = new BeneficiaryService($database, $banking, $audit);
    $mailer = new Mailer($database);
    $emailTemplates = new EmailTemplates($database);
    $otp = new OtpService($database, $mailer, $emailTemplates);
    $rates = new UsdRateService($database);

    $page = static fn (string $view, array $data = []) => View::render($view, $data, 'layouts/app');
    $admin = static fn (string $view, array $data = []) => View::render($view, $data, 'layouts/admin');
    $guest = static fn (string $view, array $data = []) => View::render($view, $data, 'layouts/guest');
    $me = static fn (): array => Context::user() ?? [];
    $attempt = static function (callable $action, string $success, string $failure): never {
        try {
            $action();
            Flash::put('success', $success);
        } catch (\PDOException $error) {
            // Never show raw SQL to a customer; keep the detail in the server log.
            error_log('[wayfairs] database error: ' . $error->getMessage());
            Flash::put('error', 'That request could not be saved. Please try again in a moment.');
        } catch (RuntimeException | InvalidArgumentException $error) {
            Flash::put('error', $error->getMessage());
        } catch (Throwable $error) {
            $reference = Context::requestId();
            error_log(sprintf('[wayfairs] request=%s %s: %s in %s:%d', $reference, $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
            Flash::put('error', 'That did not complete. Please try again. Reference: ' . $reference);
        }
        Response::redirect($failure);
    };
    $accountRows = static function (BankingService $banking, LedgerService $ledger, Database $database, string $userId): array {
        $rows = $banking->allAccounts($userId);
        foreach ($rows as &$row) {
            $row['available_minor'] = $ledger->accountBalance((string) $row['id']);
            $held = $database->fetchOne(
                "SELECT COALESCE(SUM(amount_minor),0) AS held FROM holds WHERE account_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > ?)",
                [(string) $row['id'], gmdate('Y-m-d H:i:s.u')]
            );
            $row['held_minor'] = (int) ($held['held'] ?? 0);
        }
        unset($row);
        return $rows;
    };

    // ---- public and guest pages ----
    $router->get('/', static fn () => View::render('home', [
        'settings' => wayfairs_public_settings(),
        'assets' => $database->fetchAll("SELECT code, name, asset_type, symbol, deposit_enabled, withdrawal_enabled FROM assets WHERE enabled = 1 ORDER BY asset_type, code"),
    ], 'layouts/guest'), 'public');

    $router->get('/login', static fn () => $guest('auth/login'), 'guest');

    $router->post('/login', static function (Request $request) use ($auth, $restrictions): never {
        $user = $auth->authenticate((string) $request->post('email', ''), (string) $request->post('password', ''), $request->ip());
        if ($user === null) {
            Flash::put('error', 'Invalid email or password.');
            Response::redirect('/login');
        }
        if (!$restrictions->can((string) $user['id'], 'login')) {
            Flash::put('error', 'Sign-in is restricted on this account. Contact support.');
            Response::redirect('/login');
        }
        wayfairs_set_session_cookie($auth->createSession((string) $user['id']), 720 * 60);
        Flash::put('success', 'Welcome back, ' . $user['first_name'] . '.');
        Response::redirect($user['role'] === 'admin' ? '/admin' : '/dashboard');
    }, 'guest', true);

    $router->get('/register', static fn () => $guest('auth/register'), 'guest');

    $router->post('/register', static function (Request $request) use ($auth, $mailer, $emailTemplates): never {
        try {
            $user = $auth->register($request->allPost());
            $rendered = $emailTemplates->render('registration', ['first_name' => (string) ($user['first_name'] ?? ''), 'site_url' => wayfairs_url('/')]);
            if ($rendered !== null && $mailer->isConfigured()) {
                $mailer->send((string) $user['email'], $rendered['subject'], $rendered['body']);
            }
            wayfairs_set_session_cookie($auth->createSession((string) $user['id']), 720 * 60);
            Flash::put('success', 'Your account is ready. Complete identity verification to start moving money.');
            Response::redirect('/kyc');
        } catch (RuntimeException $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect('/register');
        }
    }, 'guest', true);

    $router->post('/logout', static function () use ($auth): never {
        $token = Context::sessionToken();
        if ($token !== null) {
            $auth->revoke($token);
        }
        wayfairs_clear_session_cookie();
        Flash::put('success', 'You have signed out.');
        Response::redirect('/login');
    }, 'auth', true);

    // ---- customer dashboard ----
    $router->get('/dashboard', static function () use ($page, $banking, $ledger, $database, $accountRows, $me, $restrictions, $kyc, $rates): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/dashboard', [
            'accounts' => $accounts,
            'history' => $banking->history((string) $user['id'], null, 12),
            'restrictions' => $restrictions->active((string) $user['id']),
            'kyc' => $kyc->profile((string) $user['id']),
            'usdRates' => $rates->ratesForAssets($database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets ORDER BY code')),
        ]);
    }, 'auth');

    $router->get('/accounts/{code}', static function (Request $request) use ($page, $banking, $ledger, $database, $me, $rates): void {
        $user = $me();
        $account = $banking->accountFor((string) $user['id'], (string) $request->route('code'));
        $account['available_minor'] = $ledger->accountBalance((string) $account['id']);
        $held = $database->fetchOne(
            "SELECT COALESCE(SUM(amount_minor),0) AS held FROM holds WHERE account_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > ?)",
            [(string) $account['id'], gmdate('Y-m-d H:i:s.u')]
        );
        $account['held_minor'] = (int) ($held['held'] ?? 0);
        $page('user/account', [
            'account' => $account,
            'history' => $banking->history((string) $user['id'], (string) $account['id'], 100),
            'usdRates' => $rates->ratesForAssets([$account['asset']]),
        ]);
    }, 'auth');

    $router->get('/transactions', static function () use ($page, $banking, $ledger, $database, $accountRows, $me, $rates): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/transactions', [
            'accounts' => $accounts,
            'history' => $banking->history((string) $user['id'], null, 200),
            'usdRates' => $rates->ratesForAssets($database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets ORDER BY code')),
        ]);
    }, 'auth');

    $router->get('/transactions/status', static function (Request $request) use ($banking, $me): never {
        $ids = explode(',', (string) $request->input('ids', ''));
        Response::json(['statuses' => $banking->statusesFor((string) $me()['id'], $ids)]);
    }, 'auth');

    $router->get('/transactions/{id}', static function (Request $request) use ($page, $banking, $me, $rates): void {
        $user = $me();
        $id = (string) $request->route('id');
        try {
            $receipt = ($user['role'] ?? '') === 'admin'
                ? $banking->receiptForAdmin($id)
                : $banking->receipt($id, (string) ($user['id'] ?? ''));
        } catch (RuntimeException) {
            Response::notFound();
        }
        $page('user/transaction', [
            'receipt' => $receipt,
            'usdRates' => $rates->ratesForAssets([[
                'id' => $receipt['asset_id'], 'code' => $receipt['asset_code'], 'asset_type' => $receipt['asset_type'], 'usd_provider_reference' => $receipt['usd_provider_reference'],
            ]]),
        ]);
    }, 'auth');

    $router->get('/receipts/{id}', static function (Request $request) use ($page, $banking, $me, $rates): void {
        $user = $me();
        $id = (string) $request->route('id');
        try {
            $receipt = ($user['role'] ?? '') === 'admin'
                ? $banking->receiptForAdmin($id)
                : $banking->receipt($id, (string) ($user['id'] ?? ''));
        } catch (RuntimeException) {
            Response::notFound();
        }
        $page('user/receipt', [
            'receipt' => $receipt,
            'usdRates' => $rates->ratesForAssets([[
                'id' => $receipt['asset_id'], 'code' => $receipt['asset_code'], 'asset_type' => $receipt['asset_type'], 'usd_provider_reference' => $receipt['usd_provider_reference'],
            ]]),
        ]);
    }, 'auth');

    $router->get('/deposit', static function () use ($page, $banking, $ledger, $accountRows, $me, $database, $kyc, $rates): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/deposit', [
            'accounts' => $accounts,
            'deposits' => $database->fetchAll('SELECT d.*, a.account_number, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol FROM deposit_requests d JOIN accounts a ON a.id = d.account_id JOIN assets s ON s.id = a.asset_id WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT 50', [(string) $user['id']]),
            'kyc' => $kyc->profile((string) $user['id']),
            'usdRates' => $rates->ratesForAssets(array_column($accounts, 'asset')),
        ]);
    }, 'auth');

    $router->post('/deposit', static function (Request $request) use ($banking, $database, $attempt, $mailer, $emailTemplates): never {
        $user = Context::user() ?? [];
        $attempt(static function () use ($banking, $mailer, $emailTemplates, $user, $request): void {
            $result = $banking->submitDeposit((string) $user['id'], (string) $request->post('asset_code', ''), (string) $request->post('amount', ''), (string) $request->post('remark', ''), (string) $request->post('idem', ''));
            if (!Context::isImpersonating() && $mailer->isConfigured()) {
                $asset = $banking->assetByCode((string) $request->post('asset_code', ''));
                $transaction = $database->fetchOne('SELECT public_reference FROM transactions WHERE id = ?', [(string) ($result['transaction_id'] ?? '')]);
                $rendered = $emailTemplates->render('deposit', [
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'amount' => wayfairs_major((int) $result['amount_minor'], (int) $asset['scale']),
                    'currency' => (string) $asset['code'],
                    'reference' => (string) ($transaction['public_reference'] ?? $result['id']),
                ]);
                if ($rendered !== null) {
                    $mailer->send((string) $user['email'], $rendered['subject'], $rendered['body']);
                }
            }
        }, 'Deposit submitted for verification.', '/deposit');
    }, 'auth', true);

    $router->get('/withdraw', static function () use ($page, $banking, $ledger, $accountRows, $me, $database, $kyc, $rates): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/withdraw', [
            'accounts' => $accounts,
            'withdrawals' => $database->fetchAll("SELECT w.*, a.account_number, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol FROM withdrawal_requests w JOIN accounts a ON a.id = w.account_id JOIN assets s ON s.id = a.asset_id WHERE w.user_id = ? AND w.request_kind = 'withdrawal' ORDER BY w.created_at DESC LIMIT 50", [(string) $user['id']]),
            'kyc' => $kyc->profile((string) $user['id']),
            'usdRates' => $rates->ratesForAssets(array_column($accounts, 'asset')),
        ]);
    }, 'auth');

    $router->post('/withdraw', static function (Request $request) use ($banking, $database, $attempt, $mailer, $emailTemplates): never {
        $user = Context::user() ?? [];
        $destination = $request->allPost();
        foreach (['_token', 'asset_code', 'amount', 'idem', 'remark'] as $reserved) {
            unset($destination[$reserved]);
        }
        $destination = array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $destination), static fn ($value) => $value !== '');
        $attempt(static function () use ($banking, $database, $mailer, $emailTemplates, $user, $request, $destination): void {
            $result = $banking->submitWithdrawal((string) $user['id'], (string) $request->post('asset_code', ''), (string) $request->post('amount', ''), $destination, (string) $request->post('idem', ''), (string) $request->post('remark', ''));
            if (!Context::isImpersonating() && $mailer->isConfigured()) {
                $asset = $banking->assetByCode((string) $request->post('asset_code', ''));
                $transaction = $database->fetchOne('SELECT public_reference FROM transactions WHERE id = ?', [(string) ($result['transaction_id'] ?? '')]);
                $rendered = $emailTemplates->render('withdrawal', [
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'amount' => wayfairs_major((int) $result['amount_minor'], (int) $asset['scale']),
                    'currency' => (string) $asset['code'],
                    'reference' => (string) ($transaction['public_reference'] ?? $result['id']),
                ]);
                if ($rendered !== null) {
                    $mailer->send((string) $user['email'], $rendered['subject'], $rendered['body']);
                }
            }
        }, 'Withdrawal submitted. The amount is reserved until it is processed.', '/withdraw');
    }, 'auth', true);

    $router->get('/transfer', static function () use ($page, $banking, $ledger, $accountRows, $me, $database, $kyc, $rates, $beneficiaries): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/transfer', [
            'accounts' => $accounts,
            'bankTransfersEnabled' => Features::enabled($database, Features::BANK_TRANSFERS),
            'old' => Draft::get('form.transfer') ?? [],
            'beneficiaries' => $beneficiaries->listFor((string) $user['id']),
            'bankTransfers' => $database->fetchAll("SELECT w.*, a.account_number, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol FROM withdrawal_requests w JOIN accounts a ON a.id = w.account_id JOIN assets s ON s.id = a.asset_id WHERE w.user_id = ? AND w.request_kind = 'bank_transfer' AND w.payment_purpose = 'transfer' ORDER BY w.created_at DESC LIMIT 50", [(string) $user['id']]),
            'internalTransfers' => $database->fetchAll('SELECT t.*, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol, su.email AS sender_email, ru.email AS recipient_email FROM transfers t JOIN assets s ON s.id = t.asset_id JOIN users su ON su.id = t.sender_user_id JOIN users ru ON ru.id = t.recipient_user_id WHERE t.sender_user_id = ? OR t.recipient_user_id = ? ORDER BY t.created_at DESC LIMIT 50', [(string) $user['id'], (string) $user['id']]),
            'me' => (string) $user['id'],
            'kyc' => $kyc->profile((string) $user['id']),
            'usdRates' => $rates->ratesForAssets(array_column($accounts, 'asset')),
        ]);
    }, 'auth');

    $movementInput = static function (Request $request, string $kind): array {
        $fields = $kind === 'bill'
            ? ['biller_country', 'biller_name', 'biller_address', 'biller_bank', 'iban', 'account_number', 'routing_code', 'swift_bic', 'bill_reference', 'remark']
            : ['country_code', 'beneficiary_name', 'beneficiary_address', 'bank_name', 'bank_address', 'iban', 'account_number', 'routing_code', 'swift_bic', 'remark'];
        $input = [];
        foreach (array_merge($fields, ['asset_code', 'amount', 'transfer_route', 'save_beneficiary', 'nickname']) as $field) {
            $input[$field] = trim((string) $request->post($field, ''));
        }
        return $input;
    };

    $movementDestination = static function (array $input, string $kind): array {
        if ($kind === 'bill') {
            $destination = [
                'country_code' => $input['biller_country'], 'beneficiary_name' => $input['biller_name'], 'beneficiary_address' => $input['biller_address'],
                'bank_name' => $input['biller_bank'], 'iban' => $input['iban'], 'account_number' => $input['account_number'],
                'routing_code' => $input['routing_code'], 'swift_bic' => $input['swift_bic'],
            ];
            $note = $input['remark'];
            $remark = $input['bill_reference'] !== '' ? 'Bill reference ' . $input['bill_reference'] . ($note !== '' ? ' · ' . $note : '') : $note;
        } else {
            $destination = [
                'country_code' => $input['country_code'], 'beneficiary_name' => $input['beneficiary_name'], 'beneficiary_address' => $input['beneficiary_address'],
                'bank_name' => $input['bank_name'], 'bank_address' => $input['bank_address'], 'iban' => $input['iban'],
                'account_number' => $input['account_number'], 'routing_code' => $input['routing_code'], 'swift_bic' => $input['swift_bic'],
            ];
            $remark = $input['remark'];
        }
        return [array_filter($destination, static fn ($value) => $value !== ''), $remark];
    };

    $reviewMovement = static function (Request $request, string $kind) use ($banking, $movementInput, $movementDestination): never {
        $user = Context::user() ?? [];
        $back = $kind === 'bill' ? '/bills' : '/transfer';
        $input = $movementInput($request, $kind);
        Draft::put('form.' . $kind, $input);
        try {
            [$destination, $remark] = $movementDestination($input, $kind);
            $banking->previewBankTransfer((string) $user['id'], $input['asset_code'] ?: 'USD', $input['amount'], $destination, $remark, $input['transfer_route']);
            Draft::put('review.' . $kind, $input + ['idem' => Security::token(16)]);
            Response::redirect($kind === 'bill' ? '/bills/review' : '/transfer/review');
        } catch (RuntimeException | InvalidArgumentException $error) {
            Flash::put('error', $error->getMessage());
        } catch (Throwable $error) {
            $reference = Context::requestId();
            error_log(sprintf('[wayfairs] request=%s %s: %s in %s:%d', $reference, $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
            Flash::put('error', 'That did not complete. Please try again. Reference: ' . $reference);
        }
        Response::redirect($back);
    };

    $showReview = static function (string $kind) use ($page, $banking, $movementDestination): void {
        $user = Context::user() ?? [];
        $back = $kind === 'bill' ? '/bills' : '/transfer';
        $input = Draft::get('review.' . $kind);
        if ($input === null) {
            Flash::put('error', 'This review has expired. Enter the details again.');
            Response::redirect($back);
        }
        try {
            [$destination, $remark] = $movementDestination($input, $kind);
            $preview = $banking->previewBankTransfer((string) $user['id'], $input['asset_code'] ?: 'USD', $input['amount'], $destination, $remark, $input['transfer_route']);
        } catch (RuntimeException | InvalidArgumentException $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect($back);
        }
        $page('user/review', ['kind' => $kind, 'preview' => $preview, 'input' => $input, 'countries' => wayfairs_countries()]);
    };

    $confirmMovement = static function (string $kind) use ($banking, $database, $attempt, $movementDestination, $beneficiaries, $otp, $mailer, $emailTemplates): never {
        $user = Context::user() ?? [];
        $back = $kind === 'bill' ? '/bills' : '/transfer';
        $input = Draft::take('review.' . $kind);
        if ($input === null) {
            Flash::put('error', 'This review has expired. Enter the details again.');
            Response::redirect($back);
        }
        $otpEnabled = $otp->isEnabled();
        if ($otpEnabled) {
            $otpId = $otp->issue((string) $user['id'], $kind === 'bill' ? 'bill_payment' : 'bank_transfer', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            Draft::put('otp.' . $kind, ['id' => $otpId, 'input' => $input]);
            Response::redirect($kind === 'bill' ? '/bills/otp' : '/transfer/otp');
        }
        try {
            [$destination, $remark] = $movementDestination($input, $kind);
            $route = $input['transfer_route'] === 'local' ? 'local' : 'iban';
            $result = $banking->submitBankTransfer((string) $user['id'], $input['asset_code'] ?: 'USD', $input['amount'], $destination, (string) $input['idem'], $remark, $route, $kind === 'bill' ? 'bill' : 'transfer');
            if ($kind === 'transfer' && $input['save_beneficiary'] === '1') {
                $beneficiaries->saveFromTransfer((string) $user['id'], $destination, $route, $input['nickname']);
            }
            Draft::forget('form.' . $kind);
            Draft::forget('review.' . $kind);
            Draft::forget('otp.' . $kind);
            if (!Context::isImpersonating() && $mailer->isConfigured()) {
                $asset = $banking->assetByCode((string) $input['asset_code']);
                $transaction = $database->fetchOne('SELECT public_reference FROM transactions WHERE id = ?', [(string) ($result['transaction_id'] ?? '')]);
                $beneficiary = (string) ($result['destination_summary'] ?? '');
                $templateKey = $kind === 'bill' ? 'bill_payment' : 'bank_transfer';
                $placeholder = $kind === 'bill' ? 'biller' : 'beneficiary';
                $rendered = $emailTemplates->render($templateKey, [
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'amount' => wayfairs_major((int) $result['amount_minor'], (int) $asset['scale']),
                    'currency' => (string) $asset['code'],
                    'reference' => (string) ($transaction['public_reference'] ?? $result['id']),
                    $placeholder => $beneficiary,
                ]);
                if ($rendered !== null) {
                    $mailer->send((string) $user['email'], $rendered['subject'], $rendered['body']);
                }
            }
            Flash::put('success', $kind === 'bill' ? 'Bill payment submitted. Funds are held while it is processed.' : 'Bank transfer submitted. Funds are held while it is processed.');
            $transactionId = (string) ($result['transaction_id'] ?? '');
            if ($transactionId !== '') {
                Response::redirect('/transactions/' . $transactionId);
            }
            Response::redirect($back);
        } catch (\PDOException $error) {
            error_log('[wayfairs] database error: ' . $error->getMessage());
            Flash::put('error', 'That request could not be saved. Please try again in a moment.');
            Response::redirect($back);
        } catch (RuntimeException | InvalidArgumentException $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect($back);
        } catch (Throwable $error) {
            $reference = Context::requestId();
            error_log(sprintf('[wayfairs] request=%s %s: %s in %s:%d', $reference, $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
            Flash::put('error', 'That did not complete. Please try again. Reference: ' . $reference);
            Response::redirect($back);
        }
    };

    $router->get('/transfer/review', static fn () => $showReview('transfer'), 'auth');
    $router->get('/bills/review', static fn () => $showReview('bill'), 'auth');
    $router->post('/transfer/review', static fn (Request $request) => $reviewMovement($request, 'transfer'), 'auth', true);
    $router->post('/bills/review', static fn (Request $request) => $reviewMovement($request, 'bill'), 'auth', true);
    $router->post('/transfer/confirm', static fn () => $confirmMovement('transfer'), 'auth', true);
    $router->post('/bills/confirm', static fn () => $confirmMovement('bill'), 'auth', true);

    $verifyOtp = static function (string $kind) use ($otp, $banking, $database, $movementDestination, $beneficiaries, $attempt, $mailer, $emailTemplates): never {
        $user = Context::user() ?? [];
        $back = $kind === 'bill' ? '/bills' : '/transfer';
        $otpEntry = Draft::get('otp.' . $kind);
        if (!is_array($otpEntry) || !isset($otpEntry['input'])) {
            Flash::put('error', 'This verification has expired. Start again.');
            Response::redirect($back);
        }
        $input = $otpEntry['input'];
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!$otp->verify((string) $user['id'], $kind === 'bill' ? 'bill_payment' : 'bank_transfer', $code)) {
            Flash::put('error', 'That code is incorrect or has expired. Try again.');
            Response::redirect($kind === 'bill' ? '/bills/otp' : '/transfer/otp');
        }
        $attempt(static function () use ($banking, $movementDestination, $beneficiaries, $user, $kind, $input): void {
            [$destination, $remark] = $movementDestination($input, $kind);
            $route = $input['transfer_route'] === 'local' ? 'local' : 'iban';
            $banking->submitBankTransfer((string) $user['id'], $input['asset_code'] ?: 'USD', $input['amount'], $destination, (string) $input['idem'], $remark, $route, $kind === 'bill' ? 'bill' : 'transfer');
            if ($kind === 'transfer' && $input['save_beneficiary'] === '1') {
                $beneficiaries->saveFromTransfer((string) $user['id'], $destination, $route, $input['nickname']);
            }
            Draft::forget('form.' . $kind);
            Draft::forget('review.' . $kind);
            Draft::forget('otp.' . $kind);
        }, $kind === 'bill' ? 'Bill payment submitted. Funds are held while it is processed.' : 'Bank transfer submitted. Funds are held while it is processed.', $back);
    };
    $router->get('/transfer/otp', static function () use ($page): void {
        $page('user/otp', ['kind' => 'transfer']);
    }, 'auth');
    $router->get('/bills/otp', static function () use ($page): void {
        $page('user/otp', ['kind' => 'bill']);
    }, 'auth');
    $router->post('/transfer/otp', static fn () => $verifyOtp('transfer'), 'auth', true);
    $router->post('/bills/otp', static fn () => $verifyOtp('bill'), 'auth', true);

    $router->get('/transfer/beneficiary', static function (Request $request) use ($beneficiaries): never {
        $user = Context::user() ?? [];
        $route = (string) $request->input('route', '');
        $identifier = (string) $request->input('identifier', '');
        if (!in_array($route, ['iban', 'local'], true) || $identifier === '' || strlen($identifier) > 40) {
            Response::json(['found' => false]);
        }
        $match = $beneficiaries->findForUser((string) $user['id'], $route, $identifier);
        Response::json(['found' => $match !== null, 'beneficiary' => $match]);
    }, 'auth');

    $router->post('/beneficiaries/{id}/archive', static function (Request $request) use ($beneficiaries, $attempt): never {
        $user = Context::user() ?? [];
        $attempt(static fn () => $beneficiaries->archive((string) $request->route('id'), (string) $user['id'], false), 'Beneficiary removed.', '/transfer');
    }, 'auth', true);

    $router->get('/admin/beneficiaries', static function () use ($admin, $beneficiaries): void {
        $admin('admin/beneficiaries', ['beneficiaries' => $beneficiaries->listAllGlobal()]);
    }, 'admin');

    $router->post('/admin/beneficiaries', static function (Request $request) use ($beneficiaries, $attempt): never {
        $input = [];
        foreach (['route', 'nickname', 'country_code', 'beneficiary_name', 'beneficiary_address', 'bank_name', 'bank_address', 'iban', 'account_number', 'swift_bic', 'routing_code'] as $field) {
            $input[$field] = trim((string) $request->post($field, ''));
        }
        $attempt(static fn () => $beneficiaries->createGlobal($input, (string) Context::user()['id']), 'Beneficiary published to customers.', '/admin/beneficiaries');
    }, 'admin', true);

    $router->post('/admin/beneficiaries/{id}/archive', static function (Request $request) use ($beneficiaries, $attempt): never {
        $attempt(static fn () => $beneficiaries->archive((string) $request->route('id'), (string) Context::user()['id'], true), 'Beneficiary archived.', '/admin/beneficiaries');
    }, 'admin', true);

    $router->get('/bills', static function () use ($page, $banking, $ledger, $accountRows, $me, $database, $kyc, $rates): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/bills', [
            'accounts' => $accounts,
            'bankTransfersEnabled' => Features::enabled($database, Features::BANK_TRANSFERS),
            'old' => Draft::get('form.bill') ?? [],
            'bills' => $database->fetchAll("SELECT w.*, a.account_number, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol FROM withdrawal_requests w JOIN accounts a ON a.id = w.account_id JOIN assets s ON s.id = a.asset_id WHERE w.user_id = ? AND w.request_kind = 'bank_transfer' AND w.payment_purpose = 'bill' ORDER BY w.created_at DESC LIMIT 50", [(string) $user['id']]),
            'kyc' => $kyc->profile((string) $user['id']),
            'usdRates' => $rates->ratesForAssets(array_column($accounts, 'asset')),
        ]);
    }, 'auth');

    $router->get('/utilities', static function () use ($page, $database): void {
        $page('user/utilities', [
            'bankTransfersEnabled' => Features::enabled($database, Features::BANK_TRANSFERS),
        ]);
    }, 'auth');

    $router->get('/flights', static function () use ($page, $database, $me, $kyc, $flights): void {
        $user = $me();
        $page('user/flights', [
            'kyc' => $kyc->profile((string) $user['id']),
            'requests' => $flights->forUser((string) $user['id']),
        ]);
    }, 'auth');

    $router->post('/flights', static function (Request $request) use ($flights, $database, $attempt, $mailer, $emailTemplates): never {
        $user = Context::user() ?? [];
        $attempt(static function () use ($flights, $mailer, $emailTemplates, $user, $request): void {
            $result = $flights->create((string) $user['id'], [
                'origin' => $request->post('origin', ''),
                'destination' => $request->post('destination', ''),
                'departure_date' => $request->post('departure_date', ''),
                'return_date' => $request->post('return_date', ''),
                'travellers' => $request->post('travellers', ''),
                'cabin' => $request->post('cabin', ''),
                'notes' => $request->post('notes', ''),
            ], (string) $request->post('idem', ''));
            if (!Context::isImpersonating() && $mailer->isConfigured()) {
                $rendered = $emailTemplates->render('flight_request', [
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'origin' => (string) $request->post('origin', ''),
                    'destination' => (string) $request->post('destination', ''),
                ]);
                if ($rendered !== null) {
                    $mailer->send((string) $user['email'], $rendered['subject'], $rendered['body']);
                }
            }
        }, 'Flight request sent. We will contact you to confirm availability and price.', '/flights');
    }, 'auth', true);

    $router->post('/transfer/internal', static function (Request $request) use ($banking, $database, $attempt, $mailer, $emailTemplates): never {
        $user = Context::user() ?? [];
        $attempt(static function () use ($banking, $database, $mailer, $emailTemplates, $user, $request): void {
            $result = $banking->internalTransfer((string) $user['id'], (string) $request->post('recipient_email', ''), (string) $request->post('asset_code', ''), (string) $request->post('amount', ''), (string) $request->post('remark', ''), (string) $request->post('idem', ''));
            if (Context::isImpersonating() || !$mailer->isConfigured()) {
                return;
            }
            $asset = $banking->assetByCode((string) $request->post('asset_code', ''));
            $amount = wayfairs_major((int) $result['amount_minor'], (int) $asset['scale']);
            $recipient = $database->fetchOne('SELECT id, first_name, email FROM users WHERE email_normalized = ?', [mb_strtolower(trim((string) $request->post('recipient_email', '')), 'UTF-8')]);
            // Sender: debit email
            $rendered = $emailTemplates->render('debit', [
                'first_name' => (string) ($user['first_name'] ?? ''),
                'amount' => $amount,
                'currency' => (string) $asset['code'],
                'reference' => (string) ($result['public_reference'] ?? ''),
                'date' => gmdate('Y-m-d H:i'),
            ]);
            if ($rendered !== null) {
                $mailer->send((string) $user['email'], $rendered['subject'], $rendered['body']);
            }
            // Recipient: top-up email
            if ($recipient) {
                $rendered = $emailTemplates->render('topup', [
                    'first_name' => (string) ($recipient['first_name'] ?? ''),
                    'amount' => $amount,
                    'currency' => (string) $asset['code'],
                    'reference' => (string) ($result['public_reference'] ?? ''),
                    'date' => gmdate('Y-m-d H:i'),
                ]);
                if ($rendered !== null) {
                    $mailer->send((string) $recipient['email'], $rendered['subject'], $rendered['body']);
                }
            }
        }, 'Internal transfer completed.', '/transfer');
    }, 'auth', true);

    $router->get('/invoices', static function () use ($page, $banking, $ledger, $accountRows, $me, $database, $kyc, $rates): void {
        $user = $me();
        $accounts = $accountRows($banking, $ledger, $database, (string) $user['id']);
        $page('user/invoices', [
            'accounts' => $accounts,
            'issued' => $database->fetchAll('SELECT i.*, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol FROM invoices i JOIN assets s ON s.id = i.asset_id WHERE i.issuer_user_id = ? ORDER BY i.created_at DESC LIMIT 50', [(string) $user['id']]),
            'paid' => $database->fetchAll('SELECT i.*, s.code AS asset_code, s.scale AS asset_scale, s.symbol AS asset_symbol FROM invoices i JOIN assets s ON s.id = i.asset_id WHERE i.paid_by_user_id = ? ORDER BY i.paid_at DESC LIMIT 50', [(string) $user['id']]),
            'kyc' => $kyc->profile((string) $user['id']),
            'usdRates' => $rates->ratesForAssets(array_column($accounts, 'asset')),
        ]);
    }, 'auth');

    $router->post('/invoices', static function (Request $request) use ($banking, $attempt): never {
        $user = Context::user() ?? [];
        $attempt(static fn () => $banking->createInvoice((string) $user['id'], (string) $request->post('asset_code', ''), (string) $request->post('amount', ''), (string) $request->post('title', ''), (string) $request->post('description', ''), (string) $request->post('due_at', '')), 'Invoice created. Share its code with the payer.', '/invoices');
    }, 'auth', true);

    $router->post('/invoices/pay', static function (Request $request) use ($banking, $attempt): never {
        $user = Context::user() ?? [];
        $attempt(static fn () => $banking->payInvoice((string) $user['id'], (string) $request->post('code', ''), (string) $request->post('idem', '')), 'Invoice paid.', '/invoices');
    }, 'auth', true);

    $router->get('/kyc', static function () use ($page, $kyc, $me): void {
        $page('user/kyc', ['profile' => $kyc->profile((string) $me()['id']), 'settings' => wayfairs_public_settings()]);
    }, 'auth');

    $router->post('/kyc', static function (Request $request) use ($kyc, $attempt, $me): never {
        $attempt(static fn () => $kyc->submit((string) $me()['id'], $request->allPost()), 'Verification details submitted for review.', '/kyc');
    }, 'auth', true);

    $router->get('/security', static function () use ($page, $restrictions, $database, $me): void {
        $user = $me();
        $page('user/security', [
            'restrictions' => $restrictions->active((string) $user['id']),
            'profile' => $database->fetchOne('SELECT status, email, email_verified_at, last_login_at, created_at FROM users WHERE id = ?', [(string) $user['id']]) ?? [],
            'activeSessions' => (int) ($database->fetchOne('SELECT COUNT(*) AS total FROM sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > ?', [(string) $user['id'], gmdate('Y-m-d H:i:s.u')])['total'] ?? 0),
        ]);
    }, 'auth');

    // ---- administrator ----
    $router->get('/admin', static function () use ($admin, $database, $rates): void {
        $count = static fn (string $sql, array $params = []): int => (int) ($database->fetchOne($sql, $params)['total'] ?? 0);
        $rateAssets = $database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets WHERE enabled = 1 ORDER BY code');
        $admin('admin/dashboard', [
            'stats' => [
                'customers' => $count("SELECT COUNT(*) AS total FROM users WHERE role = 'user'"),
                'kyc_review' => $count("SELECT COUNT(*) AS total FROM kyc_profiles WHERE status IN ('pending','in_review','needs_resubmission')"),
                'deposits' => $count("SELECT COUNT(*) AS total FROM deposit_requests WHERE status = 'pending'"),
                'withdrawals' => $count("SELECT COUNT(*) AS total FROM withdrawal_requests WHERE request_kind = 'withdrawal' AND status IN ('pending','approved')"),
                'bank_transfers' => $count("SELECT COUNT(*) AS total FROM withdrawal_requests WHERE request_kind = 'bank_transfer' AND status IN ('pending','approved')"),
                'restrictions' => $count('SELECT COUNT(*) AS total FROM capability_restrictions WHERE released_at IS NULL'),
            ],
            'pendingDeposits' => $database->fetchAll(
                "SELECT d.id, d.amount_minor, d.created_at, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale, u.first_name, u.last_name, u.email
                 FROM deposit_requests d
                 JOIN accounts a ON a.id = d.account_id
                 JOIN assets s ON s.id = a.asset_id
                 JOIN users u ON u.id = d.user_id
                 WHERE d.status = 'pending'
                 ORDER BY d.created_at ASC LIMIT 5"
            ),
            'pendingWithdrawals' => $database->fetchAll(
                "SELECT w.id, w.amount_minor, w.fee_minor, w.created_at, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale, u.first_name, u.last_name, u.email
                 FROM withdrawal_requests w
                 JOIN accounts a ON a.id = w.account_id
                 JOIN assets s ON s.id = a.asset_id
                 JOIN users u ON u.id = w.user_id
                 WHERE w.request_kind = 'withdrawal' AND w.status IN ('pending','approved')
                 ORDER BY w.created_at ASC LIMIT 5"
            ),
            'pendingBankTransfers' => $database->fetchAll(
                "SELECT w.id, w.amount_minor, w.fee_minor, w.destination_summary, w.created_at, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale, u.first_name, u.last_name, u.email
                 FROM withdrawal_requests w
                 JOIN accounts a ON a.id = w.account_id
                 JOIN assets s ON s.id = a.asset_id
                 JOIN users u ON u.id = w.user_id
                 WHERE w.request_kind = 'bank_transfer' AND w.status IN ('pending','approved')
                 ORDER BY w.created_at ASC LIMIT 5"
            ),
            'recentCustomers' => $database->fetchAll(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, kp.status AS kyc_status
                 FROM users u LEFT JOIN kyc_profiles kp ON kp.user_id = u.id
                 WHERE u.role = 'user' ORDER BY u.created_at DESC LIMIT 6"
            ),
            'usdRates' => $rates->ratesForAssets($rateAssets),
        ]);
    }, 'admin');

    $router->get('/admin/users', static function () use ($admin, $database): void {
        $admin('admin/users', [
            'users' => $database->fetchAll("SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.role, u.created_at, u.last_login_at, kp.status AS kyc_status,
                (SELECT COUNT(*) FROM accounts a WHERE a.user_id = u.id) AS account_count,
                (SELECT COUNT(*) FROM capability_restrictions cr WHERE cr.user_id = u.id AND cr.released_at IS NULL) AS restriction_count,
                (SELECT MAX(t.created_at) FROM transactions t WHERE t.user_id = u.id) AS last_transaction_at
                FROM users u LEFT JOIN kyc_profiles kp ON kp.user_id = u.id WHERE u.role = 'user' ORDER BY u.created_at DESC LIMIT 300"),
        ]);
    }, 'admin');

    $router->get('/admin/users/new', static function () use ($admin): void {
        $admin('admin/user-form', ['profile' => null, 'mode' => 'create']);
    }, 'admin');

    $router->post('/admin/users', static function (Request $request) use ($auth, $audit): never {
        try {
            $customer = $auth->createCustomerByAdmin($request->allPost());
            $audit->record('customer.created', 'user', (string) $customer['id'], null, ['status' => $customer['status']]);
            Flash::put('success', 'Customer account created.');
            Response::redirect('/admin/users/' . $customer['id']);
        } catch (RuntimeException $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect('/admin/users/new');
        }
    }, 'admin', true);

    $router->get('/admin/users/{id}', static function (Request $request) use ($admin, $database, $ledger, $kyc, $restrictions, $rates): void {
        $userId = (string) $request->route('id');
        $profile = $database->fetchOne('SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.status, u.role, u.created_at, u.last_login_at FROM users u WHERE u.id = ?', [$userId]);
        if ($profile === null) {
            Response::notFound();
        }
        $accounts = $database->fetchAll("SELECT a.*, s.id AS asset_id, s.code, s.symbol, s.scale, s.asset_type, s.usd_provider_reference FROM accounts a JOIN assets s ON s.id = a.asset_id WHERE a.user_id = ? ORDER BY CASE WHEN s.code = 'USD' THEN 0 ELSE 1 END, s.code", [$userId]);
        foreach ($accounts as &$account) {
            $account['available_minor'] = $ledger->accountBalance((string) $account['id']);
            $held = $database->fetchOne("SELECT COALESCE(SUM(amount_minor),0) AS held FROM holds WHERE account_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > ?)", [(string) $account['id'], gmdate('Y-m-d H:i:s.u')]);
            $account['held_minor'] = (int) ($held['held'] ?? 0);
        }
        unset($account);
        $admin('admin/user-detail', [
            'profile' => $profile,
            'accounts' => $accounts,
            'history' => $database->fetchAll('SELECT t.*, s.id AS asset_id, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale, s.asset_type, s.usd_provider_reference FROM transactions t JOIN accounts a ON a.id = t.account_id JOIN assets s ON s.id = a.asset_id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 100', [$userId]),
            'restrictions' => $restrictions->active($userId),
            'kyc' => $kyc->profile($userId),
            'usdRates' => $rates->ratesForAssets(array_map(static fn (array $account): array => ['id' => $account['asset_id'], 'code' => $account['code'], 'asset_type' => $account['asset_type'], 'usd_provider_reference' => $account['usd_provider_reference']], $accounts)),
        ]);
    }, 'admin');

    $router->get('/admin/users/{id}/edit', static function (Request $request) use ($admin, $database): void {
        $profile = $database->fetchOne("SELECT id, email, first_name, last_name, phone, status FROM users WHERE id = ? AND role = 'user'", [(string) $request->route('id')]);
        if ($profile === null) {
            Response::notFound();
        }
        $admin('admin/user-form', ['profile' => $profile, 'mode' => 'edit']);
    }, 'admin');

    $router->post('/admin/users/{id}/edit', static function (Request $request) use ($auth, $audit, $attempt): never {
        $userId = (string) $request->route('id');
        $attempt(static function () use ($auth, $audit, $request, $userId): void {
            $before = $auth->userById($userId);
            $updated = $auth->updateCustomerByAdmin($userId, $request->allPost());
            $remark = trim((string) $request->post('admin_remark', ''));
            $audit->record('customer.updated', 'user', $userId, $remark ?: null, ['status_before' => $before['status'] ?? null, 'status_after' => $updated['status']]);
        }, 'Customer profile updated.', '/admin/users/' . $userId);
    }, 'admin', true);

    $router->post('/admin/users/{id}/accounts/{accountId}/status', static function (Request $request) use ($auth, $audit, $attempt, $database, $mailer, $emailTemplates): never {
        $userId = (string) $request->route('id');
        $accountId = (string) $request->route('accountId');
        $attempt(static function () use ($auth, $audit, $request, $userId, $accountId, $database, $mailer, $emailTemplates): void {
            $status = (string) $request->post('status', '');
            $remark = trim((string) $request->post('admin_remark', ''));
            $change = $auth->updateCustomerAccountStatus($userId, $accountId, $status);
            $audit->record('account.status_updated', 'account', $accountId, $remark ?: null, $change + ['user_id' => $userId]);
            if ($mailer->isConfigured() && in_array($status, ['restricted', 'frozen', 'closed'], true)) {
                $customer = $database->fetchOne('SELECT first_name, email FROM users WHERE id = ?', [$userId]);
                $rendered = $emailTemplates->render('account_status', [
                    'first_name' => (string) ($customer['first_name'] ?? ''),
                    'status' => ucwords(str_replace('_', ' ', $status)),
                ]);
                if ($rendered !== null && $customer) {
                    $mailer->send((string) $customer['email'], $rendered['subject'], $rendered['body']);
                }
            }
        }, 'Currency account status updated.', '/admin/users/' . $userId);
    }, 'admin', true);

    $router->post('/admin/users/{id}/adjust', static function (Request $request) use ($banking, $database, $audit, $attempt, $mailer, $emailTemplates): never {
        $userId = (string) $request->route('id');
        $attempt(static function () use ($banking, $database, $audit, $mailer, $emailTemplates, $request, $userId): void {
            $direction = (string) $request->post('direction', '');
            $remark = trim((string) $request->post('remark', ''));
            $transaction = $banking->adminAdjustBalance(
                (string) Context::user()['id'],
                $userId,
                (string) $request->post('account_id', ''),
                $direction,
                (string) $request->post('amount', ''),
                $remark,
                (string) $request->post('idem', '')
            );
            $audit->record('account.adjusted', 'transaction', (string) $transaction['id'], $remark, ['direction' => $direction, 'user_id' => $userId, 'public_reference' => $transaction['public_reference']]);
            if ($mailer->isConfigured()) {
                $customer = $database->fetchOne('SELECT first_name, email FROM users WHERE id = ?', [$userId]);
                $account = $database->fetchOne('SELECT code, scale, symbol FROM assets WHERE id = (SELECT asset_id FROM accounts WHERE id = ?)', [(string) $request->post('account_id', '')]);
                $templateKey = $direction === 'credit' ? 'topup' : 'debit';
                $rendered = $emailTemplates->render($templateKey, [
                    'first_name' => (string) ($customer['first_name'] ?? ''),
                    'amount' => wayfairs_major((int) $transaction['amount_minor'], (int) ($account['scale'] ?? 2)),
                    'currency' => (string) ($account['code'] ?? ''),
                    'reference' => (string) ($transaction['public_reference'] ?? ''),
                    'date' => gmdate('Y-m-d H:i'),
                ]);
                if ($rendered !== null && $customer) {
                    $mailer->send((string) $customer['email'], $rendered['subject'], $rendered['body']);
                }
            }
        }, 'Customer balance adjustment completed.', '/admin/users/' . $userId);
    }, 'admin', true);

    $router->post('/admin/users/{id}/kyc', static function (Request $request) use ($kyc, $audit, $attempt): never {
        $userId = (string) $request->route('id');
        $attempt(static function () use ($kyc, $audit, $userId, $request): void {
            $status = (string) $request->post('status', '');
            $reason = trim((string) $request->post('reason', ''));
            $kyc->decide($userId, $status, $reason, (string) Context::user()['id']);
            $audit->record('kyc.' . $status, 'user', $userId, $reason, ['status' => $status]);
        }, 'KYC decision recorded.', '/admin/users/' . $userId);
    }, 'admin', true);

    $router->post('/admin/users/{id}/restrict', static function (Request $request) use ($restrictions, $audit, $attempt): never {
        $userId = (string) $request->route('id');
        $attempt(static function () use ($restrictions, $audit, $userId, $request): void {
            $capability = (string) $request->post('capability', '');
            $reason = trim((string) $request->post('reason', ''));
            if (strlen($reason) < 10) {
                throw new RuntimeException('Give a restriction reason of at least 10 characters.');
            }
            $restrictions->apply($userId, $capability, (string) $request->post('reason_code', 'manual_review'), $reason, (string) Context::user()['id'], $request->post('expires_at') ?: null);
            $audit->record('restriction.applied', 'user', $userId, $reason, ['capability' => $capability]);
        }, 'Restriction applied.', '/admin/users/' . $userId);
    }, 'admin', true);

    $router->post('/admin/restrictions/{id}/release', static function (Request $request) use ($restrictions, $audit, $attempt): never {
        $restrictionId = (string) $request->route('id');
        $userId = (string) $request->post('user_id', '');
        $attempt(static function () use ($restrictions, $audit, $restrictionId, $userId, $request): void {
            $reason = trim((string) $request->post('reason', ''));
            if (strlen($reason) < 10) {
                throw new RuntimeException('Give a release reason of at least 10 characters.');
            }
            $restrictions->release($restrictionId, $reason, (string) Context::user()['id']);
            $audit->record('restriction.released', 'capability_restriction', $restrictionId, $reason);
        }, 'Restriction released.', '/admin/users/' . $userId);
    }, 'admin', true);

    $router->post('/admin/impersonate/{id}', static function (Request $request) use ($auth, $audit): never {
        $targetId = (string) $request->route('id');
        try {
            $token = $auth->impersonate((string) Context::user()['id'], $targetId, (string) $request->post('reason', ''), (string) $request->post('password', ''));
            wayfairs_set_session_cookie($token, 30 * 60);
            Flash::put('success', 'You are now viewing the account. The banner shows the session and it expires in 30 minutes.');
            Response::redirect('/dashboard');
        } catch (RuntimeException $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect('/admin/users/' . $targetId);
        }
    }, 'admin', true);

    $router->post('/admin/stop-impersonation', static function () use ($auth): never {
        $token = Context::sessionToken();
        $targetId = (string) (Context::user()['id'] ?? '');
        try {
            if ($token !== null) {
                $adminToken = $auth->resumeAdminSession($token, Context::requestId());
                wayfairs_set_session_cookie($adminToken, 720 * 60);
            }
            Flash::put('success', 'Support view ended. You are back in the administration console.');
            Response::redirect($targetId !== '' ? '/admin/users/' . $targetId : '/admin');
        } catch (RuntimeException $error) {
            Flash::put('error', $error->getMessage());
            Response::redirect('/login');
        }
    }, 'auth', true);

    $router->get('/admin/deposits', static function () use ($admin, $database, $rates): void {
        $assets = $database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets');
        $admin('admin/deposits', [
            'deposits' => $database->fetchAll("SELECT d.*, u.email, u.first_name, u.last_name, a.account_number, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale FROM deposit_requests d JOIN users u ON u.id = d.user_id JOIN accounts a ON a.id = d.account_id JOIN assets s ON s.id = a.asset_id ORDER BY CASE d.status WHEN 'pending' THEN 0 ELSE 1 END, d.created_at DESC LIMIT 300"),
            'usdRates' => $rates->ratesForAssets($assets),
        ]);
    }, 'admin');

    $router->post('/admin/deposits/{id}/decision', static function (Request $request) use ($banking, $audit, $attempt): never {
        $id = (string) $request->route('id');
        $attempt(static function () use ($banking, $audit, $id, $request): void {
            $approve = $request->post('decision') === 'approve';
            $remark = trim((string) $request->post('remark', ''));
            $banking->verifyDeposit($id, (string) Context::user()['id'], $approve, $remark, (string) $request->post('external_reference', ''));
            $audit->record($approve ? 'deposit.approved' : 'deposit.rejected', 'deposit_request', $id, $remark ?: null);
        }, 'Deposit decision recorded.', '/admin/deposits');
    }, 'admin', true);

    $router->get('/admin/withdrawals', static function () use ($admin, $database, $rates): void {
        $assets = $database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets');
        $admin('admin/withdrawals', [
            'withdrawals' => $database->fetchAll("SELECT w.*, u.email, u.first_name, u.last_name, a.account_number, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale FROM withdrawal_requests w JOIN users u ON u.id = w.user_id JOIN accounts a ON a.id = w.account_id JOIN assets s ON s.id = a.asset_id WHERE w.request_kind = 'withdrawal' ORDER BY CASE w.status WHEN 'pending' THEN 0 ELSE 1 END, w.created_at DESC LIMIT 300"),
            'usdRates' => $rates->ratesForAssets($assets),
        ]);
    }, 'admin');

    $router->get('/admin/bank-transfers', static function () use ($admin, $database, $rates): void {
        $assets = $database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets');
        $admin('admin/bank-transfers', [
            'transfers' => $database->fetchAll("SELECT w.*, u.email, u.first_name, u.last_name, a.account_number, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale FROM withdrawal_requests w JOIN users u ON u.id = w.user_id JOIN accounts a ON a.id = w.account_id JOIN assets s ON s.id = a.asset_id WHERE w.request_kind = 'bank_transfer' ORDER BY CASE w.status WHEN 'pending' THEN 0 ELSE 1 END, w.created_at DESC LIMIT 300"),
            'usdRates' => $rates->ratesForAssets($assets),
        ]);
    }, 'admin');

    $router->get('/admin/transactions', static function () use ($admin, $database, $rates): void {
        $assets = $database->fetchAll('SELECT id, code, asset_type, usd_provider_reference FROM assets');
        $admin('admin/transactions', [
            'transactions' => $database->fetchAll("SELECT t.*, u.email, u.first_name, u.last_name, a.account_number, s.code AS asset_code, s.symbol AS asset_symbol, s.scale AS asset_scale
                FROM transactions t
                JOIN users u ON u.id = t.user_id
                JOIN accounts a ON a.id = t.account_id
                JOIN assets s ON s.id = a.asset_id
                ORDER BY t.created_at DESC LIMIT 300"),
            'usdRates' => $rates->ratesForAssets($assets),
        ]);
    }, 'admin');

    $router->get('/admin/transactions/{id}', static function (Request $request) use ($admin, $banking, $rates): void {
        try {
            $receipt = $banking->receiptForAdmin((string) $request->route('id'));
        } catch (RuntimeException) {
            Response::notFound();
        }
        $admin('admin/transaction', [
            'receipt' => $receipt,
            'usdRates' => $rates->ratesForAssets([[
                'id' => $receipt['asset_id'], 'code' => $receipt['asset_code'], 'asset_type' => $receipt['asset_type'], 'usd_provider_reference' => $receipt['usd_provider_reference'],
            ]]),
        ]);
    }, 'admin');

    $router->post('/admin/withdrawals/{id}/decision', static function (Request $request) use ($banking, $database, $audit, $attempt): never {
        $id = (string) $request->route('id');
        $attempt(static function () use ($banking, $database, $audit, $id, $request): void {
            if (!$database->fetchOne("SELECT id FROM withdrawal_requests WHERE id = ? AND request_kind = 'withdrawal'", [$id])) {
                throw new RuntimeException('Withdrawal request was not found.');
            }
            $approve = $request->post('decision') === 'approve';
            $remark = trim((string) $request->post('remark', ''));
            $banking->approveWithdrawal($id, (string) Context::user()['id'], $approve, $remark, (string) $request->post('external_reference', ''));
            $audit->record($approve ? 'withdrawal.completed' : 'withdrawal.rejected', 'withdrawal_request', $id, $remark ?: null, ['external_reference' => (string) $request->post('external_reference', '')]);
        }, 'Withdrawal decision recorded.', '/admin/withdrawals');
    }, 'admin', true);

    $router->post('/admin/bank-transfers/{id}/decision', static function (Request $request) use ($banking, $database, $audit, $attempt): never {
        $id = (string) $request->route('id');
        $attempt(static function () use ($banking, $database, $audit, $id, $request): void {
            $transfer = $database->fetchOne("SELECT id FROM withdrawal_requests WHERE id = ? AND request_kind = 'bank_transfer'", [$id]);
            if (!$transfer) {
                throw new RuntimeException('Bank transfer request was not found.');
            }
            $approve = $request->post('decision') === 'approve';
            $remark = trim((string) $request->post('remark', ''));
            $banking->approveWithdrawal($id, (string) Context::user()['id'], $approve, $remark, (string) $request->post('external_reference', ''));
            $audit->record($approve ? 'bank_transfer.completed' : 'bank_transfer.rejected', 'withdrawal_request', $id, $remark ?: null, ['external_reference' => (string) $request->post('external_reference', '')]);
        }, 'Bank transfer decision recorded.', '/admin/bank-transfers');
    }, 'admin', true);

    $router->get('/admin/flights', static function () use ($admin, $flights): void {
        $admin('admin/flights', ['requests' => $flights->forAdmin()]);
    }, 'admin');

    $router->post('/admin/flights/{id}/status', static function (Request $request) use ($flights, $attempt): never {
        $id = (string) $request->route('id');
        $attempt(static fn () => $flights->decide($id, (string) $request->post('status', ''), (string) $request->post('admin_note', ''), (string) Context::user()['id']), 'Flight request updated.', '/admin/flights');
    }, 'admin', true);

    $router->get('/admin/otp', static function () use ($admin, $database, $otp): void {
        $admin('admin/otp', ['enabled' => $otp->isEnabled()]);
    }, 'admin');

    $router->post('/admin/otp', static function (Request $request) use ($database, $otp, $audit, $attempt): never {
        $attempt(static function () use ($database, $otp, $audit, $request): void {
            $settings = new SiteSettings($database);
            $enable = (string) $request->post('enabled', '0') === '1';
            $settings->update('feature.otp', $enable ? '1' : '0', (string) Context::user()['id'], false);
            $audit->record($enable ? 'feature.otp.enabled' : 'feature.otp.disabled', 'site_setting', 'feature.otp', null, []);
        }, 'Verification code setting saved.', '/admin/otp');
    }, 'admin', true);

    $router->get('/admin/smtp', static function () use ($admin, $database, $mailer): void {
        $settings = new SiteSettings($database);
        $values = [];
        foreach (['smtp.host', 'smtp.port', 'smtp.username', 'smtp.encryption', 'smtp.from_email', 'smtp.from_name', 'smtp.verify_peer'] as $key) {
            $values[$key] = $settings->get($key, '');
        }
        $admin('admin/smtp', ['values' => $values, 'mailer' => $mailer]);
    }, 'admin');

    $router->post('/admin/smtp', static function (Request $request) use ($database, $mailer, $audit, $attempt): never {
        $attempt(static function () use ($database, $mailer, $audit, $request): void {
            $settings = new SiteSettings($database);
            $keys = ['smtp.host', 'smtp.port', 'smtp.username', 'smtp.password', 'smtp.encryption', 'smtp.from_email', 'smtp.from_name', 'smtp.verify_peer'];
            $values = [];
            foreach ($keys as $key) {
                $postKey = str_replace('.', '_', $key);
                $value = trim((string) $request->post($postKey, ''));
                if ($key === 'smtp.port') {
                    $value = (string) max(1, min(65535, (int) $value));
                }
                if ($key === 'smtp.encryption' && !in_array($value, ['none', 'tls', 'ssl'], true)) {
                    $value = 'tls';
                }
                if ($key === 'smtp.verify_peer') {
                    $value = $request->post('smtp_verify_peer') === '1' ? '1' : '0';
                }
                if ($key === 'smtp.from_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid from email address.');
                }
                if ($key === 'smtp.password' && $value !== '' && !str_starts_with($value, 'enc:')) {
                    $value = 'enc:' . \Wayfairs\Core\Security::encrypt(['v' => $value], (string) \Wayfairs\Core\Config::instance()->get('app_key', ''));
                }
                $values[$key] = $value;
            }
            // Preserve an existing password when the field is left blank.
            if (trim((string) $request->post('smtp_password', '')) === '') {
                unset($values['smtp.password']);
            }
            foreach ($values as $key => $value) {
                $settings->update($key, (string) $value, (string) Context::user()['id'], false);
            }
            $audit->record('smtp.updated', 'site_setting', null, 'Administrator updated SMTP settings', ['keys' => array_keys($values)]);
        }, 'SMTP settings saved.', '/admin/smtp');
    }, 'admin', true);

    $router->post('/admin/smtp/test', static function (Request $request) use ($mailer, $attempt): never {
        $to = trim((string) $request->post('test_email', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Flash::put('error', 'Enter a valid email address to test.');
            Response::redirect('/admin/smtp');
        }
        $result = $mailer->test($to);
        if ($result['ok']) {
            Flash::put('success', 'Test email sent to ' . $to . '.');
        } else {
            Flash::put('error', 'Test email failed: ' . ($result['error'] ?? 'unknown error'));
        }
        Response::redirect('/admin/smtp');
    }, 'admin', true);

    $router->get('/admin/email-templates', static function () use ($admin, $emailTemplates): void {
        $admin('admin/email-templates', ['templates' => $emailTemplates->all()]);
    }, 'admin');

    $router->post('/admin/email-templates/{key}', static function (Request $request) use ($emailTemplates, $audit, $attempt): never {
        $key = (string) $request->route('key');
        $attempt(static function () use ($emailTemplates, $audit, $request, $key): void {
            $emailTemplates->update($key, (string) $request->post('subject', ''), (string) $request->post('body', ''), (string) Context::user()['id']);
            $audit->record('email_template.updated', 'email_template', $key, null, []);
        }, 'Email template saved.', '/admin/email-templates');
    }, 'admin', true);

    $router->get('/admin/settings', static function () use ($admin, $database): void {
        $admin('admin/settings', [
            'values' => wayfairs_public_settings(),
            'bankTransfersEnabled' => Features::enabled($database, Features::BANK_TRANSFERS),
            'missingExtensions' => Security::missingServerExtensions(),
        ]);
    }, 'admin');

    $router->post('/admin/features/bank-transfers', static function (Request $request) use ($database, $audit, $attempt): never {
        $attempt(static function () use ($database, $audit, $request): void {
            $settings = new SiteSettings($database);
            $enable = (string) $request->post('enabled', '0') === '1';
            if ($enable) {
                Security::assertServerReady();
            }
            $settings->update(Features::BANK_TRANSFERS, $enable ? '1' : '0', (string) Context::user()['id'], false);
            $audit->record($enable ? 'feature.bank_transfers.enabled' : 'feature.bank_transfers.disabled', 'site_setting', Features::BANK_TRANSFERS, null, []);
        }, 'Bank transfer setting saved.', '/admin/settings');
    }, 'admin', true);

    $router->post('/admin/settings', static function (Request $request) use ($settings, $audit, $attempt): never {
        $attempt(static function () use ($settings, $audit, $request): void {
            $keys = ['site.name', 'site.tagline', 'site.support_email', 'site.support_phone', 'site.address', 'site.primary_color', 'site.accent_color', 'site.deposit_instructions_fiat', 'site.deposit_instructions_crypto', 'site.withdrawal_notice', 'site.kyc_notice', 'site.footer', 'site.smartsupp_key'];
            $values = [];
            foreach ($keys as $key) {
                $value = trim((string) $request->post(str_replace('.', '_', $key), ''));
                if ($key === 'site.name' && $value === '') {
                    throw new RuntimeException('The bank name cannot be empty.');
                }
                if ($key === 'site.support_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid support email address.');
                }
                if (in_array($key, ['site.primary_color', 'site.accent_color'], true) && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    throw new RuntimeException('Colours must be six-digit hex values such as #0f8b6d.');
                }
                $values[$key] = mb_substr($value, 0, 1000);
            }
            $settings->updateMany($values, (string) Context::user()['id'], true);
            $audit->record('site_settings.updated', 'site_setting', null, 'Administrator updated site information', ['keys' => array_keys($values)]);
        }, 'Site information saved.', '/admin/settings');
    }, 'admin', true);

    $router->get('/admin/assets', static function () use ($admin, $database, $rates): void {
        $assets = $database->fetchAll('SELECT * FROM assets ORDER BY asset_type, code');
        $admin('admin/assets', [
            'assets' => $assets,
            'usdRates' => $rates->ratesForAssets($assets),
        ]);
    }, 'admin');

    $router->post('/admin/assets/rates/refresh', static function () use ($database, $rates, $audit): never {
        $assets = $database->fetchAll('SELECT * FROM assets WHERE enabled = 1 ORDER BY code');
        $projection = $rates->ratesForAssets($assets, true);
        $statuses = array_map(static fn (array $rate): string => (string) ($rate['status'] ?? 'unavailable'), $projection);
        $audit->record('usd_rates.refreshed', 'usd_rate', null, null, ['statuses' => $statuses]);
        $available = count(array_filter($statuses, static fn (string $status): bool => $status !== 'unavailable'));
        Flash::put($available > 0 ? 'success' : 'error', $available . ' of ' . count($assets) . ' enabled USD rates are available.');
        Response::redirect('/admin/assets');
    }, 'admin', true);

    $router->post('/admin/assets', static function (Request $request) use ($database, $audit, $attempt): never {
        $attempt(static function () use ($database, $audit, $request): void {
            $code = strtoupper(trim((string) $request->post('code', '')));
            $name = trim((string) $request->post('name', ''));
            $type = (string) $request->post('asset_type', '');
            $symbol = trim((string) $request->post('symbol', ''));
            $scale = (int) $request->post('scale', 0);
            $providerReference = strtolower(trim((string) $request->post('usd_provider_reference', '')));
            if ($code === '' || $name === '' || !in_array($type, ['fiat', 'crypto'], true) || $symbol === '' || $scale < 0 || $scale > 18) {
                throw new \RuntimeException('Complete all asset fields with a valid type and scale.');
            }
            if ($type === 'crypto' && $providerReference !== '' && !preg_match('/^[a-z0-9-]{1,120}$/', $providerReference)) {
                throw new \RuntimeException('CoinGecko ID may contain lowercase letters, numbers, and hyphens only.');
            }
            $existing = $database->fetchOne('SELECT id FROM assets WHERE code = ?', [$code]);
            if ($existing) {
                throw new \RuntimeException('That asset code already exists.');
            }
            $assetId = Security::uuid();
            $database->transaction(function (Database $db) use ($assetId, $code, $name, $type, $symbol, $scale, $providerReference): void {
                $db->execute('INSERT INTO assets (id, code, name, asset_type, symbol, scale, usd_provider_reference) VALUES (?, ?, ?, ?, ?, ?, ?)', [$assetId, $code, $name, $type, $symbol, $scale, $type === 'crypto' && $providerReference !== '' ? $providerReference : null]);
                $db->execute("INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, 'system', NULL, ?, ?, 'asset', 'debit')", [Security::uuid(), $assetId, 'SYSTEM:' . $code, 'System ' . $code . ' control']);
                $db->execute("INSERT INTO ledger_accounts (id, asset_id, owner_type, owner_id, code, name, account_class, normal_side) VALUES (?, ?, 'system', NULL, ?, ?, 'revenue', 'credit')", [Security::uuid(), $assetId, 'SYSTEM:' . $code . ':REVENUE', 'System ' . $code . ' revenue']);
            });
            $audit->record('asset.created', 'asset', $assetId, 'Administrator added asset ' . $code);
        }, 'Asset added.', '/admin/assets');
    }, 'admin', true);

    $router->post('/admin/assets/{code}/update', static function (Request $request) use ($database, $audit, $attempt): never {
        $code = (string) $request->route('code');
        $attempt(static function () use ($database, $audit, $request, $code): void {
            $asset = $database->fetchOne('SELECT * FROM assets WHERE code = ?', [$code]);
            if (!$asset) {
                throw new \RuntimeException('Asset not found.');
            }
            $name = trim((string) $request->post('name', ''));
            if ($name === '') {
                throw new \RuntimeException('Asset name is required.');
            }
            $scale = (int) $asset['scale'];
            $providerReference = strtolower(trim((string) $request->post('usd_provider_reference', '')));
            if ((string) $asset['asset_type'] === 'crypto' && $providerReference !== '' && !preg_match('/^[a-z0-9-]{1,120}$/', $providerReference)) {
                throw new \RuntimeException('CoinGecko ID may contain lowercase letters, numbers, and hyphens only.');
            }
            try {
                $minDeposit = Money::parse((string) $request->post('min_deposit', '0'), $scale);
                $minWithdrawal = Money::parse((string) $request->post('min_withdrawal', '0'), $scale);
                $withdrawalFee = Money::parse((string) $request->post('withdrawal_fee', '0'), $scale);
                $transferFee = Money::parse((string) $request->post('transfer_fee', '0'), $scale);
            } catch (\InvalidArgumentException $error) {
                throw new \RuntimeException('Check limits and fees for ' . $code . ': ' . $error->getMessage());
            }
            $database->execute(
                'UPDATE assets SET name = ?, enabled = ?, deposit_enabled = ?, withdrawal_enabled = ?, min_deposit_minor = ?, min_withdrawal_minor = ?, withdrawal_fee_minor = ?, transfer_fee_minor = ?, usd_provider_reference = ?, deposit_bank_name = ?, deposit_account_name = ?, deposit_account_number = ?, deposit_routing = ?, deposit_swift = ?, deposit_wallet_address = ?, deposit_network = ?, deposit_memo = ?, deposit_instructions = ?, updated_at = ? WHERE code = ?',
                [
                    $name,
                    (int) ($request->post('enabled') === '1'),
                    (int) ($request->post('deposit_enabled') === '1'),
                    (int) ($request->post('withdrawal_enabled') === '1'),
                    $minDeposit,
                    $minWithdrawal,
                    $withdrawalFee,
                    $transferFee,
                    (string) $asset['asset_type'] === 'crypto' ? ($providerReference ?: null) : null,
                    trim((string) $request->post('deposit_bank_name', '')) ?: null,
                    trim((string) $request->post('deposit_account_name', '')) ?: null,
                    trim((string) $request->post('deposit_account_number', '')) ?: null,
                    trim((string) $request->post('deposit_routing', '')) ?: null,
                    trim((string) $request->post('deposit_swift', '')) ?: null,
                    trim((string) $request->post('deposit_wallet_address', '')) ?: null,
                    trim((string) $request->post('deposit_network', '')) ?: null,
                    trim((string) $request->post('deposit_memo', '')) ?: null,
                    trim((string) $request->post('deposit_instructions', '')) ?: null,
                    gmdate('Y-m-d H:i:s.u'),
                    $code,
                ]
            );
            $audit->record('asset.updated', 'asset', (string) $asset['id'], 'Administrator updated asset ' . $code);
        }, 'Asset updated.', '/admin/assets');
    }, 'admin', true);

    $router->post('/admin/assets/{code}/delete', static function (Request $request) use ($database, $audit, $attempt): never {
        $code = (string) $request->route('code');
        $attempt(static function () use ($database, $audit, $code): void {
            $asset = $database->fetchOne('SELECT * FROM assets WHERE code = ?', [$code]);
            if (!$asset) {
                throw new \RuntimeException('Asset not found.');
            }
            $hasAccounts = (int) ($database->fetchOne('SELECT COUNT(*) AS n FROM accounts WHERE asset_id = ?', [(string) $asset['id']])['n'] ?? 0);
            if ($hasAccounts > 0) {
                throw new \RuntimeException('Cannot delete an asset that has customer accounts. Disable it instead.');
            }
            $database->transaction(function (Database $db) use ($asset): void {
                $db->execute('DELETE FROM ledger_accounts WHERE asset_id = ?', [(string) $asset['id']]);
                $db->execute('DELETE FROM assets WHERE id = ?', [(string) $asset['id']]);
            });
            $audit->record('asset.deleted', 'asset', (string) $asset['id'], 'Administrator removed asset ' . $code);
        }, 'Asset removed.', '/admin/assets');
    }, 'admin', true);

    $router->get('/admin/audit', static function () use ($admin, $database): void {
        $admin('admin/audit', [
            'events' => $database->fetchAll('SELECT ae.*, actor.email AS actor_email, effective.email AS effective_email FROM audit_events ae LEFT JOIN users actor ON actor.id = ae.actor_user_id LEFT JOIN users effective ON effective.id = ae.effective_user_id ORDER BY ae.created_at DESC LIMIT 300'),
        ]);
    }, 'admin');
};
