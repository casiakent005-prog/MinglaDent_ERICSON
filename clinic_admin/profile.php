<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();

$clinic = null;
if ($context['clinic_id']) {
    $stmt = $pdo->prepare("SELECT clinic_id, clinic_name, address, contact_number, email, operating_hours, map_location, status, created_at, updated_at FROM clinics WHERE clinic_id = ?");
    $stmt->execute([$context['clinic_id']]);
    $clinic = $stmt->fetch();
}

$initial = strtoupper(substr($context['name'], 0, 1));
$formatValue = static function ($value) {
    return $value !== null && trim((string) $value) !== ''
        ? clinicPortalEscape((string) $value)
        : 'Not provided';
};

clinicAdminStart($context, 'Admin Profile', 'profile');
?>
<section class="card" style="background: linear-gradient(120deg, #6784c8, #43617f); color: white; padding: 28px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <div style="width: 76px; height: 76px; flex: 0 0 76px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.7); font-size: 30px; font-weight: 700;" aria-hidden="true"><?php echo $initial; ?></div>
        <div>
            <h1 style="font-size: 24px; margin: 0 0 6px; overflow-wrap: anywhere;"><?php echo $formatValue($context['name']); ?></h1>
            <p style="margin: 0; color: rgba(255,255,255,0.85); font-size: 14px;"><?php echo ucfirst($formatValue($context['portal_role'])); ?> account | <?php echo $formatValue($context['email']); ?></p>
        </div>
    </div>
</section>

<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; margin-bottom: 20px;">
    <section class="card">
        <h3 style="margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #ecf0f1;"><i class="fas fa-user-shield" style="color: #6784c8; margin-right: 8px;"></i> Account Information</h3>
        <dl style="display: grid; gap: 16px;">
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Account ID</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($context['user_id']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Full name</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($context['name']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Email address</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($context['email']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Access role</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo ucfirst($formatValue($context['portal_role'])); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Account status</dt><dd style="margin: 0;"><span class="badge <?php echo clinicPortalEscape($context['account_status']); ?>"><?php echo $formatValue($context['account_status']); ?></span></dd></div>
        </dl>
    </section>

    <section class="card">
        <h3 style="margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid #ecf0f1;"><i class="fas fa-hospital" style="color: #6784c8; margin-right: 8px;"></i> Clinic Information</h3>
        <?php if ($clinic): ?>
        <dl style="display: grid; gap: 16px;">
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Clinic ID</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['clinic_id']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Clinic name</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['clinic_name']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Address</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['address']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Contact number</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['contact_number']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Clinic email</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['email']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Operating hours</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['operating_hours']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Map location</dt><dd style="color: #2c3e50; font-size: 14px; font-weight: 500; margin: 0;"><?php echo $formatValue($clinic['map_location']); ?></dd></div>
            <div><dt style="color: #7f8c8d; font-size: 12px; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Clinic status</dt><dd style="margin: 0;"><span class="badge <?php echo clinicPortalEscape($clinic['status']); ?>"><?php echo $formatValue($clinic['status']); ?></span></dd></div>
        </dl>
        <?php else: ?>
        <p class="empty-state">No clinic linked to this account. If you are a clinic admin, link your account to the correct clinic record to view clinic details here.</p>
        <?php endif; ?>
    </section>
</div>
<?php clinicAdminEnd(); ?>
