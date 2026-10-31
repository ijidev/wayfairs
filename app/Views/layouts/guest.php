<?php

declare(strict_types=1);

$site = wayfairs_public_settings();
$name = $site['site.name'] ?? 'Wayfairs';
$css = file_get_contents(WAYFAIRS_ROOT . '/public/assets/app.css') ?: '';
$flash = \Wayfairs\Core\Flash::consume();
$currentPath = wayfairs_current_path();
$isHome = $currentPath === '/';
$footerMode = 'guest';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="<?= e($site['site.primary_color'] ?? '#0f8b6d') ?>" />
  <meta name="description" content="<?= e($site['site.tagline'] ?? 'Modern banking for people and businesses') ?>" />
  <title><?= e($name) ?><?= $isHome ? '' : ' · Secure banking' ?></title>
  <style><?= $css ?></style>
  <style>:root{--brand:<?= e($site['site.primary_color'] ?? '#0f8b6d') ?>;--accent:<?= e($site['site.accent_color'] ?? '#f0b429') ?>}</style>
</head>
<body class="guest <?= $isHome ? 'marketing-page' : 'auth-guest' ?>">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <header class="site-header">
    <div class="guest-top">
      <a class="brand brand-large" href="<?= e(wayfairs_url('/')) ?>" aria-label="<?= e($name) ?> home">
        <span class="mark"><?= e(mb_substr($name, 0, 1)) ?></span>
        <span class="brand-copy"><strong><?= e($name) ?></strong><small>Everyday banking, made clear</small></span>
      </a>
      <nav class="guest-nav" aria-label="Main navigation">
        <a href="<?= e(wayfairs_url('/#features')) ?>">Features</a>
        <a href="<?= e(wayfairs_url('/#how-it-works')) ?>">How it works</a>
        <a href="<?= e(wayfairs_url('/#security')) ?>">Security</a>
        <a href="<?= e(wayfairs_url('/login')) ?>">Sign in</a>
        <?php require WAYFAIRS_ROOT . '/app/Views/partials/translator.php'; ?>
        <a class="btn btn-compact" href="<?= e(wayfairs_url('/register')) ?>">Open an account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a>
      </nav>
      <details class="guest-mobile-menu">
        <summary aria-label="Open navigation"><?= wayfairs_icon('menu') ?><span>Menu</span></summary>
        <nav aria-label="Mobile navigation">
          <a href="<?= e(wayfairs_url('/#features')) ?>">Features</a>
          <a href="<?= e(wayfairs_url('/#how-it-works')) ?>">How it works</a>
          <a href="<?= e(wayfairs_url('/#security')) ?>">Security</a>
          <a href="<?= e(wayfairs_url('/login')) ?>">Sign in</a>
          <?php require WAYFAIRS_ROOT . '/app/Views/partials/translator.php'; ?>
          <a class="btn" href="<?= e(wayfairs_url('/register')) ?>">Open an account</a>
        </nav>
      </details>
    </div>
  </header>
  <?php if ($flash): ?>
    <div class="flash-wrap"><div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div></div>
  <?php endif; ?>
  <main id="main-content" class="guest-main <?= $isHome ? 'marketing-main' : 'auth-main' ?>"><?= $content ?></main>
  <?php require WAYFAIRS_ROOT . '/app/Views/partials/footer.php'; ?>
  <?php require WAYFAIRS_ROOT . '/app/Views/partials/ui-scripts.php'; ?>
</body>
</html>
