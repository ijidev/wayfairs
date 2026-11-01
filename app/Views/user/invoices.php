<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$openIssued = count(array_filter($issued, static fn (array $invoice): bool => (string) $invoice['status'] === 'open'));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('invoice') ?></span><div class="page-heading-copy"><span class="page-kicker">Payment requests</span><h1>Invoices</h1><p>Create a payment request for another customer or pay an invoice using its public code.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><span class="pill <?= $openIssued > 0 ? 'warn' : '' ?>"><?= $openIssued ?> open</span></div>
</header>

<?php if (!$verified): ?><div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification approval is required</strong><p>Creating and paying invoices remains unavailable until your identity status is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div><?php endif; ?>

<section class="content-grid equal">
  <article class="card form-card">
    <div class="form-intro"><h2>Create an invoice</h2><p>Generate a public invoice code that you can share with the payer.</p></div>
    <?php if ($accounts === []): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('wallet') ?></div><h3>No currency account available</h3><p>An enabled currency is required to create an invoice.</p></div>
    <?php else: ?>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/invoices')) ?>">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <label>Invoice title<input name="title" maxlength="200" placeholder="e.g. Design services" required <?= $verified ? '' : 'disabled' ?> /></label>
        <label>Description <span class="field-hint">Optional</span><textarea name="description" rows="3" maxlength="2000" placeholder="Add payment context for the payer" <?= $verified ? '' : 'disabled' ?>></textarea></label>
        <div class="form-grid">
          <label>Currency<select name="asset_code" required <?= $verified ? '' : 'disabled' ?>><?php foreach ($accounts as $account): $asset = $account['asset']; ?><option value="<?= e((string) $asset['code']) ?>"><?= e((string) $asset['name']) ?> (<?= e((string) $asset['code']) ?>)</option><?php endforeach; ?></select></label>
          <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" required <?= $verified ? '' : 'disabled' ?> /></label>
        </div>
        <label>Due date and time <span class="field-hint">Optional</span><input type="datetime-local" name="due_at" <?= $verified ? '' : 'disabled' ?> /></label>
        <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Create invoice' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    <?php endif; ?>
  </article>

  <article class="card form-card">
    <div class="form-intro"><h2>Pay an invoice</h2><p>Enter the exact public code shared by another Wayfairs customer.</p></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/invoices/pay')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />
      <label>Invoice code<input class="mono" name="code" placeholder="INV-…" autocomplete="off" required <?= $verified ? '' : 'disabled' ?> /><span class="field-hint">Payment uses the currency and amount stored on the invoice.</span></label>
      <div class="receipt-footnote"><?= wayfairs_icon('info') ?><span>Paying an invoice moves the money between the two accounts and records it for both customers.</span></div>
      <div class="form-actions"><button class="btn full" type="submit" <?= $verified ? '' : 'disabled' ?>><?= $verified ? 'Pay invoice' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
    </form>
  </article>
</section>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Invoices you issued</h2><p>Public payment requests created from your account.</p></div><span class="pill"><?= count($issued) ?> loaded</span></div>
  <?php if ($issued === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('invoice') ?></div><h3>No issued invoices</h3><p>Create an invoice above to generate your first payment code.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Invoice code</th><th>Title</th><th>Amount</th><th>USD equivalent</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($issued as $i): $rate = $usdRates[$i['asset_code']] ?? null; ?><tr><td><code><?= e((string) $i['public_code']) ?></code></td><td><span class="cell-title"><?= e((string) $i['title']) ?></span><span class="cell-subtitle"><?= e(mb_substr((string) ($i['description'] ?? ''), 0, 70)) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $i['amount_minor'], (int) $i['asset_scale'], (string) $i['asset_symbol'], (string) $i['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $i['amount_minor'], (int) $i['asset_scale'], $rate)) ?></td><td><?= e($i['due_at'] ? wayfairs_datetime((string) $i['due_at']) : 'No due date') ?></td><td><?= wayfairs_pill((string) $i['status']) ?></td><td><button class="btn small ghost copy-button" type="button" data-copy="<?= e((string) $i['public_code']) ?>" aria-label="Copy invoice code"><?= wayfairs_icon('copy') ?></button></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Invoices you paid</h2><p>Invoice payments completed from your account.</p></div><span class="pill"><?= count($paid) ?> loaded</span></div>
  <?php if ($paid === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('check') ?></div><h3>No paid invoices</h3><p>Completed invoice payments will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Paid</th><th>Code</th><th>Title</th><th>Amount</th><th>USD equivalent</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($paid as $i): $rate = $usdRates[$i['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime($i['paid_at'] ?? null)) ?></td><td><code><?= e((string) $i['public_code']) ?></code></td><td><span class="cell-title"><?= e((string) $i['title']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $i['amount_minor'], (int) $i['asset_scale'], (string) $i['asset_symbol'], (string) $i['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $i['amount_minor'], (int) $i['asset_scale'], $rate)) ?></td><td><?= wayfairs_pill((string) $i['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
