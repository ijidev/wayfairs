<?php

declare(strict_types=1);

$status = (string) ($profile['status'] ?? 'not_started');
$locked = in_array($status, ['pending', 'in_review', 'verified'], true);
$notice = trim((string) ($settings['site.kyc_notice'] ?? ''));
$user = \Wayfairs\Core\Context::user() ?? [];
$fullName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
$initials = mb_strtoupper(mb_substr((string) ($user['first_name'] ?? 'U'), 0, 1) . mb_substr((string) ($user['last_name'] ?? ''), 0, 1));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('verification') ?></span><div class="page-heading-copy"><span class="page-kicker">Account readiness</span><h1>Identity verification</h1><p>Submit the identity details we need to verify your account.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $status) ?></div>
</header>

<?php if ($notice !== ''): ?><div class="notice brand"><?= wayfairs_icon('info') ?><div><strong>Verification information</strong><p><?= e($notice) ?></p></div></div><?php endif; ?>
<?php if (!empty($profile['review_reason'])): ?><div class="notice <?= in_array($status, ['rejected', 'expired'], true) ? 'bad' : 'warn' ?>"><?= wayfairs_icon('info') ?><div><strong>Review note</strong><p><?= e((string) $profile['review_reason']) ?></p></div></div><?php endif; ?>

<section class="profile-hero">
  <span class="profile-avatar"><?= e($initials ?: 'U') ?></span>
  <span class="profile-hero-copy"><span class="page-kicker">Customer profile</span><h2><?= e($fullName ?: 'Account owner') ?></h2><p><?= e((string) ($user['email'] ?? '')) ?></p></span>
  <span class="profile-badges"><?= wayfairs_pill('kyc ' . $status) ?></span>
</section>

<section class="content-grid">
  <article class="card form-card">
    <?php if ($locked): ?>
      <div class="form-intro"><h2><?= $status === 'verified' ? 'Verification approved' : 'Review in progress' ?></h2><p>Your submitted details are locked while this status is active.</p></div>
      <div class="status-checklist">
        <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>Details submitted</strong><span><?= e(wayfairs_datetime($profile['submitted_at'] ?? null)) ?></span></span></div>
        <div class="status-check"><span class="status-check-icon <?= $status === 'pending' ? 'warn' : '' ?>"><?= wayfairs_icon($status === 'pending' ? 'clock' : 'check') ?></span><span class="status-check-copy"><strong>Verification review</strong><span><?= $status === 'pending' ? 'Waiting to be reviewed' : ($status === 'in_review' ? 'Review in progress' : 'Review completed') ?></span></span></div>
        <div class="status-check"><span class="status-check-icon <?= $status === 'verified' ? '' : 'warn' ?>"><?= wayfairs_icon($status === 'verified' ? 'check' : 'clock') ?></span><span class="status-check-copy"><strong>Money movement access</strong><span><?= $status === 'verified' ? 'Verification gate satisfied' : 'Remains locked until approval' ?></span></span></div>
      </div>
      <?php if (!empty($profile['submission_reference'])): ?><div class="destination-field"><span>Submission reference</span><div class="copy-line"><strong class="mono"><?= e((string) $profile['submission_reference']) ?></strong><button class="btn small ghost copy-button" type="button" data-copy="<?= e((string) $profile['submission_reference']) ?>" aria-label="Copy submission reference"><?= wayfairs_icon('copy') ?></button></div></div><?php endif; ?>
    <?php else: ?>
      <div class="form-intro"><h2><?= in_array($status, ['needs_resubmission', 'rejected', 'expired'], true) ? 'Update and resubmit' : 'Submit verification details' ?></h2><p>Review each field carefully. Only the last characters of your ID number are stored here.</p></div>
      <form class="stack" method="post" action="<?= e(wayfairs_url('/kyc')) ?>">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <div class="form-grid">
          <label>Date of birth<input type="date" name="date_of_birth" value="<?= e((string) ($profile['date_of_birth'] ?? '')) ?>" required /></label>
          <label>Country code<input class="uppercase-input" name="country_code" maxlength="2" value="<?= e((string) ($profile['country_code'] ?? '')) ?>" placeholder="US" required /><span class="field-hint">Two-letter ISO country code.</span></label>
        </div>
        <label>Residential address<input name="address_line1" maxlength="200" value="<?= e((string) ($profile['address_line1'] ?? '')) ?>" required /></label>
        <label>Address line 2 <span class="field-hint">Optional</span><input name="address_line2" maxlength="200" value="<?= e((string) ($profile['address_line2'] ?? '')) ?>" /></label>
        <div class="form-grid three">
          <label>City<input name="city" maxlength="120" value="<?= e((string) ($profile['city'] ?? '')) ?>" required /></label>
          <label>State / region<input name="region" maxlength="120" value="<?= e((string) ($profile['region'] ?? '')) ?>" required /></label>
          <label>Postal code<input name="postal_code" maxlength="30" value="<?= e((string) ($profile['postal_code'] ?? '')) ?>" required /></label>
        </div>
        <div class="form-grid">
          <label>ID type<select name="id_type" required><option value="national_id" <?= ($profile['id_type'] ?? '') === 'national_id' ? 'selected' : '' ?>>National ID</option><option value="passport" <?= ($profile['id_type'] ?? '') === 'passport' ? 'selected' : '' ?>>Passport</option><option value="drivers_licence" <?= ($profile['id_type'] ?? '') === 'drivers_licence' ? 'selected' : '' ?>>Driver's licence</option></select></label>
          <label>Last 4 characters of ID<input name="id_last4" maxlength="8" value="<?= e((string) ($profile['id_last4'] ?? '')) ?>" autocomplete="off" required /></label>
        </div>
        <div class="receipt-footnote"><?= wayfairs_icon('security') ?><span>This application stores the submitted profile fields and only the supplied ending characters of the ID number. It does not claim an automated provider check.</span></div>
        <div class="form-actions"><button class="btn" type="submit">Submit for verification <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
      </form>
    <?php endif; ?>
  </article>

  <aside class="card static sticky-panel">
    <div class="panel-head"><div><h2>Submitted profile</h2><p>The identity details currently stored for review.</p></div></div>
    <?php if (empty($profile['date_of_birth']) && empty($profile['address_line1'])): ?>
      <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('user') ?></div><h3>No details submitted</h3><p>Complete the form to create a review record.</p></div>
    <?php else: ?>
      <div class="destination-details">
        <div class="destination-field"><span>Date of birth</span><strong><?= e((string) ($profile['date_of_birth'] ?? '—')) ?></strong></div>
        <div class="destination-field"><span>Country</span><strong><?= e((string) ($profile['country_code'] ?? '—')) ?></strong></div>
        <div class="destination-field wide"><span>Address</span><strong><?= e(trim(implode(', ', array_filter([(string) ($profile['address_line1'] ?? ''), (string) ($profile['address_line2'] ?? ''), (string) ($profile['city'] ?? ''), (string) ($profile['region'] ?? ''), (string) ($profile['postal_code'] ?? '')])))) ?></strong></div>
        <div class="destination-field"><span>ID type</span><strong><?= e(ucwords(str_replace('_', ' ', (string) ($profile['id_type'] ?? '—')))) ?></strong></div>
        <div class="destination-field"><span>ID ending</span><strong><?= !empty($profile['id_last4']) ? '•••• ' . e((string) $profile['id_last4']) : '—' ?></strong></div>
      </div>
    <?php endif; ?>
  </aside>
</section>
