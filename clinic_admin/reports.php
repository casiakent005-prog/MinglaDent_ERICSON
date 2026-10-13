<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();
$isGlobalAdmin = $context['portal_role'] === 'admin';
$clinicId = $isGlobalAdmin ? 0 : (int) ($context['clinic_id'] ?? 0);

$startInput = $_GET['from'] ?? date('Y-m-01');
$endInput = $_GET['to'] ?? date('Y-m-d');
$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
$endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
if (!$startDate || $startDate->format('Y-m-d') !== $startInput) {
    $startDate = new DateTimeImmutable('first day of this month');
}
if (!$endDate || $endDate->format('Y-m-d') !== $endInput || $endDate < $startDate) {
    $endDate = new DateTimeImmutable('today');
}
$rangeStart = $startDate->format('Y-m-d 00:00:00');
$rangeEnd = $endDate->modify('+1 day')->format('Y-m-d 00:00:00');
$clinicCondition = $clinicId > 0 ? ' AND clinic_id = ?' : '';
$dateParams = [$rangeStart, $rangeEnd];
if ($clinicId > 0) {
    $dateParams[] = $clinicId;
}

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM appointments WHERE appointment_date >= ? AND appointment_date < ?{$clinicCondition} GROUP BY status");
$stmt->execute($dateParams);
$appointmentStatus = $stmt->fetchAll();
$appointmentCount = array_sum(array_column($appointmentStatus, 'total'));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE created_at >= ? AND created_at < ?{$clinicCondition}");
$stmt->execute($dateParams);
$newPatients = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT s.service_name, COUNT(*) AS total FROM appointments a INNER JOIN services s ON s.service_id = a.service_id WHERE a.status = 'completed' AND a.appointment_date >= ? AND a.appointment_date < ?" . ($clinicId > 0 ? ' AND a.clinic_id = ?' : '') . " GROUP BY s.service_id, s.service_name ORDER BY total DESC LIMIT 10");
$stmt->execute($dateParams);
$treatmentSummary = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM clinic_payments WHERE payment_status = 'paid' AND paid_at >= ? AND paid_at < ?" . ($clinicId > 0 ? ' AND clinic_id = ?' : ''));
$stmt->execute($dateParams);
$collected = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(i.amount), 0) AS billed, COALESCE(SUM(GREATEST(i.amount - COALESCE(paid.total_paid, 0), 0)), 0) AS outstanding FROM clinic_invoices i LEFT JOIN (SELECT invoice_id, SUM(amount) AS total_paid FROM clinic_payments WHERE payment_status = 'paid' AND invoice_id IS NOT NULL GROUP BY invoice_id) paid ON paid.invoice_id = i.invoice_id WHERE i.status <> 'void' AND i.created_at >= ? AND i.created_at < ?" . ($clinicId > 0 ? ' AND i.clinic_id = ?' : ''));
$stmt->execute($dateParams);
$billing = $stmt->fetch();

$platform = null;
if ($isGlobalAdmin) {
    $platform = [
        'clinics' => (int) $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn(),
        'users' => (int) $pdo->query("SELECT COUNT(*) FROM auth_users")->fetchColumn(),
        'bookings' => (int) $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    ];
}

clinicAdminStart($context, 'Reports', 'reports');
?>

<section class="card"><form method="get" class="actions"><div class="form-field"><label for="from">From</label><input id="from" name="from" type="date" value="<?php echo clinicPortalEscape($startDate->format('Y-m-d')); ?>" required></div><div class="form-field"><label for="to">To</label><input id="to" name="to" type="date" value="<?php echo clinicPortalEscape($endDate->format('Y-m-d')); ?>" required></div><button class="button" type="submit"><i class="fas fa-filter"></i> Apply</button></form></section>
<?php if ($platform): ?><section class="content-grid"><div class="stat-card"><div><strong><?php echo (int) $platform['clinics']; ?></strong><span>Clinics</span></div><i class="fas fa-hospital"></i></div><div class="stat-card"><div><strong><?php echo (int) $platform['users']; ?></strong><span>Users</span></div><i class="fas fa-users"></i></div><div class="stat-card"><div><strong><?php echo (int) $platform['bookings']; ?></strong><span>Bookings</span></div><i class="fas fa-calendar-check"></i></div></section><?php endif; ?>
<section class="content-grid"><div class="stat-card"><div><strong><?php echo (int) $appointmentCount; ?></strong><span>Appointments in range</span></div><i class="fas fa-calendar-alt"></i></div><div class="stat-card"><div><strong><?php echo (int) $newPatients; ?></strong><span>New clinic patients</span></div><i class="fas fa-user-plus"></i></div><div class="stat-card"><div><strong>₱<?php echo number_format($collected, 2); ?></strong><span>Collected payments</span></div><i class="fas fa-cash-register"></i></div><div class="stat-card"><div><strong>₱<?php echo number_format((float) $billing['outstanding'], 2); ?></strong><span>Unpaid invoice balance</span></div><i class="fas fa-file-invoice-dollar"></i></div></section>
<section class="content-grid"><section class="card"><h3>Appointments by status</h3><?php if (!$appointmentStatus): ?><p class="empty-state">No bookings in this date range.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Status</th><th>Appointments</th></tr></thead><tbody><?php foreach ($appointmentStatus as $row): ?><tr><td><?php echo clinicPortalEscape($row['status']); ?></td><td><?php echo (int) $row['total']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="card"><h3>Completed services</h3><?php if (!$treatmentSummary): ?><p class="empty-state">No completed services in this date range.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Service</th><th>Completed</th></tr></thead><tbody><?php foreach ($treatmentSummary as $row): ?><tr><td><?php echo clinicPortalEscape($row['service_name']); ?></td><td><?php echo (int) $row['total']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></section>
<?php clinicAdminEnd(); ?>