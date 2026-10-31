<?php

declare(strict_types=1);

$enabledCount = count(array_filter($assets, static fn (array $asset): bool => (int) $asset['enabled'] === 1));
$depositCount = count(array_filter($assets, static fn (array $asset): bool => (int) $asset['deposit_enabled'] === 1));
$withdrawalCount = count(array_filter($assets, static fn (array $asset): bool => (int) $asset['withdrawal_enabled'] === 1));
$configuredCount = count(array_filter($assets, static function (array $asset): bool {
    return (string) $asset['asset_type'] === 'fiat'
        ? !empty($asset['deposit_bank_name']) && !empty($asset['deposit_account_number'])
        : !empty($asset['deposit_network']) && !empty($asset['deposit_wallet_address']);
}));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('layers') ?></span><div class="page-heading-copy"><span class="page-kicker">Currency operations</span><h1>Currencies</h1><p>Control customer availability, limits, fees, and the exact deposit destinations published for each asset.</p></div></div>
  <div class="page-actions"><form method="post" action="<?= e(wayfairs_url('/admin/assets/rates/refresh')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn ghost" type="submit"><?= wayfairs_icon('activity') ?> Refresh USD rates</button></form><a class="btn ghost" href="<?= e(wayfairs_url('/admin/transactions')) ?>">Transactions</a></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('layers') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($assets) ?></strong><span class="metric-label">Configured currencies</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $enabledCount ?></strong><span class="metric-label">Customer enabled</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('deposit') ?></span><span class="metric-copy"><strong class="metric-value"><?= $depositCount ?></strong><span class="metric-label">Deposits open</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('building') ?></span><span class="metric-copy"><strong class="metric-value"><?= $configuredCount ?></strong><span class="metric-label">Destinations complete</span></span></div>
</section>

<details class="card asset-create static">
  <summary><span><span class="page-kicker">New configuration</span><strong>Add a currency or asset</strong></span><span class="btn small"><?= wayfairs_icon('plus') ?> Add currency</span></summary>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/assets')) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <div class="form-grid">
      <label>Currency code<input class="uppercase-input" name="code" required maxlength="16" placeholder="EUR" /><span class="field-hint">Permanent uppercase identifier.</span></label>
      <label>Display name<input name="name" required maxlength="100" placeholder="Euro" /></label>
    </div>
    <div class="form-grid">
      <label>Type<select name="asset_type" required><option value="fiat">Fiat currency</option><option value="crypto">Crypto asset</option></select></label>
      <label>Symbol<input name="symbol" required maxlength="16" placeholder="€" /></label>
      <label>Decimal scale<input type="number" name="scale" min="0" max="18" value="2" required /><span class="field-hint">Cannot be changed after creation.</span></label>
      <label>CoinGecko ID <span class="field-hint">Crypto only, optional</span><input name="usd_provider_reference" maxlength="120" placeholder="e.g. bitcoin" /></label>
    </div>
    <div class="receipt-footnote"><?= wayfairs_icon('info') ?><span>Creating an asset also creates its system control and revenue ledger accounts. Customer availability stays closed until you save its settings.</span></div>
    <div class="form-actions"><button class="btn" type="submit">Create currency</button></div>
  </form>
</details>

<section class="stack-list">
  <div class="panel-head"><div><h2>Currency configuration</h2><p>Open one editor at a time to change its customer and settlement settings. Crypto market data by <a href="https://www.coingecko.com/" target="_blank" rel="noopener noreferrer">CoinGecko</a>; fiat reference data by <a href="https://frankfurter.dev/" target="_blank" rel="noopener noreferrer">Frankfurter</a>.</p></div><span class="pill"><?= $withdrawalCount ?> withdrawals open</span></div>
  <?php if ($assets === []): ?>
    <div class="card empty-state"><div class="empty-icon"><?= wayfairs_icon('layers') ?></div><h3>No currencies configured</h3><p>Add the first asset using the form above.</p></div>
  <?php endif; ?>
  <?php foreach ($assets as $asset):
      $isFiat = (string) $asset['asset_type'] === 'fiat';
      $rate = $usdRates[$asset['code']] ?? null;
      $missingDestination = (int) $asset['deposit_enabled'] === 1 && ($isFiat
          ? empty($asset['deposit_bank_name']) || empty($asset['deposit_account_number'])
          : empty($asset['deposit_wallet_address']) || empty($asset['deposit_network']));
  ?>
    <details class="asset-editor">
      <summary>
        <span class="asset-summary-main"><span class="mark"><?= e(mb_substr((string) $asset['code'], 0, 2)) ?></span><span class="asset-summary-copy"><strong><?= e((string) $asset['name']) ?> · <?= e((string) $asset['code']) ?></strong><span><?= e(ucfirst((string) $asset['asset_type'])) ?> · <?= (int) $asset['scale'] ?> decimals · symbol <?= e((string) $asset['symbol']) ?></span></span></span>
        <span class="asset-summary-state"><?= wayfairs_pill((int) $asset['enabled'] === 1 ? 'active' : 'closed') ?><?= wayfairs_pill((string) ($rate['status'] ?? 'unavailable')) ?><?= $missingDestination ? wayfairs_pill('blocked') : '' ?><span class="btn small ghost">Edit</span></span>
      </summary>
      <div class="asset-editor-body">
        <?php if ($missingDestination): ?><div class="notice warn"><?= wayfairs_icon('info') ?><div><strong>Deposits are open without a complete destination</strong><p>Customers see a warning until the required <?= $isFiat ? 'bank name and account number' : 'network and wallet address' ?> are saved.</p></div></div><?php endif; ?>
        <div class="rate-panel"><span class="metric-icon"><?= wayfairs_icon('activity') ?></span><span><small>Live USD rate</small><strong><?= !empty($rate['rate']) ? '1 ' . e((string) $asset['code']) . ' = ' . e((string) $rate['rate']) . ' USD' : 'Unavailable' ?></strong><em><?= e(wayfairs_rate_disclosure($rate)) ?></em></span></div>
        <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/assets/' . $asset['code'] . '/update')) ?>">
          <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
          <div class="form-grid">
            <label>Display name<input name="name" value="<?= e((string) $asset['name']) ?>" required /></label>
            <label>Customer availability<select name="enabled"><option value="1" <?= $asset['enabled'] ? 'selected' : '' ?>>Enabled</option><option value="0" <?= !$asset['enabled'] ? 'selected' : '' ?>>Disabled</option></select></label>
          </div>
          <div class="form-grid">
            <label>Deposit availability<select name="deposit_enabled"><option value="1" <?= $asset['deposit_enabled'] ? 'selected' : '' ?>>Open</option><option value="0" <?= !$asset['deposit_enabled'] ? 'selected' : '' ?>>Closed</option></select></label>
            <label>Withdrawal availability<select name="withdrawal_enabled"><option value="1" <?= $asset['withdrawal_enabled'] ? 'selected' : '' ?>>Open</option><option value="0" <?= !$asset['withdrawal_enabled'] ? 'selected' : '' ?>>Closed</option></select></label>
          </div>
          <div class="form-divider"></div>
          <div class="form-grid">
            <label>Minimum deposit (<?= e((string) $asset['code']) ?>)<input name="min_deposit" inputmode="decimal" value="<?= e(wayfairs_major((int) $asset['min_deposit_minor'], (int) $asset['scale'])) ?>" /></label>
            <label>Minimum withdrawal (<?= e((string) $asset['code']) ?>)<input name="min_withdrawal" inputmode="decimal" value="<?= e(wayfairs_major((int) $asset['min_withdrawal_minor'], (int) $asset['scale'])) ?>" /></label>
            <label>Withdrawal fee (<?= e((string) $asset['code']) ?>)<input name="withdrawal_fee" inputmode="decimal" value="<?= e(wayfairs_major((int) $asset['withdrawal_fee_minor'], (int) $asset['scale'])) ?>" /></label>
            <label>Transfer fee (<?= e((string) $asset['code']) ?>)<input name="transfer_fee" inputmode="decimal" value="<?= e(wayfairs_major((int) $asset['transfer_fee_minor'], (int) $asset['scale'])) ?>" /></label>
          </div>
          <p class="field-hint">Enter major <?= e((string) $asset['code']) ?> units with no thousands separators and no more than <?= (int) $asset['scale'] ?> decimal places.</p>
          <div class="form-divider"></div>
          <?php if (!$isFiat): ?><label>CoinGecko asset ID<input name="usd_provider_reference" maxlength="120" value="<?= e((string) ($asset['usd_provider_reference'] ?? '')) ?>" placeholder="e.g. bitcoin" /><span class="field-hint">Use the exact CoinGecko API ID. Leave blank to show USD conversion unavailable.</span></label><?php endif; ?>
          <?php if ($isFiat): ?>
            <fieldset class="stack destination-group">
              <legend>Customer fiat deposit destination</legend>
              <div class="form-grid"><label>Bank name<input name="deposit_bank_name" value="<?= e((string) ($asset['deposit_bank_name'] ?? '')) ?>" /></label><label>Account name<input name="deposit_account_name" value="<?= e((string) ($asset['deposit_account_name'] ?? '')) ?>" /></label></div>
              <div class="form-grid three"><label>Account number<input name="deposit_account_number" value="<?= e((string) ($asset['deposit_account_number'] ?? '')) ?>" /></label><label>Routing / sort code<input name="deposit_routing" value="<?= e((string) ($asset['deposit_routing'] ?? '')) ?>" /></label><label>SWIFT / BIC<input name="deposit_swift" value="<?= e((string) ($asset['deposit_swift'] ?? '')) ?>" /></label></div>
              <label>Customer instructions<textarea name="deposit_instructions" rows="3" placeholder="Explain the required sender reference or settlement timing."><?= e((string) ($asset['deposit_instructions'] ?? '')) ?></textarea></label>
            </fieldset>
          <?php else: ?>
            <fieldset class="stack destination-group">
              <legend>Customer crypto deposit destination</legend>
              <div class="form-grid"><label>Network<input name="deposit_network" value="<?= e((string) ($asset['deposit_network'] ?? '')) ?>" placeholder="e.g. Bitcoin mainnet" /></label><label>Wallet address<input name="deposit_wallet_address" value="<?= e((string) ($asset['deposit_wallet_address'] ?? '')) ?>" placeholder="bc1... or 0x..." /></label></div>
              <label>Memo / tag <span class="field-hint">Only if required</span><input name="deposit_memo" value="<?= e((string) ($asset['deposit_memo'] ?? '')) ?>" /></label>
              <label>Customer instructions<textarea name="deposit_instructions" rows="3" placeholder="Explain the required network and confirmation workflow."><?= e((string) ($asset['deposit_instructions'] ?? '')) ?></textarea></label>
            </fieldset>
          <?php endif; ?>
          <div class="form-actions"><button class="btn" type="submit">Save <?= e((string) $asset['code']) ?> configuration</button></div>
        </form>
        <div class="form-divider"></div>
        <div class="panel-head"><div><h3>Remove currency</h3><p>Deletion is refused when any customer account references this asset. Disable it instead when historical records exist.</p></div><form method="post" action="<?= e(wayfairs_url('/admin/assets/' . $asset['code'] . '/delete')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn small danger" type="submit" onclick="return confirm('Remove <?= e((string) $asset['code']) ?>? This succeeds only when no customer accounts exist.')">Remove <?= e((string) $asset['code']) ?></button></form></div>
      </div>
    </details>
  <?php endforeach; ?>
</section>
