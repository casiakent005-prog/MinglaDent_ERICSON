<?php
function authEscape($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function authCsrfToken(): string {
    if (empty($_SESSION['auth_csrf'])) {
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['auth_csrf'];
}
function authRequireCsrf(): void {
    if (!is_string($_POST['csrf_token'] ?? null)
        || !hash_equals(authCsrfToken(), $_POST['csrf_token'])) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}
function authStartSession(array $user): void {
    if (!in_array($user['status'], ['active', 'pending'], true)
        || ($user['status'] === 'pending' && $user['role'] !== 'clinic')) {
        throw new RuntimeException('This account is inactive. Contact MinglaDent for assistance.');
    }
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $user['id'], 'name' => $user['name'],
        'email' => $user['email'], 'role' => $user['role'],
    ];
}
function authClinicAssignment(int $userId): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT au.id AS user_id, au.name, au.email, au.role AS account_role,
        au.status AS account_status, cs.staff_id, cs.clinic_id, cs.role AS staff_role,
        cs.is_dentist, c.clinic_name, c.status AS clinic_status, c.owner_user_id,
        c.rejection_reason, c.reviewed_at
        FROM auth_users au JOIN clinic_staff cs ON cs.user_id = au.id
        JOIN clinics c ON c.clinic_id = cs.clinic_id
        WHERE au.id = ? AND au.role = 'clinic' LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}
function authDestination(array $user): string {
    if ($user['role'] === 'patient') return '../patients/dashboard.php';
    if ($user['role'] === 'admin' && $user['status'] === 'active') return '../system_admin/dashboard.php';
    if ($user['role'] === 'clinic') {
        $assignment = authClinicAssignment((int) $user['id']);
        if (!$assignment || $assignment['clinic_status'] !== 'approved' || $user['status'] !== 'active') {
            return '../login/clinic_status.php';
        }
        if ($assignment['staff_role'] === 'clinic_admin') return '../clinic_admin/dashboard.php';
        if ($assignment['staff_role'] === 'dentist') return '../clinic_dentist/dashboard.php';
        if (in_array($assignment['staff_role'], ['assistant', 'staff'], true)) return '../clinic_assistant/dashboard.php';
    }
    return '../index.php';
}
function authRequireClinic(): array {
    if (!hasRole('clinic')) {
        http_response_code(403);
        exit('Clinic access is required.');
    }
    $context = authClinicAssignment((int) $_SESSION['user_id']);
    if (!$context || $context['account_status'] !== 'active' || $context['clinic_status'] !== 'approved') {
        redirect('../login/clinic_status.php');
    }
    $_SESSION['clinic_id'] = $context['clinic_id'];
    $_SESSION['staff_role'] = $context['staff_role'];
    return $context;
}
function authRestoreRememberSession(): void {
    global $pdo;
    if (isLoggedIn() || !is_string($_COOKIE['remember_token'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['remember_token'])) return;
    $stmt = $pdo->prepare("SELECT u.* FROM remember_tokens t JOIN auth_users u ON u.id = t.user_id WHERE t.token = ? AND t.expires_at > NOW() AND u.status = 'active' LIMIT 1");
    $stmt->execute([hash('sha256', $_COOKIE['remember_token'])]);
    if ($user = $stmt->fetch()) authStartSession($user);
}
