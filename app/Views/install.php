<?php

declare(strict_types=1);

$css = file_get_contents(WAYFAIRS_ROOT . '/public/assets/app.css') ?: '';
$basePath = (string) ($_GET['base_path'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Install Wayfairs</title>
  <style><?= $css ?></style>
  <style>:root{--brand:#0f8b6d;--accent:#f0b429}</style>
</head>
<body class="guest auth-guest">
  <header class="site-header">
    <div class="guest-top">
      <a class="brand brand-large" href="<?= e(wayfairs_url('/')) ?>"><span class="mark">W</span><span class="brand-copy"><strong>Wayfairs installer</strong><small>First-run setup</small></span></a>
    </div>
  </header>
  <main class="guest-main auth-main">
    <section class="auth-card" style="max-width:760px">
      <div class="auth-card-head">
        <p class="eyebrow">First-run setup</p>
        <h1>Install Wayfairs</h1>
        <p class="muted">Creates the database schema, bank settings, administrator account, and default currencies. This page disables itself once installation succeeds.</p>
      </div>
      <?php if (!empty($flash)): ?><div class="flash flash-<?= e($flash['type']) ?>" style="margin-bottom:1.25rem"><?= e($flash['message']) ?></div><?php endif; ?>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/install')) ?>">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <fieldset class="stack" style="border:1.5px solid var(--line);border-radius:14px;padding:1.25rem">
          <legend class="hint" style="padding:0 .5rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-size:.72rem">Bank</legend>
          <label>Bank name<input name="site_name" value="Wayfairs" required /></label>
          <div class="row">
            <label>Support email<input type="email" name="support_email" required placeholder="support@example.com" /></label>
            <label>Support phone<input name="support_phone" placeholder="+1 555 000 0000" /></label>
          </div>
          <label>Registered address<textarea name="address" rows="2" placeholder="Street, city, country"></textarea></label>
        </fieldset>
        <fieldset class="stack" style="border:1.5px solid var(--line);border-radius:14px;padding:1.25rem">
          <legend class="hint" style="padding:0 .5rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-size:.72rem">Administrator</legend>
          <div class="row">
            <label>First name<input name="admin_first_name" value="Admin" required /></label>
            <label>Last name<input name="admin_last_name" required /></label>
          </div>
          <label>Email<input type="email" name="admin_email" autocomplete="username" required placeholder="admin@example.com" /></label>
          <label>Password<input type="password" name="admin_password" minlength="12" autocomplete="new-password" required placeholder="At least 12 characters" /><span class="hint">Store it in a password manager.</span></label>
        </fieldset>
        <fieldset class="stack" style="border:1.5px solid var(--line);border-radius:14px;padding:1.25rem">
          <legend class="hint" style="padding:0 .5rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;font-size:.72rem">Database</legend>
          <label>Driver
            <select name="db_driver" required>
              <option value="sqlite">SQLite — local preview and testing</option>
              <option value="mysql">MySQL 8.x / MariaDB 10.4+ (InnoDB) — hosting</option>
            </select>
          </label>
          <div class="row">
            <label>MySQL host<input name="db_host" value="127.0.0.1" /></label>
            <label>Port<input name="db_port" value="3306" inputmode="numeric" /></label>
          </div>
          <label>Database name<input name="db_name" placeholder="wayfairs" /></label>
          <div class="row">
            <label>User<input name="db_user" autocomplete="off" /></label>
            <label>Password<input type="password" name="db_password" autocomplete="off" /></label>
          </div>
          <p class="hint">Use a dedicated least-privilege MySQL user. Do not use the root account.</p>
        </fieldset>
        <button class="btn full" type="submit">Install Wayfairs <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button>
      </form>
      <?php if ($basePath !== ''): ?><p class="hint" style="margin-top:1rem">Serving under <?= e($basePath) ?>.</p><?php endif; ?>
    </section>
  </main>
</body>
</html>
