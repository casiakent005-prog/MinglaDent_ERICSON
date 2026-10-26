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
    $serviceClinicId = $isGlobalAdmin ? (int) ($_POST['clinic_id'] ?? 0) : $assignedClinicId;

    if ($action === 'add_service') {
        $service_name = trim($_POST['service_name']);
        $description = trim($_POST['description']);
        $duration = max(1, (int) ($_POST['duration'] ?? 0));

        $clinicCheck = $pdo->prepare("SELECT clinic_id FROM clinics WHERE clinic_id = ? LIMIT 1");
        $clinicCheck->execute([$serviceClinicId]);
        if ($clinicCheck->fetchColumn()) {
            $stmt = $pdo->prepare("INSERT INTO services (clinic_id, service_name, description, duration, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$serviceClinicId, $service_name, $description, $duration]);
            clinicPortalAudit($pdo, (int) $context['user_id'], $serviceClinicId, 'admin.service_created', 'service', (int) $pdo->lastInsertId());
        }
    } elseif ($action === 'edit_service') {
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $service_name = trim($_POST['service_name']);
        $description = trim($_POST['description']);
        $duration = max(1, (int) ($_POST['duration'] ?? 0));

        $stmt = $pdo->prepare("UPDATE services SET service_name = ?, description = ?, duration = ? WHERE service_id = ? AND clinic_id = ?");
        $stmt->execute([$service_name, $description, $duration, $service_id, $serviceClinicId]);
        clinicPortalAudit($pdo, (int) $context['user_id'], $serviceClinicId, 'admin.service_updated', 'service', $service_id);
    } elseif ($action === 'set_service_status') {
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['active', 'inactive'], true)) {
            $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE service_id = ? AND clinic_id = ?");
            $stmt->execute([$status, $service_id, $serviceClinicId]);
            clinicPortalAudit($pdo, (int) $context['user_id'], $serviceClinicId, 'admin.service_status_changed', 'service', $service_id);
        }
    }
}

$clinicStmt = $pdo->prepare("SELECT clinic_id, clinic_name FROM clinics" . ($isGlobalAdmin ? '' : ' WHERE clinic_id = ?') . " ORDER BY clinic_name");
$clinicStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$clinics = $clinicStmt->fetchAll();
$serviceSql = "SELECT s.*, c.clinic_name FROM services s INNER JOIN clinics c ON c.clinic_id = s.clinic_id" . ($isGlobalAdmin ? '' : ' WHERE s.clinic_id = ?') . " ORDER BY c.clinic_name, s.service_name";
$serviceStmt = $pdo->prepare($serviceSql);
$serviceStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$services = $serviceStmt->fetchAll();

clinicAdminStart($context, 'Manage Services', 'services');
?>

<section class="card">
    <div class="week-nav"><h3>All Services</h3><button class="button" onclick="openModal('addServiceModal')"><i class="fas fa-plus"></i> Add Service</button></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Clinic</th><th>Service Name</th><th>Description</th><th>Duration (mins)</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
                <td><?php echo (int) $service['service_id']; ?></td>
                <td><?php echo clinicPortalEscape($service['clinic_name']); ?></td>
                <td><?php echo clinicPortalEscape($service['service_name']); ?></td>
                <td><?php echo clinicPortalEscape($service['description']); ?></td>
                <td><?php echo (int) $service['duration']; ?></td>
                <td><span class="badge <?php echo clinicPortalEscape($service['status']); ?>"><?php echo ucfirst(clinicPortalEscape($service['status'])); ?></span></td>
                <td>
                    <div class="actions">
                        <button class="button secondary" onclick="editService(<?php echo (int) $service['service_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="set_service_status"><input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>"><input type="hidden" name="clinic_id" value="<?php echo (int) $service['clinic_id']; ?>"><input type="hidden" name="status" value="<?php echo $service['status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="button danger" type="submit"><i class="fas fa-power-off"></i> <?php echo $service['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button></form>
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

<div id="addServiceModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addServiceModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Service</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="add_service">
            <div class="form-grid">
                <?php if ($isGlobalAdmin): ?>
                <div class="form-field full"><label>Clinic</label><select name="clinic_id" required><?php foreach ($clinics as $clinic): ?><option value="<?php echo (int) $clinic['clinic_id']; ?>"><?php echo clinicPortalEscape($clinic['clinic_name']); ?></option><?php endforeach; ?></select></div>
                <?php else: ?>
                <input type="hidden" name="clinic_id" value="<?php echo (int) $assignedClinicId; ?>">
                <?php endif; ?>
                <div class="form-field full"><label>Service Name</label><input type="text" name="service_name" required></div>
                <div class="form-field full"><label>Description</label><textarea name="description" rows="3" required></textarea></div>
                <div class="form-field full"><label>Duration (minutes)</label><input type="number" name="duration" required></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-plus"></i> Add Service</button>
        </form>
    </div>
</div>

<div id="editServiceModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editServiceModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Service</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_service">
            <input type="hidden" name="service_id" id="editServiceId">
            <input type="hidden" name="clinic_id" id="editClinicId">
            <div class="form-grid">
                <div class="form-field full"><label>Service Name</label><input type="text" name="service_name" id="editServiceName" required></div>
                <div class="form-field full"><label>Description</label><textarea name="description" id="editDescription" rows="3" required></textarea></div>
                <div class="form-field full"><label>Duration (minutes)</label><input type="number" name="duration" id="editDuration" required></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-save"></i> Update Service</button>
        </form>
    </div>
</div>

<script>
const services = <?php echo json_encode($services, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editService(serviceId) {
    const service = services.find(s => s.service_id === serviceId);
    if (service) {
        document.getElementById('editServiceId').value = service.service_id;
        document.getElementById('editClinicId').value = service.clinic_id;
        document.getElementById('editServiceName').value = service.service_name;
        document.getElementById('editDescription').value = service.description;
        document.getElementById('editDuration').value = service.duration;
        openModal('editServiceModal');
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>
<?php clinicAdminEnd(); ?>
