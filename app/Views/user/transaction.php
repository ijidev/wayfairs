<?php

declare(strict_types=1);

$r = $receipt;
$credit = (string) $r['direction'] === 'credit';
$status = (string) $r['status'];
$final = in_array($status, ['completed', 'failed', 'reversed', 'cancelled'], true);
$scale = (int) $r['asset_scale'];
$symbol = (string) $r['asset_symbol'];
$code = (string) $r['asset_code'];
$amount = wayfairs_amount((int) $r['amount_minor'], $scale, $symbol, $code);
$fee = (int) $r['fee_minor'];
$total = (int) $r['amount_minor'] + ($credit ? 0 : $fee);
$rate = $usdRates[$code] ?? null;
$metadata = json_decode((string) ($r['metadata'] ?? '{}'), true);
$remark = is_array($metadata) ? trim((string) ($metadata['user_remark'] ?? '')) : '';
$typeLabel = is_array($metadata) && ($metadata['request_kind'] ?? null) === 'bank_transfer'
    ? 'Bank transfer'
    : ucwords(str_replace('_', ' ', (string) $r['transaction_type']));
$tone = match (true) {
    $status === 'completed' => 'good',
    in_array($status, ['failed', 'reversed', 'cancelled'], true) => 'bad',
    default => 'warn',
};
$outcomeLabel = match ($status) {
    'completed' => 'Completed',
    'failed' => 'Failed',
    'reversed' => 'Returned',
    'cancelled' => 'Cancelled',
    default => 'Completed',
};
$processingDone = $final;
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('activity') ?></span><div class="page-heading-copy"><span class="page-kicker">Transaction</span><h1><?= e($typeLabel) ?></h1><p><?= e((string) $r['public_reference']) ?></p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/transactions')) ?>"><?= wayfairs_icon('arrow-right', 'btn-icon') ?> All transactions</a><a class="btn ghost" href="<?= e(wayfairs_url('/receipts/' . $r['id'])) ?>"><?= wayfairs_icon('receipt') ?> Receipt</a></div>
</header>

<section class="tx-hero" data-tx-id="<?= e((string) $r['id']) ?>">
  <span class="tx-hero-icon <?= $credit ? 'credit' : 'debit' ?>"><?= wayfairs_icon($credit ? 'deposit' : 'withdraw') ?></span>
  <div class="tx-hero-copy">
    <span class="tx-hero-label"><?= e($credit ? 'Amount received' : 'Amount sent') ?></span>
    <strong class="tx-hero-amount <?= $credit ? 'amount-positive' : 'amount-negative' ?>"><?= e(($credit ? '+' : '−') . $amount) ?></strong>
    <span class="tx-hero-desc"><?= e((string) $r['description']) ?></span>
  </div>
  <div class="tx-hero-status">
    <span class="pill <?= e($tone) ?>" data-status-pill><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
    <span class="tx-live" data-live><?= $final ? 'Final status' : 'Live · updates automatically' ?></span>
  </div>
</section>

<section class="card static tx-timeline-card">
  <div class="panel-head"><div><h2>Progress</h2><p>Where this payment is in its lifecycle.</p></div></div>
  <ol class="tx-timeline" data-timeline>
    <li class="tx-step done"><span class="tx-dot"></span><div><strong>Submitted</strong><small><?= e(wayfairs_datetime((string) $r['created_at'])) ?></small></div></li>
    <li class="tx-step <?= $processingDone ? 'done' : ($status === 'pending' || $status === 'initiated' || $status === 'processing' ? 'current' : '') ?>" data-step="processing"><span class="tx-dot"></span><div><strong>Processing</strong><small>Handled by Wayfairs operations</small></div></li>
    <li class="tx-step <?= $final && $status === 'completed' ? 'done' : ($final ? 'stopped' : '') ?>" data-step="outcome"><span class="tx-dot"></span><div><strong data-outcome-label><?= e($outcomeLabel) ?></strong><small data-outcome-time><?= $r['completed_at'] ? e(wayfairs_datetime((string) $r['completed_at'])) : 'Awaiting outcome' ?></small></div></li>
  </ol>
  <p class="field-hint">Updated <span data-updated><?= e(wayfairs_datetime((string) $r['updated_at'])) ?></span></p>
</section>

<section class="card static">
  <div class="panel-head"><div><h2>Details</h2><p>Recorded exactly as stored.</p></div></div>
  <dl class="review-list tx-details">
    <div><dt>Reference</dt><dd class="mono"><?= e((string) $r['public_reference']) ?></dd></div>
    <div><dt>Type</dt><dd><?= e($typeLabel) ?></dd></div>
    <div><dt>Amount</dt><dd><?= e($amount) ?></dd></div>
    <?php if ($fee > 0): ?><div><dt>Fee</dt><dd><?= e(wayfairs_amount($fee, $scale, $symbol, $code)) ?></dd></div><?php endif; ?>
    <?php if (!$credit && $fee > 0): ?><div><dt>Total held</dt><dd><?= e(wayfairs_amount($total, $scale, $symbol, $code)) ?></dd></div><?php endif; ?>
    <div><dt>USD equivalent</dt><dd><?= e(wayfairs_usd_equivalent((int) $r['amount_minor'], $scale, $rate)) ?></dd></div>
    <div><dt>Currency account</dt><dd class="mono"><?= e((string) $r['account_number']) ?> · <?= e($code) ?></dd></div>
    <?php if (!empty($r['counterparty_label'])): ?><div><dt>Counterparty</dt><dd><?= e((string) $r['counterparty_label']) ?></dd></div><?php endif; ?>
    <?php if ($remark !== ''): ?><div><dt>Your note</dt><dd><?= e($remark) ?></dd></div><?php endif; ?>
    <div><dt>Created</dt><dd><?= e(wayfairs_datetime((string) $r['created_at'])) ?></dd></div>
    <div><dt>Completed</dt><dd data-completed-at><?= e(wayfairs_datetime($r['completed_at'] ?? null)) ?></dd></div>
  </dl>
  <p class="field-hint">USD equivalent uses <?= e(wayfairs_rate_disclosure($rate)) ?> and is not an additional balance.</p>
</section>

<script>
(function () {
  var hero = document.querySelector('[data-tx-id]');
  if (!hero) return;
  var id = hero.getAttribute('data-tx-id');
  var endpoint = <?= json_encode(wayfairs_url('/transactions/status')) ?>;
  var labels = { initiated: 'Initiated', pending: 'Pending', processing: 'Processing', completed: 'Completed', failed: 'Failed', reversed: 'Returned', cancelled: 'Cancelled' };
  var tones = { completed: 'good', failed: 'bad', reversed: 'bad', cancelled: 'bad' };
  var pill = document.querySelector('[data-status-pill]');
  var live = document.querySelector('[data-live]');
  var steps = document.querySelectorAll('[data-step]');
  var outcomeLabel = document.querySelector('[data-outcome-label]');
  var outcomeTime = document.querySelector('[data-outcome-time]');
  var updated = document.querySelector('[data-updated]');
  var completedAt = document.querySelector('[data-completed-at]');
  var timer = null;

  function render(data) {
    var status = data.status;
    pill.className = 'pill ' + (tones[status] || 'warn');
    pill.textContent = labels[status] || status;
    var processingDone = data.final;
    steps.forEach(function (step) {
      if (step.getAttribute('data-step') === 'processing') {
        step.className = 'tx-step ' + (processingDone ? 'done' : (status === 'pending' || status === 'initiated' || status === 'processing' ? 'current' : ''));
      }
      if (step.getAttribute('data-step') === 'outcome') {
        step.className = 'tx-step ' + (data.final && status === 'completed' ? 'done' : (data.final ? 'stopped' : ''));
      }
    });
    outcomeLabel.textContent = status === 'completed' ? 'Completed' : (status === 'reversed' ? 'Returned' : (status === 'failed' ? 'Failed' : (status === 'cancelled' ? 'Cancelled' : 'Completed')));
    if (data.completed_at) {
      outcomeTime.textContent = data.completed_at;
      if (completedAt) completedAt.textContent = data.completed_at;
    }
    if (updated) updated.textContent = data.updated_at;
    live.textContent = data.final ? 'Final status' : 'Live · updates automatically';
  }

  function poll() {
    fetch(endpoint + '?ids=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (body) {
        var data = body && body.statuses && body.statuses[id];
        if (!data) return;
        render(data);
        if (data.final && timer) { clearInterval(timer); timer = null; }
      })
      .catch(function () {});
  }

  if (!<?= $final ? 'true' : 'false' ?>) {
    timer = setInterval(poll, 5000);
  }
})();
</script>
