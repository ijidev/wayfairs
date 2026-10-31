<?php

declare(strict_types=1);
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('settings') ?></span><div class="page-heading-copy"><span class="page-kicker">Public configuration</span><h1>Site information</h1><p>Manage the brand, support details, and customer-facing operational notices stored by Wayfairs.</p></div></div>
</header>

<form class="settings-form" method="post" action="<?= e(wayfairs_url('/admin/settings')) ?>">
  <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
  <section class="card settings-section static">
    <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('building') ?></span><h2>Brand identity</h2><p>The name, tagline, and colours used throughout public and authenticated pages.</p></div>
    <div class="settings-fields">
      <div class="form-grid"><label>Bank name<input name="site_name" value="<?= e($values['site.name'] ?? 'Wayfairs') ?>" required maxlength="120" /></label><label>Tagline<input name="site_tagline" value="<?= e($values['site.tagline'] ?? '') ?>" maxlength="200" /></label></div>
      <div class="form-grid"><label>Primary colour<input name="site_primary_color" value="<?= e($values['site.primary_color'] ?? '#0f8b6d') ?>" pattern="#[0-9a-fA-F]{6}" placeholder="#0f8b6d" /></label><label>Accent colour<input name="site_accent_color" value="<?= e($values['site.accent_color'] ?? '#f0b429') ?>" pattern="#[0-9a-fA-F]{6}" placeholder="#f0b429" /></label></div>
      <p class="field-hint">Colours must be six-digit hexadecimal values. Changes apply on the next rendered page.</p>
    </div>
  </section>

  <section class="card settings-section static">
    <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('support') ?></span><h2>Support contact</h2><p>Public support channels displayed in the sidebar and footer.</p></div>
    <div class="settings-fields"><div class="form-grid"><label>Support email<input type="email" name="site_support_email" value="<?= e($values['site.support_email'] ?? '') ?>" /></label><label>Support phone<input name="site_support_phone" value="<?= e($values['site.support_phone'] ?? '') ?>" maxlength="40" /></label></div><label>Registered or support address<textarea name="site_address" rows="3" maxlength="300"><?= e($values['site.address'] ?? '') ?></textarea></label></div>
  </section>

  <section class="card settings-section static">
    <div class="settings-section-copy"><span class="page-icon"><?= wayfairs_icon('info') ?></span><h2>Customer notices</h2><p>Operational wording shown around deposits, withdrawals, verification, and the public footer.</p></div>
    <div class="settings-fields">
      <label>Default fiat deposit instructions<textarea name="site_deposit_instructions_fiat" rows="3" maxlength="1000"><?= e($values['site.deposit_instructions_fiat'] ?? '') ?></textarea><span class="field-hint">Used when a fiat asset does not have its own instructions.</span></label>
      <label>Default crypto deposit instructions<textarea name="site_deposit_instructions_crypto" rows="3" maxlength="1000"><?= e($values['site.deposit_instructions_crypto'] ?? '') ?></textarea><span class="field-hint">Used when a crypto asset does not have its own instructions.</span></label>
      <label>Withdrawal notice<textarea name="site_withdrawal_notice" rows="3" maxlength="1000"><?= e($values['site.withdrawal_notice'] ?? '') ?></textarea></label>
      <label>Verification notice<textarea name="site_kyc_notice" rows="3" maxlength="1000"><?= e($values['site.kyc_notice'] ?? '') ?></textarea></label>
      <label>Public footer text<textarea name="site_footer" rows="3" maxlength="1000"><?= e($values['site.footer'] ?? '') ?></textarea></label>
    </div>
  </section>

  <div class="receipt-footnote"><?= wayfairs_icon('audit') ?><span>Saving creates an administrative audit event listing the updated setting keys.</span></div>
  <div class="form-actions"><button class="btn" type="submit">Save site information</button></div>
</form>
