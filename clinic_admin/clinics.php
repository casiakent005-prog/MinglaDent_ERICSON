<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicAdminContext();

if ($context['portal_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$csrfToken = clinicAdminCsrfToken();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    clinicAdminRequireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_clinic') {
        $clinic_name = trim($_POST['clinic_name']);
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        $operating_hours = trim($_POST['operating_hours']);
        $map_location = trim($_POST['map_location']);
        $status = $_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO clinics (clinic_name, address, contact_number, email, operating_hours, map_location, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$clinic_name, $address, $contact_number, $email, $operating_hours, $map_location, $status]);
        clinicPortalAudit($pdo, (int) $context['user_id'], null, 'admin.clinic_created', 'clinic', (int) $pdo->lastInsertId());
    } elseif ($action === 'edit_clinic') {
        $clinic_id = $_POST['clinic_id'];
        $clinic_name = trim($_POST['clinic_name']);
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        $operating_hours = trim($_POST['operating_hours']);
        $map_location = trim($_POST['map_location']);
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE clinics SET clinic_name = ?, address = ?, contact_number = ?, email = ?, operating_hours = ?, map_location = ?, status = ? WHERE clinic_id = ?");
        $stmt->execute([$clinic_name, $address, $contact_number, $email, $operating_hours, $map_location, $status, $clinic_id]);
        clinicPortalAudit($pdo, (int) $context['user_id'], (int) $clinic_id, 'admin.clinic_updated', 'clinic', (int) $clinic_id);
    } elseif ($action === 'delete_clinic') {
        $clinic_id = $_POST['clinic_id'];
        $stmt = $pdo->prepare("DELETE FROM clinics WHERE clinic_id = ?");
        $stmt->execute([$clinic_id]);
        clinicPortalAudit($pdo, (int) $context['user_id'], null, 'admin.clinic_deleted', 'clinic', (int) $clinic_id);
    }
}

// Get all clinics
$clinics = $pdo->query("SELECT * FROM clinics ORDER BY clinic_name")->fetchAll();

clinicAdminStart($context, 'Manage Clinics', 'clinics');
?>

<section class="card">
    <div class="week-nav"><h3>All Clinics</h3><button class="button" onclick="openModal('addClinicModal')"><i class="fas fa-plus"></i> Add Clinic</button></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Clinic Name</th><th>Address</th><th>Contact</th><th>Email</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($clinics as $clinic): ?>
            <tr>
                <td><?php echo (int) $clinic['clinic_id']; ?></td>
                <td><?php echo clinicPortalEscape($clinic['clinic_name']); ?></td>
                <td><?php echo clinicPortalEscape($clinic['address']); ?></td>
                <td><?php echo clinicPortalEscape($clinic['contact_number']); ?></td>
                <td><?php echo clinicPortalEscape($clinic['email']); ?></td>
                <td><span class="badge <?php echo clinicPortalEscape($clinic['status']); ?>"><?php echo ucfirst(clinicPortalEscape($clinic['status'])); ?></span></td>
                <td>
                    <div class="actions">
                        <button class="button secondary" onclick="editClinic(<?php echo (int) $clinic['clinic_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <button class="button danger" onclick="deleteClinic(<?php echo (int) $clinic['clinic_id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
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

<div id="addClinicModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addClinicModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Clinic</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="add_clinic">
            <div class="form-grid">
                <div class="form-field"><label>Clinic Name</label><input type="text" name="clinic_name" required></div>
                <div class="form-field"><label>Contact Number</label><input type="text" name="contact_number" required></div>
                <div class="form-field full"><label>Address</label><input type="text" name="address" required></div>
                <div class="form-field"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-field"><label>Status</label><select name="status" required><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select></div>
                <div class="form-field full"><label>Operating Hours</label><input type="text" name="operating_hours" placeholder="e.g., Mon-Fri 9AM-5PM"></div>
                <div class="form-field full"><label>Map Location</label><input type="text" name="map_location" placeholder="Google Maps link"></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-plus"></i> Add Clinic</button>
        </form>
    </div>
</div>

<div id="editClinicModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editClinicModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Clinic</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_clinic">
            <input type="hidden" name="clinic_id" id="editClinicId">
            <div class="form-grid">
                <div class="form-field"><label>Clinic Name</label><input type="text" name="clinic_name" id="editClinicName" required></div>
                <div class="form-field"><label>Contact Number</label><input type="text" name="contact_number" id="editContact" required></div>
                <div class="form-field full"><label>Address</label><input type="text" name="address" id="editAddress" required></div>
                <div class="form-field"><label>Email</label><input type="email" name="email" id="editEmail" required></div>
                <div class="form-field"><label>Status</label><select name="status" id="editStatus" required><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select></div>
                <div class="form-field full"><label>Operating Hours</label><input type="text" name="operating_hours" id="editHours"></div>
                <div class="form-field full"><label>Map Location</label><input type="text" name="map_location" id="editMap"></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-save"></i> Update Clinic</button>
        </form>
    </div>
</div>

<script>
const clinics = <?php echo json_encode($clinics); ?>;
const adminCsrfToken = <?php echo json_encode($csrfToken); ?>;

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editClinic(clinicId) {
    const clinic = clinics.find(c => c.clinic_id === clinicId);
    if (clinic) {
        document.getElementById('editClinicId').value = clinic.clinic_id;
        document.getElementById('editClinicName').value = clinic.clinic_name;
        document.getElementById('editAddress').value = clinic.address;
        document.getElementById('editContact').value = clinic.contact_number;
        document.getElementById('editEmail').value = clinic.email;
        document.getElementById('editHours').value = clinic.operating_hours;
        document.getElementById('editMap').value = clinic.map_location;
        document.getElementById('editStatus').value = clinic.status;
        openModal('editClinicModal');
    }
}

function deleteClinic(clinicId) {
    if (confirm('Are you sure you want to delete this clinic?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="' + adminCsrfToken + '"><input type="hidden" name="action" value="delete_clinic"><input type="hidden" name="clinic_id" value="' + clinicId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>
<?php clinicAdminEnd(); ?>
