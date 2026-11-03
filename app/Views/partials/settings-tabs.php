<?php

declare(strict_types=1);

$settingsTabs = [
    ['path' => '/admin/settings', 'label' => 'Settings'],
    ['path' => '/admin/assets', 'label' => 'Currencies'],
    ['path' => '/admin/beneficiaries', 'label' => 'Beneficiaries'],
    ['path' => '/admin/flights', 'label' => 'Flight requests'],
    ['path' => '/admin/otp', 'label' => 'Verification codes'],
    ['path' => '/admin/smtp', 'label' => 'Email (SMTP)'],
    ['path' => '/admin/email-templates', 'label' => 'Email templates'],
];
?>
<nav class="settings-tabs" role="tablist" aria-label="Settings sections">
  <?php foreach ($settingsTabs as $tab): ?>
    <a class="settings-tab<?= wayfairs_nav_active($tab['path']) ? ' active' : '' ?>" href="<?= e(wayfairs_url($tab['path'])) ?>" role="tab" aria-selected="<?= wayfairs_nav_active($tab['path']) ? 'true' : 'false' ?>"><?= e($tab['label']) ?></a>
  <?php endforeach; ?>
</nav>
