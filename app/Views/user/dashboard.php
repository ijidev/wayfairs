<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$site = wayfairs_public_settings();
$user = \Wayfairs\Core\Context::user() ?? [];
$firstName = trim((string) ($user['first_name'] ?? '')) ?: 'there';
$requestedCode = strtoupper(trim((string) ($_GET['currency'] ?? '')));
$activeAccount = null;
foreach ($accounts as $candidate) {
    if ($requestedCode !== '' && (string) $candidate['asset']['code'] === $requestedCode) {
        $activeAccount = $candidate;
        break;
    }
}
if ($activeAccount === null && $accounts !== []) {
    $activeAccount = $accounts[0];
}
$asset = $activeAccount['asset'] ?? null;
$activeCode = (string) ($asset['code'] ?? '');
$available = (int) ($activeAccount['available_minor'] ?? 0);
$held = (int) ($activeAccount['held_minor'] ?? 0);
$total = $available + $held;
$activeRate = $usdRates[$activeCode] ?? null;
$portfolioUsdMinor = 0;
$pricedAccounts = 0;
foreach ($accounts as $portfolioAccount) {
    $portfolioAsset = $portfolioAccount['asset'];
    $usdMinor = wayfairs_usd_minor(
        (int) $portfolioAccount['available_minor'] + (int) ($portfolioAccount['held_minor'] ?? 0),
        (int) $portfolioAsset['scale'],
        $usdRates[$portfolioAsset['code']] ?? null
    );
    if ($usdMinor !== null) {
        $portfolioUsdMinor += $usdMinor;
        $pricedAccounts++;
    }
}
$accountDisplay = trim(chunk_split((string) ($activeAccount['account_number'] ?? ''), 4, ' '));
$fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
?>
<header class="page-header">
  <div class="page-heading">
    <span class="page-icon"><?= wayfairs_icon('overview') ?></span>
    <div class="page-heading-copy">
      <span class="page-kicker">Account overview</span>
      <h1>Welcome back, <?= e($firstName) ?></h1>
      <p>See your active balance, account readiness, and latest recorded money movements.</p>
    </div>
  </div>
  <div class="page-actions">
    <?= wayfairs_pill('kyc ' . $kycStatus) ?>
    <?php if ($verified): ?>
      <a class="btn" href="<?= e(wayfairs_url('/deposit')) ?>"><?= wayfairs_icon('deposit') ?> Deposit funds</a>
    <?php endif; ?>
  </div>
</header>

<?php if (!$verified): ?>
  <div class="notice warn">
    <?= wayfairs_icon('verification') ?>
    <div>
      <strong>Identity verification is not complete</strong>
      <p>Sending and withdrawing money unlocks once your identity is verified.</p>
      <p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Review verification <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a></p>
    </div>
  </div>
<?php endif; ?>

<?php foreach ($restrictions as $restriction): ?>
  <div class="notice bad">
    <?= wayfairs_icon('security') ?>
    <div>
      <strong><?= e(ucwords(str_replace('_', ' ', (string) $restriction['capability']))) ?> restricted</strong>
      <p><?= e((string) $restriction['reason']) ?>. Contact support if you believe this is incorrect.</p>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($activeAccount && $asset): ?>
  <section class="portfolio-grid">
    <article class="bank-balance-card" aria-label="<?= e((string) $asset['name']) ?> balance card">
      <div class="bank-card-top"><span class="bank-card-brand"><span class="mark"><?= e(mb_substr((string) ($site['site.name'] ?? 'Wayfairs'), 0, 1)) ?></span><span><strong><?= e((string) ($site['site.name'] ?? 'Wayfairs')) ?></strong><small>Everyday account</small></span></span><span class="bank-card-currency"><?= e($activeCode) ?></span></div>
      <div class="bank-card-chip" aria-hidden="true"><span></span><span></span><span></span></div>
      <div class="bank-card-balance"><small>Available balance</small><strong><?= e(wayfairs_amount($available, (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?></strong><span>≈ <?= e(wayfairs_usd_equivalent($available, (int) $asset['scale'], $activeRate)) ?></span></div>
      <div class="bank-card-number" translate="no"><?= e($accountDisplay) ?></div>
      <div class="bank-card-bottom"><span><small>Account holder</small><strong><?= e($fullName ?: 'Account owner') ?></strong></span><span><small>Total balance</small><strong><?= e(wayfairs_amount($total, (int) $asset['scale'], (string) $asset['symbol'])) ?></strong></span><span class="contactless"><?= wayfairs_icon('activity') ?></span></div>
    </article>

    <article class="card static portfolio-control">
      <div class="portfolio-total"><span><small>Total portfolio value</small><strong><?= $pricedAccounts > 0 ? e(wayfairs_amount($portfolioUsdMinor, 2, '$', 'USD')) : 'USD unavailable' ?></strong><em><?= $pricedAccounts === count($accounts) ? 'All currencies priced' : $pricedAccounts . ' of ' . count($accounts) . ' currencies priced' ?></em></span><span class="metric-icon"><?= wayfairs_icon('wallet') ?></span></div>
      <p class="field-hint"><?= e(wayfairs_rate_disclosure($activeRate)) ?>. USD values are estimates, not additional balances. Crypto market data by <a href="https://www.coingecko.com/" target="_blank" rel="noopener noreferrer">CoinGecko</a>; fiat reference data by <a href="https://frankfurter.dev/" target="_blank" rel="noopener noreferrer">Frankfurter</a>.</p>
      <label for="currency-select">Balance card currency<select id="currency-select"><?php foreach ($accounts as $candidate): ?><option value="<?= e((string) $candidate['asset']['code']) ?>" <?= $candidate['asset']['code'] === $activeCode ? 'selected' : '' ?>><?= e((string) $candidate['asset']['code']) ?> · <?= e((string) $candidate['asset']['name']) ?></option><?php endforeach; ?></select></label>
      <div class="card-action-row"><a class="btn" href="<?= e(wayfairs_url('/deposit')) ?>"><?= wayfairs_icon('deposit') ?> Deposit</a><a class="btn ghost" href="<?= e(wayfairs_url('/transfer')) ?>"><?= wayfairs_icon('transfer') ?> Transfer</a><a class="btn ghost" href="<?= e(wayfairs_url('/withdraw')) ?>"><?= wayfairs_icon('withdraw') ?> Withdraw</a></div>
      <div class="form-divider"></div>
      <div class="panel-head">
        <div><h2>Your currencies</h2><p>Choose an account to open its full statement.</p></div>
        <span class="pill"><?= count($accounts) ?> enabled</span>
      </div>
      <div class="account-stack">
        <?php foreach ($accounts as $candidate): $candidateAsset = $candidate['asset']; ?>
          <a class="account-tile <?= $candidateAsset['code'] === $activeCode ? 'active' : '' ?>" href="<?= e(wayfairs_url('/accounts/' . $candidateAsset['code'])) ?>">
            <span class="account-badge"><?= e((string) $candidateAsset['code']) ?></span>
            <span class="account-copy"><strong><?= e((string) $candidateAsset['name']) ?></strong><small><?= e((string) $candidate['account_number']) ?> · <?= e(ucfirst((string) $candidateAsset['asset_type'])) ?></small></span>
            <span class="account-end"><strong><?= e(wayfairs_usd_equivalent((int) $candidate['available_minor'] + (int) ($candidate['held_minor'] ?? 0), (int) $candidateAsset['scale'], $usdRates[$candidateAsset['code']] ?? null)) ?></strong><small>USD equivalent</small></span>
          </a>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <script>
  (function () {
    var select = document.getElementById('currency-select');
    if (!select) return;
    select.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('currency', select.value);
      window.location.href = url.toString();
    });
  })();
  </script>
<?php else: ?>
  <div class="notice brand">
    <?= wayfairs_icon('wallet') ?>
    <div><strong>No currency accounts yet</strong><p>Contact support to open a currency account.</p></div>
  </div>
<?php endif; ?>

<nav class="quick-grid" aria-label="Quick banking actions">
  <a class="quick-action" href="<?= e(wayfairs_url('/deposit')) ?>"><span class="icon-wrap"><?= wayfairs_icon('deposit') ?></span><span>Deposit</span></a>
  <a class="quick-action" href="<?= e(wayfairs_url('/withdraw')) ?>"><span class="icon-wrap"><?= wayfairs_icon('withdraw') ?></span><span>Withdraw</span></a>
  <a class="quick-action" href="<?= e(wayfairs_url('/transfer')) ?>"><span class="icon-wrap"><?= wayfairs_icon('transfer') ?></span><span>Send money</span></a>
  <a class="quick-action" href="<?= e(wayfairs_url('/invoices')) ?>"><span class="icon-wrap"><?= wayfairs_icon('invoice') ?></span><span>Invoices</span></a>
</nav>

<section class="utility-section" aria-labelledby="utilities-heading">
  <div class="panel-head"><div><h2 id="utilities-heading">Utilities</h2><p>Pay bills and plan travel from one place.</p></div></div>
  <div class="utility-grid">
    <a class="utility-card" href="<?= e(wayfairs_url('/bills')) ?>">
      <span class="icon-wrap"><?= wayfairs_icon('receipt') ?></span>
      <span class="utility-copy"><strong>Pay a bill</strong><small>Send money to a utility, school, or service provider using their bank details and your bill reference.</small></span>
      <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
    </a>
    <a class="utility-card" href="<?= e(wayfairs_url('/flights')) ?>">
      <span class="icon-wrap"><?= wayfairs_icon('globe') ?></span>
      <span class="utility-copy"><strong>Book a flight</strong><small>Tell us where and when you want to travel. We confirm availability and price before anything is booked.</small></span>
      <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
    </a>
  </div>
</section>

<section class="content-grid">
  <article class="card static">
    <div class="panel-head">
      <div><h2>Recent transactions</h2><p>Latest activity across your enabled accounts.</p></div>
      <a class="btn small ghost" href="<?= e(wayfairs_url('/transactions')) ?>">View all</a>
    </div>
    <?php if ($history === []): ?>
      <div class="empty-state">
        <div class="empty-icon"><?= wayfairs_icon('activity') ?></div>
        <h3>No transactions yet</h3>
        <p>Your transactions will appear here after your first money movement.</p>
      </div>
    <?php else: ?>
      <div class="transaction-list">
        <?php foreach (array_slice($history, 0, 6) as $row): $credit = (string) $row['direction'] === 'credit'; $rowRate = $usdRates[$row['asset_code']] ?? null; ?>
          <a class="transaction-item" href="<?= e(wayfairs_url('/receipts/' . $row['id'])) ?>">
            <span class="transaction-symbol <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
            <span class="transaction-main"><strong><?= e((string) $row['description']) ?></strong><span><?= e(str_replace('_', ' ', (string) $row['transaction_type'])) ?> · <?= e((string) $row['public_reference']) ?></span></span>
            <span class="transaction-meta"><strong><?= e(wayfairs_usd_equivalent((int) $row['amount_minor'], (int) $row['asset_scale'], $rowRate)) ?></strong><span><?= e(wayfairs_datetime((string) $row['created_at'])) ?></span></span>
            <span class="transaction-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . wayfairs_amount((int) $row['amount_minor'], (int) $row['asset_scale'], (string) $row['asset_symbol'])) ?></span>
            <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <aside class="card static">
    <div class="panel-head"><div><h2>Account readiness</h2><p>Controls currently applied to your account.</p></div></div>
    <div class="status-checklist">
      <div class="status-check">
        <span class="status-check-icon <?= $verified ? '' : 'warn' ?>"><?= wayfairs_icon($verified ? 'check' : 'clock') ?></span>
        <span class="status-check-copy"><strong>Identity verification</strong><span><?= e(ucwords(str_replace('_', ' ', $kycStatus))) ?></span></span>
      </div>
      <div class="status-check">
        <span class="status-check-icon <?= $restrictions === [] ? '' : 'bad' ?>"><?= wayfairs_icon($restrictions === [] ? 'check' : 'security') ?></span>
        <span class="status-check-copy"><strong>Capability restrictions</strong><span><?= $restrictions === [] ? 'None active' : count($restrictions) . ' active' ?></span></span>
      </div>
      <div class="status-check">
        <span class="status-check-icon"><?= wayfairs_icon('wallet') ?></span>
        <span class="status-check-copy"><strong>Enabled currency accounts</strong><span><?= count($accounts) ?> account<?= count($accounts) === 1 ? '' : 's' ?></span></span>
      </div>
      <div class="status-check">
        <span class="status-check-icon"><?= wayfairs_icon('support') ?></span>
        <span class="status-check-copy"><strong>Support</strong><span><?= e((string) ($site['site.support_email'] ?? 'Not configured')) ?></span></span>
      </div>
    </div>
  </aside>
</section>
