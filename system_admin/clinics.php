<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$admin = systemAdminContext();
$csrfToken = clinicPortalCsrfToken();
$message = '';
$messageType = 'success';
$validStatuses = ['pending', 'approved', 'rejected', 'suspended'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    systemAdminRequireCsrf();
    $action = $_POST['action'] ?? '';
    $clinicId = (int) ($_POST['clinic_id'] ?? 0);

    if ($action === 'create_clinic' || $action === 'update_clinic') {
        $clinicName = trim($_POST['clinic_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $hours = trim($_POST['operating_hours'] ?? '');
        $mapLocation = trim($_POST['map_location'] ?? '');
        $status = $action === 'create_clinic' ? 'pending' : ($_POST['status'] ?? 'pending');

        if ($clinicName === '' || $address === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($status, $validStatuses, true)) {
            $message = 'Enter a clinic name, address, contact number, valid email, and status.';
            $messageType = 'error';
        } elseif ($mapLocation !== '' && (!filter_var($mapLocation, FILTER_VALIDATE_URL) || !str_starts_with($mapLocation, 'https://'))) {
            $message = 'Map location must be an HTTPS URL.';
            $messageType = 'error';
        } elseif ($action === 'create_clinic') {
            $stmt = $pdo->prepare("INSERT INTO clinics (clinic_name, address, contact_number, email, operating_hours, map_location, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$clinicName, $address, $phone, $email, $hours ?: null, $mapLocation ?: null]);
            $clinicId = (int) $pdo->lastInsertId();
            clinicPortalAudit($pdo, (int) $admin['user_id'], null, 'system_admin.clinic_created', 'clinic', $clinicId);
            $message = 'Clinic profile created as pending. Assign its staff from Users & staff.';
        } else {
            $stmt = $pdo->prepare("UPDATE clinics SET clinic_name = ?, address = ?, contact_number = ?, email = ?, operating_hours = ?, map_location = ?, status = ? WHERE clinic_id = ?");
            $stmt->execute([$clinicName, $address, $phone, $email, $hours ?: null, $mapLocation ?: null, $status, $clinicId]);
            clinicPortalAudit($pdo, (int) $admin['user_id'], $clinicId, 'system_admin.clinic_profile_updated', 'clinic', $clinicId);
            $message = 'Clinic profile and marketplace status updated.';
        }
    } elseif (in_array($action, ['approve_clinic', 'suspend_clinic', 'reject_clinic', 'restore_clinic'], true)) {
        $statusByAction = [
            'approve_clinic' => 'approved',
            'suspend_clinic' => 'suspended',
            'reject_clinic' => 'rejected',
            'restore_clinic' => 'pending',
        ];
        $status = $statusByAction[$action];
        $stmt = $pdo->prepare("UPDATE clinics SET status = ? WHERE clinic_id = ?");
        $stmt->execute([$status, $clinicId]);
        if ($stmt->rowCount()) {
            clinicPortalAudit($pdo, (int) $admin['user_id'], $clinicId, 'system_admin.clinic_' . $status, 'clinic', $clinicId);
            $message = 'Clinic marketplace status changed to ' . $status . '.';
        } else {
            $message = 'Clinic not found or status unchanged.';
            $messageType = 'error';
        }
    }
}

$clinics = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM clinic_staff cs WHERE cs.clinic_id = c.clinic_id) AS staff_count, (SELECT COUNT(*) FROM service_fees sf JOIN services s ON s.service_id = sf.service_id AND s.clinic_id = c.clinic_id AND s.status = 'active' WHERE sf.clinic_id = c.clinic_id) AS active_fee_count FROM clinics c ORDER BY FIELD(c.status, 'pending', 'approved', 'suspended', 'rejected'), c.clinic_name")->fetchAll();

systemAdminStart($admin, 'Clinic directory', 'clinics');
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <h3>Add clinic profile</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="create_clinic">
        <div class="form-field"><label for="new_clinic_name">Clinic name</label><input id="new_clinic_name" name="clinic_name" maxlength="255" required></div>
        <div class="form-field"><label for="new_contact">Contact number</label><input id="new_contact" name="contact_number" maxlength="20" required></div>
        <div class="form-field full"><label for="new_address">Address</label><input id="new_address" name="address" maxlength="255" required></div>
        <div class="form-field"><label for="new_email">Public email</label><input id="new_email" name="email" type="email" maxlength="255" required></div>
        <div class="form-field"><label for="new_hours">Opening days and hours</label><input id="new_hours" name="operating_hours" maxlength="255" placeholder="Mon-Fri 8 AM-5 PM"></div>
        <div class="form-field full"><label for="new_map">HTTPS map link</label><input id="new_map" name="map_location" type="url" placeholder="https://maps.google.com/..."></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-plus"></i> Add pending clinic</button></div>
    </form>
</section>

<section class="card">
    <h3>Clinic accounts and marketplace listings</h3>
    <?php if (!$clinics): ?><p class="empty-state">No clinic profiles have been submitted.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Clinic</th><th>Status</th><th>Staff</th><th>Priced services</th><th>Contact</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($clinics as $clinic): ?>
        <tr>
            <td><strong><?php echo clinicPortalEscape($clinic['clinic_name']); ?></strong><br><small><?php echo clinicPortalEscape($clinic['address']); ?></small></td>
            <td><span class="badge <?php echo clinicPortalEscape($clinic['status']); ?>"><?php echo clinicPortalEscape($clinic['status']); ?></span></td>
            <td><?php echo (int) $clinic['staff_count']; ?></td>
            <td><?php echo (int) $clinic['active_fee_count']; ?></td>
            <td><?php echo clinicPortalEscape($clinic['contact_number']); ?><br><?php echo clinicPortalEscape($clinic['email']); ?></td>
            <td><div class="actions">
                <?php if ($clinic['status'] !== 'approved'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>"><button class="button" name="action" value="approve_clinic" type="submit"><i class="fas fa-check"></i> Approve</button></form><?php endif; ?>
                <?php if ($clinic['status'] === 'approved'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>"><button class="button danger" name="action" value="suspend_clinic" type="submit"><i class="fas fa-ban"></i> Suspend</button></form><?php elseif (in_array($clinic['status'], ['suspended', 'rejected'], true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>"><button class="button secondary" name="action" value="restore_clinic" type="submit">Restore to pending</button></form><?php endif; ?>
                <details><summary style="cursor:pointer;">Edit listing</summary><form method="post" class="form-grid" style="min-width:320px;margin-top:10px;"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="update_clinic"><input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>"><div class="form-field full"><label>Clinic name</label><input name="clinic_name" value="<?php echo clinicPortalEscape($clinic['clinic_name']); ?>" maxlength="255" required></div><div class="form-field full"><label>Address</label><input name="address" value="<?php echo clinicPortalEscape($clinic['address']); ?>" maxlength="255" required></div><div class="form-field"><label>Contact</label><input name="contact_number" value="<?php echo clinicPortalEscape($clinic['contact_number']); ?>" maxlength="20" required></div><div class="form-field"><label>Email</label><input name="email" type="email" value="<?php echo clinicPortalEscape($clinic['email']); ?>" required></div><div class="form-field full"><label>Opening days/hours</label><input name="operating_hours" value="<?php echo clinicPortalEscape($clinic['operating_hours']); ?>" maxlength="255"></div><div class="form-field full"><label>Map URL</label><input name="map_location" type="url" value="<?php echo clinicPortalEscape($clinic['map_location']); ?>"></div><div class="form-field full"><label>Marketplace status</label><select name="status"><?php foreach ($validStatuses as $status): ?><option value="<?php echo $status; ?>" <?php echo $clinic['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?></select></div><div class="form-field full"><button class="button secondary" type="submit">Save listing</button></div></form></details>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php systemAdminEnd(); ?>