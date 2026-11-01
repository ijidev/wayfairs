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
    <form method="post" action="<?= e(wayfairs_url('/admin/otp')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>" />
      <button class="btn <?= $enabled ? 'ghost' : '' ?>" type="submit"><?= $enabled ? 'Turn off' : 'Turn on' ?> verification codes</button>
    </form>
  </div>
</section>
