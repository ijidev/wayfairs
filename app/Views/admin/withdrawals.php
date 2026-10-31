<?php

declare(strict_types=1);

$openWithdrawals = array_values(array_filter($withdrawals, static fn (array $withdrawal): bool => in_array((string) $withdrawal['status'], ['pending', 'approved'], true)));
$decidedWithdrawals = array_values(array_filter($withdrawals, static fn (array $withdrawal): bool => !in_array((string) $withdrawal['status'], ['pending', 'approved'], true)));
$completedCount = count(array_filter($withdrawals, static fn (array $withdrawal): bool => (string) $withdrawal['status'] === 'completed'));
$rejectedCount = count(array_filter($withdrawals, static fn (array $withdrawal): bool => (string) $withdrawal['status'] === 'rejected'));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('withdraw') ?></span><div class="page-heading-copy"><span class="page-kicker">Settlement queue</span><h1>Withdrawal operations</h1><p>Complete the external payout first, then capture the reservation with its settlement evidence—or reject and release it.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/admin/transactions')) ?>">Transactions</a></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('receipt') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($withdrawals) ?></strong><span class="metric-label">Loaded requests</span></span></div>
  <div class="metric-card <?= $openWithdrawals !== [] ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($openWithdrawals) ?></strong><span class="metric-label">Open settlements</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $completedCount ?></strong><span class="metric-label">Completed</span></span></div>
  <div class="metric-card <?= $rejectedCount > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('info') ?></span><span class="metric-copy"><strong class="metric-value"><?= $rejectedCount ?></strong><span class="metric-label">Rejected</span></span></div>
</section>

<?php if ($openWithdrawals === []): ?>
  <div class="notice good"><?= wayfairs_icon('check') ?><div><strong>Withdrawal queue is clear</strong><p>New customer requests will appear here with their reserved amount and destination.</p></div></div>
<?php else: ?>
  <section>
    <div class="panel-head"><div><h2>Requires settlement</h2><p>Mark sent only after the external transfer is complete.</p></div><span class="pill warn"><?= count($openWithdrawals) ?> open</span></div>
    <div class="decision-list">
      <?php foreach ($openWithdrawals as $withdrawal): $initials = mb_strtoupper(mb_substr((string) $withdrawal['first_name'], 0, 1) . mb_substr((string) $withdrawal['last_name'], 0, 1)); ?>
        <article class="card decision-card">
          <div class="decision-head">
            <div><span class="page-kicker">Withdrawal to settle</span><h2><?= e(wayfairs_amount((int) $withdrawal['amount_minor'], (int) $withdrawal['asset_scale'], (string) $withdrawal['asset_symbol'], (string) $withdrawal['asset_code'])) ?></h2><a class="hint" href="<?= e(wayfairs_url('/admin/users/' . $withdrawal['user_id'])) ?>"><?= e(trim((string) $withdrawal['first_name'] . ' ' . (string) $withdrawal['last_name'])) ?> · <?= e((string) $withdrawal['email']) ?></a></div>
            <div class="decision-side"><?= wayfairs_pill((string) $withdrawal['status']) ?><span class="hint"><?= e(wayfairs_datetime((string) $withdrawal['created_at'])) ?></span></div>
          </div>
          <?php $rate = $usdRates[$withdrawal['asset_code']] ?? null; ?>
          <dl class="decision-facts"><div><dt>Total reserved</dt><dd><?= e(wayfairs_amount((int) $withdrawal['amount_minor'] + (int) $withdrawal['fee_minor'], (int) $withdrawal['asset_scale'], (string) $withdrawal['asset_symbol'], (string) $withdrawal['asset_code'])) ?></dd></div><div><dt>USD equivalent</dt><dd><?= e(wayfairs_usd_equivalent((int) $withdrawal['amount_minor'], (int) $withdrawal['asset_scale'], $rate)) ?></dd></div><div><dt>Customer remark</dt><dd><?= e(trim((string) ($withdrawal['user_remark'] ?? '')) !== '' ? (string) $withdrawal['user_remark'] : '—') ?></dd></div><div class="wide"><dt>Destination</dt><dd class="wrap-anywhere"><?= e((string) $withdrawal['destination_summary']) ?></dd></div></dl>
          <form class="decision-form" method="post" action="<?= e(wayfairs_url('/admin/withdrawals/' . $withdrawal['id'] . '/decision')) ?>">
            <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
            <label>Settlement reference<input name="external_reference" maxlength="200" placeholder="Payout or chain reference used for completion" /></label>
            <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Operational note" /></label>
            <div class="decision-actions"><button class="btn" name="decision" value="approve" type="submit"><?= wayfairs_icon('check') ?> Mark sent</button><button class="btn danger" name="decision" value="reject" type="submit">Reject &amp; release</button></div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Decision history</h2><p>Completed, rejected, processing, or cancelled withdrawal records.</p></div><span class="pill"><?= count($decidedWithdrawals) ?> loaded</span></div>
  <?php if ($decidedWithdrawals === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('receipt') ?></div><h3>No withdrawal decisions</h3><p>Closed requests will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>Customer</th><th>Amount + fee</th><th>USD equivalent</th><th>Destination</th><th>Status</th><th>Evidence</th></tr></thead><tbody>
    <?php foreach ($decidedWithdrawals as $withdrawal): $rate = $usdRates[$withdrawal['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $withdrawal['created_at'])) ?></td><td><a class="cell-title" href="<?= e(wayfairs_url('/admin/users/' . $withdrawal['user_id'])) ?>"><?= e(trim((string) $withdrawal['first_name'] . ' ' . (string) $withdrawal['last_name'])) ?></a><span class="cell-subtitle"><?= e((string) $withdrawal['email']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $withdrawal['amount_minor'] + (int) $withdrawal['fee_minor'], (int) $withdrawal['asset_scale'], (string) $withdrawal['asset_symbol'], (string) $withdrawal['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $withdrawal['amount_minor'], (int) $withdrawal['asset_scale'], $rate)) ?></td><td class="wrap-anywhere"><?= e((string) $withdrawal['destination_summary']) ?></td><td><?= wayfairs_pill((string) $withdrawal['status']) ?></td><td class="wrap-anywhere"><span class="cell-title"><?= e(trim((string) ($withdrawal['external_reference'] ?? '')) !== '' ? (string) $withdrawal['external_reference'] : '—') ?></span><span class="cell-subtitle"><?= e(trim((string) ($withdrawal['review_reason'] ?? '')) !== '' ? (string) $withdrawal['review_reason'] : 'No remark') ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
