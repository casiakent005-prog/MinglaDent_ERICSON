<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['dentist']);

$stmt = $pdo->prepare("SELECT * FROM auth_users WHERE id = ?");
$stmt->execute([$context['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: ../login/logout.php");
    exit;
}

$clinic = null;
$clinicId = $user['clinic_id'] ?? null;
if (empty($clinicId)) {
    $stmt = $pdo->prepare("SELECT clinic_id FROM clinic_staff WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $clinicId = $stmt->fetchColumn();
}

if (!empty($clinicId)) {
    $stmt = $pdo->prepare("SELECT clinic_id, clinic_name, address, contact_number, email, operating_hours, map_location, status, created_at, updated_at FROM clinics WHERE clinic_id = ?");
    $stmt->execute([$clinicId]);
    $clinic = $stmt->fetch();
}

$initial = strtoupper(substr($user['name'], 0, 1));
$formatValue = static function ($value) {
    return $value !== null && trim((string) $value) !== ''
        ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
        : 'Not provided';
};

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'href' => 'schedule.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'records', 'label' => 'Dental Records', 'href' => 'records.php', 'icon' => 'fa-notes-medical'],
    ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => 'treatments.php', 'icon' => 'fa-tooth'],
];

clinicPortalStart($context, 'My Profile', 'profile', $navigation);
?>
<section class="card">
    <div class="profile-header">
        <div class="profile-avatar"><?php echo $initial; ?></div>
        <div class="profile-info">
            <h2><?php echo $formatValue($user['name']); ?></h2>
            <p><?php echo ucfirst($formatValue($user['role'])); ?> account | <?php echo $formatValue($user['email']); ?></p>
        </div>
    </div>

    <div class="profile-grid">
        <section class="profile-section">
            <h3><i class="fas fa-user-shield"></i> Account Information</h3>
            <dl class="info-list">
                <div class="info-item"><dt>Account ID</dt><dd><?php echo $formatValue($user['id']); ?></dd></div>
                <div class="info-item"><dt>Full name</dt><dd><?php echo $formatValue($user['name']); ?></dd></div>
                <div class="info-item"><dt>Email address</dt><dd><?php echo $formatValue($user['email']); ?></dd></div>
                <div class="info-item"><dt>Access role</dt><dd><?php echo ucfirst($formatValue($user['role'])); ?></dd></div>
                <div class="info-item"><dt>Account status</dt><dd><span class="badge <?php echo htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $formatValue($user['status']); ?></span></dd></div>
                <div class="info-item"><dt>Member since</dt><dd><?php echo $formatValue($user['created_at']); ?></dd></div>
                <div class="info-item"><dt>Last account update</dt><dd><?php echo $formatValue($user['updated_at']); ?></dd></div>
            </dl>
        </section>

        <section class="profile-section">
            <h3><i class="fas fa-hospital"></i> Clinic Information</h3>
            <?php if ($clinic): ?>
            <dl class="info-list">
                <div class="info-item"><dt>Clinic ID</dt><dd><?php echo $formatValue($clinic['clinic_id']); ?></dd></div>
                <div class="info-item"><dt>Clinic name</dt><dd><?php echo $formatValue($clinic['clinic_name']); ?></dd></div>
                <div class="info-item"><dt>Address</dt><dd><?php echo $formatValue($clinic['address']); ?></dd></div>
                <div class="info-item"><dt>Contact number</dt><dd><?php echo $formatValue($clinic['contact_number']); ?></dd></div>
                <div class="info-item"><dt>Clinic email</dt><dd><?php echo $formatValue($clinic['email']); ?></dd></div>
                <div class="info-item"><dt>Operating hours</dt><dd><?php echo $formatValue($clinic['operating_hours']); ?></dd></div>
                <div class="info-item"><dt>Map location</dt><dd><?php echo $formatValue($clinic['map_location']); ?></dd></div>
                <div class="info-item"><dt>Clinic status</dt><dd><span class="badge <?php echo htmlspecialchars($clinic['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $formatValue($clinic['status']); ?></span></dd></div>
                <div class="info-item"><dt>Clinic registered</dt><dd><?php echo $formatValue($clinic['created_at']); ?></dd></div>
                <div class="info-item"><dt>Clinic last updated</dt><dd><?php echo $formatValue($clinic['updated_at']); ?></dd></div>
            </dl>
            <?php else: ?>
            <p class="empty-state">Walay clinic nga naka-link niining account. Kung dentist ka, ipa-link ang account sa sakto nga clinic record aron makita dinhi ang clinic details.</p>
            <?php endif; ?>
        </section>
    </div>
</section>

<style>
.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #6784c8, #43617f);
    border-radius: 10px;
    color: white;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 700;
    border: 2px solid rgba(255,255,255,0.7);
}

.profile-info h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.profile-info p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.profile-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e2e7ee;
}

.profile-section h3 {
    color: #2c3e50;
    font-size: 18px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ecf0f1;
}

.profile-section h3 i {
    color: #6784c8;
    margin-right: 8px;
}

.info-list {
    display: grid;
    gap: 12px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.info-item dt {
    color: #7f8c8d;
    font-size: 12px;
    font-weight: 600;
    margin: 0;
}

.info-item dd {
    color: #2c3e50;
    font-size: 14px;
    font-weight: 500;
    margin: 0;
    text-align: right;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge.active {
    background: #e7f4e8;
    color: #28633b;
}

.badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

.badge.pending {
    background: #fff3cd;
    color: #856404;
}

.badge.approved {
    background: #e7f4e8;
    color: #28633b;
}

.badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.badge.suspended {
    background: #f8d7da;
    color: #721c24;
}

.empty-state {
    color: #7f8c8d;
    font-size: 14px;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }

    .profile-header {
        flex-direction: column;
        text-align: center;
    }

    .info-item {
        flex-direction: column;
        text-align: left;
    }

    .info-item dd {
        text-align: left;
        margin-top: 5px;
    }
}
</style>

<?php clinicPortalEnd(); ?>
