<?php

declare(strict_types=1);

$site = wayfairs_public_settings();
$name = $site['site.name'] ?? 'Wayfairs';
$css = file_get_contents(WAYFAIRS_ROOT . '/public/assets/app.css') ?: '';
$flash = \Wayfairs\Core\Flash::consume();
$user = \Wayfairs\Core\Context::user() ?? [];
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = mb_strtoupper(mb_substr((string) ($user['first_name'] ?? 'A'), 0, 1) . mb_substr((string) ($user['last_name'] ?? ''), 0, 1));
$links = [
    ['path' => '/admin', 'label' => 'Dashboard', 'icon' => 'overview'],
    ['path' => '/admin/users', 'label' => 'Customers', 'icon' => 'customers'],
    ['path' => '/admin/transactions', 'label' => 'Transactions', 'icon' => 'activity'],
    ['path' => '/admin/bank-transfers', 'label' => 'Bank transfers', 'icon' => 'transfer'],
    ['path' => '/admin/deposits', 'label' => 'Deposits', 'icon' => 'deposit'],
    ['path' => '/admin/withdrawals', 'label' => 'Withdrawals', 'icon' => 'withdraw'],
    ['path' => '/admin/flights', 'label' => 'Flight requests', 'icon' => 'globe'],
    ['path' => '/admin/beneficiaries', 'label' => 'Beneficiaries', 'icon' => 'building'],
    ['path' => '/admin/smtp', 'label' => 'Email (SMTP)', 'icon' => 'support'],
    ['path' => '/admin/email-templates', 'label' => 'Email templates', 'icon' => 'invoice'],
    ['path' => '/admin/otp', 'label' => 'Verification codes', 'icon' => 'security'],
    ['path' => '/admin/assets', 'label' => 'Currencies', 'icon' => 'layers'],
    ['path' => '/admin/settings', 'label' => 'Site information', 'icon' => 'settings'],
    ['path' => '/admin/audit', 'label' => 'Audit log', 'icon' => 'audit'],
];
$currentLabel = 'Dashboard';
foreach ($links as $link) {
    if (wayfairs_nav_active($link['path'])) {
        $currentLabel = $link['label'];
        break;
    }
}
$footerMode = 'compact';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#111827" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?= e($name) ?> · Administration</title>
  <style><?= $css ?></style>
  <style>:root{--brand:<?= e($site['site.primary_color'] ?? '#0f8b6d') ?>;--accent:<?= e($site['site.accent_color'] ?? '#f0b429') ?>}</style>
</head>
<body class="app app-shell admin">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <aside class="side app-sidebar admin-sidebar">
    <a class="brand sidebar-brand" href="<?= e(wayfairs_url('/admin')) ?>">
      <span class="mark"><?= e(mb_substr($name, 0, 1)) ?></span>
      <span class="brand-copy"><strong><?= e($name) ?></strong><small>Operations console</small></span>
    </a>
    <div class="side-section">
      <p class="side-label">Administration</p>
      <nav class="side-nav" aria-label="Administration navigation">
        <?php foreach ($links as $link): ?>
          <a class="side-link<?= wayfairs_nav_active($link['path']) ? ' active' : '' ?>" href="<?= e(wayfairs_url($link['path'])) ?>"<?= wayfairs_nav_active($link['path']) ? ' aria-current="page"' : '' ?>><?= wayfairs_icon($link['icon']) ?><span><?= e($link['label']) ?></span></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="side-support admin-note">
      <?= wayfairs_icon('verification') ?>
      <div><strong>Controlled operations</strong><span>All decisions are recorded in the audit log.</span></div>
    </div>
    <div class="side-foot">
      <div class="user-chip"><span class="avatar"><?= e($initials ?: 'A') ?></span><span><strong><?= e($fullName ?: 'Administrator') ?></strong><small>Administrator</small></span></div>
      <form method="post" action="<?= e(wayfairs_url('/logout')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="signout-button" type="submit">Sign out</button></form>
    </div>
  </aside>
  <div class="column app-frame">
    <header class="app-topbar admin-topbar">
      <div class="topbar-context">
        <span class="topbar-status" aria-hidden="true"></span>
        <span><small>Operations console</small><strong><?= e($currentLabel) ?></strong></span>
      </div>
      <div class="topbar-actions">
        <?php require WAYFAIRS_ROOT . '/app/Views/partials/translator.php'; ?>
        <span class="topbar-audit-note"><?= wayfairs_icon('verification') ?> Actions are audited</span>
        <div class="topbar-profile"><span class="avatar"><?= e($initials ?: 'A') ?></span><span><strong><?= e($fullName ?: 'Administrator') ?></strong><small>Administrator</small></span></div>
      </div>
    </header>
    <header class="mobile-appbar admin-mobilebar">
      <a class="brand" href="<?= e(wayfairs_url('/admin')) ?>"><span class="mark"><?= e(mb_substr($name, 0, 1)) ?></span><span><strong><?= e($name) ?></strong><small>Admin</small></span></a>
      <details class="mobile-app-menu">
        <summary aria-label="Open admin navigation"><?= wayfairs_icon('menu') ?><span>Menu</span></summary>
        <div class="mobile-menu-panel">
          <div class="user-chip"><span class="avatar"><?= e($initials ?: 'A') ?></span><span><strong><?= e($fullName ?: 'Administrator') ?></strong><small>Administrator</small></span></div>
          <nav aria-label="Mobile administration navigation">
            <?php foreach ($links as $link): ?>
              <a class="side-link<?= wayfairs_nav_active($link['path']) ? ' active' : '' ?>" href="<?= e(wayfairs_url($link['path'])) ?>"><?= wayfairs_icon($link['icon']) ?><span><?= e($link['label']) ?></span></a>
            <?php endforeach; ?>
          </nav>
          <?php require WAYFAIRS_ROOT . '/app/Views/partials/translator.php'; ?>
          <form method="post" action="<?= e(wayfairs_url('/logout')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn ghost full" type="submit">Sign out</button></form>
        </div>
      </details>
    </header>
    <?php if ($flash): ?><div class="flash-wrap"><div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div></div><?php endif; ?>
    <main id="main-content" class="content app-content"><?= $content ?></main>
    <?php require WAYFAIRS_ROOT . '/app/Views/partials/footer.php'; ?>
  </div>
  <?php require WAYFAIRS_ROOT . '/app/Views/partials/ui-scripts.php'; ?>
</body>
</html>
