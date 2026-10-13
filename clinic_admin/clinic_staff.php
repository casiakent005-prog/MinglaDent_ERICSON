<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();
$csrfToken = clinicAdminCsrfToken();
$isGlobalAdmin = $context['portal_role'] === 'admin';
$assignedClinicId = $isGlobalAdmin ? null : (int) ($context['clinic_id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    clinicAdminRequireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $clinic_id = $isGlobalAdmin ? (int) ($_POST['clinic_id'] ?? 0) : $assignedClinicId;
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? '';

        if (!in_array($role, ['clinic_admin', 'dentist', 'assistant', 'staff'], true)) {
            http_response_code(400);
            exit('Invalid staff role.');
        }

        $clinicCheck = $pdo->prepare("SELECT clinic_id FROM clinics WHERE clinic_id = ? LIMIT 1");
        $clinicCheck->execute([$clinic_id]);
        if (!$clinicCheck->fetchColumn()) {
            http_response_code(400);
            exit('Choose a valid clinic.');
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status) VALUES (?, ?, ?, 'clinic', 'active')");
        $stmt->execute([$name, $email, $hashed_password]);

        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO clinic_staff (user_id, clinic_id, role) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $clinic_id, $role]);
        clinicPortalAudit($pdo, (int) $context['user_id'], $clinic_id, 'admin.staff_created', 'user', (int) $user_id);
    } elseif ($action === 'edit_staff') {
        $staff_id = (int) ($_POST['staff_id'] ?? 0);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'] ?? '';

        if (!in_array($role, ['clinic_admin', 'dentist', 'assistant', 'staff'], true)) {
            http_response_code(400);
            exit('Invalid staff role.');
        }

        $staffCheck = $pdo->prepare("SELECT user_id FROM clinic_staff WHERE user_id = ?" . ($isGlobalAdmin ? '' : ' AND clinic_id = ?') . " LIMIT 1");
        $staffCheck->execute($isGlobalAdmin ? [$staff_id] : [$staff_id, $assignedClinicId]);
        if (!$staffCheck->fetchColumn()) {
            http_response_code(404);
            exit('Staff member not found in this clinic.');
        }

        $stmt = $pdo->prepare("UPDATE auth_users SET name = ?, email = ?, role = 'clinic' WHERE id = ?");
        $stmt->execute([$name, $email, $staff_id]);

        $stmt = $pdo->prepare("UPDATE clinic_staff SET role = ? WHERE user_id = ?");
        $stmt->execute([$role, $staff_id]);
        clinicPortalAudit($pdo, (int) $context['user_id'], $assignedClinicId, 'admin.staff_updated', 'user', $staff_id);
    } elseif ($action === 'set_staff_status') {
        $staff_id = (int) ($_POST['staff_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active', 'inactive'], true)) {
            http_response_code(400);
            exit('Invalid account status.');
        }
        $staffCheck = $pdo->prepare("SELECT user_id FROM clinic_staff WHERE user_id = ?" . ($isGlobalAdmin ? '' : ' AND clinic_id = ?') . " LIMIT 1");
        $staffCheck->execute($isGlobalAdmin ? [$staff_id] : [$staff_id, $assignedClinicId]);
        if ($staffCheck->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE auth_users SET status = ? WHERE id = ? AND role = 'clinic'");
            $stmt->execute([$status, $staff_id]);
            $staffClinicStmt = $pdo->prepare("SELECT clinic_id FROM clinic_staff WHERE user_id = ? LIMIT 1");
            $staffClinicStmt->execute([$staff_id]);
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $staffClinicStmt->fetchColumn(), 'admin.staff_status_changed', 'user', $staff_id);
        }
    }
}

// Get all staff with clinic info
$staffSql = "
    SELECT cs.*, au.name, au.email, au.role as user_role, au.status AS account_status, c.clinic_name
    FROM clinic_staff cs
    JOIN auth_users au ON cs.user_id = au.id
    JOIN clinics c ON cs.clinic_id = c.clinic_id
    " . ($isGlobalAdmin ? '' : ' WHERE cs.clinic_id = ?') . " ORDER BY c.clinic_name, au.name";
$staffStmt = $pdo->prepare($staffSql);
$staffStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$staff = $staffStmt->fetchAll();

// Get all clinics
$clinicStmt = $pdo->prepare("SELECT * FROM clinics" . ($isGlobalAdmin ? '' : ' WHERE clinic_id = ?') . " ORDER BY clinic_name");
$clinicStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$clinics = $clinicStmt->fetchAll();

clinicAdminStart($context, 'Manage Staff', 'staff');
?>

<section class="card">
    <div class="week-nav"><h3>All Staff</h3><button class="button" onclick="openModal('addStaffModal')"><i class="fas fa-plus"></i> Add Staff</button></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Clinic</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($staff as $member): ?>
            <tr>
                <td><?php echo clinicPortalEscape($member['name']); ?></td>
                <td><?php echo clinicPortalEscape($member['email']); ?></td>
                <td><?php echo clinicPortalEscape($member['clinic_name']); ?></td>
                <td><span class="badge"><?php echo ucfirst(clinicPortalEscape(str_replace('_', ' ', $member['role']))); ?></span></td>
                <td><span class="badge <?php echo clinicPortalEscape($member['account_status']); ?>"><?php echo ucfirst(clinicPortalEscape($member['account_status'])); ?></span></td>
                <td>
                    <div class="actions">
                        <button class="button secondary" onclick="editStaff(<?php echo (int) $member['user_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="set_staff_status"><input type="hidden" name="staff_id" value="<?php echo (int) $member['user_id']; ?>"><input type="hidden" name="status" value="<?php echo $member['account_status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="button danger" type="submit"><i class="fas fa-power-off"></i> <?php echo $member['account_status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button></form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    position: relative;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.close-modal {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 28px;
    color: #7f8c8d;
    cursor: pointer;
    font-weight: bold;
    transition: color 0.3s;
}

.close-modal:hover {
    color: #e74c3c;
}

.modal-content h2 {
    color: #2c3e50;
    font-size: 24px;
    margin-bottom: 25px;
}
</style>

<div id="addStaffModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addStaffModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Staff</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="add_staff">
            <div class="form-grid">
                <div class="form-field"><label>Name</label><input type="text" name="name" required></div>
                <div class="form-field"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-field full"><label>Password</label><input type="password" name="password" required></div>
                <?php if ($isGlobalAdmin): ?>
                <div class="form-field full"><label>Clinic</label><select name="clinic_id" required><?php foreach ($clinics as $clinic): ?><option value="<?php echo (int) $clinic['clinic_id']; ?>"><?php echo clinicPortalEscape($clinic['clinic_name']); ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
                <div class="form-field full"><label>Role</label><select name="role" required><option value="clinic_admin">Clinic Admin</option><option value="dentist">Dentist</option><option value="assistant">Assistant</option><option value="staff">Staff</option></select></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-plus"></i> Add Staff</button>
        </form>
    </div>
</div>

<div id="editStaffModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editStaffModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Staff</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="staff_id" id="editStaffId">
            <div class="form-grid">
                <div class="form-field"><label>Name</label><input type="text" name="name" id="editName" required></div>
                <div class="form-field"><label>Email</label><input type="email" name="email" id="editEmail" required></div>
                <div class="form-field full"><label>Role</label><select name="role" id="editRole" required><option value="clinic_admin">Clinic Admin</option><option value="dentist">Dentist</option><option value="assistant">Assistant</option><option value="staff">Staff</option></select></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-save"></i> Update Staff</button>
        </form>
    </div>
</div>

<script>
const staff = <?php echo json_encode($staff); ?>;

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editStaff(staffId) {
    const member = staff.find(s => s.user_id === staffId);
    if (member) {
        document.getElementById('editStaffId').value = member.user_id;
        document.getElementById('editName').value = member.name;
        document.getElementById('editEmail').value = member.email;
        document.getElementById('editRole').value = member.role;
        openModal('editStaffModal');
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>
<?php clinicAdminEnd(); ?>
