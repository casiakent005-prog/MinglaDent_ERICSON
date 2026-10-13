<?php
require_once __DIR__ . '/../clinic_portal/bootstrap.php';

function clinicAdminAuthorize(): void
{
    global $pdo;

    if (($_SESSION['role'] ?? '') !== 'clinic') {
        return;
    }

    $stmt = $pdo->prepare("SELECT cs.role, cs.clinic_id, au.status FROM clinic_staff cs INNER JOIN auth_users au ON au.id = cs.user_id WHERE cs.user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $assignment = $stmt->fetch();

    if (!$assignment || $assignment['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    $_SESSION['clinic_id'] = $assignment['clinic_id'];
    $_SESSION['staff_role'] = $assignment['role'];

    if ($assignment['role'] === 'clinic_admin') {
        return;
    }

    $destination = $assignment['role'] === 'dentist'
        ? '../clinic_dentist/dashboard.php'
        : '../clinic_assistant/dashboard.php';
    header('Location: ' . $destination);
    exit;
}