<?php

declare(strict_types=1);

$depositCount = (int) $stats['deposits'];
$withdrawalCount = (int) $stats['withdrawals'];
$bankTransferCount = (int) $stats['bank_transfers'];
$reviewCount = (int) $stats['kyc_review'];
$restrictionCount = (int) ($stats['restrictions'] ?? 0);
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('overview') ?></span><div class="page-heading-copy"><span class="page-kicker">Live operations</span><h1>Operations dashboard</h1><p>Review persisted customer, verification, deposit, withdrawal, and restriction queues from one workspace.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/admin/audit')) ?>"><?= wayfairs_icon('audit') ?> Audit log</a><a class="btn" href="<?= e(wayfairs_url('/admin/users')) ?>"><?= wayfairs_icon('customers') ?> Customers</a></div>
</header>

<?php if ($restrictionCount > 0): ?>
  <div class="notice warn"><?= wayfairs_icon('security') ?><div><strong><?= $restrictionCount ?> active capability restriction<?= $restrictionCount === 1 ? '' : 's' ?></strong><p>Restricted customers remain blocked from the affected operation until an administrator records a release.</p></div></div>
<?php endif; ?>

<section class="metric-grid">
  <a class="metric-card" href="<?= e(wayfairs_url('/admin/users')) ?>"><span class="metric-icon"><?= wayfairs_icon('customers') ?></span><span class="metric-copy"><strong class="metric-value"><?= (int) $stats['customers'] ?></strong><span class="metric-label">Registered customers</span></span></a>
  <a class="metric-card <?= $reviewCount > 0 ? 'warn' : '' ?>" href="<?= e(wayfairs_url('/admin/users')) ?>"><span class="metric-icon"><?= wayfairs_icon('verification') ?></span><span class="metric-copy"><strong class="metric-value"><?= $reviewCount ?></strong><span class="metric-label">Verification reviews</span></span></a>
  <a class="metric-card <?= $depositCount > 0 ? 'warn' : '' ?>" href="<?= e(wayfairs_url('/admin/deposits')) ?>"><span class="metric-icon"><?= wayfairs_icon('deposit') ?></span><span class="metric-copy"><strong class="metric-value"><?= $depositCount ?></strong><span class="metric-label">Pending deposits</span></span></a>
  <a class="metric-card <?= $withdrawalCount > 0 ? 'warn' : '' ?>" href="<?= e(wayfairs_url('/admin/withdrawals')) ?>"><span class="metric-icon"><?= wayfairs_icon('withdraw') ?></span><span class="metric-copy"><strong class="metric-value"><?= $withdrawalCount ?></strong><span class="metric-label">Open withdrawals</span></span></a>
  <a class="metric-card <?= $bankTransferCount > 0 ? 'warn' : '' ?>" href="<?= e(wayfairs_url('/admin/bank-transfers')) ?>"><span class="metric-icon"><?= wayfairs_icon('transfer') ?></span><span class="metric-copy"><strong class="metric-value"><?= $bankTransferCount ?></strong><span class="metric-label">Bank transfers</span></span></a>
</section>

<nav class="quick-grid" aria-label="Administration shortcuts">
  <a class="quick-action" href="<?= e(wayfairs_url('/admin/transactions')) ?>"><span class="icon-wrap"><?= wayfairs_icon('activity') ?></span><span>Transactions</span></a>
  <a class="quick-action" href="<?= e(wayfairs_url('/admin/assets')) ?>"><span class="icon-wrap"><?= wayfairs_icon('layers') ?></span><span>Currencies</span></a>
  <a class="quick-action" href="<?= e(wayfairs_url('/admin/settings')) ?>"><span class="icon-wrap"><?= wayfairs_icon('settings') ?></span><span>Site information</span></a>
  <a class="quick-action" href="<?= e(wayfairs_url('/admin/audit')) ?>"><span class="icon-wrap"><?= wayfairs_icon('audit') ?></span><span>Audit trail</span></a>
</nav>

<section class="ops-queue-grid">
  <article class="card static">
    <div class="panel-head"><div><h2>Deposit queue</h2><p>Confirm observed receipt before crediting.</p></div><a class="btn small ghost" href="<?= e(wayfairs_url('/admin/deposits')) ?>">Open queue</a></div>
    <?php if ($pendingDeposits === []): ?>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>Queue clear</strong><span>No deposits are waiting for verification.</span></span></div>
    <?php else: ?>
      <div class="queue-list"><?php foreach ($pendingDeposits as $deposit): ?><a class="queue-item" href="<?= e(wayfairs_url('/admin/deposits')) ?>"><span class="record-avatar"><?= e(mb_strtoupper(mb_substr((string) $deposit['first_name'], 0, 1) . mb_substr((string) $deposit['last_name'], 0, 1))) ?></span><span class="queue-main"><strong><?= e(trim((string) $deposit['first_name'] . ' ' . (string) $deposit['last_name'])) ?></strong><span><?= e((string) $deposit['email']) ?> · <?= e(wayfairs_datetime((string) $deposit['created_at'])) ?></span></span><span class="queue-amount"><?= e(wayfairs_amount((int) $deposit['amount_minor'], (int) $deposit['asset_scale'], (string) $deposit['asset_symbol'], (string) $deposit['asset_code'])) ?></span></a><?php endforeach; ?></div>
      <?php if ($depositCount > count($pendingDeposits)): ?><p class="field-hint">Showing the oldest <?= count($pendingDeposits) ?> of <?= $depositCount ?> requests.</p><?php endif; ?>
    <?php endif; ?>
  </article>

  <article class="card static">
    <div class="panel-head"><div><h2>Withdrawal queue</h2><p>Send externally before recording completion.</p></div><a class="btn small ghost" href="<?= e(wayfairs_url('/admin/withdrawals')) ?>">Open queue</a></div>
    <?php if ($pendingWithdrawals === []): ?>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>Queue clear</strong><span>No withdrawals are waiting for settlement.</span></span></div>
    <?php else: ?>
      <div class="queue-list"><?php foreach ($pendingWithdrawals as $withdrawal): ?><a class="queue-item" href="<?= e(wayfairs_url('/admin/withdrawals')) ?>"><span class="record-avatar"><?= e(mb_strtoupper(mb_substr((string) $withdrawal['first_name'], 0, 1) . mb_substr((string) $withdrawal['last_name'], 0, 1))) ?></span><span class="queue-main"><strong><?= e(trim((string) $withdrawal['first_name'] . ' ' . (string) $withdrawal['last_name'])) ?></strong><span><?= e((string) $withdrawal['email']) ?> · <?= e(wayfairs_datetime((string) $withdrawal['created_at'])) ?></span></span><span class="queue-amount"><?= e(wayfairs_amount((int) $withdrawal['amount_minor'], (int) $withdrawal['asset_scale'], (string) $withdrawal['asset_symbol'], (string) $withdrawal['asset_code'])) ?></span></a><?php endforeach; ?></div>
      <?php if ($withdrawalCount > count($pendingWithdrawals)): ?><p class="field-hint">Showing the oldest <?= count($pendingWithdrawals) ?> of <?= $withdrawalCount ?> requests.</p><?php endif; ?>
    <?php endif; ?>
  </article>

  <article class="card static">
    <div class="panel-head"><div><h2>Bank transfer queue</h2><p>Record manual beneficiary settlement.</p></div><a class="btn small ghost" href="<?= e(wayfairs_url('/admin/bank-transfers')) ?>">Open queue</a></div>
    <?php if ($pendingBankTransfers === []): ?>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>Queue clear</strong><span>No beneficiary transfers are waiting.</span></span></div>
    <?php else: ?>
      <div class="queue-list"><?php foreach ($pendingBankTransfers as $transfer): $rate = $usdRates[$transfer['asset_code']] ?? null; ?><a class="queue-item" href="<?= e(wayfairs_url('/admin/bank-transfers')) ?>"><span class="record-avatar"><?= e(mb_strtoupper(mb_substr((string) $transfer['first_name'], 0, 1) . mb_substr((string) $transfer['last_name'], 0, 1))) ?></span><span class="queue-main"><strong><?= e(trim((string) $transfer['first_name'] . ' ' . (string) $transfer['last_name'])) ?></strong><span><?= e((string) $transfer['destination_summary']) ?></span></span><span class="queue-amount"><?= e(wayfairs_usd_equivalent((int) $transfer['amount_minor'], (int) $transfer['asset_scale'], $rate)) ?></span></a><?php endforeach; ?></div>
      <?php if ($bankTransferCount > count($pendingBankTransfers)): ?><p class="field-hint">Showing the oldest <?= count($pendingBankTransfers) ?> of <?= $bankTransferCount ?> requests.</p><?php endif; ?>
    <?php endif; ?>
  </article>
</section>

<section class="content-grid">
  <article class="card static">
    <div class="panel-head"><div><h2>Recent customers</h2><p>Latest registered accounts and their verification state.</p></div><a class="btn small ghost" href="<?= e(wayfairs_url('/admin/users')) ?>">View all</a></div>
    <?php if ($recentCustomers === []): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('customers') ?></div><h3>No customer accounts</h3><p>Registered customers will appear here.</p></div>
    <?php else: ?>
      <div class="record-list"><?php foreach ($recentCustomers as $customer): $initials = mb_strtoupper(mb_substr((string) $customer['first_name'], 0, 1) . mb_substr((string) $customer['last_name'], 0, 1)); ?><div class="record-row"><span class="record-primary"><span class="record-avatar"><?= e($initials) ?></span><span><strong><?= e(trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name'])) ?></strong><small><?= e((string) $customer['email']) ?></small></span></span><span class="record-cell"><span><?= wayfairs_pill('kyc ' . (string) ($customer['kyc_status'] ?? 'not_started')) ?></span><small>Verification</small></span><span class="record-cell"><span><?= e(wayfairs_datetime((string) $customer['created_at'])) ?></span><small>Registered</small></span><span class="record-cell"><span>Customer</span><small>Account role</small></span><span class="record-actions"><a class="btn small ghost" href="<?= e(wayfairs_url('/admin/users/' . $customer['id'])) ?>">Open</a></span></div><?php endforeach; ?></div>
    <?php endif; ?>
  </article>

  <aside class="card static surface-muted">
    <div class="panel-head"><div><h2>Decision controls</h2><p>The operating rules enforced by this console.</p></div></div>
    <div class="status-checklist">
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('deposit') ?></span><span class="status-check-copy"><strong>Deposits</strong><span>Credit only after observed receipt; remarks remain optional.</span></span></div>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('withdraw') ?></span><span class="status-check-copy"><strong>Withdrawals</strong><span>Mark sent only after the external payout is complete.</span></span></div>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('audit') ?></span><span class="status-check-copy"><strong>Audit trail</strong><span>Every administrative decision is persisted.</span></span></div>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('security') ?></span><span class="status-check-copy"><strong>Support access</strong><span>Impersonation is visible, limited, and mutation-blocked.</span></span></div>
    </div>
  </aside>
</section>
