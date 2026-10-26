<?php
require_once 'bootstrap.php';
require_once 'layout.php';
require_once __DIR__ . '/../includes/clinic_applications.php';

$admin = systemAdminContext();
$csrfToken = clinicPortalCsrfToken();
$message = '';
$messageType = 'success';
$validStatuses = ['pending', 'approved', 'rejected', 'suspended'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    systemAdminRequireCsrf();
    $action = $_POST['action'] ?? '';
    $clinicId = (int) ($_POST['clinic_id'] ?? 0);

    try {
    if ($action === 'create_clinic' || $action === 'update_clinic') {
        $clinicName = trim($_POST['clinic_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $hours = trim($_POST['operating_hours'] ?? '');
        $mapLocation = trim($_POST['map_location'] ?? '');
        $status = 'pending';

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
            $stmt = $pdo->prepare("UPDATE clinics SET clinic_name = ?, address = ?, contact_number = ?, email = ?, operating_hours = ?, map_location = ? WHERE clinic_id = ?");
            $stmt->execute([$clinicName, $address, $phone, $email, $hours ?: null, $mapLocation ?: null, $clinicId]);
            clinicPortalAudit($pdo, (int) $admin['user_id'], $clinicId, 'system_admin.clinic_profile_updated', 'clinic', $clinicId);
            $message = 'Clinic profile updated. Use the review controls to change approval status.';
        }
    } elseif (in_array($action, ['approve_clinic', 'suspend_clinic', 'reject_clinic', 'restore_clinic'], true)) {
        $statusByAction = [
            'approve_clinic' => 'approved',
            'suspend_clinic' => 'suspended',
            'reject_clinic' => 'rejected',
            'restore_clinic' => 'pending',
        ];
        $status = $statusByAction[$action];
        $reason = is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : '';
        clinicReviewDecision($pdo, (int) $admin['user_id'], $clinicId, $status, $reason);
        $message = 'Clinic status changed to ' . $status . '.';
    }
    } catch (Throwable $e) {
        $message = $e instanceof PDOException ? 'The clinic could not be saved. Please try again.' : $e->getMessage();
        $messageType = 'error';
    }
}

$clinics = $pdo->query("SELECT c.*, owner.name AS owner_name, owner.email AS owner_email, owner.contact_number AS owner_phone, cs.is_dentist, (SELECT COUNT(*) FROM clinic_staff cs WHERE cs.clinic_id = c.clinic_id) AS staff_count, (SELECT COUNT(*) FROM service_fees sf JOIN services s ON s.service_id = sf.service_id AND s.clinic_id = c.clinic_id AND s.status = 'active' WHERE sf.clinic_id = c.clinic_id) AS active_fee_count FROM clinics c LEFT JOIN auth_users owner ON owner.id = c.owner_user_id LEFT JOIN clinic_staff cs ON cs.user_id = c.owner_user_id AND cs.clinic_id = c.clinic_id ORDER BY FIELD(c.status, 'pending', 'approved', 'suspended', 'rejected'), c.clinic_name")->fetchAll();

$applicationDocuments = [];
foreach ($pdo->query('SELECT document_id, clinic_id, document_label, original_name FROM clinic_application_documents ORDER BY document_id')->fetchAll() as $document) {
    $applicationDocuments[$document['clinic_id']][] = $document;
}
$applicationDecisions = [];
foreach ($pdo->query('SELECT d.*, u.name AS reviewer_name FROM clinic_application_decisions d JOIN auth_users u ON u.id = d.reviewer_user_id ORDER BY d.decision_id DESC')->fetchAll() as $decision) {
    $applicationDecisions[$decision['clinic_id']][] = $decision;
}

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
            <td><strong><?php echo clinicPortalEscape($clinic['clinic_name']); ?></strong><br><small><?php echo clinicPortalEscape($clinic['address']); ?></small>
            <details><summary>Review application</summary>
                <p><strong>Submitted:</strong> <?php echo clinicPortalEscape($clinic['submitted_at'] ?: $clinic['created_at']); ?></p>
                <p><strong>Representative:</strong> <?php echo clinicPortalEscape($clinic['owner_name'] ?: 'Existing clinic — no owner recorded'); ?><br><?php echo clinicPortalEscape($clinic['owner_email']); ?><br><?php echo clinicPortalEscape($clinic['owner_phone']); ?></p>
                <p><strong>Operating hours:</strong> <?php echo clinicPortalEscape($clinic['operating_hours']); ?></p>
                <p><strong>Service area:</strong> <?php echo clinicPortalEscape($clinic['service_area']); ?></p>
                <p><strong>Description:</strong> <?php echo nl2br(clinicPortalEscape($clinic['description'])); ?></p>
                <p><strong>Services offered:</strong> <?php echo nl2br(clinicPortalEscape($clinic['services_offered'])); ?></p>
                <p><strong>Owner also practices as dentist:</strong> <?php echo $clinic['is_dentist'] ? 'Yes' : 'No'; ?></p>
                <h4>Supporting documents</h4>
                <?php if (empty($applicationDocuments[$clinic['clinic_id']])): ?><p>No documents recorded for this existing clinic.</p><?php else: ?><ul><?php foreach ($applicationDocuments[$clinic['clinic_id']] as $document): ?><li><a href="../login/clinic_document.php?id=<?php echo (int) $document['document_id']; ?>"><?php echo clinicPortalEscape($document['document_label'] . ' — ' . $document['original_name']); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
                <h4>Review history</h4>
                <?php foreach ($applicationDecisions[$clinic['clinic_id']] ?? [] as $decision): ?><p><?php echo clinicPortalEscape($decision['decided_at'] . ' — ' . $decision['reviewer_name'] . ': ' . $decision['previous_status'] . ' → ' . $decision['decision']); ?><br><?php echo nl2br(clinicPortalEscape($decision['reason'])); ?></p><?php endforeach; ?>
            </details></td>
            <td><span class="badge <?php echo clinicPortalEscape($clinic['status']); ?>"><?php echo clinicPortalEscape($clinic['status']); ?></span></td>
            <td><?php echo (int) $clinic['staff_count']; ?></td>
            <td><?php echo (int) $clinic['active_fee_count']; ?></td>
            <td><?php echo clinicPortalEscape($clinic['contact_number']); ?><br><?php echo clinicPortalEscape($clinic['email']); ?></td>
            <td><div class="actions">
                <form method="post" class="form-grid" style="min-width:240px">
                    <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
                    <input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>">
                    <div class="form-field full"><label for="reason_<?php echo (int) $clinic['clinic_id']; ?>">Decision reason (required for rejection / suspension)</label><textarea id="reason_<?php echo (int) $clinic['clinic_id']; ?>" name="reason" maxlength="2000" rows="2"></textarea></div>
                    <div class="actions">
                        <?php if ($clinic['status'] !== 'approved'): ?><button class="button" name="action" value="approve_clinic">Approve</button><?php endif; ?>
                        <?php if ($clinic['status'] !== 'rejected'): ?><button class="button danger" name="action" value="reject_clinic">Reject</button><?php endif; ?>
                        <?php if ($clinic['status'] === 'approved'): ?><button class="button danger" name="action" value="suspend_clinic">Suspend</button><?php endif; ?>
                        <?php if (in_array($clinic['status'], ['rejected', 'suspended'], true)): ?><button class="button secondary" name="action" value="restore_clinic">Return to pending</button><?php endif; ?>
                    </div>
                </form>
                <details><summary style="cursor:pointer;">Edit listing</summary><form method="post" class="form-grid" style="min-width:320px;margin-top:10px;"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="update_clinic"><input type="hidden" name="clinic_id" value="<?php echo (int) $clinic['clinic_id']; ?>"><div class="form-field full"><label>Clinic name</label><input name="clinic_name" value="<?php echo clinicPortalEscape($clinic['clinic_name']); ?>" maxlength="255" required></div><div class="form-field full"><label>Address</label><input name="address" value="<?php echo clinicPortalEscape($clinic['address']); ?>" maxlength="255" required></div><div class="form-field"><label>Contact</label><input name="contact_number" value="<?php echo clinicPortalEscape($clinic['contact_number']); ?>" maxlength="20" required></div><div class="form-field"><label>Email</label><input name="email" type="email" value="<?php echo clinicPortalEscape($clinic['email']); ?>" required></div><div class="form-field full"><label>Opening days/hours</label><input name="operating_hours" value="<?php echo clinicPortalEscape($clinic['operating_hours']); ?>" maxlength="255"></div><div class="form-field full"><label>Map URL</label><input name="map_location" type="url" value="<?php echo clinicPortalEscape($clinic['map_location']); ?>"></div><div class="form-field full"><button class="button secondary" type="submit">Save listing</button></div></form></details>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php systemAdminEnd(); ?>