<?php

declare(strict_types=1);

$completed = count(array_filter($transactions, static fn (array $transaction): bool => (string) $transaction['status'] === 'completed'));
$open = count(array_filter($transactions, static fn (array $transaction): bool => in_array((string) $transaction['status'], ['initiated', 'pending', 'processing'], true)));
$exceptions = count(array_filter($transactions, static fn (array $transaction): bool => in_array((string) $transaction['status'], ['failed', 'reversed', 'cancelled'], true)));
$assetCodes = array_values(array_unique(array_map(static fn (array $transaction): string => (string) $transaction['asset_code'], $transactions)));
sort($assetCodes);
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('activity') ?></span><div class="page-heading-copy"><span class="page-kicker">Ledger activity</span><h1>Transactions</h1><p>Inspect the latest persisted customer transaction records across all currency accounts.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/admin/audit')) ?>">Audit log</a></div>
</header>

<div class="notice brand"><?= wayfairs_icon('activity') ?><div><strong>USD normalization</strong><p>Equivalent values use the latest observed cached provider rate. Original ledger amounts and currencies remain authoritative and unchanged.</p></div></div>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('receipt') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($transactions) ?></strong><span class="metric-label">Loaded records</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $completed ?></strong><span class="metric-label">Completed</span></span></div>
  <div class="metric-card <?= $open > 0 ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value"><?= $open ?></strong><span class="metric-label">Open or processing</span></span></div>
  <div class="metric-card <?= $exceptions > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('info') ?></span><span class="metric-copy"><strong class="metric-value"><?= $exceptions ?></strong><span class="metric-label">Failed or reversed</span></span></div>
</section>

<section class="card table-card static" data-filter-scope>
  <div class="panel-head"><div><h2>Global transaction history</h2><p>Up to 300 records, newest first. Amounts are never aggregated across currencies.</p></div></div>
  <?php if ($transactions === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('activity') ?></div><h3>No transaction records</h3><p>Customer money movements will appear here.</p></div>
  <?php else: ?>
    <div class="filter-bar">
      <label class="filter-search"><span class="sr-only">Search transactions</span><?= wayfairs_icon('search') ?><input type="search" data-filter-input placeholder="Search customer, reference, or description" /></label>
      <label><span class="sr-only">Filter currency</span><select class="filter-select" data-filter-key="asset"><option value="">All currencies</option><?php foreach ($assetCodes as $code): ?><option value="<?= e($code) ?>"><?= e($code) ?></option><?php endforeach; ?></select></label>
      <label><span class="sr-only">Filter status</span><select class="filter-select" data-filter-key="status"><option value="">All statuses</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="processing">Processing</option><option value="failed">Failed</option><option value="reversed">Reversed</option><option value="cancelled">Cancelled</option></select></label>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Created</th><th>Customer</th><th>Reference</th><th>Description</th><th>Amount</th><th>USD equivalent</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($transactions as $transaction): $credit = (string) $transaction['direction'] === 'credit'; $rate = $usdRates[$transaction['asset_code']] ?? null; ?><tr data-filter-row data-asset="<?= e((string) $transaction['asset_code']) ?>" data-status="<?= e((string) $transaction['status']) ?>"><td><?= e(wayfairs_datetime((string) $transaction['created_at'])) ?></td><td><a class="cell-title" href="<?= e(wayfairs_url('/admin/users/' . $transaction['user_id'])) ?>"><?= e(trim((string) $transaction['first_name'] . ' ' . (string) $transaction['last_name'])) ?></a><span class="cell-subtitle"><?= e((string) $transaction['email']) ?></span></td><td><code><?= e((string) $transaction['public_reference']) ?></code><span class="cell-subtitle"><?= e((string) $transaction['account_number']) ?></span></td><td><span class="cell-title"><?= e((string) $transaction['description']) ?></span><span class="cell-subtitle"><?= e(str_replace('_', ' ', (string) $transaction['transaction_type'])) ?></span></td><td class="cell-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . wayfairs_amount((int) $transaction['amount_minor'], (int) $transaction['asset_scale'], (string) $transaction['asset_symbol'], (string) $transaction['asset_code'])) ?></td><td><span class="cell-title"><?= e(wayfairs_usd_equivalent((int) $transaction['amount_minor'], (int) $transaction['asset_scale'], $rate)) ?></span><span class="cell-subtitle"><?= e(wayfairs_rate_disclosure($rate)) ?></span></td><td><?= wayfairs_pill((string) $transaction['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="empty-state" data-filter-empty hidden><div class="empty-icon"><?= wayfairs_icon('search') ?></div><h3>No matching transactions</h3><p>Change the search or filter values.</p></div>
  <?php endif; ?>
</section>
