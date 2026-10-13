<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['assistant']);
$clinicId = (int) $context['clinic_id'];
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
$dateParams = [$rangeStart, $rangeEnd, $clinicId];

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM appointments WHERE clinic_id = ? AND appointment_date >= ? AND appointment_date < ? GROUP BY status");
$stmt->execute([$clinicId, $rangeStart, $rangeEnd]);
$appointmentStatus = $stmt->fetchAll();
$appointmentCount = array_sum(array_column($appointmentStatus, 'total'));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE clinic_id = ? AND created_at >= ? AND created_at < ?");
$stmt->execute([$clinicId, $rangeStart, $rangeEnd]);
$newPatients = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT s.service_name, COUNT(*) AS total FROM appointments a INNER JOIN services s ON s.service_id = a.service_id WHERE a.clinic_id = ? AND a.status = 'completed' AND a.appointment_date >= ? AND a.appointment_date < ? GROUP BY s.service_id, s.service_name ORDER BY total DESC LIMIT 10");
$stmt->execute([$clinicId, $rangeStart, $rangeEnd]);
$treatmentSummary = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM clinic_payments WHERE clinic_id = ? AND payment_status = 'paid' AND paid_at >= ? AND paid_at < ?");
$stmt->execute([$clinicId, $rangeStart, $rangeEnd]);
$collected = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(i.amount), 0) AS billed, COALESCE(SUM(GREATEST(i.amount - COALESCE(paid.total_paid, 0), 0)), 0) AS outstanding FROM clinic_invoices i LEFT JOIN (SELECT invoice_id, SUM(amount) AS total_paid FROM clinic_payments WHERE payment_status = 'paid' AND invoice_id IS NOT NULL GROUP BY invoice_id) paid ON paid.invoice_id = i.invoice_id WHERE i.clinic_id = ? AND i.status <> 'void' AND i.created_at >= ? AND i.created_at < ?");
$stmt->execute([$clinicId, $rangeStart, $rangeEnd]);
$billing = $stmt->fetch();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'payments', 'label' => 'Payments', 'href' => 'payments.php', 'icon' => 'fa-cash-register'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'fa-envelope'],
    ['key' => 'activity', 'label' => 'Activity log', 'href' => 'activity.php', 'icon' => 'fa-clipboard-list'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fa-chart-line'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Reports', 'reports', $navigation);
?>
<header class="top-bar"><div class="page-title"><h2>Reports</h2><p>Clinic performance</p></div></header>
<section class="card"><form method="get" class="actions"><div class="form-field"><label for="from">From</label><input id="from" name="from" type="date" value="<?php echo clinicPortalEscape($startDate->format('Y-m-d')); ?>" required></div><div class="form-field"><label for="to">To</label><input id="to" name="to" type="date" value="<?php echo clinicPortalEscape($endDate->format('Y-m-d')); ?>" required></div><button class="button" type="submit"><i class="fas fa-filter"></i> Apply</button></form></section>
<section class="content-grid"><div class="stat-card"><div><strong><?php echo $appointmentCount; ?></strong><span>Appointments in range</span></div><i class="fas fa-calendar-alt"></i></div><div class="stat-card"><div><strong><?php echo $newPatients; ?></strong><span>New clinic patients</span></div><i class="fas fa-user-plus"></i></div><div class="stat-card"><div><strong>₱<?php echo number_format($collected, 2); ?></strong><span>Collected payments</span></div><i class="fas fa-cash-register"></i></div><div class="stat-card"><div><strong>₱<?php echo number_format((float) $billing['outstanding'], 2); ?></strong><span>Unpaid invoice balance</span></div><i class="fas fa-file-invoice-dollar"></i></div></section>
<section class="content-grid"><section class="card"><h3>Appointments by status</h3><?php if (!$appointmentStatus): ?><p class="empty-state">No bookings in this date range.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Status</th><th>Appointments</th></tr></thead><tbody><?php foreach ($appointmentStatus as $row): ?><tr><td><?php echo clinicPortalEscape($row['status']); ?></td><td><?php echo (int) $row['total']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="card"><h3>Completed services</h3><?php if (!$treatmentSummary): ?><p class="empty-state">No completed services in this date range.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Service</th><th>Completed</th></tr></thead><tbody><?php foreach ($treatmentSummary as $row): ?><tr><td><?php echo clinicPortalEscape($row['service_name']); ?></td><td><?php echo (int) $row['total']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></section>
<?php clinicPortalEnd(); ?>
