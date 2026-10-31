<?php

declare(strict_types=1);
?>
<section class="auth-card">
  <div class="auth-card-head">
    <p class="eyebrow">Get started</p>
    <h1>Open your account</h1>
    <p class="muted">Use your legal name. Identity verification follows sign-up.</p>
  </div>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/register')) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <div class="row">
      <label>First name<input name="first_name" required autocomplete="given-name" placeholder="Jane" /></label>
      <label>Last name<input name="last_name" required autocomplete="family-name" placeholder="Doe" /></label>
    </div>
    <label>Email address<input type="email" name="email" autocomplete="username" required placeholder="you@example.com" /></label>
    <label>Phone number (optional)<input name="phone" autocomplete="tel" placeholder="+1 555 000 0000" /></label>
    <label>Password<input type="password" name="password" minlength="12" autocomplete="new-password" required placeholder="At least 12 characters" /><span class="hint">Use a strong, unique password of at least 12 characters.</span></label>
    <button class="btn full" type="submit">Create account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
  </form>
  <p class="hint" style="margin-top:1rem;text-align:center">Already have an account? <a href="<?= e(wayfairs_url('/login')) ?>">Sign in</a>.</p>
</section>
