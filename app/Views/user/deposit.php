<?php

declare(strict_types=1);

$site = wayfairs_public_settings();
$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$depositAccounts = array_values(array_filter($accounts, static fn (array $account): bool => (int) ($account['asset']['deposit_enabled'] ?? 0) === 1));
$firstAccount = $depositAccounts[0] ?? null;
$firstAsset = $firstAccount['asset'] ?? null;
?>
<header class="page-header">
  <div class="page-heading">
    <span class="page-icon"><?= wayfairs_icon('deposit') ?></span>
    <div class="page-heading-copy"><span class="page-kicker">Fund your account</span><h1>Deposit</h1><p>Use the published destination for your selected currency, then submit the exact amount and sender reference for verification.</p></div>
  </div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/transactions')) ?>">Transactions</a></div>
</header>

<?php if (!$verified): ?>
  <div class="notice warn">
    <?= wayfairs_icon('verification') ?>
    <div><strong>Verification approval is required</strong><p>You can inspect the published destinations, but the request form remains unavailable until your identity status is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div>
  </div>
<?php endif; ?>

<?php if ($depositAccounts === []): ?>
  <div class="notice warn"><?= wayfairs_icon('deposit') ?><div><strong>Deposits are currently closed</strong><p>No enabled customer currency currently accepts deposits. No destination is being substituted.</p></div></div>
<?php else: ?>
  <section class="content-grid">
    <article class="card form-card">
      <div class="form-intro"><h2>Submit deposit details</h2><p>Your balance changes only after an administrator verifies that the funds were actually received.</p></div>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/deposit')) ?>" id="deposit-form">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />
        <label>Currency or asset
          <select name="asset_code" id="deposit-asset" required <?= $verified ? '' : 'disabled' ?>>
            <?php foreach ($depositAccounts as $account): $optionAsset = $account['asset']; ?>
              <option value="<?= e((string) $optionAsset['code']) ?>" data-type="<?= e((string) $optionAsset['asset_type']) ?>" data-min="<?= e(wayfairs_amount((int) $optionAsset['min_deposit_minor'], (int) $optionAsset['scale'], (string) $optionAsset['symbol'], (string) $optionAsset['code'])) ?>" data-available="<?= e(wayfairs_amount((int) $account['available_minor'], (int) $optionAsset['scale'], (string) $optionAsset['symbol'], (string) $optionAsset['code'])) ?>">
                <?= e((string) $optionAsset['name']) ?> (<?= e((string) $optionAsset['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="selection-summary" aria-live="polite">
          <div class="summary-line"><small>Selected currency</small><strong id="deposit-selection-name"><?= e($firstAsset ? (string) $firstAsset['name'] . ' (' . (string) $firstAsset['code'] . ')' : '—') ?></strong></div>
          <div class="summary-line"><small>Minimum request</small><strong id="deposit-selection-min"><?= e($firstAsset ? wayfairs_amount((int) $firstAsset['min_deposit_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
        </div>
        <label>Exact amount sent
          <input name="amount" inputmode="decimal" placeholder="0.00" required <?= $verified ? '' : 'disabled' ?> />
          <span class="field-hint">Enter the amount in the selected currency. Values below its configured minimum are rejected.</span>
        </label>
        <label>Remark <span class="field-hint">Optional</span>
          <input name="remark" maxlength="500" placeholder="Add a note for the operations team" <?= $verified ? '' : 'disabled' ?> />
        </label>
        <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Submit deposit for verification' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    </article>

    <aside class="stack-list sticky-panel">
      <article class="card destination-card static" id="deposit-destination">
        <div class="panel-head"><div><h2>Deposit destination</h2><p>Send only to the details shown for the selected currency.</p></div></div>
        <?php foreach ($depositAccounts as $index => $account): $destinationAsset = $account['asset']; ?>
          <div class="deposit-dest" data-asset="<?= e((string) $destinationAsset['code']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
            <div class="destination-title"><div><strong><?= e((string) $destinationAsset['name']) ?></strong><div class="hint"><?= e((string) $destinationAsset['code']) ?> · <?= e(ucfirst((string) $destinationAsset['asset_type'])) ?></div></div><?= wayfairs_pill((string) $destinationAsset['asset_type']) ?></div>
            <?php if ($destinationAsset['asset_type'] === 'fiat'): ?>
              <?php if (!empty($destinationAsset['deposit_bank_name']) && !empty($destinationAsset['deposit_account_number'])): ?>
                <div class="destination-details">
                  <div class="destination-field"><span>Bank</span><strong><?= e((string) $destinationAsset['deposit_bank_name']) ?></strong></div>
                  <div class="destination-field"><span>Account name</span><strong><?= e((string) ($destinationAsset['deposit_account_name'] ?? '—')) ?></strong></div>
                  <div class="destination-field wide"><span>Account number</span><div class="copy-line"><code><?= e((string) $destinationAsset['deposit_account_number']) ?></code><button class="btn small ghost copy-button" type="button" data-copy="<?= e((string) $destinationAsset['deposit_account_number']) ?>" aria-label="Copy account number"><?= wayfairs_icon('copy') ?></button></div></div>
                  <?php if (!empty($destinationAsset['deposit_routing'])): ?><div class="destination-field"><span>Routing / sort code</span><strong><?= e((string) $destinationAsset['deposit_routing']) ?></strong></div><?php endif; ?>
                  <?php if (!empty($destinationAsset['deposit_swift'])): ?><div class="destination-field"><span>SWIFT / BIC</span><strong><?= e((string) $destinationAsset['deposit_swift']) ?></strong></div><?php endif; ?>
                </div>
              <?php else: ?>
                <div class="notice warn"><?= wayfairs_icon('support') ?><div><strong>Destination incomplete</strong><p>No complete bank destination is published. Contact support before sending funds.</p></div></div>
              <?php endif; ?>
            <?php else: ?>
              <?php if (!empty($destinationAsset['deposit_wallet_address']) && !empty($destinationAsset['deposit_network'])): ?>
                <div class="destination-details">
                  <div class="destination-field"><span>Network</span><strong><?= e((string) $destinationAsset['deposit_network']) ?></strong></div>
                  <?php if (!empty($destinationAsset['deposit_memo'])): ?><div class="destination-field"><span>Memo / tag</span><strong><?= e((string) $destinationAsset['deposit_memo']) ?></strong></div><?php endif; ?>
                  <div class="destination-field wide"><span>Wallet address</span><div class="copy-line"><code><?= e((string) $destinationAsset['deposit_wallet_address']) ?></code><button class="btn small ghost copy-button" type="button" data-copy="<?= e((string) $destinationAsset['deposit_wallet_address']) ?>" aria-label="Copy wallet address"><?= wayfairs_icon('copy') ?></button></div></div>
                </div>
              <?php else: ?>
                <div class="notice warn"><?= wayfairs_icon('support') ?><div><strong>Destination incomplete</strong><p>No complete wallet destination is published. Contact support before sending funds.</p></div></div>
              <?php endif; ?>
            <?php endif; ?>
            <?php
            $fallback = $destinationAsset['asset_type'] === 'crypto' ? (string) ($site['site.deposit_instructions_crypto'] ?? '') : (string) ($site['site.deposit_instructions_fiat'] ?? '');
            $instructions = trim((string) ($destinationAsset['deposit_instructions'] ?? '')) ?: $fallback;
            ?>
            <div class="receipt-footnote"><?= wayfairs_icon('info') ?><span><strong>Minimum:</strong> <?= e(wayfairs_amount((int) $destinationAsset['min_deposit_minor'], (int) $destinationAsset['scale'], (string) $destinationAsset['symbol'], (string) $destinationAsset['code'])) ?><?= $instructions !== '' ? ' · ' . e($instructions) : '' ?></span></div>
          </div>
        <?php endforeach; ?>
      </article>
      <article class="card static">
        <div class="panel-head"><div><h2>How deposits settle</h2><p>No balance is credited before verification.</p></div></div>
        <div class="process-steps">
          <div class="process-step"><div><strong>Send to the published destination</strong><p>Use only the bank account, wallet, and network shown above.</p></div></div>
          <div class="process-step"><div><strong>Submit your exact details</strong><p>Record the amount and any sender reference or transaction hash.</p></div></div>
          <div class="process-step"><div><strong>Operations verifies receipt</strong><p>Your account is credited only after an administrator confirms the incoming funds.</p></div></div>
        </div>
      </article>
    </aside>
  </section>

  <script>
  (function () {
    var select = document.getElementById('deposit-asset');
    var blocks = document.querySelectorAll('.deposit-dest');
    var name = document.getElementById('deposit-selection-name');
    var minimum = document.getElementById('deposit-selection-min');
    if (!select) return;
    function sync() {
      var code = select.value;
      var option = select.options[select.selectedIndex];
      blocks.forEach(function (block) { block.hidden = block.getAttribute('data-asset') !== code; });
      if (name && option) name.textContent = option.textContent.trim();
      if (minimum && option) minimum.textContent = option.getAttribute('data-min') || '—';
    }
    select.addEventListener('change', sync);
    sync();
  })();
  </script>
<?php endif; ?>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Deposit requests</h2><p>Submitted requests and their current review status.</p></div><span class="pill"><?= count($deposits) ?> loaded</span></div>
  <?php if ($deposits === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('deposit') ?></div><h3>No deposit requests</h3><p>Your submitted requests will appear here without invented provider statuses.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>Currency</th><th>Amount</th><th>USD equivalent</th><th>Remark</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($deposits as $deposit): $rate = $usdRates[$deposit['asset_code']] ?? null; ?><tr><td><span class="cell-title"><?= e(wayfairs_datetime((string) $deposit['created_at'])) ?></span></td><td><span class="cell-title"><?= e((string) $deposit['asset_code']) ?></span><span class="cell-subtitle"><?= e((string) $deposit['account_number']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], (string) $deposit['asset_symbol'], (string) $deposit['asset_code'])) ?></td><td><span class="cell-title"><?= e(wayfairs_usd_equivalent((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], $rate)) ?></span><span class="cell-subtitle"><?= e(wayfairs_rate_disclosure($rate)) ?></span></td><td class="wrap-anywhere"><?= e(trim((string) ($deposit['user_remark'] ?? '')) !== '' ? (string) $deposit['user_remark'] : '—') ?></td><td><?= wayfairs_pill((string) $deposit['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
