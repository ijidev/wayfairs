<?php

declare(strict_types=1);

$isEdit = $mode === 'edit' && is_array($profile);
$title = $isEdit ? 'Edit customer' : 'Create customer';
$action = $isEdit ? '/admin/users/' . $profile['id'] . '/edit' : '/admin/users';
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon($isEdit ? 'user' : 'plus') ?></span><div class="page-heading-copy"><span class="page-kicker">Customer administration</span><h1><?= e($title) ?></h1><p><?= $isEdit ? 'Update identity and access status while preserving the customer ledger and audit history.' : 'Create a real customer account with administrator-supplied identity and sign-in credentials.' ?></p></div></div>
  <div class="page-actions"><a class="btn ghost" href="<?= e(wayfairs_url($isEdit ? '/admin/users/' . $profile['id'] : '/admin/users')) ?>">Cancel</a></div>
</header>

<section class="content-grid">
  <form class="card form-card" method="post" action="<?= e(wayfairs_url($action)) ?>">
    <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
    <div class="form-intro"><h2>Customer profile</h2><p>Names and email are required. The account role always remains customer.</p></div>
    <div class="form-grid"><label>First name<input name="first_name" maxlength="120" value="<?= e((string) ($profile['first_name'] ?? '')) ?>" autocomplete="given-name" required /></label><label>Last name<input name="last_name" maxlength="120" value="<?= e((string) ($profile['last_name'] ?? '')) ?>" autocomplete="family-name" required /></label></div>
    <div class="form-grid"><label>Email address<input type="email" name="email" maxlength="190" value="<?= e((string) ($profile['email'] ?? '')) ?>" autocomplete="email" required /></label><label>Phone <span class="field-hint">Optional</span><input name="phone" maxlength="40" value="<?= e((string) ($profile['phone'] ?? '')) ?>" autocomplete="tel" /></label></div>
    <label>Account status<select name="status" required><option value="active" <?= ($profile['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active — sign-in and eligible operations</option><option value="pending" <?= ($profile['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending — profile awaiting activation</option><option value="restricted" <?= ($profile['status'] ?? '') === 'restricted' ? 'selected' : '' ?>>Restricted — sign-in allowed, money movement blocked</option><option value="blocked" <?= ($profile['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked — sign-in denied</option><option value="closed" <?= ($profile['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed — sign-in denied, history preserved</option></select></label>
    <?php if (!$isEdit): ?><label>Temporary password<input type="password" name="password" minlength="12" autocomplete="new-password" required /><span class="field-hint">At least 12 characters. The password is hashed and never displayed again.</span></label><?php else: ?><label>Administrator remark <span class="field-hint">Optional</span><input name="admin_remark" maxlength="500" placeholder="Optional audit context for this update" /></label><?php endif; ?>
    <div class="receipt-footnote"><?= wayfairs_icon('audit') ?><span><?= $isEdit ? 'Blocking or closing revokes active sessions. Closing is a reversible status change and never deletes transactions or ledger postings.' : 'Creation is recorded in the audit log. No balance, verification result, or transaction is seeded for the customer.' ?></span></div>
    <div class="form-actions"><button class="btn" type="submit"><?= $isEdit ? 'Save customer changes' : 'Create customer account' ?></button></div>
  </form>

  <aside class="card static sticky-panel">
    <div class="panel-head"><div><h2>Access-state guide</h2><p>Choose the narrowest state that matches the real operation.</p></div></div>
    <div class="status-checklist">
      <div class="status-check"><span class="status-check-icon"><?= wayfairs_icon('check') ?></span><span class="status-check-copy"><strong>Active</strong><span>Normal access, still subject to KYC and capability restrictions.</span></span></div>
      <div class="status-check"><span class="status-check-icon warn"><?= wayfairs_icon('clock') ?></span><span class="status-check-copy"><strong>Restricted</strong><span>Customer can sign in but backend money-movement guards deny execution.</span></span></div>
      <div class="status-check"><span class="status-check-icon bad"><?= wayfairs_icon('security') ?></span><span class="status-check-copy"><strong>Blocked</strong><span>Sign-in is denied and current sessions are revoked.</span></span></div>
      <div class="status-check"><span class="status-check-icon bad"><?= wayfairs_icon('close') ?></span><span class="status-check-copy"><strong>Closed</strong><span>Account access ends while immutable financial history remains.</span></span></div>
    </div>
  </aside>
</section>
