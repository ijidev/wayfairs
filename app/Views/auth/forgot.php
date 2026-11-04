<?php

declare(strict_types=1);
?>
<section class="auth-card">
  <div class="auth-card-head">
    <p class="eyebrow">Account recovery</p>
    <h1>Reset your password</h1>
    <p class="muted">Enter the email address on your account. If it matches an account, we will send a link to choose a new password.</p>
  </div>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/forgot')) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <label>Email address<input type="email" name="email" autocomplete="username" required placeholder="you@example.com" /></label>
    <button class="btn full" type="submit">Send reset link <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
  </form>
  <p class="hint" style="margin-top:1rem;text-align:center"><a href="<?= e(wayfairs_url('/login')) ?>">Back to sign in</a></p>
</section>
