<?php

declare(strict_types=1);

$footerSite = wayfairs_public_settings();
$footerName = $footerSite['site.name'] ?? 'Wayfairs';
$footerMode = $footerMode ?? 'compact';
$footerCopy = trim((string) ($footerSite['site.footer'] ?? ''));
// Older installs seeded a settlement-preview line as the public footer; keep the
// marketing footer customer-facing and let Settings override it at any time.
$legacyFooterCopy = 'Wayfairs · Secure manual settlement preview. No provider is connected until configured and verified.';
if ($footerCopy === '' || $footerCopy === $legacyFooterCopy) {
    $footerCopy = 'Multi-currency accounts, international payments, and invoices in one place.';
}
?>
<?php if ($footerMode === 'guest'): ?>
  <footer class="site-footer">
    <div class="footer-grid">
      <div class="footer-brand">
        <a class="brand" href="<?= e(wayfairs_url('/')) ?>"><span class="mark"><?= e(mb_substr($footerName, 0, 1)) ?></span><strong><?= e($footerName) ?></strong></a>
        <p><?= e($footerCopy) ?></p>
      </div>
      <div class="footer-column"><strong>Explore</strong><a href="<?= e(wayfairs_url('/#features')) ?>">Multi-currency</a><a href="<?= e(wayfairs_url('/#payments')) ?>">Payments</a><a href="<?= e(wayfairs_url('/#business')) ?>">Business</a><a href="<?= e(wayfairs_url('/#security')) ?>">Security</a></div>
      <div class="footer-column"><strong>Account</strong><a href="<?= e(wayfairs_url('/register')) ?>">Open an account</a><a href="<?= e(wayfairs_url('/login')) ?>">Sign in</a></div>
      <div class="footer-column"><strong>Support</strong>
        <?php if (!empty($footerSite['site.support_email'])): ?><a href="mailto:<?= e($footerSite['site.support_email']) ?>"><?= e($footerSite['site.support_email']) ?></a><?php endif; ?>
        <?php if (!empty($footerSite['site.support_phone'])): ?><span><?= e($footerSite['site.support_phone']) ?></span><?php endif; ?>
        <?php if (!empty($footerSite['site.address'])): ?><span><?= e($footerSite['site.address']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="footer-bottom"><span>© <?= gmdate('Y') ?> <?= e($footerName) ?>. All rights reserved.</span><span>Multi-currency accounts, payments and invoices in one place.</span></div>
  </footer>
<?php else: ?>
  <footer class="foot app-footer">
    <span>© <?= gmdate('Y') ?> <?= e($footerName) ?></span>
    <?php if (!empty($footerSite['site.support_email'])): ?><a href="mailto:<?= e($footerSite['site.support_email']) ?>">Support</a><?php endif; ?>
    <span class="footer-spacer"></span><span>Secure session · UTC timestamps</span>
  </footer>
<?php endif; ?>
