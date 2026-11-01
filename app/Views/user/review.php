<?php

declare(strict_types=1);

$isBill = ($kind ?? 'transfer') === 'bill';
$asset = $preview['asset'];
$code = (string) $asset['code'];
$symbol = (string) $asset['symbol'];
$scale = (int) $asset['scale'];
$destination = $preview['destination'];
$countries = $countries ?? [];
$countryName = $countries[(string) ($destination['country_code'] ?? '')] ?? (string) ($destination['country_code'] ?? '');
$identifier = isset($destination['iban']) ? (string) $destination['iban'] : (string) ($destination['account_number'] ?? '');
$identifierLabel = isset($destination['iban']) ? 'IBAN' : 'Account number';
$identifierDisplay = isset($destination['iban']) ? trim(chunk_split($identifier, 4, ' ')) : $identifier;
$saveAs = ($input['save_beneficiary'] ?? '') === '1' && !$isBill;
$nickname = trim((string) ($input['nickname'] ?? '')) ?: (string) ($destination['beneficiary_name'] ?? '');
$back = $isBill ? '/bills' : '/transfer';
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('receipt') ?></span><div class="page-heading-copy"><span class="page-kicker">Step 2 of 2</span><h1><?= $isBill ? 'Review your bill payment' : 'Review your transfer' ?></h1><p>Check every detail below. Nothing is sent until you confirm.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url($back)) ?>"><?= wayfairs_icon('arrow-right', 'btn-icon') ?> Edit details</a></div>
</header>

<section class="content-grid">
  <article class="card static">
    <div class="panel-head"><div><h2>Payment</h2><p>Amount, fee, and what remains after this payment.</p></div></div>
    <div class="selection-summary">
      <div class="summary-line"><small>Amount</small><strong><?= e(wayfairs_amount((int) $preview['amount_minor'], $scale, $symbol, $code)) ?></strong></div>
      <div class="summary-line"><small>Fee</small><strong><?= e(wayfairs_amount((int) $preview['fee_minor'], $scale, $symbol, $code)) ?></strong></div>
      <div class="summary-line"><small>Held from your balance</small><strong><?= e(wayfairs_amount((int) $preview['total_minor'], $scale, $symbol, $code)) ?></strong></div>
      <div class="summary-line"><small>Available now</small><strong><?= e(wayfairs_amount((int) $preview['available_minor'], $scale, $symbol, $code)) ?></strong></div>
      <div class="summary-line"><small>Available after this payment</small><strong><?= e(wayfairs_amount((int) $preview['available_after_minor'], $scale, $symbol, $code)) ?></strong></div>
    </div>
    <p class="field-hint"><?= e((string) $asset['name']) ?> (<?= e($code) ?>) account.</p>
  </article>

  <article class="card static">
    <div class="panel-head"><div><h2><?= $isBill ? 'Biller' : 'Beneficiary' ?></h2><p>Exactly as it will be sent.</p></div></div>
    <dl class="review-list">
      <div><dt><?= $isBill ? 'Biller name' : 'Account holder' ?></dt><dd><?= e((string) ($destination['beneficiary_name'] ?? '')) ?></dd></div>
      <div><dt>Country</dt><dd><?= e($countryName) ?></dd></div>
      <div><dt><?= $isBill ? 'Biller bank' : 'Bank' ?></dt><dd><?= e((string) ($destination['bank_name'] ?? '')) ?></dd></div>
      <?php if (!empty($destination['bank_address'])): ?><div><dt>Bank address</dt><dd><?= e((string) $destination['bank_address']) ?></dd></div><?php endif; ?>
      <?php if (!empty($destination['beneficiary_address'])): ?><div><dt>Address</dt><dd><?= e((string) $destination['beneficiary_address']) ?></dd></div><?php endif; ?>
      <div><dt><?= e($identifierLabel) ?></dt><dd class="review-identifier"><?= e($identifierDisplay) ?></dd></div>
      <?php if (!empty($destination['swift_bic'])): ?><div><dt>SWIFT / BIC</dt><dd><?= e((string) $destination['swift_bic']) ?></dd></div><?php endif; ?>
      <?php if (!empty($destination['routing_code'])): ?><div><dt>Routing / sort / transit</dt><dd><?= e((string) $destination['routing_code']) ?></dd></div><?php endif; ?>
      <div><dt><?= $isBill ? 'Bill reference and note' : 'Remark' ?></dt><dd><?= e($preview['remark'] !== '' ? (string) $preview['remark'] : '—') ?></dd></div>
    </dl>
  </article>
</section>

<?php if ($saveAs): ?>
  <div class="notice brand"><?= wayfairs_icon('check') ?><div><strong>This beneficiary will be saved</strong><p>Saved as "<?= e($nickname) ?>". Next time you enter this account number, the details fill in for you.</p></div></div>
<?php endif; ?>

<div class="notice warn"><?= wayfairs_icon('info') ?><div><strong>Check every detail</strong><p>The amount is held as soon as you confirm. Make sure the name, bank, and account number match the beneficiary's records.</p></div></div>

<form class="review-actions" method="post" action="<?= e(wayfairs_url($isBill ? '/bills/confirm' : '/transfer/confirm')) ?>">
  <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
  <a class="btn ghost" href="<?= e(wayfairs_url($back)) ?>">Edit details</a>
  <button class="btn" type="submit"><?= $isBill ? 'Confirm and pay bill' : 'Confirm and send' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
</form>
