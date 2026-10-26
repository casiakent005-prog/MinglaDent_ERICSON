<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$context = clinicPortalRequireRoles(['clinic_admin', 'dentist', 'assistant']);
$clinicId = (int) $context['clinic_id'];
$message = '';
$messageType = 'success';
$csrfToken = clinicPortalCsrfToken();
$clinicStmt = $pdo->prepare("SELECT clinic_id, clinic_name, address, contact_number, email, operating_hours, map_location, status FROM clinics WHERE clinic_id = ? LIMIT 1");
$clinicStmt->execute([$clinicId]);
$clinic = $clinicStmt->fetch();

if (!$clinic) {
    http_response_code(404);
    exit('Clinic profile not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'update_clinic') {
        $clinicName = trim($_POST['clinic_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $hours = trim($_POST['operating_hours'] ?? '');
        $mapLocation = trim($_POST['map_location'] ?? '');

        if ($clinicName === '' || $address === '' || $contact === '' || $hours === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Clinic name, address, phone, opening days/hours, and a valid email are required.';
            $messageType = 'error';
        } elseif ($mapLocation !== '' && (!filter_var($mapLocation, FILTER_VALIDATE_URL) || !str_starts_with($mapLocation, 'https://'))) {
            $message = 'Map location must be an HTTPS link.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE clinics SET clinic_name = ?, address = ?, contact_number = ?, email = ?, operating_hours = ?, map_location = ? WHERE clinic_id = ?");
            $stmt->execute([$clinicName, $address, $contact, $email, $hours ?: null, $mapLocation ?: null, $clinicId]);
            $clinic['clinic_name'] = $clinicName;
            $clinic['address'] = $address;
            $clinic['contact_number'] = $contact;
            $clinic['email'] = $email;
            $clinic['operating_hours'] = $hours ?: null;
            $clinic['map_location'] = $mapLocation ?: null;
            clinicPortalAudit($pdo, (int) $context['user_id'], $clinicId, 'clinic.profile_updated', 'clinic', $clinicId);
            $message = $clinic['status'] === 'approved'
                ? 'Clinic profile updated. The public clinic card is live.'
                : 'Clinic profile updated. The public card is marked pending; booking becomes available after system-admin approval.';
        }
    } elseif ($action === 'save_service') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $serviceName = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int) ($_POST['duration'] ?? 0);
        $fee = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($serviceName === '' || $duration < 5 || $duration > 480 || $fee === false || $fee < 0 || $fee > 99999999.99) {
            $message = 'Enter a service name, a duration from 5 to 480 minutes, and a valid fee.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                if ($serviceId > 0) {
                    $ownedService = $pdo->prepare("SELECT service_id FROM services WHERE service_id = ? AND clinic_id = ? LIMIT 1");
                    $ownedService->execute([$serviceId, $clinicId]);
                    if (!$ownedService->fetchColumn()) {
                        throw new RuntimeException('Service not found in this clinic.');
                    }

                    $stmt = $pdo->prepare("UPDATE services SET service_name = ?, description = ?, duration = ? WHERE service_id = ? AND clinic_id = ?");
                    $stmt->execute([$serviceName, $description ?: null, $duration, $serviceId, $clinicId]);
                    $feeLookup = $pdo->prepare("SELECT fee_id FROM service_fees WHERE service_id = ? AND clinic_id = ? ORDER BY updated_at DESC, fee_id DESC LIMIT 1");
                    $feeLookup->execute([$serviceId, $clinicId]);
                    $feeId = $feeLookup->fetchColumn();
                    if ($feeId) {
                        $feeStmt = $pdo->prepare("UPDATE service_fees SET amount = ? WHERE fee_id = ? AND clinic_id = ?");
                        $feeStmt->execute([$fee, $feeId, $clinicId]);
                    } else {
                        $feeStmt = $pdo->prepare("INSERT INTO service_fees (service_id, clinic_id, amount) VALUES (?, ?, ?)");
                        $feeStmt->execute([$serviceId, $clinicId, $fee]);
                    }
                    $auditAction = 'clinic.service_updated';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO services (clinic_id, service_name, description, duration, status) VALUES (?, ?, ?, ?, 'active')");
                    $stmt->execute([$clinicId, $serviceName, $description ?: null, $duration]);
                    $serviceId = (int) $pdo->lastInsertId();
                    $feeStmt = $pdo->prepare("INSERT INTO service_fees (service_id, clinic_id, amount) VALUES (?, ?, ?)");
                    $feeStmt->execute([$serviceId, $clinicId, $fee]);
                    $auditAction = 'clinic.service_created';
                }
                $pdo->commit();
                clinicPortalAudit($pdo, (int) $context['user_id'], $clinicId, $auditAction, 'service', $serviceId);
                $message = 'Service and clinic fee saved.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not save the service. Try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'set_service_status') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['active', 'inactive'], true)) {
            $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE service_id = ? AND clinic_id = ?");
            $stmt->execute([$status, $serviceId, $clinicId]);
            clinicPortalAudit($pdo, (int) $context['user_id'], $clinicId, 'clinic.service_status_changed', 'service', $serviceId);
            $message = 'Service status updated.';
        }
    }
}

$serviceStmt = $pdo->prepare("SELECT s.service_id, s.service_name, s.description, s.duration, s.status, (SELECT sf.amount FROM service_fees sf WHERE sf.service_id = s.service_id AND sf.clinic_id = s.clinic_id ORDER BY sf.updated_at DESC, sf.fee_id DESC LIMIT 1) AS amount FROM services s WHERE s.clinic_id = ? ORDER BY s.service_name");
$serviceStmt->execute([$clinicId]);
$services = $serviceStmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => authDestination(getCurrentUser()), 'icon' => 'fa-home'],
    ['key' => 'clinic', 'label' => 'My clinic & services', 'href' => 'manage_clinic.php', 'icon' => 'fa-hospital'],
];
if ($context['portal_role'] === 'dentist') {
    $navigation[] = ['key' => 'schedule', 'label' => 'My Schedule', 'href' => '../clinic_dentist/schedule.php', 'icon' => 'fa-calendar-alt'];
    $navigation[] = ['key' => 'patients', 'label' => 'Patients', 'href' => '../clinic_dentist/patients.php', 'icon' => 'fa-user-injured'];
    $navigation[] = ['key' => 'records', 'label' => 'Dental Records', 'href' => '../clinic_dentist/records.php', 'icon' => 'fa-notes-medical'];
    $navigation[] = ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => '../clinic_dentist/treatments.php', 'icon' => 'fa-tooth'];
} else {
    $navigation[] = ['key' => 'patients', 'label' => 'Patients', 'href' => '../clinic_assistant/patients.php', 'icon' => 'fa-user-injured'];
    $navigation[] = ['key' => 'appointments', 'label' => 'Appointments', 'href' => '../clinic_assistant/appointments.php', 'icon' => 'fa-calendar-alt'];
    $navigation[] = ['key' => 'payments', 'label' => 'Payments', 'href' => '../clinic_assistant/payments.php', 'icon' => 'fa-cash-register'];
}

clinicPortalStart($context, 'My clinic & services', 'clinic', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <div class="week-nav"><h3>Clinic profile</h3><span class="badge <?php echo clinicPortalEscape($clinic['status']); ?>"><?php echo $clinic['status'] === 'approved' ? 'Public listing approved' : 'Pending system-admin approval'; ?></span></div>
    <?php if ($clinic['status'] !== 'approved'): ?><p class="empty-state">You can complete the profile now. Patients can book after the system admin approves this clinic.</p><?php endif; ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="update_clinic">
        <div class="form-field"><label for="clinic_name">Clinic name</label><input id="clinic_name" name="clinic_name" value="<?php echo clinicPortalEscape($clinic['clinic_name']); ?>" maxlength="255" required></div>
        <div class="form-field"><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" value="<?php echo clinicPortalEscape($clinic['contact_number']); ?>" maxlength="20" required></div>
        <div class="form-field full"><label for="address">Address</label><input id="address" name="address" value="<?php echo clinicPortalEscape($clinic['address']); ?>" maxlength="255" required></div>
        <div class="form-field"><label for="email">Public email</label><input id="email" name="email" type="email" value="<?php echo clinicPortalEscape($clinic['email']); ?>" maxlength="255" required></div>
        <div class="form-field"><label for="operating_hours">Open days and hours</label><input id="operating_hours" name="operating_hours" value="<?php echo clinicPortalEscape($clinic['operating_hours']); ?>" maxlength="255" placeholder="Mon-Fri: 8 AM-5 PM; Sat: 8 AM-12 PM" required></div>
        <div class="form-field full"><label for="map_location">HTTPS map link</label><input id="map_location" name="map_location" type="url" value="<?php echo clinicPortalEscape($clinic['map_location']); ?>" placeholder="https://maps.google.com/..."></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-save"></i> Save clinic profile</button></div>
    </form>
</section>

<section class="card">
    <div class="week-nav"><h3>Clinic services and fees</h3><button class="button" type="button" onclick="document.getElementById('addServiceForm').scrollIntoView({behavior:'smooth'})"><i class="fas fa-plus"></i> Add service</button></div>
    <?php if (!$services): ?><p class="empty-state">No services yet. Add one with its description, visit duration, and published clinic fee.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Service</th><th>Description</th><th>Duration</th><th>Fee</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($services as $service): ?>
        <tr><td><?php echo clinicPortalEscape($service['service_name']); ?></td><td><?php echo clinicPortalEscape($service['description']); ?></td><td><?php echo (int) $service['duration']; ?> min</td><td><?php echo $service['amount'] === null ? 'Fee required' : '₱' . number_format((float) $service['amount'], 2); ?></td><td><span class="badge <?php echo clinicPortalEscape($service['status']); ?>"><?php echo clinicPortalEscape($service['status']); ?></span></td><td><div class="actions"><button class="button secondary" type="button" onclick="editService(<?php echo (int) $service['service_id']; ?>)"><i class="fas fa-edit"></i> Edit</button><form method="post"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="set_service_status"><input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>"><input type="hidden" name="status" value="<?php echo $service['status'] === 'active' ? 'inactive' : 'active'; ?>"><button class="button secondary" type="submit"><?php echo $service['status'] === 'active' ? 'Deactivate' : 'Activate'; ?></button></form></div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>

<section class="card" id="addServiceForm">
    <h3 id="serviceFormTitle">Add a clinic service</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="save_service">
        <input type="hidden" name="service_id" id="service_id" value="0">
        <div class="form-field"><label for="service_name">Service name</label><input id="service_name" name="service_name" maxlength="255" required></div>
        <div class="form-field"><label for="duration">Estimated duration (minutes)</label><input id="duration" name="duration" type="number" min="5" max="480" step="5" required></div>
        <div class="form-field"><label for="amount">Clinic fee (PHP)</label><input id="amount" name="amount" type="number" min="0" max="99999999.99" step="0.01" required></div>
        <div class="form-field full"><label for="description">Description</label><textarea id="description" name="description" maxlength="2000"></textarea></div>
        <div class="form-field full"><div class="actions"><button class="button" type="submit"><i class="fas fa-save"></i> Save service and fee</button><button class="button secondary" type="button" onclick="clearServiceForm()">Clear</button></div></div>
    </form>
</section>
<script>
const clinicServices = <?php echo json_encode($services, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
function editService(serviceId) {
    const service = clinicServices.find(item => Number(item.service_id) === Number(serviceId));
    if (!service) return;
    document.getElementById('service_id').value = service.service_id;
    document.getElementById('service_name').value = service.service_name;
    document.getElementById('description').value = service.description || '';
    document.getElementById('duration').value = service.duration;
    document.getElementById('amount').value = service.amount ?? '';
    document.getElementById('serviceFormTitle').textContent = 'Edit clinic service';
    document.getElementById('addServiceForm').scrollIntoView({behavior: 'smooth'});
}
function clearServiceForm() {
    document.getElementById('service_id').value = '0';
    document.getElementById('service_name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('duration').value = '';
    document.getElementById('amount').value = '';
    document.getElementById('serviceFormTitle').textContent = 'Add a clinic service';
}
</script>
<?php clinicPortalEnd(); ?>