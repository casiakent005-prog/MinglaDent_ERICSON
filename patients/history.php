<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$patient = patientPortalContext();
$stmt = $pdo->prepare("SELECT a.appointment_id, a.appointment_date, a.status AS appointment_status, c.clinic_name, s.service_name, cs_user.name AS dentist_name, i.invoice_id, i.description, i.amount AS invoice_amount, i.status AS invoice_status, COALESCE(paid.total_paid, 0) AS total_paid FROM appointments a INNER JOIN patients pt ON pt.patient_id = a.patient_id INNER JOIN clinics c ON c.clinic_id = a.clinic_id LEFT JOIN services s ON s.service_id = a.service_id LEFT JOIN clinic_staff cs ON cs.staff_id = a.staff_id LEFT JOIN auth_users cs_user ON cs_user.id = cs.user_id LEFT JOIN clinic_invoices i ON i.appointment_id = a.appointment_id LEFT JOIN (SELECT invoice_id, SUM(amount) AS total_paid FROM clinic_payments WHERE payment_status = 'paid' AND invoice_id IS NOT NULL GROUP BY invoice_id) paid ON paid.invoice_id = i.invoice_id WHERE pt.user_id = ? ORDER BY a.appointment_date DESC");
$stmt->execute([$patient['user_id']]);
$appointments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT dr.record_id, dr.diagnosis, dr.examination_findings, dr.treatment_plan, dr.clinical_notes, dr.tooth_chart, dr.recorded_at, c.clinic_name, au.name AS dentist_name FROM dental_records dr INNER JOIN patients p ON p.patient_id = dr.patient_id INNER JOIN clinics c ON c.clinic_id = dr.clinic_id INNER JOIN auth_users au ON au.id = dr.dentist_user_id WHERE p.user_id = ? ORDER BY dr.recorded_at DESC");
$stmt->execute([$patient['user_id']]);
$records = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.tooth_number, t.procedure_name, t.treatment_date, t.status, t.notes, c.clinic_name, au.name AS dentist_name FROM dental_treatments t INNER JOIN patients p ON p.patient_id = t.patient_id INNER JOIN clinics c ON c.clinic_id = t.clinic_id INNER JOIN auth_users au ON au.id = t.dentist_user_id WHERE p.user_id = ? ORDER BY t.treatment_date DESC");
$stmt->execute([$patient['user_id']]);
$treatments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT d.document_id, d.original_name, d.document_type, d.created_at, c.clinic_name FROM patient_documents d INNER JOIN patients p ON p.patient_id = d.patient_id INNER JOIN clinics c ON c.clinic_id = d.clinic_id WHERE p.user_id = ? ORDER BY d.created_at DESC");
$stmt->execute([$patient['user_id']]);
$documents = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT cp.payment_id, cp.receipt_number, cp.amount, cp.payment_method, cp.paid_at, c.clinic_name FROM clinic_payments cp INNER JOIN patients p ON p.patient_id = cp.patient_id INNER JOIN clinics c ON c.clinic_id = cp.clinic_id WHERE p.user_id = ? AND cp.payment_status = 'paid' ORDER BY cp.paid_at DESC");
$stmt->execute([$patient['user_id']]);
$receipts = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT i.invoice_id, i.description, i.amount, i.status, i.created_at, c.clinic_name, COALESCE(paid.total_paid, 0) AS total_paid FROM clinic_invoices i INNER JOIN patients p ON p.patient_id = i.patient_id INNER JOIN clinics c ON c.clinic_id = i.clinic_id LEFT JOIN (SELECT invoice_id, SUM(amount) AS total_paid FROM clinic_payments WHERE payment_status = 'paid' AND invoice_id IS NOT NULL GROUP BY invoice_id) paid ON paid.invoice_id = i.invoice_id WHERE p.user_id = ? AND i.status <> 'void' ORDER BY i.created_at DESC");
$stmt->execute([$patient['user_id']]);
$invoices = $stmt->fetchAll();
$csrfToken = patientPortalCsrfToken();

patientPortalStart($patient, 'My history and receipts', 'history');
?>
<?php if (($_GET['payment'] ?? '') === 'pending'): ?><div class="notice">The online payment is still being confirmed. Refresh this page in a moment.</div><?php elseif (($_GET['checkout'] ?? '') === 'cancelled'): ?><div class="notice">Checkout was cancelled. Your invoice remains unpaid.</div><?php endif; ?>
<section class="card">
    <h3>Appointments and balances</h3>
    <?php if (!$appointments): ?><p class="empty-state">Your appointments and visits will appear here.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Clinic</th><th>Service</th><th>Dentist</th><th>Status</th><th>Balance</th></tr></thead><tbody>
        <?php foreach ($appointments as $appointment): $due = max(0, (float) $appointment['invoice_amount'] - (float) $appointment['total_paid']); ?>
        <tr><td><?php echo patientPortalEscape(date('M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></td><td><?php echo patientPortalEscape($appointment['clinic_name']); ?></td><td><?php echo patientPortalEscape($appointment['service_name'] ?: 'Visit'); ?></td><td><?php echo patientPortalEscape($appointment['dentist_name'] ?: 'Not assigned'); ?></td><td><span class="badge <?php echo patientPortalEscape($appointment['appointment_status']); ?>"><?php echo patientPortalEscape($appointment['appointment_status']); ?></span></td><td><?php echo $appointment['invoice_amount'] === null ? 'Not billed' : '₱' . number_format($due, 2); ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Invoices and balances</h3>
    <?php if (!$invoices): ?><p class="empty-state">There are no invoices on your account.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Clinic</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Payment</th></tr></thead><tbody>
    <?php foreach ($invoices as $invoice): $due = max(0, (float) $invoice['amount'] - (float) $invoice['total_paid']); ?>
        <tr><td><?php echo patientPortalEscape($invoice['description']); ?></td><td><?php echo patientPortalEscape($invoice['clinic_name']); ?></td><td><?php echo patientPortalEscape(date('M j, Y', strtotime($invoice['created_at']))); ?></td><td>₱<?php echo number_format((float) $invoice['amount'], 2); ?></td><td>₱<?php echo number_format((float) $invoice['total_paid'], 2); ?></td><td>₱<?php echo number_format($due, 2); ?></td><td><?php if ($due > 0.001 && str_starts_with((string) getenv('PAYMONGO_SECRET_KEY'), 'sk_test_')): ?><form method="post" action="checkout.php"><input type="hidden" name="csrf_token" value="<?php echo patientPortalEscape($csrfToken); ?>"><input type="hidden" name="invoice_id" value="<?php echo (int) $invoice['invoice_id']; ?>"><button class="button" type="submit"><i class="fas fa-lock"></i> Pay online</button></form><?php elseif ($due > 0.001): ?>Pay at clinic<?php else: ?><span class="badge">Paid</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Dental history</h3>
    <?php if (!$records): ?><p class="empty-state">No dental records are available yet.</p><?php else: ?>
        <?php foreach ($records as $record): $toothChart = json_decode($record['tooth_chart'] ?? '', true) ?: []; ?>
        <details style="padding: 14px 0; border-bottom: 1px solid #e9edf0;">
            <summary style="cursor:pointer;font-weight:600;"><?php echo patientPortalEscape(date('M j, Y', strtotime($record['recorded_at']))); ?> | <?php echo patientPortalEscape($record['clinic_name']); ?> | <?php echo patientPortalEscape($record['dentist_name']); ?></summary>
            <dl class="info-list" style="margin-top:14px;">
                <div><dt>Diagnosis</dt><dd><?php echo nl2br(patientPortalEscape($record['diagnosis'])); ?></dd></div>
                <div><dt>Examination findings</dt><dd><?php echo nl2br(patientPortalEscape($record['examination_findings'])); ?></dd></div>
                <div><dt>Treatment plan</dt><dd><?php echo nl2br(patientPortalEscape($record['treatment_plan'])); ?></dd></div>
                <div><dt>Clinical notes</dt><dd><?php echo nl2br(patientPortalEscape($record['clinical_notes'])); ?></dd></div>
                <?php if ($toothChart): ?><div><dt>Tooth chart (FDI)</dt><dd><?php foreach ($toothChart as $tooth => $condition): ?>Tooth <?php echo patientPortalEscape($tooth); ?>: <?php echo patientPortalEscape(str_replace('_', ' ', $condition)); ?><br><?php endforeach; ?></dd></div><?php endif; ?>
            </dl>
        </details>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Treatment history and planned procedures</h3>
    <?php if (!$treatments): ?><p class="empty-state">No procedures have been recorded.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Procedure</th><th>Tooth</th><th>Clinic</th><th>Dentist</th><th>Status</th><th>Notes</th></tr></thead><tbody><?php foreach ($treatments as $treatment): ?><tr><td><?php echo patientPortalEscape($treatment['treatment_date']); ?></td><td><?php echo patientPortalEscape($treatment['procedure_name']); ?></td><td><?php echo patientPortalEscape($treatment['tooth_number'] ?: 'General'); ?></td><td><?php echo patientPortalEscape($treatment['clinic_name']); ?></td><td><?php echo patientPortalEscape($treatment['dentist_name']); ?></td><td><span class="badge <?php echo patientPortalEscape($treatment['status']); ?>"><?php echo patientPortalEscape($treatment['status']); ?></span></td><td><?php echo patientPortalEscape($treatment['notes']); ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Documents and X-rays</h3>
    <?php if (!$documents): ?><p class="empty-state">No documents have been attached to your records yet.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Document</th><th>Type</th><th>Clinic</th><th>Uploaded</th><th></th></tr></thead><tbody><?php foreach ($documents as $document): ?><tr><td><?php echo patientPortalEscape($document['original_name']); ?></td><td><?php echo patientPortalEscape($document['document_type']); ?></td><td><?php echo patientPortalEscape($document['clinic_name']); ?></td><td><?php echo patientPortalEscape(date('M j, Y', strtotime($document['created_at']))); ?></td><td><a class="button-link secondary" href="document.php?id=<?php echo (int) $document['document_id']; ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Receipts</h3>
    <?php if (!$receipts): ?><p class="empty-state">Receipts for your payments will appear here.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Clinic</th><th>Paid</th><th>Date</th><th></th></tr></thead><tbody><?php foreach ($receipts as $receipt): ?><tr><td><?php echo patientPortalEscape($receipt['receipt_number']); ?></td><td><?php echo patientPortalEscape($receipt['clinic_name']); ?></td><td>₱<?php echo number_format((float) $receipt['amount'], 2); ?></td><td><?php echo patientPortalEscape(date('M j, Y g:i A', strtotime($receipt['paid_at']))); ?></td><td><a class="button-link secondary" href="receipt.php?id=<?php echo (int) $receipt['payment_id']; ?>">View receipt</a></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
<?php patientPortalEnd(); ?>