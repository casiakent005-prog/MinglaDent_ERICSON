<?php
require_once __DIR__ . '/../clinic_portal/bootstrap.php';

function patientPortalContext(): array
{
    global $pdo;

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
        header('Location: ../index.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT au.id AS user_id, au.name, au.email, au.status, pp.mobile_number, pp.address, pp.date_of_birth, pp.emergency_contact_name, pp.emergency_contact_phone, pp.privacy_consent_at FROM auth_users au INNER JOIN patient_profiles pp ON pp.user_id = au.id WHERE au.id = ? AND au.role = 'patient' AND au.status = 'active' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch();

    if (!$patient) {
        http_response_code(403);
        exit('Patient profile not found. Contact the clinic for assistance.');
    }

    return $patient;
}

function patientPortalEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function patientPortalCsrfToken(): string
{
    if (empty($_SESSION['patient_csrf'])) {
        $_SESSION['patient_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['patient_csrf'];
}

function patientPortalVerifyCsrf(): bool
{
    return is_string($_POST['csrf_token'] ?? null) && isset($_SESSION['patient_csrf'])
        && hash_equals($_SESSION['patient_csrf'], $_POST['csrf_token']);
}

