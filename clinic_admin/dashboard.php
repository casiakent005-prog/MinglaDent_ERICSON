<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();

// Get clinic info if user is clinic staff
$clinic = null;
if ($context['portal_role'] === 'clinic_admin' && $context['clinic_id']) {
    $stmt = $pdo->prepare("SELECT * FROM clinics WHERE clinic_id = ?");
    $stmt->execute([$context['clinic_id']]);
    $clinic = $stmt->fetch();
}

// Get statistics based on role
if ($context['portal_role'] === 'admin') {
    $totalClinics = $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn();
    $totalStaff = $pdo->query("SELECT COUNT(*) FROM clinic_staff")->fetchColumn();
    $totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $totalAppointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $totalServices = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $pendingClinics = $pdo->query("SELECT COUNT(*) FROM clinics WHERE status = 'pending'")->fetchColumn();
} else {
    // Clinic-specific stats
    $totalStaff = $pdo->prepare("SELECT COUNT(*) FROM clinic_staff WHERE clinic_id = ?");
    $totalStaff->execute([$context['clinic_id']]);
    $totalStaff = $totalStaff->fetchColumn();

    $totalPatients = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ?");
    $totalPatients->execute([$context['clinic_id']]);
    $totalPatients = $totalPatients->fetchColumn();

    $totalAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ?");
    $totalAppointments->execute([$context['clinic_id']]);
    $totalAppointments = $totalAppointments->fetchColumn();

    $totalServices = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();

    // Today's appointments
    $todayAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND DATE(appointment_date) = CURDATE()");
    $todayAppointments->execute([$context['clinic_id']]);
    $todayAppointments = $todayAppointments->fetchColumn();
}

// Get recent activities
$recentActivities = [];
if ($context['portal_role'] === 'admin') {
    $recentActivities = $pdo->query("
        SELECT 'clinic' as type, clinic_name as title, 'New clinic registered' as action, created_at
        FROM clinics
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();
} else {
    $recentActivities = $pdo->prepare("
        SELECT 'appointment' as type, appointment_date as title, 'New appointment scheduled' as action, created_at
        FROM appointments
        WHERE clinic_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentActivities->execute([$context['clinic_id']]);
    $recentActivities = $recentActivities->fetchAll();
}

// Get chart data
if ($context['portal_role'] === 'admin') {
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
} else {
    // Appointments over last 7 days for this clinic
    $appointmentTrend = $pdo->prepare("
        SELECT DATE(appointment_date) as date, COUNT(*) as count
        FROM appointments
        WHERE clinic_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(appointment_date)
        ORDER BY date ASC
    ");
    $appointmentTrend->execute([$context['clinic_id']]);
    $appointmentTrend = $appointmentTrend->fetchAll();

    // Appointment status distribution
    $appointmentStatus = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM appointments
        WHERE clinic_id = ?
        GROUP BY status
    ");
    $appointmentStatus->execute([$context['clinic_id']]);
    $appointmentStatus = $appointmentStatus->fetchAll();
}

clinicAdminStart($context, 'Admin Dashboard', 'dashboard');
?>

<?php if ($clinic): ?>
<section class="card" style="background: linear-gradient(135deg, #6784c8, #5a6fa8); color: white;">
    <h2 style="margin-bottom: 15px;"><i class="fas fa-hospital"></i> <?php echo clinicPortalEscape($clinic['clinic_name']); ?></h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
            <label style="display: block; font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Status</label>
            <p style="margin: 0; font-size: 16px; font-weight: 600;"><span class="badge <?php echo clinicPortalEscape($clinic['status']); ?>"><?php echo ucfirst(clinicPortalEscape($clinic['status'])); ?></span></p>
        </div>
        <div>
            <label style="display: block; font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Contact</label>
            <p style="margin: 0; font-size: 16px; font-weight: 600;"><?php echo clinicPortalEscape($clinic['contact_number']); ?></p>
        </div>
        <div>
            <label style="display: block; font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Email</label>
            <p style="margin: 0; font-size: 16px; font-weight: 600;"><?php echo clinicPortalEscape($clinic['email']); ?></p>
        </div>
        <div>
            <label style="display: block; font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Address</label>
            <p style="margin: 0; font-size: 16px; font-weight: 600;"><?php echo clinicPortalEscape($clinic['address']); ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="content-grid">
    <?php if ($context['portal_role'] === 'admin'): ?>
    <div class="stat-card"><div><strong><?php echo $totalClinics; ?></strong><span>Total Clinics</span></div><i class="fas fa-hospital"></i></div>
    <?php endif; ?>
    <div class="stat-card"><div><strong><?php echo $totalStaff; ?></strong><span>Staff Members</span></div><i class="fas fa-users"></i></div>
    <div class="stat-card"><div><strong><?php echo $totalPatients; ?></strong><span>Patients</span></div><i class="fas fa-user"></i></div>
    <div class="stat-card"><div><strong><?php echo $totalAppointments; ?></strong><span>Appointments</span></div><i class="fas fa-calendar"></i></div>
    <div class="stat-card"><div><strong><?php echo $totalServices; ?></strong><span>Services</span></div><i class="fas fa-tooth"></i></div>
    <?php if ($context['portal_role'] === 'admin'): ?>
    <div class="stat-card"><div><strong><?php echo $pendingClinics; ?></strong><span>Pending Approvals</span></div><i class="fas fa-clock"></i></div>
    <?php else: ?>
    <div class="stat-card"><div><strong><?php echo $todayAppointments; ?></strong><span>Today's Appointments</span></div><i class="fas fa-calendar-day"></i></div>
    <?php endif; ?>
</section>

<div class="chart-grid">
    <section class="card">
        <div class="week-nav"><h3>Appointments (Last 7 Days)</h3></div>
        <div class="chart-container">
            <canvas id="appointmentTrendChart"></canvas>
        </div>
    </section>
    <?php if ($context['portal_role'] === 'admin'): ?>
    <section class="card">
        <div class="week-nav"><h3>Clinic Status Distribution</h3></div>
        <div class="chart-container">
            <canvas id="clinicStatusChart"></canvas>
        </div>
    </section>
    <?php else: ?>
    <section class="card">
        <div class="week-nav"><h3>Appointment Status</h3></div>
        <div class="chart-container">
            <canvas id="appointmentStatusChart"></canvas>
        </div>
    </section>
    <?php endif; ?>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
    <section class="card">
        <div class="week-nav"><h3>Recent Activity</h3></div>
        <?php if (empty($recentActivities)): ?>
            <p class="empty-state">No recent activity</p>
        <?php else: ?>
            <?php foreach ($recentActivities as $activity): ?>
            <div style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #ecf0f1;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #e9effb; display: flex; align-items: center; justify-content: center; color: #5879b8; font-size: 18px;">
                    <i class="fas fa-bell"></i>
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 3px; color: #2c3e50; font-size: 14px; font-weight: 600;"><?php echo clinicPortalEscape($activity['action']); ?></h4>
                    <p style="margin: 0; color: #7f8c8d; font-size: 12px;"><?php echo clinicPortalEscape($activity['title']); ?></p>
                </div>
                <div style="color: #95a5a6; font-size: 12px;">
                    <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="week-nav"><h3>Quick Actions</h3></div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <?php if ($context['portal_role'] === 'admin'): ?>
            <a href="clinics.php" class="button" style="display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 20px;">
                <i class="fas fa-hospital" style="font-size: 24px;"></i>
                <span>Manage Clinics</span>
            </a>
            <?php endif; ?>
            <a href="clinic_staff.php" class="button" style="display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 20px;">
                <i class="fas fa-users" style="font-size: 24px;"></i>
                <span>Manage Staff</span>
            </a>
            <a href="services.php" class="button" style="display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 20px;">
                <i class="fas fa-tooth" style="font-size: 24px;"></i>
                <span>Manage Services</span>
            </a>
            <a href="fees.php" class="button" style="display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 20px;">
                <i class="fas fa-dollar-sign" style="font-size: 24px;"></i>
                <span>Manage Fees</span>
            </a>
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

<?php if ($context['portal_role'] === 'admin'): ?>
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
<?php else: ?>
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
<?php endif; ?>
</script>
<?php clinicAdminEnd(); ?>
