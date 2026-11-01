<?php

declare(strict_types=1);

$completed = count(array_filter($history, static fn (array $row): bool => (string) $row['status'] === 'completed'));
$open = count(array_filter($history, static fn (array $row): bool => in_array((string) $row['status'], ['initiated', 'pending', 'processing'], true)));
$failed = count(array_filter($history, static fn (array $row): bool => in_array((string) $row['status'], ['failed', 'reversed', 'cancelled'], true)));
?>
<header class="page-header">
  <div class="page-heading">
    <span class="page-icon"><?= wayfairs_icon('activity') ?></span>
    <div class="page-heading-copy"><span class="page-kicker">Money activity</span><h1>Transactions</h1><p>Search and review your recent transactions across all your currency accounts.</p></div>
  </div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/dashboard')) ?>">Back to overview</a></div>
</header>

<div class="notice brand"><?= wayfairs_icon('activity') ?><div><strong>USD normalization</strong><p>Equivalent values use the latest observed provider rate available now, not a reconstructed historical trade rate. Unavailable rates remain explicitly unavailable.</p></div></div>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('receipt') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($history) ?></strong><span class="metric-label">Loaded records</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $completed ?></strong><span class="metric-label">Completed</span></span></div>
  <div class="metric-card <?= $open > 0 ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value"><?= $open ?></strong><span class="metric-label">Open or processing</span></span></div>
  <div class="metric-card <?= $failed > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('info') ?></span><span class="metric-copy"><strong class="metric-value"><?= $failed ?></strong><span class="metric-label">Failed or reversed</span></span></div>
</section>

<section class="card static" data-filter-scope>
  <div class="panel-head"><div><h2>Transaction history</h2><p>Up to the latest 200 records are loaded from your account.</p></div></div>
  <?php if ($history === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('activity') ?></div><h3>No transactions yet</h3><p>Your transaction history will appear here once money movement is recorded.</p></div>
  <?php else: ?>
    <div class="filter-bar">
      <label class="filter-search"><span class="sr-only">Search transactions</span><?= wayfairs_icon('search') ?><input data-filter-input type="search" placeholder="Search description, reference, or amount" /></label>
      <label><span class="sr-only">Filter by currency</span><select class="filter-select" data-filter-key="asset"><option value="">All currencies</option><?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['asset']['code']) ?>"><?= e((string) $account['asset']['code']) ?></option><?php endforeach; ?></select></label>
      <label><span class="sr-only">Filter by status</span><select class="filter-select" data-filter-key="status"><option value="">All statuses</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="processing">Processing</option><option value="failed">Failed</option><option value="reversed">Reversed</option><option value="cancelled">Cancelled</option></select></label>
    </div>
    <div class="transaction-list">
      <?php foreach ($history as $row): $credit = (string) $row['direction'] === 'credit'; $rate = $usdRates[$row['asset_code']] ?? null; ?>
        <a class="transaction-item" data-filter-row data-asset="<?= e((string) $row['asset_code']) ?>" data-status="<?= e((string) $row['status']) ?>" href="<?= e(wayfairs_url('/receipts/' . $row['id'])) ?>">
          <span class="transaction-symbol <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
          <span class="transaction-main"><strong><?= e((string) $row['description']) ?></strong><span><?= e(str_replace('_', ' ', (string) $row['transaction_type'])) ?> · <?= e((string) $row['public_reference']) ?></span></span>
          <span class="transaction-meta"><strong><?= e(wayfairs_usd_equivalent((int) $row['amount_minor'], (int) $row['asset_scale'], $rate)) ?></strong><span><?= e((string) $row['asset_code']) ?> · <?= e(wayfairs_datetime((string) $row['created_at'])) ?></span></span>
          <span class="transaction-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . wayfairs_amount((int) $row['amount_minor'], (int) $row['asset_scale'], (string) $row['asset_symbol'], (string) $row['asset_code'])) ?></span>
          <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="empty-state" data-filter-empty hidden><div class="empty-icon"><?= wayfairs_icon('search') ?></div><h3>No matching transactions</h3><p>Change the search or filter values to see other records.</p></div>
  <?php endif; ?>
</section>
