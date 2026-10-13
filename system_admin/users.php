<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$admin = systemAdminContext();
$csrfToken = clinicPortalCsrfToken();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    systemAdminRequireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_clinic_staff') {
        $clinicId = (int) ($_POST['clinic_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $staffRole = $_POST['staff_role'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !in_array($staffRole, ['clinic_admin', 'dentist', 'assistant', 'staff'], true)) {
            $message = 'Provide a name, valid email, password of at least 8 characters, and valid clinic role.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $clinicCheck = $pdo->prepare("SELECT clinic_id FROM clinics WHERE clinic_id = ? LIMIT 1");
                $clinicCheck->execute([$clinicId]);
                if (!$clinicCheck->fetchColumn()) {
                    throw new RuntimeException('Choose an existing clinic.');
                }
                $stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status) VALUES (?, ?, ?, 'clinic', 'active')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO clinic_staff (user_id, clinic_id, role) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $clinicId, $staffRole]);
                $pdo->commit();
                clinicPortalAudit($pdo, (int) $admin['user_id'], $clinicId, 'system_admin.user_created', 'user', $userId);
                $message = 'Clinic staff account created and assigned.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not create the account. Check whether the email already exists.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($userId < 1 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Enter a valid name and email address.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE auth_users SET name = ?, email = ? WHERE id = ? AND role <> 'admin'");
            $stmt->execute([$name, $email, $userId]);
            if ($stmt->rowCount()) {
                clinicPortalAudit($pdo, (int) $admin['user_id'], null, 'system_admin.user_updated', 'user', $userId);
                $message = 'Account details updated.';
            } else {
                $message = 'No changes saved, or global-admin accounts cannot be edited here.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'set_user_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($userId === (int) $admin['user_id']) {
            $message = 'You cannot deactivate your own system-admin account.';
            $messageType = 'error';
        } elseif (!in_array($status, ['active', 'inactive'], true)) {
            $message = 'Choose a valid account status.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE auth_users SET status = ? WHERE id = ? AND role <> 'admin'");
            $stmt->execute([$status, $userId]);
            if ($stmt->rowCount()) {
                clinicPortalAudit($pdo, (int) $admin['user_id'], null, 'system_admin.user_status_changed', 'user', $userId);
                $message = 'Account status updated.';
            } else {
                $message = 'System-admin accounts cannot be changed here, or the status is unchanged.';
                $messageType = 'error';
            }
        }
    }
}

$clinics = $pdo->query("SELECT clinic_id, clinic_name FROM clinics ORDER BY clinic_name")->fetchAll();
$users = $pdo->query("SELECT au.id, au.name, au.email, au.role, au.status, au.created_at, pp.mobile_number, GROUP_CONCAT(DISTINCT CONCAT(c.clinic_name, ' (', cs.role, ')') ORDER BY c.clinic_name SEPARATOR ', ') AS clinic_assignments FROM auth_users au LEFT JOIN patient_profiles pp ON pp.user_id = au.id LEFT JOIN clinic_staff cs ON cs.user_id = au.id LEFT JOIN clinics c ON c.clinic_id = cs.clinic_id GROUP BY au.id ORDER BY au.created_at DESC LIMIT 250")->fetchAll();

systemAdminStart($admin, 'Users & staff', 'users');
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <h3>Create clinic staff account</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="create_clinic_staff">
        <div class="form-field"><label for="name">Full name</label><input id="name" name="name" maxlength="255" required></div>
        <div class="form-field"><label for="email">Login email</label><input id="email" name="email" type="email" maxlength="255" required></div>
        <div class="form-field"><label for="password">Temporary password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
        <div class="form-field"><label for="clinic_id">Clinic</label><select id="clinic_id" name="clinic_id" required><option value="">Choose clinic</option><?php foreach ($clinics as $clinic): ?><option value="<?php echo (int) $clinic['clinic_id']; ?>"><?php echo clinicPortalEscape($clinic['clinic_name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="staff_role">Role</label><select id="staff_role" name="staff_role" required><option value="clinic_admin">Clinic Admin</option><option value="dentist">Dentist</option><option value="assistant">Dental Assistant / Cashier / Staff</option></select></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-user-plus"></i> Create account</button></div>
    </form>
</section>

<section class="card">
    <h3>Platform accounts</h3>
    <div class="table-wrap"><table><thead><tr><th>Account</th><th>Role</th><th>Clinic assignment</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($users as $user): ?>
        <tr><td><?php echo clinicPortalEscape($user['name']); ?><br><small><?php echo clinicPortalEscape($user['email']); ?></small><?php if ($user['mobile_number']): ?><br><small><?php echo clinicPortalEscape($user['mobile_number']); ?></small><?php endif; ?></td><td><?php echo clinicPortalEscape($user['role']); ?></td><td><?php echo clinicPortalEscape($user['clinic_assignments'] ?: 'Platform'); ?></td><td><span class="badge <?php echo clinicPortalEscape($user['status']); ?>"><?php echo clinicPortalEscape($user['status']); ?></span></td><td><?php echo clinicPortalEscape(date('M j, Y', strtotime($user['created_at']))); ?></td><td><?php if ($user['role'] !== 'admin'): ?><div class="actions"><details><summary style="cursor:pointer;">Edit</summary><form method="post" class="form-grid" style="min-width:280px;margin-top:8px;"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>"><div class="form-field"><label>Name</label><input name="name" value="<?php echo clinicPortalEscape($user['name']); ?>" required></div><div class="form-field"><label>Email</label><input name="email" type="email" value="<?php echo clinicPortalEscape($user['email']); ?>" required></div><button class="button secondary" type="submit">Save</button></form></details><form method="post"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="set_user_status"><input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>"><input type="hidden" name="status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="button <?php echo $user['status'] === 'active' ? 'danger' : 'secondary'; ?>" type="submit"><?php echo $user['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button></form></div><?php else: ?>Protected<?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php systemAdminEnd(); ?>