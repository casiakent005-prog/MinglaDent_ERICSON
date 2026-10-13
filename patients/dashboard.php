<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$patient = patientPortalContext();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id WHERE p.user_id = ? AND a.status = 'scheduled' AND a.appointment_date >= NOW()");
$stmt->execute([$patient['user_id']]);
$upcomingCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(i.amount - COALESCE(paid.total_paid, 0)), 0) FROM clinic_invoices i INNER JOIN patients p ON p.patient_id = i.patient_id LEFT JOIN (SELECT invoice_id, SUM(amount) AS total_paid FROM clinic_payments WHERE payment_status = 'paid' AND invoice_id IS NOT NULL GROUP BY invoice_id) paid ON paid.invoice_id = i.invoice_id WHERE p.user_id = ? AND i.status <> 'void'");
$stmt->execute([$patient['user_id']]);
$balance = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id WHERE p.user_id = ?");
$stmt->execute([$patient['user_id']]);
$visitCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT a.appointment_id, a.appointment_date, a.status, c.clinic_name, s.service_name, cs_user.name AS dentist_name FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id INNER JOIN clinics c ON c.clinic_id = a.clinic_id LEFT JOIN services s ON s.service_id = a.service_id LEFT JOIN clinic_staff cs ON cs.staff_id = a.staff_id LEFT JOIN auth_users cs_user ON cs_user.id = cs.user_id WHERE p.user_id = ? AND a.status = 'scheduled' AND a.appointment_date >= NOW() ORDER BY a.appointment_date LIMIT 5");
$stmt->execute([$patient['user_id']]);
$appointments = $stmt->fetchAll();

// Get chart data
// Appointments over last 6 months
$appointmentTrend = $pdo->prepare("
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as count
    FROM appointments a
    INNER JOIN patients p ON p.patient_id = a.patient_id
    WHERE p.user_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month ASC
");
$appointmentTrend->execute([$patient['user_id']]);
$appointmentTrend = $appointmentTrend->fetchAll();

// Visit types
$visitTypes = $pdo->prepare("
    SELECT s.service_name, COUNT(*) as count
    FROM appointments a
    INNER JOIN patients p ON p.patient_id = a.patient_id
    LEFT JOIN services s ON s.service_id = a.service_id
    WHERE p.user_id = ?
    GROUP BY s.service_name
    ORDER BY count DESC
    LIMIT 5
");
$visitTypes->execute([$patient['user_id']]);
$visitTypes = $visitTypes->fetchAll();

patientPortalStart($patient, 'My dashboard', 'dashboard');
?>
<section class="content-grid">
    <div class="stat-card"><div><strong><?php echo $upcomingCount; ?></strong><span>Upcoming appointments</span></div><i class="fas fa-calendar-check"></i></div>
    <div class="stat-card"><div><strong>₱<?php echo number_format($balance, 2); ?></strong><span>Outstanding balance</span></div><i class="fas fa-file-invoice-dollar"></i></div>
    <div class="stat-card"><div><strong><?php echo $visitCount; ?></strong><span>Total visits</span></div><i class="fas fa-notes-medical"></i></div>
</section>

<section class="card">
    <div class="week-nav"><h3>Upcoming appointments</h3><a class="button-link" href="clinics.php"><i class="fas fa-plus"></i> Book a visit</a></div>
    <?php if (!$appointments): ?>
        <p class="empty-state">You have no upcoming appointments.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Date and time</th><th>Clinic</th><th>Service</th><th>Dentist</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($appointments as $appointment): ?>
            <tr><td><?php echo patientPortalEscape(date('M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></td><td><?php echo patientPortalEscape($appointment['clinic_name']); ?></td><td><?php echo patientPortalEscape($appointment['service_name'] ?: 'To be confirmed'); ?></td><td><?php echo patientPortalEscape($appointment['dentist_name'] ?: 'To be confirmed'); ?></td><td><span class="badge"><?php echo patientPortalEscape($appointment['status']); ?></span></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>

<div class="chart-grid">
    <section class="card">
        <div class="week-nav"><h3>My Appointments (Last 6 Months)</h3></div>
        <div class="chart-container">
            <canvas id="appointmentTrendChart"></canvas>
        </div>
    </section>
    <section class="card">
        <div class="week-nav"><h3>Visit Types</h3></div>
        <div class="chart-container">
            <canvas id="visitTypesChart"></canvas>
        </div>
    </section>
</div>

<script>
// Appointment Trend Chart
const appointmentTrendCtx = document.getElementById('appointmentTrendChart');
if (appointmentTrendCtx) {
    const trendData = <?php echo json_encode($appointmentTrend); ?>;
    const labels = trendData.map(d => d.month);
    const data = trendData.map(d => d.count);

    new Chart(appointmentTrendCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Appointments',
                data: data,
                borderColor: '#6784c8',
                backgroundColor: 'rgba(103, 132, 200, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Visit Types Chart
const visitTypesCtx = document.getElementById('visitTypesChart');
if (visitTypesCtx) {
    const typesData = <?php echo json_encode($visitTypes); ?>;
    const labels = typesData.map(d => d.service_name || 'Other');
    const data = typesData.map(d => d.count);

    new Chart(visitTypesCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: ['#6784c8', '#2ecc71', '#f39c12', '#e74c3c', '#3498db'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>
<?php patientPortalEnd(); ?>