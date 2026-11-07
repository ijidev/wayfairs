<?php

declare(strict_types=1);

$isBill = ($kind ?? 'transfer') === 'bill';
$back = $isBill ? '/bills' : '/transfer';
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('security') ?></span><div class="page-heading-copy"><span class="page-kicker">Step 3 of 3</span><h1>Enter your verification code</h1><p>We sent a code to the email address on your account. Enter it to confirm the <?= $isBill ? 'bill payment' : 'transfer' ?>.</p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url($back)) ?>">Cancel</a></div>
</header>

<section class="content-grid">
  <article class="card form-card">
    <div class="form-intro"><span class="page-kicker">Verification</span><h2>One-time code</h2><p>The code expires in 10 minutes. You have 5 attempts.</p></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url($isBill ? '/bills/otp' : '/transfer/otp')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <label>Verification code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required autofocus /></label>
      <div class="form-actions"><button class="btn full" type="submit">Verify and send <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
    </form>
    <form method="post" action="<?= e(wayfairs_url($isBill ? '/bills/otp/resend' : '/transfer/otp/resend')) ?>" class="otp-resend">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <span class="field-hint">Didn't get the code or it expired?</span>
      <button class="btn ghost small" type="submit"><?= wayfairs_icon('mail') ?> Resend code</button>
    </form>
  </article>
</section>
