<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$fiatAccounts = array_values(array_filter($accounts, static fn (array $account): bool => (string) $account['asset']['asset_type'] === 'fiat' && (int) $account['asset']['withdrawal_enabled'] === 1));
$firstAccount = $fiatAccounts[0] ?? null;
$firstAsset = $firstAccount['asset'] ?? null;
$countries = wayfairs_countries();
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('transfer') ?></span><div class="page-heading-copy"><span class="page-kicker">International payments</span><h1>Bank transfer</h1><p>Send from a fiat wallet to a beneficiary bank account using country-specific banking details.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/transactions')) ?>">Transactions</a></div>
</header>

<?php if (!$verified): ?><div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification approval is required</strong><p>Bank and internal transfers remain unavailable until your identity status is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div><?php endif; ?>

<section class="content-grid">
  <article class="card form-card bank-transfer-form">
    <div class="form-intro"><span class="page-kicker">New payment</span><h2>Beneficiary transfer</h2><p>Funds and the configured fee are reserved immediately. Operations records the external settlement or releases the reservation.</p></div>
    <?php if ($fiatAccounts === []): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No fiat transfer account</h3><p>No enabled fiat account currently accepts bank transfers.</p></div>
    <?php else: ?>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/transfer')) ?>" id="bank-transfer-form">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />

        <div class="form-section-title"><span>1</span><div><strong>Transfer amount</strong><small>USD is selected by default when available.</small></div></div>
        <div class="form-grid">
          <label>Account currency
            <select name="asset_code" id="transfer-asset" required <?= $verified ? '' : 'disabled' ?>>
              <?php foreach ($fiatAccounts as $account): $asset = $account['asset']; $rate = $usdRates[$asset['code']] ?? null; ?>
                <option value="<?= e((string) $asset['code']) ?>" <?= (string) $asset['code'] === 'USD' ? 'selected' : '' ?> data-available="<?= e(wayfairs_amount((int) $account['available_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?>" data-fee="<?= e(wayfairs_amount((int) $asset['withdrawal_fee_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?>" data-usd="<?= e(wayfairs_usd_equivalent((int) $account['available_minor'], (int) $asset['scale'], $rate)) ?>"><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" required <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <div class="selection-summary transfer-summary">
          <div class="summary-line"><small>Available balance</small><strong id="transfer-available"><?= e($firstAccount ? wayfairs_amount((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
          <div class="summary-line"><small>USD equivalent</small><strong id="transfer-usd"><?= e($firstAccount ? wayfairs_usd_equivalent((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], $usdRates[$firstAsset['code']] ?? null) : '—') ?></strong></div>
          <div class="summary-line"><small>Transfer fee</small><strong id="transfer-fee"><?= e($firstAsset ? wayfairs_amount((int) $firstAsset['withdrawal_fee_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
        </div>

        <div class="form-section-title"><span>2</span><div><strong>Beneficiary</strong><small>Enter the legal recipient details held by their bank.</small></div></div>
        <div class="form-grid">
          <label>Beneficiary country<select name="country_code" required <?= $verified ? '' : 'disabled' ?>><option value="">Select country or territory</option><?php foreach ($countries as $code => $country): ?><option value="<?= e($code) ?>"><?= e($country) ?></option><?php endforeach; ?></select></label>
          <label>Account holder name<input name="beneficiary_name" maxlength="160" autocomplete="name" placeholder="Full legal name" required <?= $verified ? '' : 'disabled' ?> /></label>
          <label class="wide">Beneficiary address <span class="field-hint">Optional</span><input name="beneficiary_address" maxlength="300" autocomplete="street-address" placeholder="Street, city, postal code" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>

        <div class="form-section-title"><span>3</span><div><strong>Receiving bank</strong><small>Provide an IBAN or local account number.</small></div></div>
        <div class="form-grid">
          <label>Bank name<input name="bank_name" maxlength="160" autocomplete="organization" required <?= $verified ? '' : 'disabled' ?> /></label>
          <label>SWIFT / BIC <span class="field-hint">Optional</span><input class="uppercase-input" name="swift_bic" maxlength="11" placeholder="AAAABBCCDDD" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>IBAN<input class="uppercase-input" name="iban" maxlength="34" placeholder="International account number" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Local account number<input name="account_number" maxlength="40" placeholder="Use when IBAN is not applicable" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Routing / sort / transit code <span class="field-hint">Optional</span><input name="routing_code" maxlength="40" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Bank address <span class="field-hint">Optional</span><input name="bank_address" maxlength="300" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Payment purpose or note for operations" <?= $verified ? '' : 'disabled' ?> /></label>
        <div class="receipt-footnote"><?= wayfairs_icon('security') ?><span>Full bank details are encrypted at rest. Browser and queue views expose only safe labels and last-four summaries.</span></div>
        <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Submit bank transfer' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    <?php endif; ?>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static transfer-status-card">
      <div class="panel-head"><div><h2>Settlement flow</h2><p>Traditional transfer controls with honest manual settlement.</p></div></div>
      <div class="process-steps">
        <div class="process-step"><div><strong>Validate beneficiary details</strong><p>Country, legal name, bank, and IBAN or account number are required.</p></div></div>
        <div class="process-step"><div><strong>Reserve funds</strong><p>The transfer amount and configured fee leave the available balance.</p></div></div>
        <div class="process-step"><div><strong>Operations settles externally</strong><p>An administrator records sent or rejected; no bank-provider state is simulated.</p></div></div>
        <div class="process-step"><div><strong>Ledger finalizes</strong><p>Sent captures the hold. Rejected releases it in full.</p></div></div>
      </div>
    </article>

    <details class="card asset-create static internal-transfer-panel">
      <summary><span><span class="page-kicker">Wayfairs network</span><strong>Send to another customer</strong></span><span class="btn small ghost">Open</span></summary>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/transfer/internal')) ?>">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />
        <label>Customer email<input type="email" name="recipient_email" placeholder="customer@example.com" required <?= $verified ? '' : 'disabled' ?> /></label>
        <div class="form-grid"><label>Currency<select name="asset_code" required <?= $verified ? '' : 'disabled' ?>><?php foreach ($accounts as $account): $asset = $account['asset']; ?><option value="<?= e((string) $asset['code']) ?>" <?= (string) $asset['code'] === 'USD' ? 'selected' : '' ?>><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>)</option><?php endforeach; ?></select></label><label>Amount<input name="amount" inputmode="decimal" required <?= $verified ? '' : 'disabled' ?> /></label></div>
        <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="200" <?= $verified ? '' : 'disabled' ?> /></label>
        <button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>>Send instantly</button>
      </form>
    </details>
  </aside>
</section>

<script>
(function () {
  var select = document.getElementById('transfer-asset');
  var available = document.getElementById('transfer-available');
  var usd = document.getElementById('transfer-usd');
  var fee = document.getElementById('transfer-fee');
  if (!select) return;
  function sync() { var option = select.options[select.selectedIndex]; if (!option) return; if (available) available.textContent = option.getAttribute('data-available') || '—'; if (usd) usd.textContent = option.getAttribute('data-usd') || '—'; if (fee) fee.textContent = option.getAttribute('data-fee') || '—'; }
  select.addEventListener('change', sync); sync();
})();
</script>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Bank transfer history</h2><p>Manual settlement requests, current status, and latest observed USD estimate.</p></div><span class="pill"><?= count($bankTransfers) ?> loaded</span></div>
  <?php if ($bankTransfers === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No bank transfers</h3><p>Submitted beneficiary transfers will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>From</th><th>Amount</th><th>USD equivalent</th><th>Beneficiary</th><th>Remark</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($bankTransfers as $transfer): $rate = $usdRates[$transfer['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $transfer['created_at'])) ?></td><td><span class="cell-title"><?= e((string) $transfer['asset_code']) ?></span><span class="cell-subtitle"><?= e((string) $transfer['account_number']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], (string) $transfer['asset_symbol'], (string) $transfer['asset_code'])) ?></td><td><span class="cell-title"><?= e(wayfairs_usd_equivalent((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], $rate)) ?></span><span class="cell-subtitle"><?= e(wayfairs_rate_disclosure($rate)) ?></span></td><td class="wrap-anywhere"><?= e((string) $transfer['destination_summary']) ?></td><td><?= e(trim((string) ($transfer['user_remark'] ?? '')) !== '' ? (string) $transfer['user_remark'] : '—') ?></td><td><?= wayfairs_pill((string) $transfer['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<?php if ($internalTransfers !== []): ?>
<section class="card table-card static">
  <div class="panel-head"><div><h2>Wayfairs transfers</h2><p>Instant payments between customer accounts.</p></div><span class="pill"><?= count($internalTransfers) ?> loaded</span></div>
  <div class="table-wrap"><table><thead><tr><th>Date</th><th>Direction</th><th>Counterparty</th><th>Amount</th><th>USD equivalent</th><th>Remark</th></tr></thead><tbody>
  <?php foreach ($internalTransfers as $transfer): $sent = (string) $transfer['sender_user_id'] === $me; $rate = $usdRates[$transfer['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $transfer['created_at'])) ?></td><td><?= wayfairs_pill($sent ? 'sent' : 'received') ?></td><td><?= e($sent ? (string) $transfer['recipient_email'] : (string) $transfer['sender_email']) ?></td><td class="cell-amount <?= $sent ? 'amount-negative' : 'amount-positive' ?>"><?= e(($sent ? '−' : '+') . wayfairs_amount((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], (string) $transfer['asset_symbol'], (string) $transfer['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], $rate)) ?></td><td><?= e(trim((string) ($transfer['note'] ?? '')) !== '' ? (string) $transfer['note'] : '—') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>
