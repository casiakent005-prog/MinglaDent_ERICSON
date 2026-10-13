<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['assistant']);
$message = '';
$messageType = 'success';
$csrfToken = clinicPortalCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $amount = $_POST['amount'] ?? '';
    $method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 99999999.99 || !in_array($method, ['cash', 'card', 'gcash', 'bank_transfer', 'other'], true)) {
        $message = 'Check the invoice, amount, and payment method.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            $invoiceStmt = $pdo->prepare("SELECT invoice_id, patient_id, appointment_id, amount, status FROM clinic_invoices WHERE invoice_id = ? AND clinic_id = ? AND status <> 'void' FOR UPDATE");
            $invoiceStmt->execute([$invoiceId, $context['clinic_id']]);
            $invoice = $invoiceStmt->fetch();

            if (!$invoice) {
                throw new RuntimeException('Invoice not found.');
            }

            $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM clinic_payments WHERE invoice_id = ? AND payment_status = 'paid'");
            $paidStmt->execute([$invoiceId]);
            $balance = max(0, (float) $invoice['amount'] - (float) $paidStmt->fetchColumn());
            if ((float) $amount > $balance + 0.001) {
                throw new RuntimeException('Payment cannot be greater than the invoice balance.');
            }

            $receiptNumber = 'MD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
            $stmt = $pdo->prepare("INSERT INTO clinic_payments (clinic_id, patient_id, appointment_id, invoice_id, recorded_by, amount, payment_method, payment_status, reference_number, receipt_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?)");
            $stmt->execute([$context['clinic_id'], $invoice['patient_id'], $invoice['appointment_id'], $invoiceId, $context['user_id'], $amount, $method, $reference ?: null, $receiptNumber, $notes ?: null]);
            $paymentId = (int) $pdo->lastInsertId();

            $remaining = $balance - (float) $amount;
            $invoiceStatus = $remaining <= 0.001 ? 'paid' : 'partial';
            $statusStmt = $pdo->prepare("UPDATE clinic_invoices SET status = ? WHERE invoice_id = ? AND clinic_id = ?");
            $statusStmt->execute([$invoiceStatus, $invoiceId, $context['clinic_id']]);
            $contactStmt = $pdo->prepare("SELECT user_id, email, phone FROM patients WHERE patient_id = ? AND clinic_id = ? LIMIT 1");
            $contactStmt->execute([$invoice['patient_id'], $context['clinic_id']]);
            $contact = $contactStmt->fetch();
            if ($contact) {
                $noticeStmt = $pdo->prepare("INSERT INTO notification_queue (clinic_id, patient_user_id, appointment_id, channel, notification_type, recipient, message, send_at) VALUES (?, ?, ?, ?, 'receipt', ?, ?, NOW())");
                foreach ([['email', $contact['email']], ['sms', $contact['phone']]] as [$channel, $recipient]) {
                    if ($recipient) {
                        $noticeStmt->execute([$context['clinic_id'], $contact['user_id'], $invoice['appointment_id'], $channel, $recipient, 'Payment received. Receipt ' . $receiptNumber . ' is available in your MinglaDent patient history.']);
                    }
                }
            }
            $pdo->commit();
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'assistant.payment_recorded', 'payment', $paymentId);

            header('Location: receipt.php?id=' . $paymentId);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Could not record payment. Please try again.';
            $messageType = 'error';
        }
    }
}

$stmt = $pdo->prepare("SELECT i.invoice_id, i.patient_id, i.appointment_id, i.description, i.amount, p.name AS patient_name, COALESCE(SUM(cp.amount), 0) AS total_paid FROM clinic_invoices i INNER JOIN patients p ON p.patient_id = i.patient_id LEFT JOIN clinic_payments cp ON cp.invoice_id = i.invoice_id AND cp.payment_status = 'paid' WHERE i.clinic_id = ? AND i.status <> 'void' GROUP BY i.invoice_id ORDER BY i.created_at DESC");
$stmt->execute([$context['clinic_id']]);
$invoices = $stmt->fetchAll();
$openInvoices = array_values(array_filter($invoices, static fn($invoice) => (float) $invoice['amount'] - (float) $invoice['total_paid'] > 0.001));

$stmt = $pdo->prepare("SELECT cp.payment_id, cp.patient_id, cp.amount, cp.payment_method, cp.receipt_number, cp.paid_at, p.name AS patient_name FROM clinic_payments cp INNER JOIN patients p ON p.patient_id = cp.patient_id WHERE cp.clinic_id = ? AND cp.payment_status = 'paid' ORDER BY cp.paid_at DESC LIMIT 100");
$stmt->execute([$context['clinic_id']]);
$payments = $stmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'payments', 'label' => 'Payments', 'href' => 'payments.php', 'icon' => 'fa-cash-register'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'fa-envelope'],
    ['key' => 'activity', 'label' => 'Activity log', 'href' => 'activity.php', 'icon' => 'fa-clipboard-list'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fa-chart-line'],
];

clinicPortalStart($context, 'Payments', 'payments', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <h3>Outstanding invoices</h3>
    <?php if (!$openInvoices): ?><p class="empty-state">No outstanding invoices. Mark a billed appointment as completed to generate its invoice.</p><?php else: ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="record_payment">
        <div class="form-field full"><label for="invoice_id">Invoice</label><select id="invoice_id" name="invoice_id" required><option value="">Choose an outstanding invoice</option><?php foreach ($openInvoices as $invoice): $invoiceBalance = (float) $invoice['amount'] - (float) $invoice['total_paid']; ?><option value="<?php echo (int) $invoice['invoice_id']; ?>"><?php echo clinicPortalEscape($invoice['patient_name'] . ' | ' . $invoice['description'] . ' | balance ₱' . number_format($invoiceBalance, 2)); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="amount">Amount (PHP)</label><input id="amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" required></div>
        <div class="form-field"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method" required><option value="cash">Cash / over the counter</option><option value="card">Card (in clinic)</option><option value="gcash">GCash (record reference)</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select></div>
        <div class="form-field"><label for="reference_number">Reference number</label><input id="reference_number" name="reference_number" maxlength="100"></div>
        <div class="form-field"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="500"></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-receipt"></i> Record payment and issue e-receipt</button></div>
    </form>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Recent payments</h3>
    <?php if (!$payments): ?><p class="empty-state">No payments have been recorded.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Receipt</th><th>Patient ID</th><th>Date</th><th>Method</th><th>Amount</th><th></th></tr></thead>
        <tbody><?php foreach ($payments as $payment): ?>
            <tr><td><?php echo clinicPortalEscape($payment['receipt_number']); ?></td><td><?php echo clinicPortalEscape($payment['patient_name']); ?></td><td><?php echo clinicPortalEscape(date('M j, Y g:i A', strtotime($payment['paid_at']))); ?></td><td><?php echo clinicPortalEscape(ucwords(str_replace('_', ' ', $payment['payment_method']))); ?></td><td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td><td><a class="button-link secondary" href="receipt.php?id=<?php echo (int) $payment['payment_id']; ?>">View receipt</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php clinicPortalEnd(); ?>