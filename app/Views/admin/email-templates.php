<?php

declare(strict_types=1);

$placeholders = [
    'registration' => '{first_name}, {site_url}',
    'deposit' => '{first_name}, {amount}, {currency}',
    'withdrawal' => '{first_name}, {amount}, {currency}',
    'bank_transfer' => '{first_name}, {amount}, {currency}, {beneficiary}',
    'bill_payment' => '{first_name}, {amount}, {currency}, {biller}',
    'topup' => '{first_name}, {amount}, {currency}',
    'debit' => '{first_name}, {amount}, {currency}',
    'otp' => '{first_name}, {code}, {minutes}',
    'flight_request' => '{first_name}, {origin}, {destination}',
];
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('invoice') ?></span><div class="page-heading-copy"><span class="page-kicker">Delivery</span><h1>Email templates</h1><p>Draft the messages sent for each event. Placeholders in braces are filled in at send time.</p></div></div>
</header>
</header>


<?php foreach ($templates as $template): $key = (string) $template['template_key']; ?>
<section class="card settings-section static">
  <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('invoice') ?></span><h2><?= e(ucwords(str_replace('_', ' ', $key))) ?></h2><p>Placeholders: <?= e($placeholders[$key] ?? '') ?></p></div>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/email-templates/' . $key)) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <div class="settings-fields">
      <label>Subject<input name="subject" value="<?= e((string) $template['subject']) ?>" maxlength="200" required /></label>
      <label>Body<textarea name="body" rows="8" required><?= e((string) $template['body']) ?></textarea></label>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Save template</button></div>
  </form>
</section>
<?php endforeach; ?>
