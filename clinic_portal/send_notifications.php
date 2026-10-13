<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../database/config.php';

$stmt = $pdo->query("SELECT * FROM notification_queue WHERE status = 'pending' AND send_at <= NOW() ORDER BY send_at, notification_id LIMIT 50");
$notifications = $stmt->fetchAll();
$sent = 0;
$failed = 0;

foreach ($notifications as $notification) {
    $delivered = false;
    $error = 'Provider is not configured.';

    if ($notification['channel'] === 'email') {
        $fromAddress = getenv('MINGLADENT_MAIL_FROM');
        if ($fromAddress && filter_var($notification['recipient'], FILTER_VALIDATE_EMAIL)) {
            $headers = "From: MinglaDent <{$fromAddress}>\r\nContent-Type: text/plain; charset=UTF-8";
            $delivered = mail($notification['recipient'], 'MinglaDent appointment or payment update', $notification['message'], $headers);
            $error = $delivered ? null : 'PHP mail() delivery failed.';
        }
    } elseif ($notification['channel'] === 'sms') {
        $accountSid = getenv('TWILIO_ACCOUNT_SID');
        $authToken = getenv('TWILIO_AUTH_TOKEN');
        $fromNumber = getenv('TWILIO_FROM_NUMBER');
        if ($accountSid && $authToken && $fromNumber && function_exists('curl_init')) {
            $curl = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['To' => $notification['recipient'], 'From' => $fromNumber, 'Body' => $notification['message']]),
                CURLOPT_USERPWD => $accountSid . ':' . $authToken,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ]);
            $response = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            $delivered = $response !== false && $httpStatus >= 200 && $httpStatus < 300;
            $error = $delivered ? null : ($curlError ?: 'SMS provider returned HTTP ' . $httpStatus . '.');
        }
    }

    $status = $delivered ? 'sent' : 'failed';
    $update = $pdo->prepare("UPDATE notification_queue SET status = ?, attempts = attempts + 1, last_error = ?, sent_at = ? WHERE notification_id = ? AND status = 'pending'");
    $update->execute([$status, $error, $delivered ? date('Y-m-d H:i:s') : null, $notification['notification_id']]);
    $delivered ? $sent++ : $failed++;
}

printf("Processed %d notifications: %d sent, %d failed.\n", count($notifications), $sent, $failed);