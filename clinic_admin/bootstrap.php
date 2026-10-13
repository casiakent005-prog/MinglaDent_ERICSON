<?php
require_once __DIR__ . '/../database/config.php';

function clinicAdminContext(): array
{
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }

    $role = $_SESSION['role'] ?? '';

    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT id AS user_id, name, email, status AS account_status FROM auth_users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $context = $stmt->fetch();

        if (!$context) {
            header('Location: ../login/logout.php');
            exit;
        }

        $context['portal_role'] = 'admin';
        $context['clinic_id'] = null;
        $context['clinic_name'] = 'System Administration';
        $context['staff_id'] = null;
        $context['staff_role'] = 'admin';

        return $context;
    }

    if ($role === 'clinic') {
        $stmt = $pdo->prepare("SELECT au.id AS user_id, au.name, au.email, au.status AS account_status, cs.staff_id, cs.clinic_id, cs.role AS staff_role, c.clinic_name FROM auth_users au INNER JOIN clinic_staff cs ON cs.user_id = au.id INNER JOIN clinics c ON c.clinic_id = cs.clinic_id WHERE au.id = ? AND au.status = 'active' LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $context = $stmt->fetch();

        if (!$context) {
            http_response_code(403);
            exit('This account is not assigned to an active clinic.');
        }

        if ($context['staff_role'] !== 'clinic_admin') {
            header('Location: ../clinic_portal/dashboard.php');
            exit;
        }

        $context['portal_role'] = 'clinic_admin';
        $_SESSION['clinic_id'] = $context['clinic_id'];
        $_SESSION['staff_role'] = $context['staff_role'];

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
    return isset($_POST['csrf_token'], $_SESSION['clinic_admin_csrf'])
        && hash_equals($_SESSION['clinic_admin_csrf'], $_POST['csrf_token']);
}

function clinicAdminRequireCsrf(): void
{
    if (!clinicAdminVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}
