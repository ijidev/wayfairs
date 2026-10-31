<?php

declare(strict_types=1);

use Wayfairs\Core\Context;

$site = wayfairs_public_settings();
$name = $site['site.name'] ?? 'Wayfairs';
$css = file_get_contents(WAYFAIRS_ROOT . '/public/assets/app.css') ?: '';
$flash = \Wayfairs\Core\Flash::consume();
$user = Context::user() ?? [];
$impersonating = Context::isImpersonating();
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = mb_strtoupper(mb_substr((string) ($user['first_name'] ?? 'U'), 0, 1) . mb_substr((string) ($user['last_name'] ?? ''), 0, 1));
$links = [
    ['path' => '/dashboard', 'label' => 'Overview', 'icon' => 'overview'],
    ['path' => '/transactions', 'label' => 'Transactions', 'icon' => 'activity'],
    ['path' => '/deposit', 'label' => 'Deposit', 'icon' => 'deposit'],
    ['path' => '/withdraw', 'label' => 'Withdraw', 'icon' => 'withdraw'],
    ['path' => '/transfer', 'label' => 'Transfer', 'icon' => 'transfer'],
    ['path' => '/invoices', 'label' => 'Invoices', 'icon' => 'invoice'],
    ['path' => '/kyc', 'label' => 'Verification', 'icon' => 'verification'],
    ['path' => '/security', 'label' => 'Security', 'icon' => 'security'],
];
$currentLabel = 'Overview';
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
  <meta name="theme-color" content="<?= e($site['site.primary_color'] ?? '#0f8b6d') ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?= e($name) ?> · Banking</title>
  <style><?= $css ?></style>
  <style>:root{--brand:<?= e($site['site.primary_color'] ?? '#0f8b6d') ?>;--accent:<?= e($site['site.accent_color'] ?? '#f0b429') ?>}</style>
</head>
<body class="app app-shell">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <aside class="side app-sidebar">
    <a class="brand sidebar-brand" href="<?= e(wayfairs_url('/dashboard')) ?>">
      <span class="mark"><?= e(mb_substr($name, 0, 1)) ?></span>
      <span class="brand-copy"><strong><?= e($name) ?></strong><small>Personal banking</small></span>
    </a>
    <div class="side-section">
      <p class="side-label">Your money</p>
      <nav class="side-nav" aria-label="Banking navigation">
        <?php foreach ($links as $link): ?>
          <a class="side-link<?= wayfairs_nav_active($link['path']) ? ' active' : '' ?>" href="<?= e(wayfairs_url($link['path'])) ?>"<?= wayfairs_nav_active($link['path']) ? ' aria-current="page"' : '' ?>>
            <?= wayfairs_icon($link['icon']) ?><span><?= e($link['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="side-support">
      <?= wayfairs_icon('support') ?>
      <div><strong>Need help?</strong><span><?= e($site['site.support_email'] ?? 'Contact support') ?></span></div>
    </div>
    <div class="side-foot">
      <div class="user-chip">
        <span class="avatar"><?= e($initials ?: 'U') ?></span>
        <span><strong><?= e($fullName ?: 'Account owner') ?></strong><small><?= e((string) ($user['email'] ?? '')) ?></small></span>
      </div>
      <form method="post" action="<?= e(wayfairs_url('/logout')) ?>">
        <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
        <button class="signout-button" type="submit">Sign out</button>
      </form>
    </div>
  </aside>
  <div class="column app-frame">
    <header class="app-topbar">
      <div class="topbar-context">
        <span class="topbar-status" aria-hidden="true"></span>
        <span><small>Personal banking</small><strong><?= e($currentLabel) ?></strong></span>
      </div>
      <div class="topbar-actions">
        <?php require WAYFAIRS_ROOT . '/app/Views/partials/translator.php'; ?>
        <span class="topbar-date"><?= e(gmdate('D, M j')) ?> · UTC</span>
        <div class="topbar-profile"><span class="avatar"><?= e($initials ?: 'U') ?></span><span><strong><?= e($fullName ?: 'Account owner') ?></strong><small><?= e((string) ($user['email'] ?? '')) ?></small></span></div>
      </div>
    </header>
    <header class="mobile-appbar">
      <a class="brand" href="<?= e(wayfairs_url('/dashboard')) ?>"><span class="mark"><?= e(mb_substr($name, 0, 1)) ?></span><strong><?= e($name) ?></strong></a>
      <details class="mobile-app-menu">
        <summary aria-label="Open account navigation"><?= wayfairs_icon('menu') ?><span>Menu</span></summary>
        <div class="mobile-menu-panel">
          <div class="user-chip"><span class="avatar"><?= e($initials ?: 'U') ?></span><span><strong><?= e($fullName ?: 'Account owner') ?></strong><small><?= e((string) ($user['email'] ?? '')) ?></small></span></div>
          <nav aria-label="Mobile banking navigation">
            <?php foreach ($links as $link): ?>
              <a class="side-link<?= wayfairs_nav_active($link['path']) ? ' active' : '' ?>" href="<?= e(wayfairs_url($link['path'])) ?>"><?= wayfairs_icon($link['icon']) ?><span><?= e($link['label']) ?></span></a>
            <?php endforeach; ?>
          </nav>
          <?php require WAYFAIRS_ROOT . '/app/Views/partials/translator.php'; ?>
          <form method="post" action="<?= e(wayfairs_url('/logout')) ?>"><input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" /><button class="btn ghost full" type="submit">Sign out</button></form>
        </div>
      </details>
    </header>
    <?php if ($impersonating): ?>
      <div class="banner">
        <span><strong>Support view active.</strong> You are viewing <?= e($fullName) ?>'s account. Money movement and security changes are disabled. Reason: <?= e($user['impersonation_reason'] ?? '') ?></span>
        <form method="post" action="<?= e(wayfairs_url('/admin/stop-impersonation')) ?>">
          <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
          <button type="submit">End support view</button>
        </form>
      </div>
    <?php endif; ?>
    <?php if ($flash): ?><div class="flash-wrap"><div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div></div><?php endif; ?>
    <main id="main-content" class="content app-content"><?= $content ?></main>
    <?php require WAYFAIRS_ROOT . '/app/Views/partials/footer.php'; ?>
  </div>
  <?php require WAYFAIRS_ROOT . '/app/Views/partials/ui-scripts.php'; ?>
</body>
</html>
