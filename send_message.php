<?php
require_once 'database/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$clinicId = (int) ($_POST['clinic_id'] ?? 0);
$senderName = trim($_POST['sender_name'] ?? '');
$senderEmail = trim($_POST['sender_email'] ?? '');
$senderPhone = trim($_POST['sender_phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($clinicId <= 0 || $senderName === '' || $senderEmail === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO clinic_messages (clinic_id, sender_id, sender_name, sender_email, sender_phone, message) VALUES (?, ?, ?, ?, ?, ?)");
    $senderId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $stmt->execute([$clinicId, $senderId, $senderName, $senderEmail, $senderPhone ?: null, $message]);

    echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again.']);
}
