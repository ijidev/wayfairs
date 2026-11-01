<?php

declare(strict_types=1);
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('layers') ?></span><div class="page-heading-copy"><span class="page-kicker">Services</span><h1>Utilities</h1><p>Pay bills and request travel from your account.</p></div></div>
</header>

<section class="utility-grid">
  <a class="utility-card" href="<?= e(wayfairs_url('/bills')) ?>">
    <span class="icon-wrap"><?= wayfairs_icon('receipt') ?></span>
    <span class="utility-copy"><strong>Pay a bill</strong><small><?= empty($bankTransfersEnabled) ? 'Bill payments are not available on your account yet.' : 'Send money to a utility, school, or service provider using their bank details and your bill reference.' ?></small></span>
    <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
  </a>
  <a class="utility-card" href="<?= e(wayfairs_url('/flights')) ?>">
    <span class="icon-wrap"><?= wayfairs_icon('globe') ?></span>
    <span class="utility-copy"><strong>Book a flight</strong><small>Tell us where and when you want to travel. We confirm availability and price before anything is booked.</small></span>
    <span class="transaction-arrow"><?= wayfairs_icon('arrow-right') ?></span>
  </a>
</section>
