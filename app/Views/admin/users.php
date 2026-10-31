<?php

declare(strict_types=1);

$activeCount = count(array_filter($users, static fn (array $u): bool => (string) $u['status'] === 'active'));
$reviewCount = count(array_filter($users, static fn (array $u): bool => in_array((string) ($u['kyc_status'] ?? 'not_started'), ['pending', 'in_review', 'needs_resubmission'], true)));
$restrictedCount = count(array_filter($users, static fn (array $u): bool => (int) ($u['restriction_count'] ?? 0) > 0));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('customers') ?></span><div class="page-heading-copy"><span class="page-kicker">Customer operations</span><h1>Customers</h1><p>Search registered customers, inspect verification state, and open account controls.</p></div></div>
  <div class="page-actions"><a class="btn" href="<?= e(wayfairs_url('/admin/users/new')) ?>"><?= wayfairs_icon('plus') ?> Create customer</a></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('customers') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($users) ?></strong><span class="metric-label">Loaded customers</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $activeCount ?></strong><span class="metric-label">Active accounts</span></span></div>
  <div class="metric-card <?= $reviewCount > 0 ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('verification') ?></span><span class="metric-copy"><strong class="metric-value"><?= $reviewCount ?></strong><span class="metric-label">Verification attention</span></span></div>
  <div class="metric-card <?= $restrictedCount > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('security') ?></span><span class="metric-copy"><strong class="metric-value"><?= $restrictedCount ?></strong><span class="metric-label">Customers restricted</span></span></div>
</section>

<section class="card static" data-filter-scope>
  <div class="panel-head"><div><h2>Customer directory</h2><p>Up to 300 customer rows, newest registration first.</p></div></div>
  <?php if ($users === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('customers') ?></div><h3>No customers yet</h3><p>Registered customers will appear here.</p></div>
  <?php else: ?>
    <div class="filter-bar">
      <label class="filter-search"><span class="sr-only">Search customers</span><?= wayfairs_icon('search') ?><input type="search" data-filter-input placeholder="Search name or email" /></label>
      <label><span class="sr-only">Filter account status</span><select class="filter-select" data-filter-key="status"><option value="">All account states</option><option value="active">Active</option><option value="restricted">Restricted</option><option value="blocked">Blocked</option><option value="closed">Closed</option><option value="pending">Pending</option></select></label>
      <label><span class="sr-only">Filter verification</span><select class="filter-select" data-filter-key="kyc"><option value="">All verification states</option><option value="verified">Verified</option><option value="pending">Pending</option><option value="in_review">In review</option><option value="needs_resubmission">Needs resubmission</option><option value="not_started">Not started</option><option value="rejected">Rejected</option></select></label>
    </div>
    <div class="record-list">
      <?php foreach ($users as $u): $initials = mb_strtoupper(mb_substr((string) $u['first_name'], 0, 1) . mb_substr((string) $u['last_name'], 0, 1)); $kycState = (string) ($u['kyc_status'] ?? 'not_started'); ?>
        <div class="record-row" data-filter-row data-status="<?= e((string) $u['status']) ?>" data-kyc="<?= e($kycState) ?>">
          <span class="record-primary"><span class="record-avatar"><?= e($initials ?: 'U') ?></span><span><strong><?= e(trim((string) $u['first_name'] . ' ' . (string) $u['last_name'])) ?></strong><small><?= e((string) $u['email']) ?></small></span></span>
          <span class="record-cell"><span><?= wayfairs_pill((string) $u['status']) ?></span><small>Account</small></span>
          <span class="record-cell"><span><?= wayfairs_pill('kyc ' . $kycState) ?></span><small>Verification</small></span>
          <span class="record-cell"><span><?= (int) $u['account_count'] ?> account<?= (int) $u['account_count'] === 1 ? '' : 's' ?></span><small><?= (int) $u['restriction_count'] ?> restriction<?= (int) $u['restriction_count'] === 1 ? '' : 's' ?></small></span>
          <span class="record-actions"><a class="btn small ghost" href="<?= e(wayfairs_url('/admin/users/' . $u['id'])) ?>">Open <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="empty-state" data-filter-empty hidden><div class="empty-icon"><?= wayfairs_icon('search') ?></div><h3>No matching customers</h3><p>Change the search or filter values.</p></div>
  <?php endif; ?>
</section>
