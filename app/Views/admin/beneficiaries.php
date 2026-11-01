<?php

declare(strict_types=1);

$countries = wayfairs_countries();
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('building') ?></span><div class="page-heading-copy"><span class="page-kicker">Payments</span><h1>Beneficiaries</h1><p>Publish payee details that every customer can use. When a customer enters a matching account number, these details fill in automatically.</p></div></div>
</header>

<section class="content-grid">
  <article class="card form-card">
    <div class="form-intro"><span class="page-kicker">New beneficiary</span><h2>Payee details</h2><p>Enter exactly what the payee's bank requires. The account number is encrypted and never shown in full.</p></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/beneficiaries')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <div class="form-grid">
        <label>Nickname<input name="nickname" maxlength="80" placeholder="e.g. Head office rent" required /></label>
        <label>Route<select name="route" required><option value="iban">IBAN (international)</option><option value="local">Local account number</option></select></label>
        <label>Country<select name="country_code" required><option value="">Select country or territory</option><?php foreach ($countries as $code => $country): ?><option value="<?= e($code) ?>"><?= e($country) ?></option><?php endforeach; ?></select></label>
        <label>Account holder name<input name="beneficiary_name" maxlength="160" required /></label>
        <label>Bank name<input name="bank_name" maxlength="160" required /></label>
        <label>Bank address <span class="field-hint">Optional</span><input name="bank_address" maxlength="300" /></label>
        <label class="wide">Beneficiary address <span class="field-hint">Optional</span><input name="beneficiary_address" maxlength="300" /></label>
        <label>IBAN <span class="field-hint">For the IBAN route</span><input class="uppercase-input" name="iban" maxlength="48" autocomplete="off" /></label>
        <label>SWIFT / BIC <span class="field-hint">Optional</span><input class="uppercase-input" name="swift_bic" maxlength="11" autocomplete="off" /></label>
        <label>Account number <span class="field-hint">For the local route</span><input name="account_number" maxlength="40" autocomplete="off" /></label>
        <label>Routing / sort / transit code <span class="field-hint">Optional</span><input name="routing_code" maxlength="40" autocomplete="off" /></label>
      </div>
      <div class="receipt-footnote"><?= wayfairs_icon('security') ?><span>Enter either an IBAN or an account number for the route you chose. Publishing a beneficiary is recorded in the audit log.</span></div>
      <div class="form-actions"><button class="btn full" type="submit">Publish beneficiary</button></div>
    </form>
  </article>

  <aside class="card static">
    <div class="panel-head"><div><h2>How matching works</h2><p>Customers see these details only when they type the exact account number.</p></div></div>
    <div class="process-steps">
      <div class="process-step"><div><strong>Exact match only</strong><p>No list of payees is shown. A customer must enter the number, and only then do the details fill in.</p></div></div>
      <div class="process-step"><div><strong>Customers can still change details</strong><p>Pre-filled fields can be edited before review.</p></div></div>
      <div class="process-step"><div><strong>Archive to stop matching</strong><p>Archived beneficiaries no longer fill in for anyone.</p></div></div>
    </div>
  </aside>
</section>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Published beneficiaries</h2><p>Active beneficiaries are offered to every customer.</p></div><span class="pill"><?= count($beneficiaries) ?> listed</span></div>
  <?php if ($beneficiaries === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('building') ?></div><h3>No beneficiaries yet</h3><p>Published beneficiaries will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Nickname</th><th>Route</th><th>Country</th><th>Account holder</th><th>Bank</th><th>Account</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($beneficiaries as $beneficiary): ?><tr>
      <td><?= e((string) $beneficiary['nickname']) ?></td>
      <td><?= e($beneficiary['route'] === 'iban' ? 'IBAN' : 'Local account') ?></td>
      <td><?= e($countries[$beneficiary['country_code']] ?? $beneficiary['country_code']) ?></td>
      <td><?= e((string) $beneficiary['beneficiary_name']) ?></td>
      <td><?= e((string) $beneficiary['bank_name']) ?></td>
      <td>…<?= e((string) $beneficiary['identifier_last4']) ?></td>
      <td><?= wayfairs_pill((string) $beneficiary['status']) ?></td>
      <td><?php if ($beneficiary['status'] === 'active'): ?><form method="post" action="<?= e(wayfairs_url('/admin/beneficiaries/' . $beneficiary['id'] . '/archive')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn small ghost" type="submit">Archive</button></form><?php else: ?><span class="field-hint">Archived</span><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
