<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['assistant']);
$message = '';
$messageType = 'success';
$csrfToken = clinicPortalCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $messageId = (int) ($_POST['message_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['pending', 'read', 'replied'], true)) {
        $message = 'Choose a valid message status.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE clinic_messages SET status = ? WHERE message_id = ? AND clinic_id = ?");
            $stmt->execute([$status, $messageId, $context['clinic_id']]);
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'assistant.message_status_updated', 'message', $messageId);
            $message = 'Message status updated.';
        } catch (Throwable $exception) {
            $message = 'Could not update the message status.';
            $messageType = 'error';
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$statusCondition = '';
$params = [$context['clinic_id']];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'read', 'replied'], true)) {
    $statusCondition = ' AND status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT * FROM clinic_messages WHERE clinic_id = ?{$statusCondition} ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$messages = $stmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'payments', 'label' => 'Payments', 'href' => 'payments.php', 'icon' => 'fa-cash-register'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'fa-envelope'],
    ['key' => 'activity', 'label' => 'Activity log', 'href' => 'activity.php', 'icon' => 'fa-clipboard-list'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fa-chart-line'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Messages', 'messages', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <div class="week-nav"><h3>Clinic Messages</h3><form method="get" class="actions"><select name="status" aria-label="Filter by status"><option value="">All messages</option><option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="read" <?php echo $statusFilter === 'read' ? 'selected' : ''; ?>>Read</option><option value="replied" <?php echo $statusFilter === 'replied' ? 'selected' : ''; ?>>Replied</option></select><button class="button secondary" type="submit"><i class="fas fa-filter"></i> Filter</button></form></div>
    <?php if (!$messages): ?><p class="empty-state">No messages yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Sender</th><th>Email</th><th>Phone</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody><?php foreach ($messages as $msg): ?>
            <tr>
                <td><?php echo clinicPortalEscape(date('M j, Y g:i A', strtotime($msg['created_at']))); ?></td>
                <td><?php echo clinicPortalEscape($msg['sender_name']); ?></td>
                <td><?php echo clinicPortalEscape($msg['sender_email']); ?></td>
                <td><?php echo clinicPortalEscape($msg['sender_phone'] ?: 'Not provided'); ?></td>
                <td><?php echo clinicPortalEscape(substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : '')); ?></td>
                <td><span class="badge <?php echo clinicPortalEscape($msg['status']); ?>"><?php echo clinicPortalEscape(ucfirst($msg['status'])); ?></span></td>
                <td><form method="post" class="actions"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="message_id" value="<?php echo (int) $msg['message_id']; ?>"><select name="status" aria-label="Message status"><?php foreach (['pending', 'read', 'replied'] as $status): ?><option value="<?php echo clinicPortalEscape($status); ?>" <?php echo $msg['status'] === $status ? 'selected' : ''; ?>><?php echo clinicPortalEscape(ucfirst($status)); ?></option><?php endforeach; ?></select><button class="button secondary" type="submit">Save</button></form></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>

<style>
.badge.pending {
    background: #fff3cd;
    color: #856404;
}

.badge.read {
    background: #d1ecf1;
    color: #0c5460;
}

.badge.replied {
    background: #e7f4e8;
    color: #28633b;
}
</style>

<?php clinicPortalEnd(); ?>
