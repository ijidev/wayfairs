<?php

declare(strict_types=1);

$asset = $account['asset'];
$available = (int) $account['available_minor'];
$held = (int) ($account['held_minor'] ?? 0);
$total = $available + $held;
$rate = $usdRates[$asset['code']] ?? null;
?>
<header class="page-header">
  <div class="page-heading">
    <span class="page-icon"><?= wayfairs_icon('wallet') ?></span>
    <div class="page-heading-copy">
      <span class="page-kicker"><?= e($asset['asset_type'] === 'crypto' ? 'Digital asset account' : 'Fiat currency account') ?></span>
      <h1><?= e((string) $asset['name']) ?> account</h1>
      <p>Account details and the complete loaded statement for <?= e((string) $asset['code']) ?>.</p>
    </div>
  </div>
  <div class="page-actions">
    <a class="btn ghost" href="<?= e(wayfairs_url('/dashboard?currency=' . $asset['code'])) ?>">Back to overview</a>
  </div>
</header>

<section class="content-grid">
  <article class="card balance-card v2">
    <div class="balance-layout">
      <div>
        <p class="eyebrow">Available balance</p>
        <div class="balance"><?= e(wayfairs_amount($available, (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?></div>
        <strong class="usd-equivalent">≈ <?= e(wayfairs_usd_equivalent($available, (int) $asset['scale'], $rate)) ?></strong>
        <div class="balance-owner"><span class="hint"><?= e((string) $account['account_number']) ?></span><?= wayfairs_pill((string) $account['status']) ?></div>
      </div>
      <div class="balance-side">
        <p class="hint">Available excludes funds reserved for open withdrawal requests.</p>
        <div class="balance-actions">
          <?php if ((int) $asset['deposit_enabled'] === 1): ?><a class="btn small" href="<?= e(wayfairs_url('/deposit')) ?>">Deposit</a><?php endif; ?>
          <?php if ((int) $asset['withdrawal_enabled'] === 1): ?><a class="btn small ghost" href="<?= e(wayfairs_url('/withdraw')) ?>">Withdraw</a><?php endif; ?>
          <a class="btn small ghost" href="<?= e(wayfairs_url('/transfer')) ?>">Transfer</a>
        </div>
      </div>
    </div>
    <div class="balance-meta">
      <div><strong><?= e(wayfairs_amount($held, (int) $asset['scale'], (string) $asset['symbol'])) ?></strong><span>Reserved</span></div>
      <div><strong><?= e(wayfairs_amount($total, (int) $asset['scale'], (string) $asset['symbol'])) ?></strong><span>Total balance</span></div>
      <div><strong><?= e(wayfairs_datetime((string) $account['created_at'])) ?></strong><span>Opened</span></div>
    </div>
  </article>

  <aside class="card static">
    <div class="panel-head"><div><h2>Account details</h2><p>Identifiers and current operating state.</p></div></div>
    <div class="destination-details">
      <div class="destination-field wide"><span>Account number</span><div class="copy-line"><strong class="mono"><?= e((string) $account['account_number']) ?></strong><button class="btn small ghost copy-button" type="button" data-copy="<?= e((string) $account['account_number']) ?>" aria-label="Copy account number"><?= wayfairs_icon('copy') ?></button></div></div>
      <div class="destination-field"><span>Currency</span><strong><?= e((string) $asset['code']) ?></strong></div>
      <div class="destination-field"><span>Type</span><strong><?= e(ucfirst((string) $asset['asset_type'])) ?></strong></div>
      <div class="destination-field"><span>Status</span><strong><?= wayfairs_pill((string) $account['status']) ?></strong></div>
      <div class="destination-field"><span>Decimals</span><strong><?= (int) $asset['scale'] ?></strong></div>
    </div>
  </aside>
</section>

<section class="card static">
  <div class="panel-head">
    <div><h2><?= e((string) $asset['code']) ?> statement</h2><p><?= count($history) ?> loaded transaction record<?= count($history) === 1 ? '' : 's' ?> for this account.</p></div>
    <a class="btn small ghost" href="<?= e(wayfairs_url('/transactions')) ?>">All transactions</a>
  </div>
  <?php if ($history === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('activity') ?></div><h3>No account activity</h3><p>This account has no transactions yet.</p></div>
  <?php else: ?>
    <div class="transaction-list">
      <?php foreach ($history as $row): $credit = (string) $row['direction'] === 'credit'; ?>
        <a class="transaction-item" href="<?= e(wayfairs_url('/transactions/' . $row['id'])) ?>">
          <span class="transaction-symbol <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
          <span class="transaction-main"><strong><?= e((string) $row['description']) ?></strong><span><?= e((string) $row['public_reference']) ?> · <?= e(str_replace('_', ' ', (string) $row['transaction_type'])) ?></span></span>
          <span class="transaction-meta"><strong><?= e(wayfairs_usd_equivalent((int) $row['amount_minor'], (int) $asset['scale'], $rate)) ?></strong><span><?= e(wayfairs_datetime((string) $row['created_at'])) ?></span></span>
          <span class="transaction-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . wayfairs_amount((int) $row['amount_minor'], (int) $asset['scale'], (string) $asset['symbol'])) ?></span>
          <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
