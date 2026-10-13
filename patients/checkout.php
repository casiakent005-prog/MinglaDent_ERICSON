<?php
require_once 'bootstrap.php';

$patient = patientPortalContext();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !patientPortalVerifyCsrf()) {
    http_response_code(419);
    exit('Your session expired. Reload your history and try again.');
}

$secretKey = getenv('PAYMONGO_SECRET_KEY');
if (!$secretKey || !str_starts_with($secretKey, 'sk_test_') || !function_exists('curl_init')) {
    http_response_code(503);
    exit('PayMongo sandbox checkout is not configured. Contact the clinic to pay in person.');
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$invoiceStmt = $pdo->prepare("SELECT i.invoice_id, i.clinic_id, i.patient_id, i.appointment_id, i.description, i.amount, c.clinic_name FROM clinic_invoices i INNER JOIN patients p ON p.patient_id = i.patient_id INNER JOIN clinics c ON c.clinic_id = i.clinic_id WHERE i.invoice_id = ? AND p.user_id = ? AND i.status <> 'void' LIMIT 1");
$invoiceStmt->execute([$invoiceId, $patient['user_id']]);
$invoice = $invoiceStmt->fetch();

if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found.');
}

$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM clinic_payments WHERE invoice_id = ? AND payment_status = 'paid'");
$paidStmt->execute([$invoiceId]);
$balance = max(0, (float) $invoice['amount'] - (float) $paidStmt->fetchColumn());
if ($balance <= 0.001) {
    header('Location: history.php');
    exit;
}

$receiptNumber = 'MD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
$pdo->beginTransaction();
$pendingStmt = $pdo->prepare("INSERT INTO clinic_payments (clinic_id, patient_id, appointment_id, invoice_id, recorded_by, amount, payment_method, payment_status, receipt_number, notes) VALUES (?, ?, ?, ?, ?, ?, 'gcash', 'pending', ?, 'PayMongo sandbox checkout')");
$pendingStmt->execute([$invoice['clinic_id'], $invoice['patient_id'], $invoice['appointment_id'], $invoiceId, $patient['user_id'], $balance, $receiptNumber]);
$paymentId = (int) $pdo->lastInsertId();
$pdo->commit();

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$payload = [
    'data' => [
        'attributes' => [
            'line_items' => [[
                'currency' => 'PHP',
                'amount' => (int) round($balance * 100),
                'name' => mb_substr($invoice['description'], 0, 80),
                'quantity' => 1,
            ]],
            'payment_method_types' => ['gcash', 'card'],
            'description' => $invoice['clinic_name'] . ' invoice ' . $invoiceId,
            'success_url' => $baseUrl . '/payment_return.php?payment_id=' . $paymentId,
            'cancel_url' => $baseUrl . '/payment_return.php?payment_id=' . $paymentId . '&cancelled=1',
            'send_email_receipt' => false,
        ],
    ],
];

$curl = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_USERPWD => $secretKey . ':',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
$checkout = $response ? json_decode($response, true) : null;
$checkoutUrl = $checkout['data']['attributes']['checkout_url'] ?? null;
$checkoutId = $checkout['data']['id'] ?? null;

if ($httpStatus >= 200 && $httpStatus < 300 && $checkoutUrl && $checkoutId) {
    $stmt = $pdo->prepare("UPDATE clinic_payments SET gateway_reference = ? WHERE payment_id = ? AND clinic_id = ? AND payment_status = 'pending'");
    $stmt->execute([$checkoutId, $paymentId, $invoice['clinic_id']]);
    header('Location: ' . $checkoutUrl);
    exit;
}

$stmt = $pdo->prepare("UPDATE clinic_payments SET payment_status = 'failed', notes = 'PayMongo checkout session could not be created.' WHERE payment_id = ? AND clinic_id = ?");
$stmt->execute([$paymentId, $invoice['clinic_id']]);
http_response_code(502);
exit('Could not start PayMongo checkout. The clinic can still record an in-person payment.');