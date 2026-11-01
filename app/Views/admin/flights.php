<?php

declare(strict_types=1);

$statusLabels = ['requested' => 'Requested', 'contacted' => 'Contacted', 'booked' => 'Booked', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];
$nextStatuses = [
    'requested' => ['contacted', 'declined', 'cancelled'],
    'contacted' => ['booked', 'declined', 'cancelled'],
    'booked' => ['cancelled'],
    'declined' => [],
    'cancelled' => [],
];
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('globe') ?></span><div class="page-heading-copy"><span class="page-kicker">Utilities</span><h1>Flight requests</h1><p>Confirm availability and price with the customer, then record the outcome here.</p></div></div>
</header>

<section class="card table-card static">
  <div class="panel-head"><div><h2>Requests</h2><p>Newest open requests appear first.</p></div><span class="pill"><?= count($requests) ?> loaded</span></div>
  <?php if ($requests === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('globe') ?></div><h3>No flight requests</h3><p>Customer requests will appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Sent</th><th>Customer</th><th>Trip</th><th>Dates</th><th>Travellers</th><th>Cabin</th><th>Notes</th><th>Status</th><th>Update</th></tr></thead><tbody>
    <?php foreach ($requests as $request): $options = $nextStatuses[(string) $request['status']] ?? []; ?><tr>
      <td><?= e(wayfairs_datetime((string) $request['created_at'])) ?><span class="cell-subtitle"><?= e((string) $request['public_reference']) ?></span></td>
      <td><span class="cell-title"><?= e(trim((string) $request['first_name'] . ' ' . (string) $request['last_name'])) ?></span><span class="cell-subtitle"><?= e((string) $request['customer_email']) ?></span></td>
      <td><?= e((string) $request['origin']) ?> → <?= e((string) $request['destination']) ?></td>
      <td><?= e((string) $request['departure_date']) ?><?= $request['return_date'] ? ' → ' . e((string) $request['return_date']) : '' ?></td>
      <td><?= (int) $request['travellers'] ?></td>
      <td><?= e(ucwords(str_replace('_', ' ', (string) $request['cabin']))) ?></td>
      <td class="wrap-anywhere"><?= e((string) ($request['notes'] ?? '') !== '' ? (string) $request['notes'] : '—') ?></td>
      <td><?= wayfairs_pill((string) $request['status']) ?><span class="cell-subtitle"><?= e($statusLabels[(string) $request['status']] ?? '') ?></span></td>
      <td>
        <?php if ($options === []): ?><span class="field-hint">No further changes</span><?php else: ?>
        <form class="decision-form" method="post" action="<?= e(wayfairs_url('/admin/flights/' . $request['id'] . '/status')) ?>">
          <input type="hidden" name="_token" value="<?= e(wayfairs_csrf()) ?>" />
          <select name="status" required><?php foreach ($options as $option): ?><option value="<?= e($option) ?>"><?= e($statusLabels[$option]) ?></option><?php endforeach; ?></select>
          <input name="admin_note" maxlength="500" placeholder="Note for the customer" />
          <button class="btn small" type="submit">Save</button>
        </form>
        <?php endif; ?>
      </td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
