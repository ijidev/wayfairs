<?php

declare(strict_types=1);

$actorEmails = array_values(array_unique(array_filter(array_map(static fn (array $event): string => (string) ($event['actor_email'] ?? ''), $events))));
$impersonationCount = count(array_filter($events, static fn (array $event): bool => str_starts_with((string) $event['action'], 'admin.impersonate')));
$decisionCount = count(array_filter($events, static fn (array $event): bool => str_starts_with((string) $event['action'], 'deposit.') || str_starts_with((string) $event['action'], 'withdrawal.') || str_starts_with((string) $event['action'], 'kyc.')));
?>
<header class="page-header">
  <div class="page-heading"><span class="page-icon"><?= wayfairs_icon('audit') ?></span><div class="page-heading-copy"><span class="page-kicker">Accountability</span><h1>Audit log</h1><p>Immutable operational events showing who acted, on whose account, what changed, and why.</p></div></div>
</header>

<section class="metric-grid">
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('audit') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($events) ?></strong><span class="metric-label">Loaded events</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('customers') ?></span><span class="metric-copy"><strong class="metric-value"><?= count($actorEmails) ?></strong><span class="metric-label">Identified actors</span></span></div>
  <div class="metric-card"><span class="metric-icon"><?= wayfairs_icon('check') ?></span><span class="metric-copy"><strong class="metric-value"><?= $decisionCount ?></strong><span class="metric-label">Money / KYC decisions</span></span></div>
  <div class="metric-card <?= $impersonationCount > 0 ? 'warn' : '' ?>"><span class="metric-icon"><?= wayfairs_icon('security') ?></span><span class="metric-copy"><strong class="metric-value"><?= $impersonationCount ?></strong><span class="metric-label">Support-view events</span></span></div>
</section>

<section class="card table-card static" data-filter-scope>
  <div class="panel-head"><div><h2>Recorded events</h2><p>Up to the latest 300 events, newest first.</p></div></div>
  <?php if ($events === []): ?>
    <div class="empty-state"><div class="empty-icon"><?= wayfairs_icon('audit') ?></div><h3>No audit events</h3><p>No administrative events are currently recorded.</p></div>
  <?php else: ?>
    <div class="filter-bar"><label class="filter-search"><span class="sr-only">Search audit log</span><?= wayfairs_icon('search') ?><input type="search" data-filter-input placeholder="Search actor, action, resource, or reason" /></label></div>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Actor</th><th>Effective customer</th><th>Action</th><th>Resource</th><th>Reason</th></tr></thead><tbody>
    <?php foreach ($events as $event): ?><tr data-filter-row><td><?= e(wayfairs_datetime((string) $event['created_at'])) ?></td><td><span class="cell-title"><?= e((string) ($event['actor_email'] ?? 'System')) ?></span></td><td><?= e((string) ($event['effective_email'] ?? '—')) ?></td><td class="audit-action"><code><?= e((string) $event['action']) ?></code></td><td class="audit-resource"><span class="cell-title"><?= e((string) $event['resource_type']) ?></span><span class="cell-subtitle mono"><?= e((string) ($event['resource_id'] ?? '')) ?></span></td><td class="wrap-anywhere"><?= e(trim((string) ($event['reason'] ?? '')) !== '' ? (string) $event['reason'] : '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="empty-state" data-filter-empty hidden><div class="empty-icon"><?= wayfairs_icon('search') ?></div><h3>No matching events</h3><p>Change the search terms to see other audit rows.</p></div>
  <?php endif; ?>
</section>
