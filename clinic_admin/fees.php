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
    $clinic_id = $isGlobalAdmin ? (int) ($_POST['clinic_id'] ?? 0) : $assignedClinicId;

    if ($action === 'add_fee') {
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $serviceCheck = $pdo->prepare("SELECT service_id FROM services WHERE service_id = ? AND clinic_id = ? AND status = 'active' LIMIT 1");
        $serviceCheck->execute([$service_id, $clinic_id]);
        if ($serviceCheck->fetchColumn() && $amount !== false && $amount >= 0) {
            $stmt = $pdo->prepare("INSERT INTO service_fees (service_id, clinic_id, amount) VALUES (?, ?, ?)");
            $stmt->execute([$service_id, $clinic_id, $amount]);
            clinicPortalAudit($pdo, (int) $context['user_id'], $clinic_id, 'admin.fee_created', 'service_fee', (int) $pdo->lastInsertId());
        }
    } elseif ($action === 'edit_fee') {
        $fee_id = (int) ($_POST['fee_id'] ?? 0);
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $serviceCheck = $pdo->prepare("SELECT service_id FROM services WHERE service_id = ? AND clinic_id = ? AND status = 'active' LIMIT 1");
        $serviceCheck->execute([$service_id, $clinic_id]);
        if ($serviceCheck->fetchColumn() && $amount !== false && $amount >= 0) {
            $stmt = $pdo->prepare("UPDATE service_fees SET service_id = ?, amount = ? WHERE fee_id = ? AND clinic_id = ?");
            $stmt->execute([$service_id, $amount, $fee_id, $clinic_id]);
            clinicPortalAudit($pdo, (int) $context['user_id'], $clinic_id, 'admin.fee_updated', 'service_fee', $fee_id);
        }
    } elseif ($action === 'delete_fee') {
        $fee_id = (int) ($_POST['fee_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM service_fees WHERE fee_id = ?" . ($isGlobalAdmin ? '' : ' AND clinic_id = ?'));
        $stmt->execute($isGlobalAdmin ? [$fee_id] : [$fee_id, $assignedClinicId]);
        clinicPortalAudit($pdo, (int) $context['user_id'], $isGlobalAdmin ? null : $assignedClinicId, 'admin.fee_deleted', 'service_fee', $fee_id);
    }
}

// Get fees only for clinics this account can manage.
$feeSql = "
    SELECT sf.*, s.service_name, c.clinic_name
    FROM service_fees sf
    JOIN services s ON sf.service_id = s.service_id
    JOIN clinics c ON sf.clinic_id = c.clinic_id
    " . ($isGlobalAdmin ? '' : ' WHERE sf.clinic_id = ?') . " ORDER BY c.clinic_name, s.service_name";
$feeStmt = $pdo->prepare($feeSql);
$feeStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$fees = $feeStmt->fetchAll();

$serviceStmt = $pdo->prepare("SELECT * FROM services WHERE status = 'active'" . ($isGlobalAdmin ? '' : ' AND clinic_id = ?') . " ORDER BY clinic_id, service_name");
$serviceStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$services = $serviceStmt->fetchAll();

$clinicStmt = $pdo->prepare("SELECT * FROM clinics" . ($isGlobalAdmin ? '' : ' WHERE clinic_id = ?') . " ORDER BY clinic_name");
$clinicStmt->execute($isGlobalAdmin ? [] : [$assignedClinicId]);
$clinics = $clinicStmt->fetchAll();

clinicAdminStart($context, 'Manage Fees', 'fees');
?>

<section class="card">
    <div class="week-nav"><h3>All Fees</h3><button class="button" onclick="openModal('addFeeModal')"><i class="fas fa-plus"></i> Add Fee</button></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Service</th><th>Clinic</th><th>Amount (₱)</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($fees as $fee): ?>
            <tr>
                <td><?php echo (int) $fee['fee_id']; ?></td>
                <td><?php echo clinicPortalEscape($fee['service_name']); ?></td>
                <td><?php echo clinicPortalEscape($fee['clinic_name']); ?></td>
                <td><strong>₱<?php echo number_format($fee['amount'], 2); ?></strong></td>
                <td>
                    <div class="actions">
                        <button class="button secondary" onclick="editFee(<?php echo (int) $fee['fee_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <button class="button danger" onclick="deleteFee(<?php echo (int) $fee['fee_id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
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

<div id="addFeeModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addFeeModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Fee</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="add_fee">
            <div class="form-grid">
                <div class="form-field full"><label>Service</label><select name="service_id" id="addServiceId" required><?php foreach ($services as $service): ?><option value="<?php echo (int) $service['service_id']; ?>" data-clinic-id="<?php echo (int) $service['clinic_id']; ?>"><?php echo clinicPortalEscape($service['service_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-field full"><label>Clinic</label><select name="clinic_id" id="addClinicId" required><?php foreach ($clinics as $clinic): ?><option value="<?php echo (int) $clinic['clinic_id']; ?>"><?php echo clinicPortalEscape($clinic['clinic_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-field full"><label>Amount (₱)</label><input type="number" name="amount" min="0" step="0.01" required></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-plus"></i> Add Fee</button>
        </form>
    </div>
</div>

<div id="editFeeModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editFeeModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Fee</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_fee">
            <input type="hidden" name="fee_id" id="editFeeId">
            <div class="form-grid">
                <div class="form-field full"><label>Service</label><select name="service_id" id="editServiceId" required><?php foreach ($services as $service): ?><option value="<?php echo (int) $service['service_id']; ?>" data-clinic-id="<?php echo (int) $service['clinic_id']; ?>"><?php echo clinicPortalEscape($service['service_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-field full"><label>Clinic</label><select name="clinic_id" id="editClinicId" required><?php foreach ($clinics as $clinic): ?><option value="<?php echo (int) $clinic['clinic_id']; ?>"><?php echo clinicPortalEscape($clinic['clinic_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-field full"><label>Amount (₱)</label><input type="number" name="amount" id="editAmount" min="0" step="0.01" required></div>
            </div>
            <button type="submit" class="button"><i class="fas fa-save"></i> Update Fee</button>
        </form>
    </div>
</div>

<script>
const fees = <?php echo json_encode($fees); ?>;
const adminCsrfToken = <?php echo json_encode($csrfToken); ?>;

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editFee(feeId) {
    const fee = fees.find(f => f.fee_id === feeId);
    if (fee) {
        document.getElementById('editFeeId').value = fee.fee_id;
        document.getElementById('editClinicId').value = fee.clinic_id;
        filterServiceOptions(document.getElementById('editServiceId'), fee.clinic_id, fee.service_id);
        document.getElementById('editAmount').value = fee.amount;
        openModal('editFeeModal');
    }
}

function filterServiceOptions(select, clinicId, selectedServiceId) {
    const matchingOptions = Array.from(select.options).filter(option => {
        if (!option.value) return true;
        option.hidden = option.dataset.clinicId !== String(clinicId);
        return !option.hidden;
    });
    select.value = selectedServiceId || matchingOptions.find(option => option.value)?.value || '';
}

document.getElementById('addClinicId').addEventListener('change', function() {
    filterServiceOptions(document.getElementById('addServiceId'), this.value, '');
});

filterServiceOptions(document.getElementById('addServiceId'), document.getElementById('addClinicId').value, '');

function deleteFee(feeId) {
    if (confirm('Are you sure you want to delete this fee?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="' + adminCsrfToken + '"><input type="hidden" name="action" value="delete_fee"><input type="hidden" name="fee_id" value="' + feeId + '">';
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
