<?php

declare(strict_types=1);

$p = $preview;
$from = $p['from_asset'];
$to = $p['to_asset'];
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('layers') ?></span><div class="page-heading-copy"><span class="page-kicker">Step 2 of 2</span><h1>Review your swap</h1><p>Check the amounts below. Nothing is converted until you confirm.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url('/swap')) ?>">Edit details</a></div>
</header>

<section class="card static">
  <div class="panel-head"><div><h2>Conversion</h2><p>Amounts that will move between your accounts.</p></div></div>
  <div class="selection-summary">
    <div class="summary-line"><small>You convert</small><strong><?= e(wayfairs_amount((int) $p['from_amount_minor'], (int) $from['scale'], (string) $from['symbol'], (string) $from['code'])) ?></strong></div>
    <div class="summary-line"><small>Fee (<?= e(rtrim(rtrim(number_format($p['fee_percent'], 4), '0'), '.')) ?>%)</small><strong><?= e(wayfairs_amount((int) $p['fee_minor'], (int) $from['scale'], (string) $from['symbol'], (string) $from['code'])) ?></strong></div>
    <div class="summary-line"><small>You receive</small><strong><?= e(wayfairs_amount((int) $p['to_amount_minor'], (int) $to['scale'], (string) $to['symbol'], (string) $to['code'])) ?></strong></div>
    <div class="summary-line"><small>Rate</small><strong>1 <?= e((string) $from['code']) ?> = <?= e(number_format((float) $p['from_rate'] / (float) $p['to_rate'], 6)) ?> <?= e((string) $to['code']) ?></strong></div>
    <div class="summary-line"><small>Available after</small><strong><?= e(wayfairs_amount((int) $p['available_after_minor'], (int) $from['scale'], (string) $from['symbol'], (string) $from['code'])) ?></strong></div>
  </div>
</section>

<div class="notice warn"><?= wayfairs_icon('info') ?><div><strong>Check the amounts</strong><p>The conversion is instant and cannot be reversed once confirmed.</p></div></div>

<?php if (empty($hasPin)): ?>
<div class="notice warn"><?= wayfairs_icon('security') ?><div><strong>Set a transaction PIN first</strong><p>Payments need your 4–6 digit transaction PIN. <a href="<?= e(wayfairs_url('/security')) ?>">Set it in Security</a>.</p></div></div>
<?php endif; ?>
<form class="review-actions" method="post" action="<?= e(wayfairs_url('/swap/confirm')) ?>">
  <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <label class="pin-field">Transaction PIN<input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required placeholder="••••" /></label>
  <a class="btn ghost" href="<?= e(wayfairs_url('/swap')) ?>">Edit details</a>
  <button class="btn" type="submit">Confirm and swap <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
</form>
