<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['dentist']);
$todayStart = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');
$tomorrowStart = (new DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s');
$weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d H:i:s');
$nextWeek = (new DateTimeImmutable('monday this week'))->modify('+7 days')->format('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND staff_id = ? AND appointment_date >= ? AND appointment_date < ?");
$stmt->execute([$context['clinic_id'], $context['staff_id'], $todayStart, $tomorrowStart]);
$todayCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND staff_id = ? AND appointment_date >= ? AND appointment_date < ?");
$stmt->execute([$context['clinic_id'], $context['staff_id'], $weekStart, $nextWeek]);
$weekCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dental_records WHERE clinic_id = ? AND dentist_user_id = ?");
$stmt->execute([$context['clinic_id'], $context['user_id']]);
$recordCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT a.appointment_id, a.appointment_date, a.status, a.notes, p.patient_id, p.name AS patient_name FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id WHERE a.clinic_id = ? AND a.staff_id = ? AND a.appointment_date >= ? AND a.appointment_date < ? ORDER BY a.appointment_date ASC LIMIT 12");
$stmt->execute([$context['clinic_id'], $context['staff_id'], $todayStart, $tomorrowStart]);
$appointments = $stmt->fetchAll();

// Get chart data
// Appointments over last 7 days
$appointmentTrend = $pdo->prepare("
    SELECT DATE(appointment_date) as date, COUNT(*) as count
    FROM appointments
    WHERE clinic_id = ? AND staff_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(appointment_date)
    ORDER BY date ASC
");
$appointmentTrend->execute([$context['clinic_id'], $context['staff_id']]);
$appointmentTrend = $appointmentTrend->fetchAll();

// Appointment status distribution
$appointmentStatus = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM appointments
    WHERE clinic_id = ? AND staff_id = ?
    GROUP BY status
");
$appointmentStatus->execute([$context['clinic_id'], $context['staff_id']]);
$appointmentStatus = $appointmentStatus->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'href' => 'schedule.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'records', 'label' => 'Dental Records', 'href' => 'records.php', 'icon' => 'fa-notes-medical'],
    ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => 'treatments.php', 'icon' => 'fa-tooth'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Dentist Dashboard', 'dashboard', $navigation);
?>
<section class="content-grid">
    <div class="stat-card"><div><strong><?php echo $todayCount; ?></strong><span>Appointments today</span></div><i class="fas fa-calendar-day"></i></div>
    <div class="stat-card"><div><strong><?php echo $weekCount; ?></strong><span>Appointments this week</span></div><i class="fas fa-calendar-week"></i></div>
    <div class="stat-card"><div><strong><?php echo $recordCount; ?></strong><span>My dental records</span></div><i class="fas fa-notes-medical"></i></div>
</section>

<section class="card">
    <div class="week-nav"><h3>Today's Schedule</h3><a class="button-link secondary" href="schedule.php"><i class="fas fa-calendar-alt"></i> Open schedule</a></div>
    <?php if (!$appointments): ?>
        <p class="empty-state">No appointments are assigned to you today.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>Patient</th><th>Status</th><th>Appointment</th></tr></thead>
        <tbody>
        <?php foreach ($appointments as $appointment): ?>
            <tr>
                <td><?php echo clinicPortalEscape(date('g:i A', strtotime($appointment['appointment_date']))); ?></td>
                <td><?php echo clinicPortalEscape($appointment['patient_name']); ?></td>
                <td><span class="badge <?php echo clinicPortalEscape($appointment['status']); ?>"><?php echo clinicPortalEscape($appointment['status']); ?></span></td>
                <td><a class="button-link secondary" href="records.php?patient_id=<?php echo (int) $appointment['patient_id']; ?>">Open record</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<div class="chart-grid">
    <section class="card">
        <div class="week-nav"><h3>My Appointments (Last 7 Days)</h3></div>
        <div class="chart-container">
            <canvas id="appointmentTrendChart"></canvas>
        </div>
    </section>
    <section class="card">
        <div class="week-nav"><h3>Appointment Status</h3></div>
        <div class="chart-container">
            <canvas id="appointmentStatusChart"></canvas>
        </div>
    </section>
</div>

<script>
// Appointment Trend Chart
const appointmentTrendCtx = document.getElementById('appointmentTrendChart');
if (appointmentTrendCtx) {
    const trendData = <?php echo json_encode($appointmentTrend); ?>;
    const labels = trendData.map(d => d.date);
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

// Appointment Status Chart
const appointmentStatusCtx = document.getElementById('appointmentStatusChart');
if (appointmentStatusCtx) {
    const statusData = <?php echo json_encode($appointmentStatus); ?>;
    const labels = statusData.map(d => d.status);
    const data = statusData.map(d => d.count);

    new Chart(appointmentStatusCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Count',
                data: data,
                backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c', '#3498db'],
                borderWidth: 0
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
</script>
<?php clinicPortalEnd(); ?>