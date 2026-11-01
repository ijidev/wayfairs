<?php

declare(strict_types=1);

$kycStatus = (string) ($kyc['status'] ?? 'not_started');
$verified = $kycStatus === 'verified';
$cabins = ['economy' => 'Economy', 'premium_economy' => 'Premium economy', 'business' => 'Business', 'first' => 'First'];
$statusLabels = ['requested' => 'Request received', 'contacted' => 'In touch with you', 'booked' => 'Booked', 'declined' => 'Not available', 'cancelled' => 'Cancelled'];
$today = gmdate('Y-m-d');
$disabled = $verified ? '' : 'disabled';
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('globe') ?></span><div class="page-heading-copy"><span class="page-kicker">Utilities</span><h1>Book a flight</h1><p>Describe the trip you need. We confirm availability and price with you before anything is booked.</p></div></div>
  <div class="page-actions"><?= wayfairs_pill('kyc ' . $kycStatus) ?><a class="btn ghost" href="<?= e(wayfairs_url('/utilities')) ?>">Utilities</a></div>
</header>

<?php if (!$verified): ?><div class="notice warn"><?= wayfairs_icon('verification') ?><div><strong>Verification is required</strong><p>Flight requests unlock once your identity is verified.</p><p><a class="btn small" href="<?= e(wayfairs_url('/kyc')) ?>">Go to verification</a></p></div></div><?php endif; ?>

<section class="content-grid">
  <article class="card form-card">
    <div class="form-intro"><span class="page-kicker">New request</span><h2>Trip details</h2><p>Nothing is booked or charged when you send this request. We reply with the options and the price.</p></div>
    <form class="stack" method="post" action="<?= e(wayfairs_url('/flights')) ?>">
      <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
      <input type="hidden" name="idem" value="<?= e(wayfairs_idem()) ?>" />
      <div class="form-grid">
        <label>From<input name="origin" maxlength="120" placeholder="City or airport" required <?= $disabled ?> /></label>
        <label>To<input name="destination" maxlength="120" placeholder="City or airport" required <?= $disabled ?> /></label>
        <label>Departure date<input type="date" name="departure_date" min="<?= e($today) ?>" required <?= $disabled ?> /></label>
        <label>Return date <span class="field-hint">Optional</span><input type="date" name="return_date" min="<?= e($today) ?>" <?= $disabled ?> /></label>
        <label>Travellers<select name="travellers" required <?= $disabled ?>><?php for ($count = 1; $count <= 9; $count++): ?><option value="<?= $count ?>"><?= $count ?></option><?php endfor; ?></select></label>
        <label>Cabin<select name="cabin" required <?= $disabled ?>><?php foreach ($cabins as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
      </div>
      <label>Notes <span class="field-hint">Optional</span><textarea name="notes" rows="3" maxlength="500" placeholder="Preferred times, flexibility, or anything else we should know" <?= $disabled ?>></textarea></label>
      <div class="form-actions"><button class="btn full" type="submit" <?= $disabled ?>><?= $verified ? 'Send request' : 'Verification required' ?> <?= wayfairs_icon('arrow-right', 'btn-icon') ?></button></div>
    </form>
  </article>

  <aside class="stack-list sticky-panel">
    <article class="card static transfer-status-card">
      <div class="panel-head"><div><h2>What happens next</h2><p>Each request is reviewed by hand.</p></div></div>
      <div class="process-steps">
        <div class="process-step"><div><strong>We review the request</strong><p>Availability and price depend on the airline at the time.</p></div></div>
        <div class="process-step"><div><strong>We contact you</strong><p>You confirm the option you want before any booking is made.</p></div></div>
        <div class="process-step"><div><strong>Track it here</strong><p>The status of each request is shown below.</p></div></div>
      </div>
    </article>
  </aside>
</section>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Your flight requests</h2><p>Requests you have sent and their current status.</p></div><span class="pill"><?= count($requests) ?> loaded</span></div>
  <?php if ($requests === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('globe') ?></div><h3>No flight requests</h3><p>Requests you send will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Sent</th><th>Trip</th><th>Dates</th><th>Travellers</th><th>Cabin</th><th>Reference</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($requests as $request): ?><tr>
      <td><?= e(wayfairs_datetime((string) $request['created_at'])) ?></td>
      <td><span class="cell-title"><?= e((string) $request['origin']) ?> → <?= e((string) $request['destination']) ?></span><?php if (trim((string) ($request['admin_note'] ?? '')) !== ''): ?><span class="cell-subtitle"><?= e((string) $request['admin_note']) ?></span><?php endif; ?></td>
      <td><?= e((string) $request['departure_date']) ?><?= $request['return_date'] ? ' → ' . e((string) $request['return_date']) : '' ?></td>
      <td><?= (int) $request['travellers'] ?></td>
      <td><?= e(ucwords(str_replace('_', ' ', (string) $request['cabin']))) ?></td>
      <td><?= e((string) $request['public_reference']) ?></td>
      <td><?= e($statusLabels[(string) $request['status']] ?? (string) $request['status']) ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
