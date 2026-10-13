<?php
require_once __DIR__ . '/../clinic_portal/bootstrap.php';

function systemAdminContext(): array
{
    global $pdo;

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../index.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id AS user_id, name, email, status FROM auth_users WHERE id = ? AND role = 'admin' AND status = 'active' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    return $admin;
}

function systemAdminRequireCsrf(): void
{
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}