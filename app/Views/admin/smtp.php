<?php

declare(strict_types=1);

$values = $values ?? [];
$encryption = (string) ($values['smtp.encryption'] ?? 'tls');
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('support') ?></span><div class="page-heading-copy"><span class="page-kicker">Delivery</span><h1>Email (SMTP)</h1><p>Configure the mail server used for verification codes, welcome emails, and customer notices.</p></div></div>
</header>
</header>


<form class="settings-form" method="post" action="<?= e(wayfairs_url('/admin/smtp')) ?>">
  <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
  <section class="card settings-section static">
    <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('support') ?></span><h2>Server</h2><p>Connection details for your mail provider.</p></div>
    <div class="settings-fields">
      <div class="form-grid">
        <label>SMTP host<input name="smtp_host" value="<?= e((string) ($values['smtp.host'] ?? '')) ?>" placeholder="smtp.example.com" /></label>
        <label>Port<input name="smtp_port" type="number" min="1" max="65535" value="<?= e((string) ($values['smtp.port'] ?? '587')) ?>" /></label>
      </div>
      <div class="form-grid">
        <label>Username<input name="smtp_username" value="<?= e((string) ($values['smtp.username'] ?? '')) ?>" autocomplete="off" /></label>
        <label>Password<input type="password" name="smtp_password" placeholder="Leave blank to keep the current password" autocomplete="new-password" /></label>
      </div>
      <div class="form-grid">
        <label>Encryption<select name="smtp_encryption">
          <option value="tls" <?= $encryption === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
          <option value="ssl" <?= $encryption === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
          <option value="none" <?= $encryption === 'none' ? 'selected' : '' ?>>None (not recommended)</option>
        </select></label>
        <label>From email<input type="email" name="smtp_from_email" value="<?= e((string) ($values['smtp.from_email'] ?? '')) ?>" /></label>
      </div>
      <label>From name<input name="smtp_from_name" value="<?= e((string) ($values['smtp.from_name'] ?? 'Wayfairs')) ?>" /></label>
      <label class="inline-check"><input type="checkbox" name="smtp_verify_peer" value="1" <?= ($values['smtp.verify_peer'] ?? '1') !== '0' ? 'checked' : '' ?> /> Verify the server certificate (turn off only for self-signed servers)</label>
    </div>
  </section>
  <div class="form-actions"><button class="btn" type="submit">Save SMTP settings</button></div>
</form>

<section class="card settings-section static">
  <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('support') ?></span><h2>Test connection</h2><p>Send a test message to confirm the settings work.</p></div>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/smtp/test')) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <div class="form-grid">
      <label>Send test to<input type="email" name="test_email" required placeholder="you@example.com" /></label>
    </div>
    <div class="form-actions"><button class="btn ghost" type="submit">Send test email</button></div>
  </form>
</section>
