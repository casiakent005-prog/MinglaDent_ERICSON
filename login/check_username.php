<?php
require_once '../database/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM auth_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $exists = $stmt->fetch() !== false;

        echo json_encode(['exists' => $exists]);
    } catch (PDOException $e) {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false]);
}
?>
