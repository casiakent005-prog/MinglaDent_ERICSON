<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['dentist']);
$message = '';
$messageType = 'success';
$csrfToken = clinicPortalCsrfToken();
$selectedPatientId = (int) ($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'add_treatment') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $toothNumber = trim($_POST['tooth_number'] ?? '');
        $treatmentDate = trim($_POST['treatment_date'] ?? '');
        $status = $_POST['status'] ?? 'planned';
        $notes = trim($_POST['notes'] ?? '');
        $service = null;
        if ($serviceId > 0) {
            $stmt = $pdo->prepare("SELECT s.service_id, s.service_name FROM services s INNER JOIN service_fees sf ON sf.service_id = s.service_id WHERE sf.clinic_id = ? AND s.clinic_id = ? AND s.service_id = ? AND s.status = 'active' ORDER BY sf.updated_at DESC LIMIT 1");
            $stmt->execute([$context['clinic_id'], $context['clinic_id'], $serviceId]);
            $service = $stmt->fetch();
        }
        $patientStmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ? AND clinic_id = ? LIMIT 1");
        $patientStmt->execute([$selectedPatientId, $context['clinic_id']]);
        $patientExists = $patientStmt->fetchColumn();
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $treatmentDate);
        $allowedTeeth = array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));

        if (!$patientExists || !$service || !$date || $date->format('Y-m-d') !== $treatmentDate || !in_array($status, ['planned', 'completed'], true) || ($toothNumber !== '' && !in_array((int) $toothNumber, $allowedTeeth, true))) {
            $message = 'Choose a clinic patient, valid service, treatment date, and valid tooth number.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO dental_treatments (clinic_id, patient_id, dentist_user_id, service_id, tooth_number, procedure_name, treatment_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$context['clinic_id'], $selectedPatientId, $context['user_id'], $serviceId, $toothNumber ?: null, $service['service_name'], $treatmentDate, $status, $notes ?: null]);
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'dentist.treatment_created', 'dental_treatment', (int) $pdo->lastInsertId());
            $message = 'Treatment added to the patient history.';
        }
    } elseif ($action === 'update_status') {
        $treatmentId = (int) ($_POST['treatment_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['planned', 'completed', 'cancelled'], true)) {
            $message = 'Choose a valid treatment status.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE dental_treatments SET status = ? WHERE treatment_id = ? AND clinic_id = ? AND dentist_user_id = ?");
            $stmt->execute([$status, $treatmentId, $context['clinic_id'], $context['user_id']]);
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'dentist.treatment_updated', 'dental_treatment', $treatmentId);
            $message = 'Treatment status updated.';
        }
    }
}

$stmt = $pdo->prepare("SELECT patient_id, name FROM patients WHERE clinic_id = ? ORDER BY name");
$stmt->execute([$context['clinic_id']]);
$patients = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT DISTINCT s.service_id, s.service_name FROM services s INNER JOIN service_fees sf ON sf.service_id = s.service_id WHERE sf.clinic_id = ? AND s.clinic_id = ? AND s.status = 'active' ORDER BY s.service_name");
$stmt->execute([$context['clinic_id'], $context['clinic_id']]);
$services = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT t.*, p.name AS patient_name FROM dental_treatments t INNER JOIN patients p ON p.patient_id = t.patient_id WHERE t.clinic_id = ? AND t.dentist_user_id = ? ORDER BY t.treatment_date DESC LIMIT 100");
$stmt->execute([$context['clinic_id'], $context['user_id']]);
$treatments = $stmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'href' => 'schedule.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'records', 'label' => 'Dental Records', 'href' => 'records.php', 'icon' => 'fa-notes-medical'],
    ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => 'treatments.php', 'icon' => 'fa-tooth'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Treatment plans', 'treatments', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>
<section class="card">
    <h3>Add planned or completed procedure</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="add_treatment">
        <div class="form-field"><label for="patient_id">Patient</label><select id="patient_id" name="patient_id" required><option value="">Choose a patient</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>" <?php echo $selectedPatientId === (int) $patient['patient_id'] ? 'selected' : ''; ?>><?php echo clinicPortalEscape($patient['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="service_id">Procedure/service</label><select id="service_id" name="service_id" required><option value="">Choose a clinic service</option><?php foreach ($services as $service): ?><option value="<?php echo (int) $service['service_id']; ?>"><?php echo clinicPortalEscape($service['service_name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="tooth_number">Tooth (FDI, optional)</label><select id="tooth_number" name="tooth_number"><option value="">General / not tooth-specific</option><?php foreach (array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48)) as $tooth): ?><option value="<?php echo $tooth; ?>"><?php echo $tooth; ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="treatment_date">Treatment date</label><input id="treatment_date" name="treatment_date" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="form-field"><label for="status">Status</label><select id="status" name="status"><option value="planned">Planned</option><option value="completed">Completed</option></select></div>
        <div class="form-field"><label for="notes">Clinical notes</label><input id="notes" name="notes" maxlength="500"></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-plus"></i> Add treatment</button></div>
    </form>
</section>

<section class="card">
    <h3>My treatment history</h3>
    <?php if (!$treatments): ?><p class="empty-state">No treatment plans or procedures recorded.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Patient</th><th>Tooth</th><th>Procedure</th><th>Notes</th><th>Status</th></tr></thead><tbody><?php foreach ($treatments as $treatment): ?>
        <tr><td><?php echo clinicPortalEscape($treatment['treatment_date']); ?></td><td><?php echo clinicPortalEscape($treatment['patient_name']); ?></td><td><?php echo clinicPortalEscape($treatment['tooth_number'] ?: 'General'); ?></td><td><?php echo clinicPortalEscape($treatment['procedure_name']); ?></td><td><?php echo clinicPortalEscape($treatment['notes']); ?></td><td><form method="post" class="actions"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="treatment_id" value="<?php echo (int) $treatment['treatment_id']; ?>"><select name="status"><?php foreach (['planned', 'completed', 'cancelled'] as $status): ?><option value="<?php echo $status; ?>" <?php echo $treatment['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?></select><button class="button secondary" type="submit">Save</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
<?php clinicPortalEnd(); ?>