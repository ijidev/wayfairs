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
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('receipt') ?></span><div class="page-heading-copy"><span class="page-kicker">Utilities</span><h1>Pay a bill</h1><p>Send money to a utility, school, or service provider using the bank details printed on their invoice.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/dashboard')) ?>">Overview</a></div>
</header>

<?php if (!$verified): ?><div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification is required</strong><p>Bill payments unlock once your identity is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div><?php endif; ?>

<section class="content-grid">
  <article class="card form-card bank-transfer-form">
    <div class="form-intro"><span class="page-kicker">New bill payment</span><h2>Biller details</h2><p>Enter the biller's bank details and the reference from your bill so they can match your payment.</p></div>
    <?php if ($fiatAccounts === []): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No account for bill payments</h3><p>No enabled fiat account currently accepts bill payments.</p></div>
    <?php else: ?>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/bills')) ?>" id="bill-payment-form">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />

        <div class="form-section-title"><span>1</span><div><strong>Amount</strong><small>Choose the account to pay from.</small></div></div>
        <div class="form-grid">
          <label>Account currency
            <select name="asset_code" id="bill-asset" required <?= $verified ? '' : 'disabled' ?>>
              <?php foreach ($fiatAccounts as $account): $asset = $account['asset']; $rate = $usdRates[$asset['code']] ?? null; ?>
                <option value="<?= e((string) $asset['code']) ?>" <?= (string) $asset['code'] === 'USD' ? 'selected' : '' ?> data-available="<?= e(wayfairs_amount((int) $account['available_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?>" data-usd="<?= e(wayfairs_usd_equivalent((int) $account['available_minor'], (int) $asset['scale'], $rate)) ?>"><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" required <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <div class="selection-summary transfer-summary">
          <div class="summary-line"><small>Available balance</small><strong id="bill-available"><?= e($firstAccount ? wayfairs_amount((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
          <div class="summary-line"><small>USD equivalent</small><strong id="bill-usd"><?= e($firstAccount ? wayfairs_usd_equivalent((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], $usdRates[$firstAsset['code']] ?? null) : '—') ?></strong></div>
        </div>

        <div class="form-section-title"><span>2</span><div><strong>Biller</strong><small>Use the name and bank shown on the bill.</small></div></div>
        <div class="form-grid">
          <label>Biller country<select name="biller_country" required <?= $verified ? '' : 'disabled' ?>><option value="">Select country or territory</option><?php foreach ($countries as $code => $country): ?><option value="<?= e($code) ?>"><?= e($country) ?></option><?php endforeach; ?></select></label>
          <label>Biller name<input name="biller_name" maxlength="160" placeholder="Name on the bill" required <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Biller bank<input name="biller_bank" maxlength="160" placeholder="Bank holding the biller's account" required <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Bill reference<input name="bill_reference" maxlength="120" placeholder="Account or invoice number on the bill" required <?= $verified ? '' : 'disabled' ?> /></label>
        </div>

        <fieldset class="route-selector">
          <legend class="sr-only">Payment details</legend>
          <label class="route-option">
            <input type="radio" name="transfer_route" value="iban" <?= $verified ? '' : 'disabled' ?> checked />
            <span class="route-body">
              <span class="route-icon"><?= wayfairs_icon('globe') ?></span>
              <span class="route-copy"><strong>IBAN</strong><small>The biller gave you an IBAN.</small></span>
            </span>
          </label>
          <label class="route-option">
            <input type="radio" name="transfer_route" value="local" <?= $verified ? '' : 'disabled' ?> />
            <span class="route-body">
              <span class="route-icon"><?= wayfairs_icon('building') ?></span>
              <span class="route-copy"><strong>Account number</strong><small>The biller gave you a local account number.</small></span>
            </span>
          </label>
        </fieldset>
        <div class="form-grid route-fields" data-route-group="iban">
          <label>IBAN<input class="uppercase-input" name="iban" maxlength="34" placeholder="e.g. GB33BUKB20201555555555" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>SWIFT / BIC <span class="field-hint">Optional for some countries</span><input class="uppercase-input" name="swift_bic" maxlength="11" placeholder="AAAABBCCDDD" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <div class="form-grid route-fields" data-route-group="local" hidden>
          <label>Account number<input name="account_number" maxlength="40" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
          <label>Routing / sort / transit code <span class="field-hint">Optional</span><input name="routing_code" maxlength="40" autocomplete="off" <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <noscript><style>[data-route-group]{display:grid!important}</style><p class="field-hint route-noscript-note">JavaScript is turned off, so both the IBAN and account number fields are shown. Fill in the one the biller gave you and leave the other empty.</p></noscript>
        <label>Note <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Anything else the biller should know" <?= $verified ? '' : 'disabled' ?> /></label>
        <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Pay bill' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    <?php endif; ?>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static transfer-status-card">
      <div class="panel-head"><div><h2>Before you pay</h2><p>Check these details against your bill.</p></div></div>
      <div class="process-steps">
        <div class="process-step"><div><strong>Use the biller's bank</strong><p>Paying a different account may delay your payment.</p></div></div>
        <div class="process-step"><div><strong>Include the reference</strong><p>The bill reference lets the biller match your payment to your account.</p></div></div>
        <div class="process-step"><div><strong>Funds are held</strong><p>The amount and any fee are held until the payment is resolved.</p></div></div>
      </div>
    </article>
  </aside>
</section>

<script>
(function () {
  var select = document.getElementById('bill-asset');
  var available = document.getElementById('bill-available');
  var usd = document.getElementById('bill-usd');
  if (select) {
    var sync = function () { var option = select.options[select.selectedIndex]; if (!option) return; if (available) available.textContent = option.getAttribute('data-available') || '—'; if (usd) usd.textContent = option.getAttribute('data-usd') || '—'; };
    select.addEventListener('change', sync); sync();
  }
  var form = document.getElementById('bill-payment-form');
  if (!form) return;
  var verified = <?= $verified ? 'true' : 'false' ?>;
  var routes = form.querySelectorAll('input[name="transfer_route"]');
  var groups = form.querySelectorAll('[data-route-group]');
  function syncRoute() {
    var route = 'iban';
    routes.forEach(function (radio) { if (radio.checked) route = radio.value; });
    groups.forEach(function (group) {
      var active = group.getAttribute('data-route-group') === route;
      group.hidden = !active;
      group.querySelectorAll('input').forEach(function (input) {
        input.disabled = !active || !verified;
        input.required = active && verified && (route === 'iban' ? input.name === 'iban' : input.name === 'account_number');
        if (!active) input.value = '';
      });
    });
  }
  routes.forEach(function (radio) { radio.addEventListener('change', syncRoute); });
  syncRoute();
})();
</script>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Bill payments</h2><p>Your recent bill payments and their current status.</p></div><span class="pill"><?= count($bills) ?> loaded</span></div>
  <?php if ($bills === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('receipt') ?></div><h3>No bill payments</h3><p>Bills you pay will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>From</th><th>Amount</th><th>USD equivalent</th><th>Biller</th><th>Reference</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($bills as $bill): $rate = $usdRates[$bill['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $bill['created_at'])) ?></td><td><span class="cell-title"><?= e((string) $bill['asset_code']) ?></span><span class="cell-subtitle"><?= e((string) $bill['account_number']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $bill['amount_minor'], (int) $bill['asset_scale'], (string) $bill['asset_symbol'], (string) $bill['asset_code'])) ?></td><td><span class="cell-title"><?= e(wayfairs_usd_equivalent((int) $bill['amount_minor'], (int) $bill['asset_scale'], $rate)) ?></span><span class="cell-subtitle"><?= e(wayfairs_rate_disclosure($rate)) ?></span></td><td class="wrap-anywhere"><?= e((string) $bill['destination_summary']) ?></td><td><?= e(trim((string) ($bill['user_remark'] ?? '')) !== '' ? (string) $bill['user_remark'] : '—') ?></td><td><?= wayfairs_pill((string) $bill['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
