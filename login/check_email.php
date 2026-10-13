<?php
require_once '../database/config.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM auth_users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch(PDOException $e) {
    echo json_encode(['exists' => false]);
}
?>
