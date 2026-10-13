<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['assistant']);
$paymentId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, pt.name AS patient_name, pt.email AS patient_email, pt.phone AS patient_phone, c.clinic_name, c.address AS clinic_address, c.contact_number AS clinic_phone, c.email AS clinic_email, au.name AS received_by FROM clinic_payments p INNER JOIN patients pt ON pt.patient_id = p.patient_id INNER JOIN clinics c ON c.clinic_id = p.clinic_id INNER JOIN auth_users au ON au.id = p.recorded_by WHERE p.payment_id = ? AND p.clinic_id = ? LIMIT 1");
$stmt->execute([$paymentId, $context['clinic_id']]);
$receipt = $stmt->fetch();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'payments', 'label' => 'Payments', 'href' => 'payments.php', 'icon' => 'fa-cash-register'],
];

clinicPortalStart($context, 'Payment Receipt', 'payments', $navigation);
?>
<?php if (!$receipt): ?>
    <section class="card"><p class="empty-state">Receipt not found for this clinic.</p><a class="button-link secondary" href="payments.php">Back to payments</a></section>
<?php else: ?>
    <section class="card receipt">
        <div class="week-nav"><div><h3>Payment receipt</h3><p><?php echo clinicPortalEscape($receipt['clinic_name']); ?></p></div><div class="actions print-actions"><button class="button" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print receipt</button><a class="button-link secondary" href="payments.php">Back</a></div></div>
        <dl class="info-list">
            <div class="week-nav"><dt>Receipt number</dt><dd><strong><?php echo clinicPortalEscape($receipt['receipt_number']); ?></strong></dd></div>
            <div class="week-nav"><dt>Date paid</dt><dd><?php echo clinicPortalEscape(date('F j, Y g:i A', strtotime($receipt['paid_at']))); ?></dd></div>
            <div class="week-nav"><dt>Patient</dt><dd><?php echo clinicPortalEscape($receipt['patient_name']); ?></dd></div>
            <div class="week-nav"><dt>Patient contact</dt><dd><?php echo clinicPortalEscape($receipt['patient_phone']); ?> | <?php echo clinicPortalEscape($receipt['patient_email']); ?></dd></div>
            <div class="week-nav"><dt>Payment method</dt><dd><?php echo clinicPortalEscape(ucwords(str_replace('_', ' ', $receipt['payment_method']))); ?></dd></div>
            <?php if ($receipt['reference_number']): ?><div class="week-nav"><dt>Reference</dt><dd><?php echo clinicPortalEscape($receipt['reference_number']); ?></dd></div><?php endif; ?>
            <?php if ($receipt['appointment_id']): ?><div class="week-nav"><dt>Appointment ID</dt><dd><?php echo (int) $receipt['appointment_id']; ?></dd></div><?php endif; ?>
            <?php if ($receipt['notes']): ?><div class="week-nav"><dt>Notes</dt><dd><?php echo clinicPortalEscape($receipt['notes']); ?></dd></div><?php endif; ?>
            <div class="week-nav"><dt>Received by</dt><dd><?php echo clinicPortalEscape($receipt['received_by']); ?></dd></div>
        </dl>
        <div class="receipt-total"><span>Total paid</span><span>₱<?php echo number_format((float) $receipt['amount'], 2); ?></span></div>
        <p class="empty-state"><?php echo clinicPortalEscape($receipt['clinic_address']); ?> | <?php echo clinicPortalEscape($receipt['clinic_phone']); ?> | <?php echo clinicPortalEscape($receipt['clinic_email']); ?></p>
    </section>
<?php endif; ?>
<?php clinicPortalEnd(); ?>