<?php

declare(strict_types=1);

$accountStatus = (string) ($profile['status'] ?? 'unknown');
$restrictionCount = count($restrictions);
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('security') ?></span><div class="page-heading-copy"><span class="page-kicker">Account controls</span><h1>Security</h1><p>Review current account access, active sessions, and any capability restrictions recorded against your profile.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill($accountStatus) ?></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('security') ?></span><span class="metric-copy"><strong class="metric-value"><?= e(ucfirst($accountStatus)) ?></strong><span class="metric-label">Account status</span></span></div>
  <div class="metric-card <?= $activeSessions > 1 ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('user') ?></span><span class="metric-copy"><strong class="metric-value"><?= $activeSessions ?></strong><span class="metric-label">Active session<?= $activeSessions === 1 ? '' : 's' ?></span></span></div>
  <div class="metric-card <?= $restrictionCount > 0 ? 'bad' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('verification') ?></span><span class="metric-copy"><strong class="metric-value"><?= $restrictionCount ?></strong><span class="metric-label">Active restrictions</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('clock') ?></span><span class="metric-copy"><strong class="metric-value long"><?= e($profile['last_login_at'] ? wayfairs_datetime((string) $profile['last_login_at']) : '—') ?></strong><span class="metric-label">Last successful sign-in</span></span></div>
</section>

<section class="card form-card">
  <div class="form-intro"><span class="page-kicker">Payments</span><h2>Transaction PIN</h2><p>Required to send transfers, bill payments, swaps, and Wayfairs transfers. Five wrong tries lock the PIN for 15 minutes.</p></div>
  <p class="field-hint"><?= !empty($hasPin) ? 'A PIN is set. Enter your account password and a new PIN to change it.' : 'No transaction PIN is set yet. You cannot send money until you set one.' ?></p>
  <form class="stack" method="post" action="<?= e(wayfairs_url('/security/pin')) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <label>Account password<input type="password" name="current_password" autocomplete="current-password" required /></label>
    <div class="form-grid">
      <label>New PIN (4–6 digits)<input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required /></label>
      <label>Confirm PIN<input type="password" name="pin_confirm" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required /></label>
    </div>
    <div class="form-actions"><button class="btn" type="submit"><?= !empty($hasPin) ? 'Change PIN' : 'Set PIN' ?></button></div>
  </form>
</section>

<section class="content-grid">
  <article class="card static">
    <div class="panel-head"><div><h2>Capability restrictions</h2><p>Restrictions stop the affected actions on your account until they are lifted.</p></div><span class="pill <?= $restrictionCount > 0 ? 'bad' : 'good' ?>"><?= $restrictionCount ?> active</span></div>
    <?php if ($restrictions === []): ?>
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>No active restrictions</strong><span>No capability restriction row currently blocks this account.</span></span></div>
    <?php else: ?>
      <div class="stack-list">
        <?php foreach ($restrictions as $restriction): ?>
          <div class="restriction-card"><div><strong><?= e(ucwords(str_replace('_', ' ', (string) $restriction['capability']))) ?></strong><p><?= e((string) $restriction['reason']) ?> · since <?= e(wayfairs_datetime((string) $restriction['starts_at'])) ?></p></div><?= wayfairs_pill('restricted') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static">
      <div class="panel-head"><div><h2>Current access</h2><p>Observed account and session information.</p></div></div>
      <div class="destination-details">
        <div class="destination-field wide"><span>Sign-in email</span><strong><?= e((string) ($profile['email'] ?? '—')) ?></strong></div>
        <div class="destination-field"><span>Active sessions</span><strong><?= $activeSessions ?></strong></div>
        <div class="destination-field"><span>Session lifetime</span><strong>Up to 12 hours</strong></div>
        <div class="destination-field wide"><span>Account created</span><strong><?= e(wayfairs_datetime($profile['created_at'] ?? null)) ?></strong></div>
      </div>
      <form method="post" action="<?= e(wayfairs_url('/logout')) ?>" class="form-actions start">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <button class="btn danger" type="submit">Sign out this session</button>
      </form>
    </article>
    <article class="card static surface-muted">
      <div class="panel-head"><div><h2>Need a security change?</h2><p>Credential-management controls are not exposed by this build. Contact support rather than relying on a non-working button.</p></div></div>
      <?php $support = (string) (wayfairs_public_settings()['site.support_email'] ?? ''); ?>
      <?php if ($support !== ''): ?><a class="btn ghost full" href="mailto:<?= e($support) ?>"><?= wayfairs_icon('support') ?> Contact support</a><?php endif; ?>
    </article>
  </aside>
</section>
