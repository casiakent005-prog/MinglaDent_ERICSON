<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();
$isGlobalAdmin = $context['portal_role'] === 'admin';
$clinicId = $isGlobalAdmin ? 0 : (int) ($context['clinic_id'] ?? 0);

$startInput = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$endInput = $_GET['to'] ?? date('Y-m-d');
$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
$endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
if (!$startDate || $startDate->format('Y-m-d') !== $startInput) {
    $startDate = new DateTimeImmutable('-30 days');
}
if (!$endDate || $endDate->format('Y-m-d') !== $endInput || $endDate < $startDate) {
    $endDate = new DateTimeImmutable('today');
}

$query = "SELECT l.activity_id, l.action, l.entity_type, l.entity_id, l.created_at, u.name AS actor_name, c.clinic_name FROM activity_logs l LEFT JOIN auth_users u ON u.id = l.actor_user_id LEFT JOIN clinics c ON c.clinic_id = l.clinic_id WHERE l.created_at >= ? AND l.created_at < ?";
$params = [$startDate->format('Y-m-d 00:00:00'), $endDate->modify('+1 day')->format('Y-m-d 00:00:00')];
if (!$isGlobalAdmin) {
    $query .= ' AND l.clinic_id = ?';
    $params[] = $clinicId;
}
$query .= ' ORDER BY l.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

clinicAdminStart($context, 'Activity Log', 'activity');
?>

<section class="card"><form method="get" class="actions"><div class="form-field"><label for="from">From</label><input id="from" name="from" type="date" value="<?php echo clinicPortalEscape($startDate->format('Y-m-d')); ?>" required></div><div class="form-field"><label for="to">To</label><input id="to" name="to" type="date" value="<?php echo clinicPortalEscape($endDate->format('Y-m-d')); ?>" required></div><button class="button" type="submit"><i class="fas fa-filter"></i> Filter</button></form></section>
<section class="card"><h3>Latest events (up to 200)</h3><?php if (!$activities): ?><p class="empty-state">No activity in this date range.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Date/time</th><th>User</th><?php if ($isGlobalAdmin): ?><th>Clinic</th><?php endif; ?><th>Action</th><th>Record</th></tr></thead><tbody><?php foreach ($activities as $activity): ?><tr><td><?php echo clinicPortalEscape(date('M j, Y g:i A', strtotime($activity['created_at']))); ?></td><td><?php echo clinicPortalEscape($activity['actor_name'] ?: 'System'); ?></td><?php if ($isGlobalAdmin): ?><td><?php echo clinicPortalEscape($activity['clinic_name'] ?: 'Platform'); ?></td><?php endif; ?><td><?php echo clinicPortalEscape($activity['action']); ?></td><td><?php echo clinicPortalEscape($activity['entity_type']); ?> #<?php echo (int) $activity['entity_id']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<p class="empty-state">The activity log records event type and record ID only; it does not store diagnosis text or contact details.</p>
<?php clinicAdminEnd(); ?>