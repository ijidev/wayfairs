<?php

declare(strict_types=1);

$site = wayfairs_public_settings();
$notice = trim((string) ($site['site.withdrawal_notice'] ?? ''));
$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$withdrawalAccounts = array_values(array_filter($accounts, static fn (array $account): bool => (int) ($account['asset']['withdrawal_enabled'] ?? 0) === 1));
$firstAccount = $withdrawalAccounts[0] ?? null;
$firstAsset = $firstAccount['asset'] ?? null;
$firstType = (string) ($firstAsset['asset_type'] ?? 'fiat');
?>
<header class="page-header">
  <div class="page-heading">
    <span class="page-icon"><?= wayfairs_icon('withdraw') ?></span>
    <div class="page-heading-copy"><span class="page-kicker">Move money out</span><h1>Withdraw</h1><p>Request a payout to your bank account or wallet. The requested amount and fee are reserved while operations processes it.</p></div>
  </div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/transactions')) ?>">Transactions</a></div>
</header>

<?php if (!$verified): ?>
  <div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification approval is required</strong><p>The withdrawal form remains unavailable until your identity status is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div>
<?php endif; ?>
<?php if ($notice !== ''): ?><div class="notice brand"><?= wayfairs_icon('info') ?><div><strong>Withdrawal notice</strong><p><?= e($notice) ?></p></div></div><?php endif; ?>

<?php if ($withdrawalAccounts === []): ?>
  <div class="notice warn"><?= wayfairs_icon('withdraw') ?><div><strong>Withdrawals are currently closed</strong><p>No enabled customer currency currently accepts withdrawals.</p></div></div>
<?php else: ?>
  <section class="content-grid">
    <article class="card form-card">
      <div class="form-intro"><h2>Withdrawal request</h2><p>Check the minimum, fee, available balance, and destination before submitting.</p></div>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/withdraw')) ?>" id="withdraw-form">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />
        <label>Currency or asset
          <select name="asset_code" id="withdraw-asset" required <?= $verified ? '' : 'disabled' ?>>
            <?php foreach ($withdrawalAccounts as $account): $optionAsset = $account['asset']; ?>
              <option value="<?= e((string) $optionAsset['code']) ?>" data-type="<?= e((string) $optionAsset['asset_type']) ?>" data-name="<?= e((string) $optionAsset['name'] . ' (' . (string) $optionAsset['code'] . ')') ?>" data-min="<?= e(wayfairs_amount((int) $optionAsset['min_withdrawal_minor'], (int) $optionAsset['scale'], (string) $optionAsset['symbol'], (string) $optionAsset['code'])) ?>" data-fee="<?= e(wayfairs_amount((int) $optionAsset['withdrawal_fee_minor'], (int) $optionAsset['scale'], (string) $optionAsset['symbol'], (string) $optionAsset['code'])) ?>" data-available="<?= e(wayfairs_amount((int) $account['available_minor'], (int) $optionAsset['scale'], (string) $optionAsset['symbol'], (string) $optionAsset['code'])) ?>"><?= e((string) $optionAsset['name']) ?> (<?= e((string) $optionAsset['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="selection-summary" aria-live="polite">
          <div class="summary-line"><small>Available</small><strong id="withdraw-available"><?= e($firstAccount ? wayfairs_amount((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
          <div class="summary-line"><small>Minimum</small><strong id="withdraw-minimum"><?= e($firstAsset ? wayfairs_amount((int) $firstAsset['min_withdrawal_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
          <div class="summary-line"><small>Withdrawal fee</small><strong id="withdraw-fee"><?= e($firstAsset ? wayfairs_amount((int) $firstAsset['withdrawal_fee_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
        </div>
        <label>Amount to withdraw
          <input name="amount" inputmode="decimal" placeholder="0.00" required <?= $verified ? '' : 'disabled' ?> />
          <span class="field-hint">The configured fee is reserved in addition to this amount.</span>
        </label>

        <fieldset class="stack destination-group" data-type="fiat"<?= $firstType === 'fiat' ? '' : ' hidden' ?>>
          <legend>Bank destination</legend>
          <div class="form-grid">
            <label>Bank name<input name="bank_name" maxlength="120" autocomplete="organization"<?= $firstType === 'fiat' && $verified ? ' required' : '' ?><?= $verified ? '' : ' disabled' ?> /></label>
            <label>Account name <span class="field-hint">Optional</span><input name="account_name" maxlength="120" autocomplete="name"<?= $verified ? '' : ' disabled' ?> /></label>
            <label>Account number<input name="account_number" maxlength="40" inputmode="numeric"<?= $firstType === 'fiat' && $verified ? ' required' : '' ?><?= $verified ? '' : ' disabled' ?> /></label>
            <label>Routing / sort code <span class="field-hint">Optional</span><input name="routing" maxlength="40"<?= $verified ? '' : ' disabled' ?> /></label>
            <label class="wide">SWIFT / BIC <span class="field-hint">Optional</span><input name="swift" maxlength="40"<?= $verified ? '' : ' disabled' ?> /></label>
          </div>
        </fieldset>

        <fieldset class="stack destination-group" data-type="crypto"<?= $firstType === 'crypto' ? '' : ' hidden' ?>>
          <legend>Wallet destination</legend>
          <label>Network<input name="network" maxlength="60" placeholder="e.g. Bitcoin, Ethereum (ERC-20)"<?= $firstType === 'crypto' && $verified ? ' required' : '' ?><?= $verified ? '' : ' disabled' ?> /></label>
          <label>Wallet address<input name="address" maxlength="200" placeholder="bc1... or 0x..."<?= $firstType === 'crypto' && $verified ? ' required' : '' ?><?= $verified ? '' : ' disabled' ?> /></label>
          <label>Memo / tag <span class="field-hint">Only if required</span><input name="memo" maxlength="120"<?= $verified ? '' : ' disabled' ?> /></label>
        </fieldset>
        <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Add a note for the operations team"<?= $verified ? '' : ' disabled' ?> /></label>
        <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Submit withdrawal request' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    </article>

    <aside class="stack-list sticky-panel">
      <article class="card static">
        <div class="panel-head"><div><h2>Available balances</h2><p>Already-reserved funds are excluded.</p></div></div>
        <div class="account-stack">
          <?php foreach ($withdrawalAccounts as $account): $rowAsset = $account['asset']; ?>
            <div class="account-tile">
              <span class="account-badge"><?= e((string) $rowAsset['code']) ?></span>
              <span class="account-copy"><strong><?= e((string) $rowAsset['name']) ?></strong><small>Min <?= e(wayfairs_amount((int) $rowAsset['min_withdrawal_minor'], (int) $rowAsset['scale'], (string) $rowAsset['symbol'])) ?> · Fee <?= e(wayfairs_amount((int) $rowAsset['withdrawal_fee_minor'], (int) $rowAsset['scale'], (string) $rowAsset['symbol'])) ?></small></span>
              <span class="account-end"><strong><?= e(wayfairs_amount((int) $account['available_minor'], (int) $rowAsset['scale'], (string) $rowAsset['symbol'])) ?></strong><small><?= e((string) $rowAsset['code']) ?></small></span>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
      <article class="card static">
        <div class="panel-head"><div><h2>What happens next</h2><p>Settlement is recorded, never simulated.</p></div></div>
        <div class="process-steps">
          <div class="process-step"><div><strong>Funds are reserved</strong><p>The amount and configured fee leave your available balance immediately.</p></div></div>
          <div class="process-step"><div><strong>Operations sends the payout</strong><p>An administrator verifies and completes it outside Wayfairs.</p></div></div>
          <div class="process-step"><div><strong>The decision is recorded</strong><p>Completion captures the hold; rejection releases it back to your available balance.</p></div></div>
        </div>
      </article>
    </aside>
  </section>

  <script>
  (function () {
    var select = document.getElementById('withdraw-asset');
    var form = document.getElementById('withdraw-form');
    var groups = document.querySelectorAll('.destination-group');
    var available = document.getElementById('withdraw-available');
    var minimum = document.getElementById('withdraw-minimum');
    var fee = document.getElementById('withdraw-fee');
    if (!select || !form) return;
    function requiredFor(type, name) { return type === 'crypto' ? name === 'network' || name === 'address' : name === 'bank_name' || name === 'account_number'; }
    function sync() {
      var option = select.options[select.selectedIndex];
      var type = option ? option.getAttribute('data-type') : 'fiat';
      groups.forEach(function (group) {
        var matches = group.getAttribute('data-type') === type;
        group.hidden = !matches;
        group.querySelectorAll('input').forEach(function (input) {
          input.disabled = !matches || <?= $verified ? 'false' : 'true' ?>;
          input.required = matches && <?= $verified ? 'true' : 'false' ?> && requiredFor(type, input.name);
        });
      });
      if (option) {
        if (available) available.textContent = option.getAttribute('data-available') || '—';
        if (minimum) minimum.textContent = option.getAttribute('data-min') || '—';
        if (fee) fee.textContent = option.getAttribute('data-fee') || '—';
      }
    }
    select.addEventListener('change', sync);
    sync();
  })();
  </script>
<?php endif; ?>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Withdrawal requests</h2><p>Reservations remain active until a request is completed or rejected.</p></div><span class="pill"><?= count($withdrawals) ?> loaded</span></div>
  <?php if ($withdrawals === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('withdraw') ?></div><h3>No withdrawal requests</h3><p>Your submitted payout requests will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>Currency</th><th>Amount</th><th>USD equivalent</th><th>Destination</th><th>Remark</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($withdrawals as $w): $rate = $usdRates[$w['asset_code']] ?? null; ?><tr><td><span class="cell-title"><?= e(wayfairs_datetime((string) $w['created_at'])) ?></span></td><td><span class="cell-title"><?= e((string) $w['asset_code']) ?></span><span class="cell-subtitle"><?= e((string) $w['account_number']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $w['amount_minor'], (int) $w['asset_scale'], (string) $w['asset_symbol'], (string) $w['asset_code'])) ?></td><td><span class="cell-title"><?= e(wayfairs_usd_equivalent((int) $w['amount_minor'], (int) $w['asset_scale'], $rate)) ?></span><span class="cell-subtitle"><?= e(wayfairs_rate_disclosure($rate)) ?></span></td><td class="wrap-anywhere"><?= e((string) ($w['destination_summary'] ?? '—')) ?></td><td><?= e(trim((string) ($w['user_remark'] ?? '')) !== '' ? (string) $w['user_remark'] : '—') ?></td><td><?= wayfairs_pill((string) $w['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
