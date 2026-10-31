<?php

declare(strict_types=1);

$site = $settings ?? [];
$name = $site['site.name'] ?? 'Wayfairs';
$tagline = $site['site.tagline'] ?? 'Modern banking for people and businesses.';
$primary = $site['site.primary_color'] ?? '#0f8b6d';
$supportEmail = $site['site.support_email'] ?? '';
$footer = $site['site.footer'] ?? 'Clear, accountable banking for everyday money movement.';
$assets = $assets ?? [];
$assetCount = count($assets);
$fiatCount = count(array_filter($assets, static fn (array $asset): bool => $asset['asset_type'] === 'fiat'));
$cryptoCount = $assetCount - $fiatCount;
?>
<section class="hero">
  <div class="hero-content">
    <p class="eyebrow"><?= wayfairs_icon('globe', 'eyebrow-icon') ?> A better way to bank</p>
    <h1><?= e($tagline) ?></h1>
    <p class="lead">Hold multiple currencies, move money to people you trust, request and pay invoices, and keep a complete, auditable record of every transaction — all in one secure place.</p>
    <div class="hero-actions">
      <a class="btn large" href="<?= e(wayfairs_url('/register')) ?>">Open an account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a>
      <a class="btn large ghost" href="<?= e(wayfairs_url('/login')) ?>">Sign in</a>
    </div>
    <div class="trust-row">
      <span><?= wayfairs_icon('check') ?> Identity-verified accounts</span>
      <span><?= wayfairs_icon('check') ?> Balanced double-entry ledger</span>
      <span><?= wayfairs_icon('check') ?> Manual settlement transparency</span>
      <span><?= wayfairs_icon('check') ?> Short-lived support access</span>
    </div>
  </div>
  <div class="hero-visual">
    <div class="hero-card">
      <div class="hero-product">
        <span class="hero-mark"><?= e(mb_substr($name, 0, 1)) ?></span>
        <div>
          <strong>Manual settlement, clearly shown</strong>
          <small>Every movement has a visible state and an audit trail</small>
        </div>
      </div>
      <div class="hero-flow">
        <div class="flow-step">
          <span class="flow-number">1</span>
          <div><strong>You submit a request</strong><span>The amount, reference, and destination are stored immediately.</span></div>
        </div>
        <div class="flow-step">
          <span class="flow-number">2</span>
          <div><strong>An administrator verifies it</strong><span>Receipt or completion is checked against the external record.</span></div>
        </div>
        <div class="flow-step">
          <span class="flow-number">3</span>
          <div><strong>The ledger records the movement</strong><span>Balanced double-entry postings and a receipt you can keep.</span></div>
        </div>
      </div>
      <?php if ($assets !== []): ?>
        <p class="hint" style="color:rgba(255,255,255,.72);font-size:.78rem;margin:.95rem 0 0;position:relative;z-index:1">Currently enabled: <?= $assetCount ?> account<?= $assetCount === 1 ? '' : 's' ?> · <?= $fiatCount ?> fiat · <?= $cryptoCount ?> crypto</p>
        <div class="hero-currency-list" aria-label="Enabled currencies and assets">
          <?php foreach ($assets as $asset): ?>
            <span class="currency-chip"><?= e((string) $asset['code']) ?> <em><?= e(ucfirst((string) $asset['asset_type'])) ?></em></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="hero-note">No invented provider states. Balances move only after an administrator records observed receipt or completion.</p>
    </div>
  </div>
</section>

<section id="features" class="section">
  <div class="section-head">
    <div><p class="eyebrow">Features</p><h2>Everything you need to run your money</h2></div>
    <p>Designed for individuals and small businesses who want clarity, control, and an honest record of every movement.</p>
  </div>
  <div class="grid three">
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('wallet') ?></div>
      <h3>Multi-currency accounts</h3>
      <p>Hold every fiat currency and digital asset your bank enables. Each one has its own account number and double-entry ledger.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('deposit') ?></div>
      <h3>Deposits & withdrawals</h3>
      <p>Request deposits and withdrawals with clear pending, reserved, completed, and rejected states. Nothing moves until it is verified.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('transfer') ?></div>
      <h3>Instant transfers</h3>
      <p>Send money to any Wayfairs customer by email. Transfers settle immediately and arrive with a receipt on both sides.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('invoice') ?></div>
      <h3>Invoices & payments</h3>
      <p>Create payment requests with due dates, share a code, and get paid directly into your account with full audit trail.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('verification') ?></div>
      <h3>Identity verification</h3>
      <p>Complete KYC before moving money. Verification status is enforced server-side, not just hidden in the interface.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('layers') ?></div>
      <h3>Immutable ledger</h3>
      <p>Every balance change is a balanced double-entry journal entry. You can open a receipt for any transaction at any time.</p>
    </article>
  </div>
</section>

<section id="how-it-works" class="section" style="background:var(--card-2);border-radius:var(--radius-xl);padding:clamp(2.5rem,6vw,4rem) clamp(1rem,4vw,2rem)">
  <div class="container">
    <div class="section-head">
      <div><p class="eyebrow">How it works</p><h2>Open, verify, and start moving money</h2></div>
      <p>Three steps from sign-up to your first transfer. No paperwork, no waiting on hold.</p>
    </div>
    <div class="grid three">
      <article class="card step-card">
        <span class="step-number">01</span>
        <h3>Create your account</h3>
        <p>Sign up with your legal name and a strong password. Your accounts for every supported currency are opened automatically.</p>
      </article>
      <article class="card step-card">
        <span class="step-number">02</span>
        <h3>Verify your identity</h3>
        <p>Submit your date of birth, address, and ID details. An administrator reviews and approves before any money movement is allowed.</p>
      </article>
      <article class="card step-card">
        <span class="step-number">03</span>
        <h3>Deposit and transact</h3>
        <p>Request a deposit, have it verified against your bank transfer, then transfer, withdraw, invoice, and reconcile with receipts.</p>
      </article>
    </div>
  </div>
</section>

<section id="security" class="section">
  <div class="security-grid">
    <div>
      <p class="eyebrow">Security</p>
      <h2>Built to be accountable, not just convenient</h2>
      <p class="muted">Security is enforced on the server before any money moves. Every action is recorded, every balance is reconciled, and every support session is visible.</p>
      <div class="security-list" style="margin-top:1.5rem">
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Server-side enforcement</strong><p>KYC, account status, limits, and capability restrictions are checked before holds or postings — never only hidden in the interface.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Immutable double-entry ledger</strong><p>Every balance change is a balanced journal entry. Balances are computed from postings, never from a mutable column.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Audited support access</strong><p>Admin impersonation requires a password and reason, lasts 30 minutes, shows a persistent banner, and blocks financial mutations.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Encrypted destination data</strong><p>Bank and wallet details are encrypted with Sodium. Logs and browser payloads expose only safe labels and last-four summaries.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>CSRF and session protection</strong><p>Sessions are random, server-stored, HttpOnly, SameSite, and rotate. Every mutating request requires a CSRF token.</p></div>
        </div>
      </div>
    </div>
    <div class="card" style="padding:2rem">
      <p class="eyebrow">Manual settlement</p>
      <h3>Honest about what is connected</h3>
      <p class="muted">Wayfairs does not pretend to be connected to a bank or blockchain. Deposits are credited only after an administrator records observed receipt. Withdrawals are debited only when an administrator records completion and an external reference. Every manual step is visible in the audit log.</p>
      <div class="grid two" style="margin-top:1.25rem">
        <div class="card" style="background:var(--good-soft);border-color:#bbf7d0"><strong style="color:var(--good)">Pending</strong><p class="hint" style="margin:.25rem 0 0">Submitted, awaiting verification</p></div>
        <div class="card" style="background:var(--warn-soft);border-color:#fde68a"><strong style="color:var(--warn)">Reserved</strong><p class="hint" style="margin:.25rem 0 0">Held until completed or released</p></div>
        <div class="card" style="background:var(--brand-soft);border-color:#99e6d9"><strong style="color:var(--brand)">Completed</strong><p class="hint" style="margin:.25rem 0 0">Posted to the ledger</p></div>
        <div class="card" style="background:var(--bad-soft);border-color:#fecaca"><strong style="color:var(--bad)">Rejected</strong><p class="hint" style="margin:.25rem 0 0">Returned with a reason</p></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="cta-band">
    <p class="eyebrow" style="color:rgba(255,255,255,.7)">Get started</p>
    <h2>Open your account in minutes</h2>
    <p>Join Wayfairs and take control of your money with clear records, multi-currency accounts, and honest settlement.</p>
    <div class="hero-actions" style="justify-content:center;margin-top:.5rem">
      <a class="btn large" href="<?= e(wayfairs_url('/register')) ?>">Open an account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a>
      <a class="btn large ghost" href="<?= e(wayfairs_url('/login')) ?>">Sign in</a>
    </div>
    <?php if ($supportEmail !== ''): ?>
      <p style="margin-top:1rem;font-size:.85rem;color:rgba(255,255,255,.7)">Questions? <a href="mailto:<?= e($supportEmail) ?>" style="color:#fff;text-decoration:underline"><?= e($supportEmail) ?></a></p>
    <?php endif; ?>
  </div>
</section>
