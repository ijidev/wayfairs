<?php

declare(strict_types=1);

$openTransfers = array_values(array_filter($transfers, static fn (array $transfer): bool => in_array((string) $transfer['status'], ['pending', 'approved'], true)));
$closedTransfers = array_values(array_filter($transfers, static fn (array $transfer): bool => !in_array((string) $transfer['status'], ['pending', 'approved'], true)));
$completedCount = count(array_filter($transfers, static fn (array $transfer): bool => (string) $transfer['status'] === 'completed'));
$rejectedCount = count(array_filter($transfers, static fn (array $transfer): bool => (string) $transfer['status'] === 'rejected'));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('transfer') ?></span><div class="page-heading-copy"><span class="page-kicker">Beneficiary payments</span><h1>Bank transfer operations</h1><p>Review reserved external-transfer requests and record the real manual settlement outcome.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/admin/transactions')) ?>">Transactions</a></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('receipt') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($transfers) ?></strong><span class="metric-label">Loaded requests</span></span></div>
  <div class="metric-card <?= $openTransfers !== [] ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($openTransfers) ?></strong><span class="metric-label">Awaiting settlement</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $completedCount ?></strong><span class="metric-label">Sent</span></span></div>
  <div class="metric-card <?= $rejectedCount > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('info') ?></span><span class="metric-copy"><strong class="metric-value"><?= $rejectedCount ?></strong><span class="metric-label">Rejected</span></span></div>
</section>

<div class="notice brand"><?= wayfairs_icon('security') ?><div><strong>Encrypted destination boundary</strong><p>Full beneficiary banking details remain encrypted and are not exposed in browser payloads. This queue shows safe labels and last-four summaries; use an approved secure settlement channel before marking sent.</p></div></div>

<?php if ($openTransfers === []): ?>
  <div class="notice good"><?= wayfairs_icon('check') ?><div><strong>Bank transfer queue is clear</strong><p>New beneficiary requests appear here after funds are reserved.</p></div></div>
<?php else: ?>
  <section><div class="panel-head"><div><h2>Awaiting settlement</h2><p>External references and remarks are optional; the selected outcome is always audited.</p></div><span class="pill warn"><?= count($openTransfers) ?> open</span></div>
    <div class="decision-list">
      <?php foreach ($openTransfers as $transfer): $rate = $usdRates[$transfer['asset_code']] ?? null; ?>
        <article class="card decision-card bank-transfer-decision">
          <div class="decision-head"><div><span class="page-kicker">Bank transfer</span><h2><?= e(wayfairs_amount((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], (string) $transfer['asset_symbol'], (string) $transfer['asset_code'])) ?></h2><strong class="usd-inline"><?= e(wayfairs_usd_equivalent((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], $rate)) ?></strong><a class="hint" href="<?= e(wayfairs_url('/admin/users/' . $transfer['user_id'])) ?>"><?= e(trim((string) $transfer['first_name'] . ' ' . (string) $transfer['last_name'])) ?> · <?= e((string) $transfer['email']) ?></a></div><div class="decision-side"><?= wayfairs_pill((string) $transfer['status']) ?><span class="hint"><?= e(wayfairs_datetime((string) $transfer['created_at'])) ?></span></div></div>
          <dl class="decision-facts"><div><dt>Total reserved</dt><dd><?= e(wayfairs_amount((int) $transfer['amount_minor'] + (int) $transfer['fee_minor'], (int) $transfer['asset_scale'], (string) $transfer['asset_symbol'], (string) $transfer['asset_code'])) ?></dd></div><div><dt>Customer remark</dt><dd><?= e(trim((string) ($transfer['user_remark'] ?? '')) !== '' ? (string) $transfer['user_remark'] : '—') ?></dd></div><div class="wide"><dt>Beneficiary summary</dt><dd class="wrap-anywhere"><?= e((string) $transfer['destination_summary']) ?></dd></div></dl>
          <form class="decision-form" method="post" action="<?= e(wayfairs_url('/admin/bank-transfers/' . $transfer['id'] . '/decision')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><label>Settlement reference <span class="field-hint">Optional</span><input name="external_reference" maxlength="200" placeholder="Bank payout reference" /></label><label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Operational note" /></label><div class="decision-actions"><button class="btn" name="decision" value="approve" type="submit"><?= wayfairs_icon('check') ?> Mark sent</button><button class="btn danger" name="decision" value="reject" type="submit">Reject &amp; release</button></div></form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Settlement history</h2><p>Closed beneficiary-transfer requests, newest first.</p></div><span class="pill"><?= count($closedTransfers) ?> loaded</span></div>
  <?php if ($closedTransfers === []): ?><div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('receipt') ?></div><h3>No closed bank transfers</h3><p>Sent and rejected requests will appear here.</p></div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>Customer</th><th>Amount</th><th>USD equivalent</th><th>Beneficiary</th><th>Status</th><th>Reference / remark</th></tr></thead><tbody><?php foreach ($closedTransfers as $transfer): $rate = $usdRates[$transfer['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $transfer['created_at'])) ?></td><td><a class="cell-title" href="<?= e(wayfairs_url('/admin/users/' . $transfer['user_id'])) ?>"><?= e(trim((string) $transfer['first_name'] . ' ' . (string) $transfer['last_name'])) ?></a><span class="cell-subtitle"><?= e((string) $transfer['email']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], (string) $transfer['asset_symbol'], (string) $transfer['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], $rate)) ?></td><td class="wrap-anywhere"><?= e((string) $transfer['destination_summary']) ?></td><td><?= wayfairs_pill((string) $transfer['status']) ?></td><td><span class="cell-title"><?= e(trim((string) ($transfer['external_reference'] ?? '')) !== '' ? (string) $transfer['external_reference'] : '—') ?></span><span class="cell-subtitle"><?= e(trim((string) ($transfer['review_reason'] ?? '')) !== '' ? (string) $transfer['review_reason'] : 'No remark') ?></span></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>
