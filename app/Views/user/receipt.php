<?php

declare(strict_types=1);

$r = $receipt;
$site = wayfairs_public_settings();
$name = (string) ($site['site.name'] ?? 'Wayfairs');
$amount = wayfairs_amount((int) $r['amount_minor'], (int) $r['asset_scale'], (string) $r['asset_symbol'], (string) $r['asset_code']);
$fee = (int) $r['fee_minor'] > 0 ? wayfairs_amount((int) $r['fee_minor'], (int) $r['asset_scale'], (string) $r['asset_symbol'], (string) $r['asset_code']) : null;
$credit = (string) $r['direction'] === 'credit';
$metadata = json_decode((string) ($r['metadata'] ?? '{}'), true);
$typeLabel = is_array($metadata) && ($metadata['kind'] ?? null) === 'swap'
    ? 'Currency swap'
    : (is_array($metadata) && ($metadata['request_kind'] ?? null) === 'bank_transfer'
        ? 'Bank Transfer'
        : ucwords(str_replace('_', ' ', (string) $r['transaction_type'])));
$rate = $usdRates[$r['asset_code']] ?? null;
?>
<div class="receipt-page">
  <header class="page-header">
    <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('receipt') ?></span><div class="page-heading-copy"><span class="page-kicker">Transaction document</span><h1>Transaction receipt</h1><p>Details of this transaction.</p></div></div>
    <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/transactions')) ?>">Transactions</a><button class="btn" type="button" onclick="window.print()"><?= wayfairs_icon('receipt') ?> Print receipt</button></div>
  </header>

  <article class="receipt-document">
    <div class="receipt-banner">
      <div class="receipt-brand-row">
        <div class="receipt-brand"><span class="mark"><?= e(mb_substr($name, 0, 1)) ?></span><span><strong><?= e($name) ?></strong><small>Transaction receipt</small></span></div>
        <?= wayfairs_pill((string) $r['status']) ?>
      </div>
      <div class="receipt-amount"><small><?= e($credit ? 'Amount received' : 'Amount sent') ?></small><strong><?= e(($credit ? '+' : '−') . $amount) ?></strong><span>≈ <?= e(wayfairs_usd_equivalent((int) $r['amount_minor'], (int) $r['asset_scale'], $rate)) ?> · <?= e($typeLabel) ?></span></div>
    </div>
    <div class="receipt-body">
      <div class="receipt-status">
        <span class="receipt-status-copy"><strong><?= e((string) $r['description']) ?></strong><span>Recorded against <?= e((string) $r['account_number']) ?></span></span>
        <span class="transaction-symbol <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
      </div>
      <div class="receipt-details">
        <div class="receipt-detail wide"><span>Public reference</span><div class="copy-line"><strong class="mono"><?= e((string) $r['public_reference']) ?></strong><button class="btn small ghost copy-button" type="button" data-copy="<?= e((string) $r['public_reference']) ?>" aria-label="Copy transaction reference"><?= wayfairs_icon('copy') ?></button></div></div>
        <div class="receipt-detail"><span>Transaction type</span><strong><?= e($typeLabel) ?></strong></div>
        <div class="receipt-detail"><span>Direction</span><strong><?= e(ucfirst((string) $r['direction'])) ?></strong></div>
        <div class="receipt-detail"><span>Currency</span><strong><?= e((string) $r['asset_code']) ?></strong></div>
        <div class="receipt-detail"><span>USD equivalent</span><strong><?= e(wayfairs_usd_equivalent((int) $r['amount_minor'], (int) $r['asset_scale'], $rate)) ?></strong></div>
        <div class="receipt-detail"><span>Account</span><strong class="mono"><?= e((string) $r['account_number']) ?></strong></div>
        <?php if ($fee !== null): ?><div class="receipt-detail"><span>Fee</span><strong><?= e($fee) ?></strong></div><?php endif; ?>
        <?php if (!empty($r['counterparty_label'])): ?><div class="receipt-detail"><span>Counterparty</span><strong><?= e((string) $r['counterparty_label']) ?></strong></div><?php endif; ?>
        <div class="receipt-detail"><span>Created</span><strong><?= e(wayfairs_datetime((string) $r['created_at'])) ?></strong></div>
        <div class="receipt-detail"><span>Completed</span><strong><?= e(wayfairs_datetime($r['completed_at'] ?? null)) ?></strong></div>
      </div>
      <div class="receipt-footnote"><?= wayfairs_icon('info') ?><span>Status and completion time are shown exactly as stored. USD equivalent uses <?= e(wayfairs_rate_disclosure($rate)) ?> and is not an additional balance.</span></div>
    </div>
  </article>
</div>

