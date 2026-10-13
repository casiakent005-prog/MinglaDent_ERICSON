<?php
require_once 'bootstrap.php';

$patient = patientPortalContext();
$paymentId = (int) ($_GET['payment_id'] ?? 0);
$stmt = $pdo->prepare("SELECT cp.payment_id, cp.clinic_id, cp.patient_id, cp.invoice_id, cp.amount, cp.payment_status, cp.gateway_reference, cp.receipt_number, i.amount AS invoice_amount FROM clinic_payments cp INNER JOIN patients p ON p.patient_id = cp.patient_id INNER JOIN clinic_invoices i ON i.invoice_id = cp.invoice_id WHERE cp.payment_id = ? AND p.user_id = ? LIMIT 1");
$stmt->execute([$paymentId, $patient['user_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    exit('Payment not found.');
}

if (isset($_GET['cancelled']) && $payment['payment_status'] === 'pending') {
    $stmt = $pdo->prepare("UPDATE clinic_payments SET payment_status = 'failed', notes = 'PayMongo checkout was cancelled by the patient.' WHERE payment_id = ? AND payment_status = 'pending'");
    $stmt->execute([$paymentId]);
    header('Location: history.php?checkout=cancelled');
    exit;
}

if ($payment['payment_status'] === 'pending' && $payment['gateway_reference']) {
    $secretKey = getenv('PAYMONGO_SECRET_KEY');
    if ($secretKey && str_starts_with($secretKey, 'sk_test_') && function_exists('curl_init')) {
        $curl = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($payment['gateway_reference']));
        curl_setopt_array($curl, [CURLOPT_USERPWD => $secretKey . ':', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $response = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response && $httpStatus >= 200 && $httpStatus < 300) {
            $checkout = json_decode($response, true);
            $remotePayments = $checkout['data']['attributes']['payments'] ?? [];
            foreach ($remotePayments as $remotePayment) {
                $attributes = $remotePayment['attributes'] ?? [];
                if (($attributes['status'] ?? '') !== 'paid' || (int) ($attributes['amount'] ?? 0) !== (int) round((float) $payment['amount'] * 100)) {
                    continue;
                }

                $pdo->beginTransaction();
                $lockInvoice = $pdo->prepare("SELECT amount FROM clinic_invoices WHERE invoice_id = ? AND clinic_id = ? FOR UPDATE");
                $lockInvoice->execute([$payment['invoice_id'], $payment['clinic_id']]);
                $invoiceAmount = (float) $lockInvoice->fetchColumn();
                $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM clinic_payments WHERE invoice_id = ? AND payment_status = 'paid'");
                $sumStmt->execute([$payment['invoice_id']]);
                $paidBefore = (float) $sumStmt->fetchColumn();

                if ($invoiceAmount >= $paidBefore + (float) $payment['amount'] - 0.001) {
                    $updatePayment = $pdo->prepare("UPDATE clinic_payments SET payment_status = 'paid', reference_number = ? WHERE payment_id = ? AND payment_status = 'pending'");
                    $updatePayment->execute([$remotePayment['id'] ?? null, $paymentId]);
                    $totalPaid = $paidBefore + (float) $payment['amount'];
                    $invoiceStatus = $totalPaid >= $invoiceAmount - 0.001 ? 'paid' : 'partial';
                    $updateInvoice = $pdo->prepare("UPDATE clinic_invoices SET status = ? WHERE invoice_id = ? AND clinic_id = ?");
                    $updateInvoice->execute([$invoiceStatus, $payment['invoice_id'], $payment['clinic_id']]);
                    $patientStmt = $pdo->prepare("SELECT user_id, email, phone AS mobile_number FROM patients WHERE patient_id = ? AND clinic_id = ? LIMIT 1");
                    $patientStmt->execute([$payment['patient_id'], $payment['clinic_id']]);
                    $patientContact = $patientStmt->fetch();
                    if ($patientContact) {
                        $noticeStmt = $pdo->prepare("INSERT INTO notification_queue (clinic_id, patient_user_id, appointment_id, channel, notification_type, recipient, message, send_at) VALUES (?, ?, ?, ?, 'receipt', ?, ?, NOW())");
                        foreach ([['email', $patientContact['email']], ['sms', $patientContact['mobile_number']]] as [$channel, $recipient]) {
                            if ($recipient) {
                                $noticeStmt->execute([$payment['clinic_id'], $patientContact['user_id'], null, $channel, $recipient, 'Online payment received. Receipt ' . $payment['receipt_number'] . ' is available in your MinglaDent patient history.']);
                            }
                        }
                    }
                    $pdo->commit();
                    clinicPortalAudit($pdo, (int) $patient['user_id'], (int) $payment['clinic_id'], 'patient.online_payment_verified', 'payment', $paymentId);
                    $payment['payment_status'] = 'paid';
                } else {
                    $pdo->rollBack();
                }
                break;
            }
        }
    }
}

header('Location: history.php?payment=' . ($payment['payment_status'] === 'paid' ? 'paid' : 'pending'));
exit;