<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$fiatAccounts = $fiatAccounts ?? [];
$old = is_array($old ?? null) ? $old : [];
$oldValue = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $default);
$disabled = $verified ? '' : 'disabled';
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('layers') ?></span><div class="page-heading-copy"><span class="page-kicker">Utilities</span><h1>Swap currencies</h1><p>Convert a balance from one currency to another at the current market rate.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/utilities')) ?>">Utilities</a></div>
</header>

<?php if (!$verified): ?><div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification is required</strong><p>Currency swaps unlock once your identity is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div><?php endif; ?>
<?php if (empty($swapsEnabled)): ?><div class="notice warn"><?= wayfairs_icon('info') ?><div><strong>Swaps are not available yet</strong><p>Currency swapping is not switched on for this account.</p></div></div><?php endif; ?>

<?php if ($fiatAccounts === []): ?>
  <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('wallet') ?></div><h3>No currency accounts</h3><p>You need at least two enabled fiat accounts to swap.</p></div>
<?php else: ?>
<section class="content-grid">
  <article class="card form-card">
    <div class="form-intro"><span class="page-kicker">New swap</span><h2>Convert balance</h2><p>Swaps are instant. A fee of <?= e(rtrim(rtrim(number_format($feePercent, 4), '0'), '.')) ?>% applies to the amount you convert.</p></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/swap/review')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <div class="form-grid">
        <label>From
          <select name="from_code" required <?= $disabled ?>>
            <?php foreach ($fiatAccounts as $account): $asset = $account['asset']; ?>
              <option value="<?= e((string) $asset['code']) ?>" <?= $oldValue('from_code', (string) $asset['code']) === (string) $asset['code'] ? 'selected' : '' ?> data-available="<?= e(wayfairs_amount((int) $account['available_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?>"><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>) — <?= e(wayfairs_amount((int) $account['available_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>To
          <select name="to_code" required <?= $disabled ?>>
            <?php foreach ($fiatAccounts as $account): $asset = $account['asset']; ?>
              <option value="<?= e((string) $asset['code']) ?>" <?= $oldValue('to_code') === (string) $asset['code'] ? 'selected' : '' ?>><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Amount to convert<input name="amount" inputmode="decimal" placeholder="0.00" value="<?= e($oldValue('amount')) ?>" required <?= $disabled ?> /></label>
      <div class="form-actions"><button class="btn full" type="submit" <?= $disabled || empty($swapsEnabled) ? 'disabled' : '' ?>><?= $verified ? 'Review swap' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
    </form>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static transfer-status-card">
      <div class="panel-head"><div><h2>How swaps work</h2><p>Instant conversion between your own accounts.</p></div></div>
      <div class="process-steps">
        <div class="process-step"><div><strong>Choose currencies</strong><p>Pick the currency you have and the one you want.</p></div></div>
        <div class="process-step"><div><strong>See the rate</strong><p>The next page shows the exact amount you will receive.</p></div></div>
        <div class="process-step"><div><strong>Instant settlement</strong><p>Both balances update immediately. A fee of <?= e(rtrim(rtrim(number_format($feePercent, 4), '0'), '.')) ?>% applies.</p></div></div>
      </div>
    </article>
  </aside>
</section>
<?php endif; ?>

<?php if (!empty($preview)): ?>
<section class="card static">
  <div class="panel-head"><div><h2>Last preview</h2><p>Amounts you reviewed most recently.</p></div></div>
  <div class="selection-summary">
    <div class="summary-line"><small>From</small><strong><?= e(wayfairs_amount((int) $preview['from_amount_minor'], (int) $preview['from_asset']['scale'], (string) $preview['from_asset']['symbol'], (string) $preview['from_asset']['code'])) ?></strong></div>
    <div class="summary-line"><small>Fee (<?= e(rtrim(rtrim(number_format($preview['fee_percent'], 4), '0'), '.')) ?>%)</small><strong><?= e(wayfairs_amount((int) $preview['fee_minor'], (int) $preview['from_asset']['scale'], (string) $preview['from_asset']['symbol'], (string) $preview['from_asset']['code'])) ?></strong></div>
    <div class="summary-line"><small>You receive</small><strong><?= e(wayfairs_amount((int) $preview['to_amount_minor'], (int) $preview['to_asset']['scale'], (string) $preview['to_asset']['symbol'], (string) $preview['to_asset']['code'])) ?></strong></div>
    <div class="summary-line"><small>Rate</small><strong>1 <?= e((string) $preview['from_asset']['code']) ?> = <?= e(number_format((float) $preview['from_rate'] / (float) $preview['to_rate'], 6)) ?> <?= e((string) $preview['to_asset']['code']) ?></strong></div>
  </div>
</section>
<?php endif; ?>