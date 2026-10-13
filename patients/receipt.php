<?php
require_once 'bootstrap.php';

$patient = patientPortalContext();
$paymentId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT cp.payment_id, cp.receipt_number, cp.amount, cp.payment_method, cp.reference_number, cp.paid_at, cp.notes, c.clinic_name, c.address AS clinic_address, c.contact_number AS clinic_phone, c.email AS clinic_email FROM clinic_payments cp INNER JOIN patients p ON p.patient_id = cp.patient_id INNER JOIN clinics c ON c.clinic_id = cp.clinic_id WHERE cp.payment_id = ? AND p.user_id = ? AND cp.payment_status = 'paid' LIMIT 1");
$stmt->execute([$paymentId, $patient['user_id']]);
$receipt = $stmt->fetch();

require_once 'layout.php';
patientPortalStart($patient, 'E-receipt', 'history');
?>
<?php if (!$receipt): ?>
    <section class="card"><p class="empty-state">Receipt not found in your account.</p><a class="button-link secondary" href="history.php">Back to history</a></section>
<?php else: ?>
    <section class="card receipt">
        <h3>Payment receipt</h3>
        <p><?php echo patientPortalEscape($receipt['clinic_name']); ?></p>
        <dl class="info-list">
            <div class="week-nav"><dt>Receipt number</dt><dd><strong><?php echo patientPortalEscape($receipt['receipt_number']); ?></strong></dd></div>
            <div class="week-nav"><dt>Date paid</dt><dd><?php echo patientPortalEscape(date('F j, Y g:i A', strtotime($receipt['paid_at']))); ?></dd></div>
            <div class="week-nav"><dt>Paid by</dt><dd><?php echo patientPortalEscape($patient['name']); ?></dd></div>
            <div class="week-nav"><dt>Payment method</dt><dd><?php echo patientPortalEscape(ucwords(str_replace('_', ' ', $receipt['payment_method']))); ?></dd></div>
            <?php if ($receipt['reference_number']): ?><div class="week-nav"><dt>Reference</dt><dd><?php echo patientPortalEscape($receipt['reference_number']); ?></dd></div><?php endif; ?>
            <?php if ($receipt['notes']): ?><div class="week-nav"><dt>Notes</dt><dd><?php echo patientPortalEscape($receipt['notes']); ?></dd></div><?php endif; ?>
        </dl>
        <div class="receipt-total"><span>Total paid</span><span>₱<?php echo number_format((float) $receipt['amount'], 2); ?></span></div>
        <p class="empty-state"><?php echo patientPortalEscape($receipt['clinic_address']); ?> | <?php echo patientPortalEscape($receipt['clinic_phone']); ?> | <?php echo patientPortalEscape($receipt['clinic_email']); ?></p>
    </section>
<?php endif; ?>
<?php patientPortalEnd(); ?>