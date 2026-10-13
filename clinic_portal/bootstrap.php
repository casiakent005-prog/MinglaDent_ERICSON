<?php
require_once __DIR__ . '/../database/config.php';

function clinicPortalRequireRoles(array $allowedRoles): array
{
    global $pdo;

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
        header('Location: ../index.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT au.id AS user_id, au.name, au.email, au.status AS account_status, cs.staff_id, cs.clinic_id, cs.role AS staff_role, c.clinic_name FROM auth_users au INNER JOIN clinic_staff cs ON cs.user_id = au.id INNER JOIN clinics c ON c.clinic_id = cs.clinic_id WHERE au.id = ? AND au.status = 'active' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $context = $stmt->fetch();

    if (!$context) {
        http_response_code(403);
        exit('This account is not assigned to an active clinic.');
    }

    $context['portal_role'] = $context['staff_role'] === 'staff'
        ? 'assistant'
        : $context['staff_role'];

    if (!in_array($context['portal_role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }

    $_SESSION['clinic_id'] = $context['clinic_id'];
    $_SESSION['staff_role'] = $context['staff_role'];

    return $context;
}

function clinicPortalEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function clinicPortalCsrfToken(): string
{
    if (empty($_SESSION['clinic_portal_csrf'])) {
        $_SESSION['clinic_portal_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['clinic_portal_csrf'];
}

function clinicPortalVerifyCsrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['clinic_portal_csrf'])
        && hash_equals($_SESSION['clinic_portal_csrf'], $_POST['csrf_token']);
}

function clinicPortalAudit(PDO $pdo, ?int $actorId, ?int $clinicId, string $action, string $entityType, ?int $entityId = null): void
{
    $stmt = $pdo->prepare("INSERT INTO activity_logs (actor_user_id, clinic_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$actorId, $clinicId, $action, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function clinicPortalQueueAppointmentNotice(PDO $pdo, array $patient, int $clinicId, int $appointmentId, string $appointmentDate, string $type): void
{
    $clinicStmt = $pdo->prepare("SELECT clinic_name FROM clinics WHERE clinic_id = ?");
    $clinicStmt->execute([$clinicId]);
    $clinicName = (string) $clinicStmt->fetchColumn();
    $dateLabel = date('M j, Y g:i A', strtotime($appointmentDate));
    $message = $type === 'cancellation'
        ? "Your appointment at {$clinicName} on {$dateLabel} was cancelled."
        : "Your appointment at {$clinicName} is scheduled for {$dateLabel}.";
    $recipients = [['email', $patient['email'] ?? null], ['sms', $patient['mobile_number'] ?? null]];
    $stmt = $pdo->prepare("INSERT INTO notification_queue (clinic_id, patient_user_id, appointment_id, channel, notification_type, recipient, message, send_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($recipients as [$channel, $recipient]) {
        if ($recipient) {
            $stmt->execute([$clinicId, $patient['user_id'] ?? null, $appointmentId, $channel, $type, $recipient, $message, date('Y-m-d H:i:s')]);
        }
    }

    if ($type !== 'cancellation') {
        $reminderAt = max(time(), strtotime($appointmentDate) - 86400);
        $reminderMessage = "Reminder: your appointment at {$clinicName} is on {$dateLabel}.";
        foreach ($recipients as [$channel, $recipient]) {
            if ($recipient) {
                $stmt->execute([$clinicId, $patient['user_id'] ?? null, $appointmentId, $channel, 'reminder', $recipient, $reminderMessage, date('Y-m-d H:i:s', $reminderAt)]);
            }
        }
    }
}