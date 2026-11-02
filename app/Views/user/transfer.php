<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$fiatAccounts = array_values(array_filter($accounts, static fn (array $account): bool => (string) $account['asset']['asset_type'] === 'fiat' && (int) $account['asset']['withdrawal_enabled'] === 1));
$firstAccount = $fiatAccounts[0] ?? null;
$firstAsset = $firstAccount['asset'] ?? null;
$countries = wayfairs_countries();
$old = is_array($old ?? null) ? $old : [];
$oldValue = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $default);
$route = $oldValue('transfer_route', 'iban') === 'local' ? 'local' : 'iban';
$disabled = $verified ? '' : 'disabled';
$beneficiaries = $beneficiaries ?? [];
$panels = ['new' => 'New transfer', 'beneficiaries' => 'Beneficiaries', 'history' => 'History', 'internal' => 'Wayfairs transfers'];
$activePanel = (string) ($_GET['panel'] ?? 'new');
if (!array_key_exists($activePanel, $panels)) {
    $activePanel = 'new';
}
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('transfer') ?></span><div class="page-heading-copy"><span class="page-kicker">International payments</span><h1>Bank transfer</h1><p>Send from a fiat wallet to a beneficiary bank account. You review every detail before anything is sent.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/transactions')) ?>">Transactions</a></div>
</header>

<nav class="subnav" aria-label="Transfer sections">
  <?php foreach ($panels as $key => $label): ?>
    <a class="subnav-link<?= $activePanel === $key ? ' active' : '' ?>" href="<?= e(wayfairs_url('/transfer?panel=' . $key)) ?>" data-panel="<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$verified): ?><div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification approval is required</strong><p>Bank and internal transfers remain unavailable until your identity status is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div><?php endif; ?>

<?php if ($activePanel === 'new'): ?>
<section class="content-grid">
  <article class="card form-card bank-transfer-form">
    <div class="form-intro"><span class="page-kicker">New payment</span><h2>Beneficiary transfer</h2><p>Enter the details, then review them on the next page before anything is sent.</p></div>
    <?php if (empty($bankTransfersEnabled)): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>Bank transfers are not available yet</h3><p>Sending money to other banks is not switched on for this account. Your Wayfairs transfers below still work.</p></div>
    <?php elseif ($fiatAccounts === []): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No fiat transfer account</h3><p>No enabled fiat account currently accepts bank transfers.</p></div>
    <?php else: ?>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/transfer/review')) ?>" id="bank-transfer-form">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <div class="form-section-title"><span>1</span><div><strong>Transfer amount</strong><small>USD is selected by default when available.</small></div></div>
        <div class="form-grid">
          <label>Account currency
            <select name="asset_code" id="transfer-asset" required <?= $disabled ?>>
              <?php foreach ($fiatAccounts as $account): $asset = $account['asset']; ?>
                <option value="<?= e((string) $asset['code']) ?>" <?= (string) $asset['code'] === $oldValue('asset_code', 'USD') ? 'selected' : '' ?> data-available="<?= e(wayfairs_amount((int) $account['available_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?>" data-fee="<?= e(wayfairs_amount((int) $asset['withdrawal_fee_minor'], (int) $asset['scale'], (string) $asset['symbol'], (string) $asset['code'])) ?>" data-usd="<?= e(wayfairs_usd_equivalent((int) $account['available_minor'], (int) $asset['scale'], $usdRates[$asset['code']] ?? null)) ?>"><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" value="<?= e($oldValue('amount')) ?>" required <?= $disabled ?> /></label>
        </div>
        <div class="selection-summary transfer-summary">
          <div class="summary-line"><small>Available balance</small><strong id="transfer-available"><?= e($firstAccount ? wayfairs_amount((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
          <div class="summary-line"><small>USD equivalent</small><strong id="transfer-usd"><?= e($firstAccount ? wayfairs_usd_equivalent((int) $firstAccount['available_minor'], (int) $firstAsset['scale'], $usdRates[$firstAsset['code']] ?? null) : '—') ?></strong></div>
          <div class="summary-line"><small>Transfer fee</small><strong id="transfer-fee"><?= e($firstAsset ? wayfairs_amount((int) $firstAsset['withdrawal_fee_minor'], (int) $firstAsset['scale'], (string) $firstAsset['symbol'], (string) $firstAsset['code']) : '—') ?></strong></div>
        </div>

        <div class="form-section-title"><span>2</span><div><strong>Beneficiary account</strong><small>Choose how the account is addressed. Typing a saved account number fills in the rest.</small></div></div>
        <fieldset class="route-selector">
          <legend class="sr-only">Transfer option</legend>
          <label class="route-option">
            <input type="radio" name="transfer_route" value="iban" <?= $route === 'iban' ? 'checked' : '' ?> <?= $disabled ?> />
            <span class="route-body">
              <span class="route-icon"><?= wayfairs_icon('globe') ?></span>
              <span class="route-copy"><strong>International (IBAN)</strong><small>Use the beneficiary's IBAN, with a SWIFT/BIC where the payment crosses borders.</small></span>
            </span>
          </label>
          <label class="route-option">
            <input type="radio" name="transfer_route" value="local" <?= $route === 'local' ? 'checked' : '' ?> <?= $disabled ?> />
            <span class="route-body">
              <span class="route-icon"><?= wayfairs_icon('building') ?></span>
              <span class="route-copy"><strong>Local account number</strong><small>Use a domestic account number, with a routing, sort, or transit code where required.</small></span>
            </span>
          </label>
        </fieldset>
        <div class="form-grid route-fields" data-route-group="iban" <?= $route === 'iban' ? '' : 'hidden' ?>>
          <label>IBAN<input class="uppercase-input" name="iban" id="transfer-iban" maxlength="48" placeholder="e.g. GB33BUKB20201555555555" autocomplete="off" value="<?= e($oldValue('iban')) ?>" <?= $disabled ?> /></label>
          <label>SWIFT / BIC <span class="field-hint">Optional for some countries</span><input class="uppercase-input" name="swift_bic" id="transfer-swift" maxlength="11" placeholder="AAAABBCCDDD" autocomplete="off" value="<?= e($oldValue('swift_bic')) ?>" <?= $disabled ?> /></label>
        </div>
        <div class="form-grid route-fields" data-route-group="local" <?= $route === 'local' ? '' : 'hidden' ?>>
          <label>Account number<input name="account_number" id="transfer-account" maxlength="40" inputmode="numeric" autocomplete="off" value="<?= e($oldValue('account_number')) ?>" <?= $disabled ?> /></label>
          <label>Routing / sort / transit code <span class="field-hint">Optional</span><input name="routing_code" id="transfer-routing" maxlength="40" autocomplete="off" value="<?= e($oldValue('routing_code')) ?>" <?= $disabled ?> /></label>
        </div>
        <noscript><style>[data-route-group]{display:grid!important}</style><p class="field-hint route-noscript-note">JavaScript is turned off, so both the IBAN and local account number fields are shown. Fill in the one your beneficiary's bank uses and leave the other empty.</p></noscript>
        <p class="notice brand" id="beneficiary-match" role="status" hidden></p>

        <div class="form-section-title"><span>3</span><div><strong>Beneficiary</strong><small>Enter the legal recipient details held by their bank.</small></div></div>
        <div class="form-grid">
          <label>Beneficiary country<select name="country_code" id="transfer-country" required <?= $disabled ?>><option value="">Select country or territory</option><?php foreach ($countries as $code => $country): ?><option value="<?= e($code) ?>" <?= $oldValue('country_code') === $code ? 'selected' : '' ?>><?= e($country) ?></option><?php endforeach; ?></select></label>
          <label>Account holder name<input name="beneficiary_name" id="transfer-beneficiary-name" maxlength="160" autocomplete="name" placeholder="Full legal name" value="<?= e($oldValue('beneficiary_name')) ?>" required <?= $disabled ?> /></label>
          <label class="wide">Beneficiary address <span class="field-hint">Optional</span><input name="beneficiary_address" id="transfer-beneficiary-address" maxlength="300" autocomplete="street-address" placeholder="Street, city, postal code" value="<?= e($oldValue('beneficiary_address')) ?>" <?= $disabled ?> /></label>
        </div>
        <div class="form-grid">
          <label>Bank name<input name="bank_name" id="transfer-bank-name" maxlength="160" autocomplete="organization" value="<?= e($oldValue('bank_name')) ?>" required <?= $disabled ?> /></label>
          <label>Bank address <span class="field-hint">Optional</span><input name="bank_address" id="transfer-bank-address" maxlength="300" value="<?= e($oldValue('bank_address')) ?>" <?= $disabled ?> /></label>
        </div>

        <div class="form-section-title"><span>4</span><div><strong>Remember this beneficiary</strong><small>Optional. Saved beneficiaries fill in automatically next time you enter their account number.</small></div></div>
        <label class="inline-check"><input type="checkbox" name="save_beneficiary" value="1" <?= $oldValue('save_beneficiary') === '1' ? 'checked' : '' ?> <?= $disabled ?> /> Save to my beneficiaries</label>
        <label>Nickname <span class="field-hint">Optional</span><input name="nickname" maxlength="80" placeholder="e.g. Landlord" value="<?= e($oldValue('nickname')) ?>" <?= $disabled ?> /></label>
        <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Payment purpose or a short note" value="<?= e($oldValue('remark')) ?>" <?= $disabled ?> /></label>
        <div class="receipt-footnote"><?= wayfairs_icon('security') ?><span>Your beneficiary's full bank details are stored securely and shown to you only in masked form.</span></div>
        <div class="form-actions"><button class="btn full" type="submit" <?= $disabled ?>><?= $verified ? 'Review transfer' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    <?php endif; ?>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static transfer-status-card">
      <div class="panel-head"><div><h2>Before you send</h2><p>What to check and what happens next.</p></div></div>
      <div class="process-steps">
        <div class="process-step"><div><strong>Check the beneficiary</strong><p>Use the account holder's legal name exactly as their bank holds it.</p></div></div>
        <div class="process-step"><div><strong>Review every detail</strong><p>The next page shows the full payment before anything is sent.</p></div></div>
        <div class="process-step"><div><strong>Funds are held</strong><p>The amount and any fee move to held funds until the transfer is resolved.</p></div></div>
        <div class="process-step"><div><strong>If it is returned</strong><p>Held funds go back to your available balance in full.</p></div></div>
      </div>
    </article>

    <article class="card static">
      <div class="panel-head"><div><h2>Your beneficiaries</h2><p>Saved by you, and published by Wayfairs.</p></div><span class="pill"><?= count($beneficiaries) ?> saved</span></div>
      <?php if ($beneficiaries === []): ?>
        <p class="field-hint">Tick "Save to my beneficiaries" on a transfer to keep its details here.</p>
      <?php else: ?>
        <div class="account-stack">
          <?php foreach ($beneficiaries as $beneficiary): ?>
            <div class="summary-line">
              <span><strong><?= e((string) $beneficiary['nickname']) ?></strong><small><?= e((string) $beneficiary['bank_name']) ?> · <?= e($beneficiary['route'] === 'iban' ? 'IBAN' : 'Account') ?> ending <?= e((string) $beneficiary['identifier_last4']) ?> · <?= $beneficiary['scope'] === 'global' ? 'Wayfairs' : 'Saved by you' ?></small></span>
              <?php if ($beneficiary['scope'] === 'user'): ?>
                <form method="post" action="<?= e(wayfairs_url('/beneficiaries/' . $beneficiary['id'] . '/archive')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn small ghost" type="submit">Remove</button></form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </aside>
</section>
<?php endif; ?>

<?php if ($activePanel === 'beneficiaries'): ?>
<section class="card table-card static">
  <div class="panel-head"><div><h2>Your beneficiaries</h2><p>Saved by you, and published by Wayfairs. Enter one in the transfer form to fill its details.</p></div><span class="pill"><?= count($beneficiaries) ?> saved</span></div>
  <?php if ($beneficiaries === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No beneficiaries yet</h3><p>Tick "Save to my beneficiaries" on a transfer to keep its details here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Nickname</th><th>Route</th><th>Account holder</th><th>Bank</th><th>Account</th><th>Scope</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($beneficiaries as $beneficiary): ?><tr>
      <td><?= e((string) $beneficiary['nickname']) ?></td>
      <td><?= e($beneficiary['route'] === 'iban' ? 'IBAN' : 'Local account') ?></td>
      <td><?= e((string) $beneficiary['beneficiary_name']) ?></td>
      <td><?= e((string) $beneficiary['bank_name']) ?></td>
      <td>…<?= e((string) $beneficiary['identifier_last4']) ?></td>
      <td><?= $beneficiary['scope'] === 'global' ? 'Wayfairs' : 'Saved by you' ?></td>
      <td><?php if ($beneficiary['scope'] === 'user'): ?><form method="post" action="<?= e(wayfairs_url('/beneficiaries/' . $beneficiary['id'] . '/archive')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn small ghost" type="submit">Remove</button></form><?php else: ?><span class="field-hint">—</span><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($activePanel === 'history'): ?>
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
<?php endif; ?>

<?php if ($activePanel === 'internal'): ?>
<section class="card table-card static">
  <div class="panel-head"><div><h2>Wayfairs transfers</h2><p>Instant payments between customer accounts.</p></div><span class="pill"><?= count($internalTransfers) ?> loaded</span></div>
  <?php if ($internalTransfers === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No Wayfairs transfers</h3><p>Instant payments to other Wayfairs customers will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Direction</th><th>Counterparty</th><th>Amount</th><th>USD equivalent</th><th>Remark</th></tr></thead><tbody>
    <?php foreach ($internalTransfers as $transfer): $sent = (string) $transfer['sender_user_id'] === $me; $rate = $usdRates[$transfer['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $transfer['created_at'])) ?></td><td><?= wayfairs_pill($sent ? 'sent' : 'received') ?></td><td><?= e($sent ? (string) $transfer['recipient_email'] : (string) $transfer['sender_email']) ?></td><td class="cell-amount <?= $sent ? 'amount-negative' : 'amount-positive' ?>"><?= e(($sent ? '−' : '+') . wayfairs_amount((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], (string) $transfer['asset_symbol'], (string) $transfer['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], $rate)) ?></td><td><?= e(trim((string) ($transfer['note'] ?? '')) !== '' ? (string) $transfer['note'] : '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
