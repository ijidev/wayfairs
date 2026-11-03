<?php

declare(strict_types=1);

$completed = count(array_filter($history, static fn (array $row): bool => (string) $row['status'] === 'completed'));
$open = count(array_filter($history, static fn (array $row): bool => in_array((string) $row['status'], ['initiated', 'pending', 'processing'], true)));
$failed = count(array_filter($history, static fn (array $row): bool => in_array((string) $row['status'], ['failed', 'reversed', 'cancelled'], true)));
$toneFor = static fn (string $status): string => match (true) {
    $status === 'completed' => 'good',
    in_array($status, ['failed', 'reversed', 'cancelled'], true) => 'bad',
    default => 'warn',
};
?>
<header class="page-header">
  <div class="page-heading">
    <span class="page-icon"><?= wayfairs_icon('activity') ?></span>
    <div class="page-heading-copy"><span class="page-kicker">Money activity</span><h1>Transactions</h1><p>Tap a transaction to see its full overview. Status updates appear here automatically.</p></div>
  </div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/dashboard')) ?>">Overview</a></div>
</header>

<section class="metric-grid tx-metrics">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('receipt') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($history) ?></strong><span class="metric-label">Records</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $completed ?></strong><span class="metric-label">Completed</span></span></div>
  <div class="metric-card <?= $open > 0 ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value" data-open-count><?= $open ?></strong><span class="metric-label">In progress</span></span></div>
  <div class="metric-card <?= $failed > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('info') ?></span><span class="metric-copy"><strong class="metric-value"><?= $failed ?></strong><span class="metric-label">Returned or failed</span></span></div>
</section>

<section class="card static tx-list-card" data-filter-scope>
  <div class="panel-head"><div><h2>History</h2><p>Latest 200 records across your accounts.</p></div></div>
  <?php if ($history === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('activity') ?></div><h3>No transactions yet</h3><p>Your transaction history will appear here once money movement is recorded.</p></div>
  <?php else: ?>
    <div class="tx-filters">
      <label class="filter-search"><span class="sr-only">Search transactions</span><?= wayfairs_icon('search') ?><input data-filter-input type="search" placeholder="Search description or reference" /></label>
      <div class="tx-filter-row">
        <label><span class="sr-only">Filter by currency</span><select class="filter-select" data-filter-key="asset"><option value="">All currencies</option><?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['asset']['code']) ?>"><?= e((string) $account['asset']['code']) ?></option><?php endforeach; ?></select></label>
        <label><span class="sr-only">Filter by status</span><select class="filter-select" data-filter-key="status"><option value="">All statuses</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="processing">Processing</option><option value="failed">Failed</option><option value="reversed">Returned</option><option value="cancelled">Cancelled</option></select></label>
      </div>
    </div>
    <ul class="tx-list">
      <?php foreach ($history as $row): $credit = (string) $row['direction'] === 'credit'; $status = (string) $row['status']; $final = in_array($status, ['completed', 'failed', 'reversed', 'cancelled'], true); ?>
        <li>
          <a class="tx-row" data-filter-row data-asset="<?= e((string) $row['asset_code']) ?>" data-status="<?= e($status) ?>" data-tx-row="<?= e((string) $row['id']) ?>" data-final="<?= $final ? '1' : '0' ?>" href="<?= e(wayfairs_url('/transactions/' . $row['id'])) ?>">
            <span class="tx-row-icon <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
            <span class="tx-row-main">
              <span class="tx-row-top"><strong class="tx-row-title"><?= e((string) $row['description']) ?></strong><span class="tx-row-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . wayfairs_amount((int) $row['amount_minor'], (int) $row['asset_scale'], (string) $row['asset_symbol'], (string) $row['asset_code'])) ?></span></span>
              <span class="tx-row-meta"><?= e(str_replace('_', ' ', (string) $row['transaction_type'])) ?> · <?= e(wayfairs_datetime((string) $row['created_at'])) ?></span>
              <span class="tx-row-bottom"><span class="pill <?= e($toneFor($status)) ?>" data-row-status><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span><span class="tx-row-ref"><?= e((string) $row['public_reference']) ?></span></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="empty-state" data-filter-empty hidden><div class="empty-icon"><?= wayfairs_icon('search') ?></div><h3>No matching transactions</h3><p>Change the search or filter values to see other records.</p></div>
  <?php endif; ?>
</section>

<script>
(function () {
  var rows = document.querySelectorAll('[data-tx-row][data-final="0"]');
  if (!rows.length) return;
  var endpoint = <?= json_encode(wayfairs_url('/transactions/status')) ?>;
  var labels = { initiated: 'Initiated', pending: 'Pending', processing: 'Processing', completed: 'Completed', failed: 'Failed', reversed: 'Returned', cancelled: 'Cancelled' };
  var tones = { completed: 'good', failed: 'bad', reversed: 'bad', cancelled: 'bad' };
  var openCount = document.querySelector('[data-open-count]');

  function poll() {
    var pending = document.querySelectorAll('[data-tx-row][data-final="0"]');
    if (!pending.length) { clearInterval(timer); return; }
    var ids = Array.prototype.map.call(pending, function (row) { return row.getAttribute('data-tx-row'); });
    fetch(endpoint + '?ids=' + encodeURIComponent(ids.join(',')), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (body) {
        if (!body || !body.statuses) return;
        Object.keys(body.statuses).forEach(function (id) {
          var data = body.statuses[id];
          var row = document.querySelector('[data-tx-row="' + id + '"]');
          if (!row) return;
          var pill = row.querySelector('[data-row-status]');
          pill.className = 'pill ' + (tones[data.status] || 'warn');
          pill.textContent = labels[data.status] || data.status;
          row.setAttribute('data-status', data.status);
          row.setAttribute('data-final', data.final ? '1' : '0');
        });
        if (openCount) {
          openCount.textContent = document.querySelectorAll('[data-tx-row][data-final="0"]').length;
        }
      })
      .catch(function () {});
  }

  var timer = setInterval(poll, 6000);
})();
</script>
