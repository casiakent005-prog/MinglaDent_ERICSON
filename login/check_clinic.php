<?php
require_once '../database/config.php';

header('Content-Type: application/json');

$clinicId = trim($_POST['clinic_id'] ?? '');

if (empty($clinicId)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    // Check if clinic ID exists in clinics table
    $stmt = $pdo->prepare("SELECT clinic_id FROM clinics WHERE clinic_id = ? AND status = 'approved'");
    $stmt->execute([$clinicId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch(PDOException $e) {
    echo json_encode(['exists' => false]);
}
?>
