<?php
require_once __DIR__ . '/../database/config.php';

function clinicAdminContext(): array
{
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }

    $user = getCurrentUser();
    if ($user && $user['role'] === 'admin' && $user['status'] === 'active') {
        return ['user_id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'],
            'account_status' => $user['status'], 'portal_role' => 'admin', 'clinic_id' => null,
            'clinic_name' => 'System Administration', 'staff_id' => null, 'staff_role' => 'admin'];
    }
    if ($user && $user['role'] === 'clinic') {
        $context = authRequireClinic();
        if ($context['staff_role'] !== 'clinic_admin') {
            http_response_code(403);
            exit('Clinic administrator access is required.');
        }
        $context['portal_role'] = 'clinic_admin';
        return $context;
    }

    header('Location: ../index.php');
    exit;
}

function clinicAdminCsrfToken(): string
{
    if (empty($_SESSION['clinic_admin_csrf'])) {
        $_SESSION['clinic_admin_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['clinic_admin_csrf'];
}

function clinicAdminVerifyCsrf(): bool
{
    return is_string($_POST['csrf_token'] ?? null) && isset($_SESSION['clinic_admin_csrf'])
        && hash_equals($_SESSION['clinic_admin_csrf'], $_POST['csrf_token']);
}

function clinicAdminRequireCsrf(): void
{
    if (!clinicAdminVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}
