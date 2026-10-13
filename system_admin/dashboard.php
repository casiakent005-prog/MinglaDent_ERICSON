<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$admin = systemAdminContext();
$csrfToken = clinicPortalCsrfToken();

$counts = [
    'clinics' => (int) $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM clinics WHERE status = 'pending'")->fetchColumn(),
    'suspended' => (int) $pdo->query("SELECT COUNT(*) FROM clinics WHERE status = 'suspended'")->fetchColumn(),
    'users' => (int) $pdo->query("SELECT COUNT(*) FROM auth_users")->fetchColumn(),
    'active_users' => (int) $pdo->query("SELECT COUNT(*) FROM auth_users WHERE status = 'active'")->fetchColumn(),
    'appointments' => (int) $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
];

$pendingStmt = $pdo->query("SELECT clinic_id, clinic_name, address, contact_number, email, created_at FROM clinics WHERE status = 'pending' ORDER BY created_at ASC LIMIT 8");
$pendingClinics = $pendingStmt->fetchAll();

// Get chart data
// Appointments over last 7 days
$appointmentTrend = $pdo->query("
    SELECT DATE(appointment_date) as date, COUNT(*) as count
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(appointment_date)
    ORDER BY date ASC
")->fetchAll();

// Clinic status distribution
$clinicStatus = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM clinics
    GROUP BY status
")->fetchAll();

// User distribution
$userDistribution = $pdo->query("
    SELECT role, COUNT(*) as count
    FROM auth_users
    GROUP BY role
")->fetchAll();

systemAdminStart($admin, 'System Admin dashboard', 'dashboard');
?>
<section class="content-grid">
    <div class="stat-card"><div><strong><?php echo $counts['clinics']; ?></strong><span>Clinic accounts</span></div><i class="fas fa-hospital"></i></div>
    <div class="stat-card"><div><strong><?php echo $counts['pending']; ?></strong><span>Pending approval</span></div><i class="fas fa-clock"></i></div>
    <div class="stat-card"><div><strong><?php echo $counts['suspended']; ?></strong><span>Suspended clinics</span></div><i class="fas fa-ban"></i></div>
    <div class="stat-card"><div><strong><?php echo $counts['users']; ?></strong><span>All user accounts</span></div><i class="fas fa-users"></i></div>
    <div class="stat-card"><div><strong><?php echo $counts['active_users']; ?></strong><span>Active users</span></div><i class="fas fa-user-check"></i></div>
    <div class="stat-card"><div><strong><?php echo $counts['appointments']; ?></strong><span>Platform bookings</span></div><i class="fas fa-calendar-check"></i></div>
</section>

<section class="card">
    <div class="week-nav"><h3>Clinic approvals</h3><a class="button-link secondary" href="clinics.php">Manage clinic directory</a></div>
    <?php if (!$pendingClinics): ?><p class="empty-state">No clinics are waiting for approval.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Clinic</th><th>Address</th><th>Contact</th><th>Submitted</th><th></th></tr></thead><tbody>
    <?php foreach ($pendingClinics as $clinic): ?>
        <tr><td><?php echo clinicPortalEscape($clinic['clinic_name']); ?></td><td><?php echo clinicPortalEscape($clinic['address']); ?></td><td><?php echo clinicPortalEscape($clinic['contact_number']); ?><br><?php echo clinicPortalEscape($clinic['email']); ?></td><td><?php echo clinicPortalEscape(date('M j, Y', strtotime($clinic['created_at']))); ?></td><td><form method="post" action="clinics.php" class="actions"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>"><button class="button" name="action" value="approve_clinic" type="submit"><i class="fas fa-check"></i> Approve</button><button class="button danger" name="action" value="suspend_clinic" type="submit"><i class="fas fa-ban"></i> Suspend</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>

<div class="chart-grid">
    <section class="card">
        <div class="week-nav"><h3>Platform Appointments (Last 7 Days)</h3></div>
        <div class="chart-container">
            <canvas id="appointmentTrendChart"></canvas>
        </div>
    </section>
    <section class="card">
        <div class="week-nav"><h3>Clinic Status Distribution</h3></div>
        <div class="chart-container">
            <canvas id="clinicStatusChart"></canvas>
        </div>
    </section>
    <section class="card">
        <div class="week-nav"><h3>User Distribution</h3></div>
        <div class="chart-container">
            <canvas id="userDistributionChart"></canvas>
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

// Clinic Status Chart
const clinicStatusCtx = document.getElementById('clinicStatusChart');
if (clinicStatusCtx) {
    const statusData = <?php echo json_encode($clinicStatus); ?>;
    const labels = statusData.map(d => d.status);
    const data = statusData.map(d => d.count);

    new Chart(clinicStatusCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
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
                    position: 'bottom'
                }
            }
        }
    });
}

// User Distribution Chart
const userDistributionCtx = document.getElementById('userDistributionChart');
if (userDistributionCtx) {
    const userData = <?php echo json_encode($userDistribution); ?>;
    const labels = userData.map(d => d.role);
    const data = userData.map(d => d.count);

    new Chart(userDistributionCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Users',
                data: data,
                backgroundColor: ['#6784c8', '#2ecc71', '#f39c12', '#e74c3c'],
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
<?php systemAdminEnd(); ?>