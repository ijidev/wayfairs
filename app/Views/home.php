<?php

declare(strict_types=1);

$site = $settings ?? [];
$name = $site['site.name'] ?? 'Wayfairs';
$supportEmail = $site['site.support_email'] ?? '';
$assets = $assets ?? [];
$registerUrl = wayfairs_url('/register');
$loginUrl = wayfairs_url('/login');
?>
<section class="hero">
  <div class="hero-content">
    <p class="eyebrow"><?= wayfairs_icon('globe', 'eyebrow-icon') ?> International money, in one place</p>
    <h1>Your money. Multiple currencies. One place.</h1>
    <p class="lead">Hold and manage balances in more than one currency, receive and send payments at home or abroad, and keep your personal and business finances together — with a clear record of every movement.</p>
    <div class="hero-actions">
      <a class="btn large" href="<?= e($registerUrl) ?>">Open an account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a>
      <a class="btn large ghost" href="<?= e($loginUrl) ?>">Sign in</a>
    </div>
    <div class="trust-row">
      <span><?= wayfairs_icon('check') ?> Multi-currency accounts</span>
      <span><?= wayfairs_icon('check') ?> International bank transfers</span>
      <span><?= wayfairs_icon('check') ?> Protected payment details</span>
      <span><?= wayfairs_icon('check') ?> A receipt for every movement</span>
    </div>
  </div>

  <div class="hero-visual">
    <div class="hero-card hero-panel">
      <p class="hero-panel-label">One account, four essentials</p>
      <div class="hero-pillar">
        <span class="hero-pillar-icon"><?= wayfairs_icon('wallet') ?></span>
        <div><strong>Balances side by side</strong><span>Fiat and digital-asset accounts in a single view, with USD equivalents.</span></div>
      </div>
      <div class="hero-pillar">
        <span class="hero-pillar-icon"><?= wayfairs_icon('transfer') ?></span>
        <div><strong>Payments in every direction</strong><span>Receive into an account, pay a beneficiary's bank, or send to a Wayfairs customer.</span></div>
      </div>
      <div class="hero-pillar">
        <span class="hero-pillar-icon"><?= wayfairs_icon('invoice') ?></span>
        <div><strong>Invoices that get paid</strong><span>Request payment with a due date and share a code your payer can settle.</span></div>
      </div>
      <div class="hero-pillar">
        <span class="hero-pillar-icon"><?= wayfairs_icon('security') ?></span>
        <div><strong>Details kept private</strong><span>Bank and wallet details are stored encrypted and shown as last-four summaries.</span></div>
      </div>
      <?php if ($assets !== []): ?>
        <div class="hero-panel-foot">
          <small>Accounts available</small>
          <div class="hero-currency-list" aria-label="Account currencies">
            <?php foreach ($assets as $asset): ?>
              <span class="currency-chip"><?= e((string) $asset['code']) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="features" class="section">
  <div class="section-head">
    <div>
      <p class="eyebrow">Multi-currency money management</p>
      <h2>Hold more than one currency, without the juggling</h2>
    </div>
    <p>Open an account for each currency you need and see them together. Money never mixes between accounts, and every balance is easy to compare.</p>
  </div>
  <div class="grid three">
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('wallet') ?></div>
      <h3>A separate account per currency</h3>
      <p>Each currency gets its own account, giving you a clean balance and its own account details for receiving money.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('activity') ?></div>
      <h3>Everything compared in USD</h3>
      <p>USD equivalents sit beside every balance and payment, so you always know what your money is worth at a glance.</p>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('layers') ?></div>
      <h3>Fiat and digital assets together</h3>
      <p>Keep traditional currency accounts and supported digital-asset accounts under the same login, not in separate apps.</p>
    </article>
  </div>
</section>

<section id="payments" class="section section-tint">
  <div class="payments-grid">
    <div class="payments-copy">
      <p class="eyebrow">Receive and send payments</p>
      <h2>Money in, money out, wherever you are</h2>
      <p class="muted">Take payment into the account that suits you, then move it on with the details your recipient's bank expects — or send it to another Wayfairs customer in seconds.</p>
      <div class="copy-list">
        <div class="copy-item">
          <span class="copy-icon"><?= wayfairs_icon('deposit') ?></span>
          <div><strong>Receive into the right currency</strong><p>Use the account details and deposit instructions shown for each currency account.</p></div>
        </div>
        <div class="copy-item">
          <span class="copy-icon"><?= wayfairs_icon('globe') ?></span>
          <div><strong>Pay a beneficiary abroad</strong><p>Send an international bank transfer using an IBAN or a local account number, with SWIFT and routing details where they apply.</p></div>
        </div>
        <div class="copy-item">
          <span class="copy-icon"><?= wayfairs_icon('transfer') ?></span>
          <div><strong>Send to another customer</strong><p>Pay anyone on Wayfairs by email address. The money arrives immediately, with a receipt for both sides.</p></div>
        </div>
      </div>
    </div>
    <div class="card journey-card">
      <p class="eyebrow">The payment journey</p>
      <h3>Nothing leaves you guessing</h3>
      <ol class="journey">
        <li><span class="journey-mark">1</span><div><strong>Start from the account you want</strong><p>Choose the currency, enter the amount, and see the fee and USD equivalent before you confirm.</p></div></li>
        <li><span class="journey-mark">2</span><div><strong>Add the recipient details</strong><p>Only the fields that fit the payment type appear, so you never wonder which number goes where.</p></div></li>
        <li><span class="journey-mark">3</span><div><strong>Follow it to the end</strong><p>Each payment keeps a clear status and a receipt you can return to at any time.</p></div></li>
      </ol>
    </div>
  </div>
</section>

<section id="business" class="section">
  <div class="section-head">
    <div>
      <p class="eyebrow">Personal and business</p>
      <h2>One place for the money you live on and the money you earn</h2>
    </div>
    <p>Use the same account for everyday personal money and for business payments. There is no second login and no separate app to reconcile at the end of the month.</p>
  </div>
  <div class="grid two">
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('user') ?></div>
      <h3>For everyday life</h3>
      <p>Keep day-to-day balances in the currencies you use, pay people you know, and withdraw to your own bank or wallet when you need to.</p>
      <ul class="tick-list">
        <li><?= wayfairs_icon('check') ?> Transfers to other Wayfairs customers</li>
        <li><?= wayfairs_icon('check') ?> Withdrawals to your own bank account</li>
        <li><?= wayfairs_icon('check') ?> Full history with a receipt for each movement</li>
      </ul>
    </article>
    <article class="card feature">
      <div class="feature-icon"><?= wayfairs_icon('building') ?></div>
      <h3>For business</h3>
      <p>Invoice clients, collect payment in the currency of the account, and keep payment references with the transaction so records stay tidy.</p>
      <ul class="tick-list">
        <li><?= wayfairs_icon('check') ?> Invoices and payment requests</li>
        <li><?= wayfairs_icon('check') ?> Payment references and remarks</li>
        <li><?= wayfairs_icon('check') ?> One record across currencies</li>
      </ul>
    </article>
  </div>
</section>

<section id="invoices" class="section section-tint">
  <div class="payments-grid reverse">
    <div class="card journey-card">
      <p class="eyebrow">Invoices and payment requests</p>
      <h3>Ask for payment, then watch it arrive</h3>
      <ol class="journey">
        <li><span class="journey-mark">1</span><div><strong>Create the request</strong><p>Set the amount, currency, and due date, and add a note describing the work.</p></div></li>
        <li><span class="journey-mark">2</span><div><strong>Share the code</strong><p>Send your payer the invoice reference so the payment finds the right request.</p></div></li>
        <li><span class="journey-mark">3</span><div><strong>Keep the record</strong><p>Paid, unpaid, and pending requests stay together with the matching transaction and receipt.</p></div></li>
      </ol>
    </div>
    <div class="payments-copy">
      <p class="eyebrow">Get paid without chasing</p>
      <h2>Invoices that live next to your money</h2>
      <p class="muted">Bill a client in the currency you hold, share one reference, and let the payment land in the same account you use every day. No spreadsheets, no copy-pasting bank details.</p>
      <p class="muted">Because the request, the payment, and the receipt are connected, month-end stops being a reconstruction exercise.</p>
      <div class="hero-actions">
        <a class="btn" href="<?= e($registerUrl) ?>">Open an account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a>
      </div>
    </div>
  </div>
</section>

<section id="security" class="section">
  <div class="security-grid">
    <div>
      <p class="eyebrow">Security and trust</p>
      <h2>Your account is yours. It stays that way.</h2>
      <p class="muted">Protection is built into how the account works, not bolted on afterwards. You are asked to verify who you are before money moves, and your payment details are never on display.</p>
      <div class="security-list" style="margin-top:1.5rem">
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Verified accounts only</strong><p>Identity verification is required before deposits, transfers, and withdrawals, keeping the network accountable.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Payment details kept private</strong><p>Bank and wallet details are stored encrypted. Your history shows only safe labels and the last four digits.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>Sessions that do not linger</strong><p>Sign-in sessions expire automatically, and every money movement requires a fresh, verified request.</p></div>
        </div>
        <div class="security-item">
          <span class="check"><?= wayfairs_icon('check') ?></span>
          <div><strong>A record you can rely on</strong><p>Every balance change is recorded with a status and a receipt, so your history matches what actually happened.</p></div>
        </div>
      </div>
    </div>
    <div class="card trust-card">
      <p class="eyebrow">Clear by design</p>
      <h3>What you see is what happened</h3>
      <p class="muted">Payment statuses are honest states, not decoration. When a transfer is submitted you see it as pending; when it completes, the receipt shows the amount, the currency, and the reference.</p>
      <div class="trust-points">
        <div><strong>Pending</strong><span>Submitted and awaiting settlement</span></div>
        <div><strong>Completed</strong><span>Settled and recorded on your account</span></div>
        <div><strong>Rejected</strong><span>Returned to you with the reason shown</span></div>
      </div>
      <p class="hint">Availability of currencies, limits, and account features depends on the settings enabled for your account.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="cta-band">
    <p class="eyebrow" style="color:rgba(255,255,255,.7)">Get started</p>
    <h2>Open your <?= e($name) ?> account</h2>
    <p>Bring your balances, payments, and invoices into one clear view — and keep every record in the same place.</p>
    <div class="hero-actions" style="justify-content:center;margin-top:.5rem">
      <a class="btn large" href="<?= e($registerUrl) ?>">Open an account <?= wayfairs_icon('arrow-right', 'btn-icon') ?></a>
      <a class="btn large ghost" href="<?= e($loginUrl) ?>">Sign in</a>
    </div>
    <?php if ($supportEmail !== ''): ?>
      <p style="margin-top:1rem;font-size:.85rem;color:rgba(255,255,255,.7)">Questions? <a href="mailto:<?= e($supportEmail) ?>" style="color:#fff;text-decoration:underline"><?= e($supportEmail) ?></a></p>
    <?php endif; ?>
  </div>
</section>
