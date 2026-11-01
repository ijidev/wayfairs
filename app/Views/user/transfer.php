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
    <div class="form-intro"><span class="page-kicker">New payment</span><h2>Beneficiary transfer</h2><p>The amount and any fee are held from your available balance until the transfer is completed or returned to you.</p></div>
    <?php if (empty($bankTransfersEnabled)): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>Bank transfers are not available yet</h3><p>Sending money to other banks is not switched on for this account. Your Wayfairs transfers below still work.</p></div>
    <?php elseif ($fiatAccounts === []): ?>
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

        <div class="form-section-title"><span>3</span><div><strong>Receiving bank</strong><small>Choose how the beneficiary's account is addressed, then enter only the details that apply.</small></div></div>
        <fieldset class="route-selector">
          <legend class="sr-only">Transfer option</legend>
          <label class="route-option">
            <input type="radio" name="transfer_route" value="iban" <?= $verified ? '' : 'disabled' ?> checked />
            <span class="route-body">
              <span class="route-icon"><?= wayfairs_icon('globe') ?></span>
              <span class="route-copy"><strong>International (IBAN)</strong><small>Use the beneficiary's IBAN, with a SWIFT/BIC where the payment crosses borders.</small></span>
            </span>
          </label>
          <label class="route-option">
            <input type="radio" name="transfer_route" value="local" <?= $verified ? '' : 'disabled' ?> />
            <span class="route-body">
              <span class="route-icon"><?= wayfairs_icon('building') ?></span>
              <span class="route-copy"><strong>Local account number</strong><small>Use a domestic account number, with a routing, sort, or transit code where required.</small></span>
            </span>
          </label>
        </fieldset>
        <div class="form-grid">
          <label>Bank name<input name="bank_name" maxlength="160" autocomplete="organization" required <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Bank address <span class="field-hint">Optional</span><input name="bank_address" maxlength="300" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <div class="form-grid route-fields" data-route-group="iban">
          <label>IBAN<input class="uppercase-input" name="iban" maxlength="34" placeholder="e.g. GB33BUKB20201555555555" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>SWIFT / BIC <span class="field-hint">Optional for some countries</span><input class="uppercase-input" name="swift_bic" maxlength="11" placeholder="AAAABBCCDDD" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <div class="form-grid route-fields" data-route-group="local" hidden>
          <label>Account number<input name="account_number" maxlength="40" inputmode="numeric" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Routing / sort / transit code <span class="field-hint">Optional</span><input name="routing_code" maxlength="40" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <noscript><style>[data-route-group]{display:grid!important}</style><p class="field-hint route-noscript-note">JavaScript is turned off, so both the IBAN and local account number fields are shown. Fill in the one your beneficiary's bank uses and leave the other empty.</p></noscript>
        <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Payment purpose or a short note" <?= $verified ? '' : 'disabled' ?> /></label>
        <div class="receipt-footnote"><?= wayfairs_icon('security') ?><span>Your beneficiary's full bank details are stored securely and shown to you only in masked form.</span></div>
        <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Submit bank transfer' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    <?php endif; ?>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static transfer-status-card">
      <div class="panel-head"><div><h2>Before you send</h2><p>What to check and what happens next.</p></div></div>
      <div class="process-steps">
        <div class="process-step"><div><strong>Check the beneficiary</strong><p>Use the account holder's legal name exactly as their bank holds it.</p></div></div>
        <div class="process-step"><div><strong>Funds are held</strong><p>The amount and any fee move to held funds until the transfer is resolved.</p></div></div>
        <div class="process-step"><div><strong>Track it here</strong><p>Each transfer's status appears in the history below.</p></div></div>
        <div class="process-step"><div><strong>If it is returned</strong><p>Held funds go back to your available balance in full.</p></div></div>
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

(function () {
  var form = document.getElementById('bank-transfer-form');
  if (!form) return;
  var verified = <?= $verified ? 'true' : 'false' ?>;
  var routes = form.querySelectorAll('input[name="transfer_route"]');
  var groups = form.querySelectorAll('[data-route-group]');
  function selectedRoute() {
    var route = 'iban';
    routes.forEach(function (radio) { if (radio.checked) route = radio.value; });
    return route;
  }
  function isRequired(route, name) {
    return route === 'iban' ? name === 'iban' : name === 'account_number';
  }
  function syncRoute() {
    var route = selectedRoute();
    groups.forEach(function (group) {
      var active = group.getAttribute('data-route-group') === route;
      group.hidden = !active;
      group.querySelectorAll('input').forEach(function (input) {
        input.disabled = !active || !verified;
        input.required = active && verified && isRequired(route, input.name);
        if (!active) input.value = '';
      });
    });
  }
  routes.forEach(function (radio) { radio.addEventListener('change', syncRoute); });
  syncRoute();
})();
</script>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Bank transfer history</h2><p>Your bank transfers, their current status, and an estimated USD value.</p></div><span class="pill"><?= count($bankTransfers) ?> loaded</span></div>
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
