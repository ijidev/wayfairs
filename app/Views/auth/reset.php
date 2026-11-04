<?php

declare(strict_types=1);
?>
<section class="auth-card">
  <div class="auth-card-head">
    <p class="eyebrow">Account recovery</p>
    <h1>Choose a new password</h1>
    <p class="muted">Use at least 12 characters. Signing in again will be required on every device after you reset.</p>
  </div>
  <?php if (($token ?? '') === ''): ?>
    <div class="notice warn"><?= wayfairs_icon('info') ?><div><strong>This link is incomplete</strong><p>Request a new reset link.</p><p><a class="btn small" href="<?= e(wayfairs_url('/forgot')) ?>">Request a new link</a></p></div></div>
  <?php else: ?>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/reset')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <input type="hidden" name="token" value="<?= e($token) ?>" />
      <label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" required /></label>
      <label>Confirm new password<input type="password" name="password_confirm" autocomplete="new-password" minlength="12" required /></label>
      <button class="btn full" type="submit">Set new password <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
    </form>
  <?php endif; ?>
</section>
