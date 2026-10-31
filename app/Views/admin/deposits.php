<?php

declare(strict_types=1);

$pendingDeposits = array_values(array_filter($deposits, static fn (array $deposit): bool => (string) $deposit['status'] === 'pending'));
$decidedDeposits = array_values(array_filter($deposits, static fn (array $deposit): bool => (string) $deposit['status'] !== 'pending'));
$completedCount = count(array_filter($deposits, static fn (array $deposit): bool => (string) $deposit['status'] === 'completed'));
$rejectedCount = count(array_filter($deposits, static fn (array $deposit): bool => (string) $deposit['status'] === 'rejected'));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('deposit') ?></span><div class="page-heading-copy"><span class="page-kicker">Settlement queue</span><h1>Deposit operations</h1><p>Confirm incoming funds outside Wayfairs before crediting the customer's ledger account.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/admin/transactions')) ?>">Transactions</a></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('receipt') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($deposits) ?></strong><span class="metric-label">Loaded requests</span></span></div>
  <div class="metric-card <?= $pendingDeposits !== [] ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($pendingDeposits) ?></strong><span class="metric-label">Awaiting verification</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $completedCount ?></strong><span class="metric-label">Completed</span></span></div>
  <div class="metric-card <?= $rejectedCount > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('info') ?></span><span class="metric-copy"><strong class="metric-value"><?= $rejectedCount ?></strong><span class="metric-label">Rejected</span></span></div>
</section>

<?php if ($pendingDeposits === []): ?>
  <div class="notice good"><?= wayfairs_icon('check') ?><div><strong>Deposit queue is clear</strong><p>New customer requests will appear here as soon as they are persisted.</p></div></div>
<?php else: ?>
  <section>
    <div class="panel-head"><div><h2>Awaiting decision</h2><p>External references and operational remarks are optional.</p></div><span class="pill warn"><?= count($pendingDeposits) ?> pending</span></div>
    <div class="decision-list">
      <?php foreach ($pendingDeposits as $deposit): $initials = mb_strtoupper(mb_substr((string) $deposit['first_name'], 0, 1) . mb_substr((string) $deposit['last_name'], 0, 1)); ?>
        <article class="card decision-card">
          <div class="decision-head">
            <div><span class="page-kicker">Pending deposit</span><h2><?= e(wayfairs_amount((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], (string) $deposit['asset_symbol'], (string) $deposit['asset_code'])) ?></h2><a class="hint" href="<?= e(wayfairs_url('/admin/users/' . $deposit['user_id'])) ?>"><?= e(trim((string) $deposit['first_name'] . ' ' . (string) $deposit['last_name'])) ?> · <?= e((string) $deposit['email']) ?></a></div>
            <div class="decision-side"><?= wayfairs_pill((string) $deposit['status']) ?><span class="hint"><?= e(wayfairs_datetime((string) $deposit['created_at'])) ?></span></div>
          </div>
          <?php $rate = $usdRates[$deposit['asset_code']] ?? null; ?>
          <dl class="decision-facts"><div><dt>Customer</dt><dd><span class="record-primary"><span class="record-avatar"><?= e($initials ?: 'U') ?></span><span><strong><?= e(trim((string) $deposit['first_name'] . ' ' . (string) $deposit['last_name'])) ?></strong><small><?= e((string) $deposit['email']) ?></small></span></span></dd></div><div><dt>USD equivalent</dt><dd><?= e(wayfairs_usd_equivalent((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], $rate)) ?></dd></div><div><dt>Customer remark</dt><dd class="wrap-anywhere"><?= e(trim((string) ($deposit['user_remark'] ?? '')) !== '' ? (string) $deposit['user_remark'] : '—') ?></dd></div></dl>
          <form class="decision-form" method="post" action="<?= e(wayfairs_url('/admin/deposits/' . $deposit['id'] . '/decision')) ?>">
            <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
            <label>External receipt reference<input name="external_reference" maxlength="200" placeholder="Bank or chain reference used for an approval" /></label>
            <label>Remark <span class="field-hint">Optional</span><input name="remark" maxlength="500" placeholder="Operational note" /></label>
            <div class="decision-actions"><button class="btn" name="decision" value="approve" type="submit"><?= wayfairs_icon('check') ?> Credit deposit</button><button class="btn danger" name="decision" value="reject" type="submit">Reject request</button></div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Decision history</h2><p>Non-pending deposit requests, newest first.</p></div><span class="pill"><?= count($decidedDeposits) ?> loaded</span></div>
  <?php if ($decidedDeposits === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('receipt') ?></div><h3>No deposit decisions</h3><p>Completed and rejected requests will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Submitted</th><th>Customer</th><th>Amount</th><th>USD equivalent</th><th>Customer remark</th><th>Status</th><th>Reference / remark</th></tr></thead><tbody>
    <?php foreach ($decidedDeposits as $deposit): $rate = $usdRates[$deposit['asset_code']] ?? null; ?><tr><td><?= e(wayfairs_datetime((string) $deposit['created_at'])) ?></td><td><a class="cell-title" href="<?= e(wayfairs_url('/admin/users/' . $deposit['user_id'])) ?>"><?= e(trim((string) $deposit['first_name'] . ' ' . (string) $deposit['last_name'])) ?></a><span class="cell-subtitle"><?= e((string) $deposit['email']) ?></span></td><td class="cell-amount"><?= e(wayfairs_amount((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], (string) $deposit['asset_symbol'], (string) $deposit['asset_code'])) ?></td><td><?= e(wayfairs_usd_equivalent((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], $rate)) ?></td><td><?= e(trim((string) ($deposit['user_remark'] ?? '')) !== '' ? (string) $deposit['user_remark'] : '—') ?></td><td><?= wayfairs_pill((string) $deposit['status']) ?></td><td class="wrap-anywhere"><span class="cell-title"><?= e(trim((string) ($deposit['external_reference'] ?? '')) !== '' ? (string) $deposit['external_reference'] : '—') ?></span><span class="cell-subtitle"><?= e(trim((string) ($deposit['review_reason'] ?? '')) !== '' ? (string) $deposit['review_reason'] : 'No remark') ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
