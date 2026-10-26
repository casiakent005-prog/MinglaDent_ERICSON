<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();
$csrfToken = clinicAdminCsrfToken();
$isGlobalAdmin = $context['portal_role'] === 'admin';
$assignedClinicId = $isGlobalAdmin ? null : (int) ($context['clinic_id'] ?? 0);

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    clinicAdminRequireCsrf();
    $action = $_POST['action'] ?? '';
    try {
        $pdo->beginTransaction();
        if ($action === 'add_staff' || $action === 'edit_staff') {
            $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $role = $_POST['role'] ?? '';
            if ($name === '' || mb_strlen($name) > 255 || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || !in_array($role, ['clinic_admin', 'dentist', 'assistant', 'staff'], true)) {
                throw new RuntimeException('Enter a valid name, email, and clinic role.');
            }
            $isDentist = $role === 'clinic_admin' && ($_POST['is_dentist'] ?? '') === '1' ? 1 : 0;
        }
        if ($action === 'add_staff') {
            $clinicId = $isGlobalAdmin ? (int) ($_POST['clinic_id'] ?? 0) : $assignedClinicId;
            $password = $_POST['password'] ?? '';
            if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
                throw new RuntimeException('Use a password of 8–72 bytes.');
            }
            $check = $pdo->prepare('SELECT clinic_id FROM clinics WHERE clinic_id = ?');
            $check->execute([$clinicId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Choose a valid clinic.');
            $stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status, clinic_id) VALUES (?, ?, ?, 'clinic', 'active', ?)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $clinicId]);
            $userId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO clinic_staff (user_id, clinic_id, role, is_dentist) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $clinicId, $role, $isDentist]);
            clinicPortalAudit($pdo, (int) $context['user_id'], $clinicId, 'admin.staff_created', 'user', $userId);
        } elseif (in_array($action, ['edit_staff', 'set_staff_status'], true)) {
            $userId = (int) ($_POST['staff_id'] ?? 0);
            $check = $pdo->prepare("SELECT cs.clinic_id, c.owner_user_id FROM clinic_staff cs JOIN clinics c ON c.clinic_id = cs.clinic_id JOIN auth_users u ON u.id = cs.user_id WHERE cs.user_id = ? AND u.role = 'clinic'" . ($isGlobalAdmin ? '' : ' AND cs.clinic_id = ?') . ' FOR UPDATE');
            $check->execute($isGlobalAdmin ? [$userId] : [$userId, $assignedClinicId]);
            $member = $check->fetch();
            if (!$member) {
                http_response_code(404);
                throw new RuntimeException('Staff member not found in this clinic.');
            }
            if ($action === 'edit_staff') {
                if ((int) $member['owner_user_id'] === $userId && $role !== 'clinic_admin') {
                    throw new RuntimeException('The clinic owner must retain clinic administrator access.');
                }
                $stmt = $pdo->prepare("UPDATE auth_users SET name = ?, email = ? WHERE id = ? AND role = 'clinic'");
                $stmt->execute([$name, $email, $userId]);
                $stmt = $pdo->prepare('UPDATE clinic_staff SET role = ?, is_dentist = ? WHERE user_id = ? AND clinic_id = ?');
                $stmt->execute([$role, $isDentist, $userId, $member['clinic_id']]);
            } else {
                $status = $_POST['status'] ?? '';
                if (!in_array($status, ['active', 'inactive'], true)) throw new RuntimeException('Invalid account status.');
                if (!$isGlobalAdmin && (int) $member['owner_user_id'] === $userId) throw new RuntimeException('Only the System Admin can deactivate a clinic owner.');
                $stmt = $pdo->prepare("UPDATE auth_users SET status = ? WHERE id = ? AND role = 'clinic'");
                $stmt->execute([$status, $userId]);
            }
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $member['clinic_id'], 'admin.staff_updated', 'user', $userId);
        } else {
            throw new RuntimeException('Invalid staff action.');
        }
        $pdo->commit();
        $message = 'Staff account saved.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $e instanceof PDOException ? 'Unable to save this account. The email may already be registered.' : $e->getMessage();
        $messageType = 'error';
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

<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>
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
                <td><span class="badge"><?php echo ucfirst(clinicPortalEscape(str_replace('_', ' ', $member['role']))); ?><?php if ($member['role'] === 'clinic_admin' && $member['is_dentist']) echo ' + Dentist'; ?></span></td>
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
                <div class="form-field full"><label><input type="checkbox" name="is_dentist" value="1"> Also practices as dentist (Clinic Admin)</label></div>
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
                <div class="form-field full"><label><input type="checkbox" id="editIsDentist" name="is_dentist" value="1"> Also practices as dentist (Clinic Admin)</label></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-save"></i> Update Staff</button>
        </form>
    </div>
</div>

<script>
const staff = <?php echo json_encode($staff, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

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
        document.getElementById('editIsDentist').checked = Number(member.is_dentist) === 1;
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
