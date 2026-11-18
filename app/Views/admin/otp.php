<?php

declare(strict_types=1);
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('security') ?></span><div class="page-heading-copy"><span class="page-kicker">Security</span><h1>Verification codes</h1><p>When enabled, customers must enter a code sent to their email before a transfer or bill payment is sent.</p></div></div>
</header>

<section class="card settings-section static">
  <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('security') ?></span><h2>One-time codes</h2><p>Off by default. Turn on only after SMTP is configured and tested.</p></div>
  <div class="settings-fields">
    <div class="summary-line"><small>Verification codes</small><?= wayfairs_pill($enabled ? 'enabled' : 'disabled') ?></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/admin/otp')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <label class="inline-check"><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> /> Enable verification codes</label>
      <label>Code lifetime in minutes
        <input type="number" name="ttl_minutes" min="5" max="60" step="1" value="<?= (int) ($ttlMinutes ?? 30) ?>" required />
        <span class="field-hint">5–60 minutes. Longer values help customers using slow email. The saved payment stays available for 90 minutes.</span>
      </label>
      <div class="form-actions"><button class="btn <?= $enabled ? 'ghost' : '' ?>" type="submit">Save verification settings</button></div>
    </form>
  </div>
</section>
