<?php

declare(strict_types=1);
?>
<section class="auth-card">
  <div class="auth-card-head">
    <p class="eyebrow">Welcome back</p>
    <h1>Sign in to your account</h1>
    <p class="muted">Access your balances, transactions, and money movement tools.</p>
  </div>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/login')) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <label>Email address<input type="email" name="email" autocomplete="username" required placeholder="you@example.com" /></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required placeholder="••••••••••••" /></label>
    <p class="hint" style="margin:-.25rem 0 0;text-align:right"><a href="<?= e(wayfairs_url('/forgot')) ?>">Forgot password?</a></p>
    <button class="btn full" type="submit">Sign in <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
  </form>
  <p class="hint" style="margin-top:1rem;text-align:center">New to Wayfairs? <a href="<?= e(wayfairs_url('/register')) ?>">Open an account</a>.</p>
</section>
