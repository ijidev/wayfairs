<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$customerName = trim((string) $profile['first_name'] . ' ' . (string) $profile['last_name']);
$initials = mb_strtoupper(mb_substr((string) $profile['first_name'], 0, 1) . mb_substr((string) $profile['last_name'], 0, 1));
$panels = [
    'overview' => 'Overview',
    'verification' => 'Verification',
    'support' => 'Support access',
    'funding' => 'Fund / debit',
    'restrictions' => 'Restrictions',
    'transactions' => 'Transactions',
];
$activePanel = (string) ($_GET['panel'] ?? 'overview');
if (!array_key_exists($activePanel, $panels)) {
    $activePanel = 'overview';
}
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('user') ?></span><div class="page-heading-copy"><span class="page-kicker">Customer account</span><h1><?= e($customerName) ?></h1><p><?= e((string) $profile['email']) ?> · registered <?= e(wayfairs_datetime((string) $profile['created_at'])) ?></p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/admin/users')) ?>">Customer directory</a><a class="btn" href="<?= e(wayfairs_url('/admin/users/' . $profile['id'] . '/edit')) ?>"><?= wayfairs_icon('settings') ?> Edit customer</a></div>
</header>

<nav class="subnav" aria-label="Customer sections">
  <?php foreach ($panels as $key => $label): ?>
    <a class="subnav-link<?= $activePanel === $key ? ' active' : '' ?>" href="<?= e(wayfairs_url('/admin/users/' . $profile['id'] . '?panel=' . $key)) ?>" data-panel="<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($activePanel === 'overview'): ?>
<section class="profile-hero">
  <span class="profile-avatar"><?= e($initials ?: 'U') ?></span>
  <span class="profile-hero-copy"><span class="page-kicker">Customer profile</span><h2><?= e($customerName) ?></h2><p><?= e((string) $profile['email']) ?><?= !empty($profile['phone']) ? ' · ' . e((string) $profile['phone']) : '' ?></p></span>
  <span class="profile-badges"><?= wayfairs_pill((string) $profile['status']) ?><?= wayfairs_pill('kyc ' . $kycStatus) ?></span>
</section>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('wallet') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($accounts) ?></strong><span class="metric-label">Currency accounts</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('activity') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($history) ?></strong><span class="metric-label">Loaded transactions</span></span></div>
  <div class="metric-card <?= $restrictions !== [] ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('security') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($restrictions) ?></strong><span class="metric-label">Active restrictions</span></span></div>
  <div class="metric-card <?= in_array($kycStatus, ['pending', 'in_review', 'needs_resubmission'], true) ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('verification') ?></span><span class="metric-copy"><strong class="metric-value long"><?= e(ucwords(str_replace('_', ' ', $kycStatus))) ?></strong><span class="metric-label">Verification status</span></span></div>
</section>

<section class="card static">
  <div class="panel-head"><div><h2>Currency accounts</h2><p>Available, reserved, and total balances remain separate by currency.</p></div><span class="pill"><?= count($accounts) ?> account<?= count($accounts) === 1 ? '' : 's' ?></span></div>
  <?php if ($accounts === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('wallet') ?></div><h3>No currency accounts</h3><p>No customer accounts have been opened.</p></div>
  <?php else: ?>
    <ul class="acct-list">
    <?php foreach ($accounts as $account): $held = (int) ($account['held_minor'] ?? 0); $available = (int) $account['available_minor']; $rate = $usdRates[$account['code']] ?? null; ?>
      <li class="acct-item">
        <details>
          <summary class="acct-row">
            <span class="acct-code"><?= e((string) $account['code']) ?></span>
            <span class="acct-main"><strong><?= e((string) ($account['name'] ?? $account['code'])) ?></strong><span class="mono"><?= e((string) $account['account_number']) ?></span></span>
            <span class="acct-amount"><strong><?= e(wayfairs_amount($available, (int) $account['scale'], (string) $account['symbol'], (string) $account['code'])) ?></strong><span>available</span></span>
            <span class="acct-state"><?= wayfairs_pill((string) $account['status']) ?></span>
          </summary>
          <div class="acct-body">
            <div class="acct-facts">
              <div><span>Reserved</span><strong><?= e(wayfairs_amount($held, (int) $account['scale'], (string) $account['symbol'])) ?></strong></div>
              <div><span>Total</span><strong><?= e(wayfairs_amount($available + $held, (int) $account['scale'], (string) $account['symbol'], (string) $account['code'])) ?></strong></div>
              <div><span>USD equivalent</span><strong><?= e(wayfairs_usd_equivalent($available + $held, (int) $account['scale'], $rate)) ?></strong></div>
            </div>
            <form class="acct-access" method="post" action="<?= e(wayfairs_url('/admin/users/' . $profile['id'] . '/accounts/' . $account['id'] . '/status')) ?>">
              <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
              <label>Access
                <select name="status"><option value="active" <?= $account['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="restricted" <?= $account['status'] === 'restricted' ? 'selected' : '' ?>>Restricted</option><option value="frozen" <?= $account['status'] === 'frozen' ? 'selected' : '' ?>>Frozen</option><option value="closed" <?= $account['status'] === 'closed' ? 'selected' : '' ?>>Closed</option></select>
              </label>
              <label>Remark<input name="admin_remark" maxlength="120" placeholder="Optional" /></label>
              <button class="btn small" type="submit">Save access</button>
            </form>
          </div>
        </details>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($activePanel === 'verification'): ?>
<section class="content-grid equal">
  <article class="card form-card">
    <div class="form-intro"><h2>Verification review</h2><p>Inspect the stored customer submission and record a reasoned decision.</p></div>
    <?php if (!empty($kyc['id_last4'])): ?>
      <div class="destination-details">
        <div class="destination-field"><span>Date of birth</span><strong><?= e((string) ($kyc['date_of_birth'] ?? '—')) ?></strong></div>
        <div class="destination-field"><span>Country</span><strong><?= e((string) ($kyc['country_code'] ?? '—')) ?></strong></div>
        <div class="destination-field wide"><span>Address</span><strong><?= e(trim(implode(', ', array_filter([(string) ($kyc['address_line1'] ?? ''), (string) ($kyc['address_line2'] ?? ''), (string) ($kyc['city'] ?? ''), (string) ($kyc['region'] ?? ''), (string) ($kyc['postal_code'] ?? '')])))) ?></strong></div>
        <div class="destination-field"><span>ID type</span><strong><?= e(ucwords(str_replace('_', ' ', (string) ($kyc['id_type'] ?? '—')))) ?></strong></div>
        <div class="destination-field"><span>ID ending</span><strong>•••• <?= e((string) $kyc['id_last4']) ?></strong></div>
        <div class="destination-field wide"><span>Submitted</span><strong><?= e(wayfairs_datetime($kyc['submitted_at'] ?? null)) ?></strong></div>
      </div>
    <?php else: ?>
      <div class="notice warn"><?= wayfairs_icon('info') ?><div><strong>No verification submission</strong><p>The customer has not stored identity details for review.</p></div></div>
    <?php endif; ?>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/users/' . $profile['id'] . '/kyc')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <label>Decision<select name="status" required><option value="verified">Approve</option><option value="needs_resubmission">Request resubmission</option><option value="rejected">Reject</option><option value="expired">Mark expired</option></select></label>
      <label>Reason shown to customer<input name="reason" minlength="10" maxlength="300" placeholder="Explain the evidence behind this decision" required /></label>
      <div class="form-actions"><button class="btn" type="submit">Record verification decision</button></div>
    </form>
  </article>
</section>
<?php endif; ?>

<?php if ($activePanel === 'support'): ?>
<section class="content-grid equal">
  <article class="card form-card">
    <div class="form-intro"><h2>View customer dashboard</h2><p>Open a time-limited audited support session using your current administrator credentials.</p></div>
    <div class="notice brand"><?= wayfairs_icon('security') ?><div><strong>Support access</strong><p>Use the separate balance-adjustment control below for funding or debiting an account.</p></div></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/impersonate/' . $profile['id'])) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <label>Support reason<input name="reason" minlength="10" maxlength="300" placeholder="Why support access is necessary" required /></label>
      <label>Your administrator password<input type="password" name="password" autocomplete="current-password" required /></label>
      <div class="form-actions"><button class="btn" type="submit">Open customer dashboard</button></div>
    </form>
  </article>
</section>
<?php endif; ?>

<?php if ($activePanel === 'funding'): ?>
<section class="content-grid">
  <article class="card form-card admin-adjustment-card">
    <div class="form-intro"><span class="page-kicker">Ledger operation</span><h2>Fund or debit customer</h2><p>Post an explicit balanced adjustment to one currency account without entering support impersonation.</p></div>
    <?php if ($accounts === []): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('wallet') ?></div><h3>No currency accounts</h3><p>The customer has no account available for adjustment.</p></div>
    <?php else: ?>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/users/' . $profile['id'] . '/adjust')) ?>">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />
        <div class="form-grid">
          <label>Currency account<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['id']) ?>" <?= $account['status'] === 'closed' ? 'disabled' : '' ?>><?= e((string) $account['code']) ?> · <?= e((string) $account['account_number']) ?> · <?= e(wayfairs_amount((int) $account['available_minor'], (int) $account['scale'], (string) $account['symbol'])) ?> available<?= $account['status'] === 'closed' ? ' · closed' : '' ?></option><?php endforeach; ?></select></label>
          <label>Operation<select name="direction" required><option value="credit">Credit — fund account</option><option value="debit">Debit — reduce account</option></select></label>
        </div>
        <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" required /></label>
        <label>Adjustment note<input name="remark" minlength="10" maxlength="500" placeholder="Explain the verified basis for this adjustment" required /><span class="field-hint">Required financial evidence; shown in the customer transaction history.</span></label>
        <div class="form-actions"><button class="btn" type="submit">Post balance adjustment</button></div>
      </form>
    <?php endif; ?>
  </article>
  <aside class="card static surface-muted sticky-panel">
    <div class="panel-head"><div><h2>Adjustment controls</h2><p>This is the supported way to act on a customer balance.</p></div></div>
    <div class="status-checklist">
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('audit') ?></span><span class="status-check-copy"><strong>Immutable receipt</strong><span>A completed adjustment transaction and audit event are appended.</span></span></div>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('layers') ?></span><span class="status-check-copy"><strong>Double-entry posting</strong><span>Customer and system control accounts remain balanced.</span></span></div>
      <div class="status-check"><span class="status-check-icon warn"><?= wayfairs_icon('security') ?></span><span class="status-check-copy"><strong>No overdrafts</strong><span>Administrative debits cannot exceed available funds or target a closed account.</span></span></div>
    </div>
  </aside>
</section>
<?php endif; ?>

<?php if ($activePanel === 'restrictions'): ?>
<section class="content-grid">
  <article class="card form-card">
    <div class="panel-head"><div><h2>Capability restrictions</h2><p>Active backend-enforced blocks for this customer.</p></div><span class="pill <?= $restrictions === [] ? 'good' : 'bad' ?>"><?= count($restrictions) ?> active</span></div>
    <?php if ($restrictions === []): ?>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>No active restrictions</strong><span>All capability checks defer to the customer's other account gates.</span></span></div>
    <?php else: ?>
      <div class="stack-list">
        <?php foreach ($restrictions as $restriction): ?>
          <div class="restriction-card">
            <div><strong><?= e(ucwords(str_replace('_', ' ', (string) $restriction['capability']))) ?></strong><p><?= e((string) $restriction['reason']) ?> · since <?= e(wayfairs_datetime((string) $restriction['starts_at'])) ?></p></div>
            <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/restrictions/' . $restriction['id'] . '/release')) ?>">
              <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><input type="hidden" name="user_id" value="<?= e((string) $profile['id']) ?>" />
              <input name="reason" minlength="10" maxlength="300" placeholder="Release reason" aria-label="Restriction release reason" required />
              <button class="btn small ghost" type="submit">Release</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
  <aside class="card form-card sticky-panel">
    <div class="form-intro"><h2>Apply a restriction</h2><p>A reason of at least 10 characters is required and audited.</p></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/users/' . $profile['id'] . '/restrict')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <div class="form-grid">
        <label>Capability<select name="capability" required><option value="fiat_deposit">Fiat deposits</option><option value="fiat_withdrawal">Fiat withdrawals</option><option value="crypto_deposit">Crypto deposits</option><option value="crypto_withdrawal">Crypto withdrawals</option><option value="internal_transfer">Transfers</option><option value="invoice">Invoices</option><option value="login">Sign-in</option></select></label>
        <label>Reason code<select name="reason_code"><option value="manual_review">Manual review</option><option value="fraud_risk">Fraud risk</option><option value="sanctions_hold">Sanctions hold</option><option value="customer_request">Customer request</option></select></label>
      </div>
      <label>Detailed reason<input name="reason" minlength="10" maxlength="300" placeholder="Evidence or customer instruction" required /></label>
      <label>Expires at <span class="field-hint">Optional</span><input type="datetime-local" name="expires_at" /></label>
      <div class="form-actions"><button class="btn danger" type="submit">Apply restriction</button></div>
    </form>
  </aside>
</section>
<?php endif; ?>

<?php if ($activePanel === 'transactions'): ?>
<section class="card static">
  <div class="panel-head"><div><h2>Customer transactions</h2><p>Latest 100 persisted records across this customer's accounts.</p></div><span class="pill"><?= count($history) ?> loaded</span></div>
  <?php if ($history === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('activity') ?></div><h3>No transactions</h3><p>No money movement is recorded for this customer.</p></div>
  <?php else: ?>
    <ul class="tx-list">
    <?php foreach ($history as $transaction): $credit = (string) $transaction['direction'] === 'credit'; $rate = $usdRates[$transaction['asset_code']] ?? null; $status = (string) $transaction['status']; ?>
      <li>
        <div class="tx-row admin-tx-row">
          <span class="tx-row-icon <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
          <span class="tx-row-main">
            <span class="tx-row-top"><strong class="tx-row-title"><?= e((string) $transaction['description']) ?></strong><span class="tx-row-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . wayfairs_amount((int) $transaction['amount_minor'], (int) $transaction['asset_scale'], (string) $transaction['asset_symbol'], (string) $transaction['asset_code'])) ?></span></span>
            <span class="tx-row-meta"><?= e(str_replace('_', ' ', (string) $transaction['transaction_type'])) ?> · <?= e(wayfairs_datetime((string) $transaction['created_at'])) ?> · <?= e(wayfairs_usd_equivalent((int) $transaction['amount_minor'], (int) $transaction['asset_scale'], $rate)) ?></span>
            <span class="tx-row-bottom"><?= wayfairs_pill($status) ?><span class="tx-row-ref"><?= e((string) $transaction['public_reference']) ?></span></span>
          </span>
        </div>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>
