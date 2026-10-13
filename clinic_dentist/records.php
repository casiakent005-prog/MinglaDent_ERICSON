<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['dentist']);
$message = '';
$messageType = 'success';
$selectedPatientId = (int) ($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$csrfToken = clinicPortalCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $patientCheck = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ? AND clinic_id = ? LIMIT 1");
    $patientCheck->execute([$selectedPatientId, $context['clinic_id']]);
    $patientExists = $patientCheck->fetchColumn();

    if (!$patientExists) {
        $message = 'Choose a patient in your clinic.';
        $messageType = 'error';
    } elseif ($action === 'upload_document') {
        $upload = $_FILES['document'] ?? null;
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 10485760) {
            $message = 'Choose a file smaller than 10 MB.';
            $messageType = 'error';
        } else {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($upload['tmp_name']);
            if (!isset($allowedTypes[$mimeType])) {
                $message = 'Only JPEG, PNG, WebP, and PDF files are accepted.';
                $messageType = 'error';
            } else {
                if ($recordId > 0) {
                    $recordCheck = $pdo->prepare("SELECT record_id FROM dental_records WHERE record_id = ? AND clinic_id = ? AND patient_id = ? LIMIT 1");
                    $recordCheck->execute([$recordId, $context['clinic_id'], $selectedPatientId]);
                    $recordId = (int) ($recordCheck->fetchColumn() ?: 0);
                }

                $storageName = bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
                $storageDirectory = dirname(__DIR__) . '/storage/patient_documents';
                if (!is_dir($storageDirectory)) {
                    mkdir($storageDirectory, 0700, true);
                }
                $destination = $storageDirectory . '/' . $storageName;

                if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                    $message = 'The file could not be stored securely.';
                    $messageType = 'error';
                } else {
                    $documentType = str_starts_with($mimeType, 'image/') ? 'xray' : 'attachment';
                    try {
                        $stmt = $pdo->prepare("INSERT INTO patient_documents (clinic_id, patient_id, dental_record_id, uploaded_by, original_name, storage_name, mime_type, file_size, document_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$context['clinic_id'], $selectedPatientId, $recordId ?: null, $context['user_id'], basename($upload['name']), $storageName, $mimeType, (int) $upload['size'], $documentType]);
                        clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'dentist.document_uploaded', 'patient_document', (int) $pdo->lastInsertId());
                        $message = 'Document uploaded to the patient record.';
                    } catch (PDOException $exception) {
                        unlink($destination);
                        $message = 'Could not attach the document. Please try again.';
                        $messageType = 'error';
                    }
                }
            }
        }
    } else {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $findings = trim($_POST['examination_findings'] ?? '');
        $treatmentPlan = trim($_POST['treatment_plan'] ?? '');
        $clinicalNotes = trim($_POST['clinical_notes'] ?? '');
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $validTeeth = array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48));
        $allowedConditions = ['healthy', 'caries', 'missing', 'restored', 'crown', 'root_canal', 'watch'];
        $toothChart = [];
        foreach (($_POST['tooth_conditions'] ?? []) as $tooth => $condition) {
            if (in_array((int) $tooth, $validTeeth, true) && in_array($condition, $allowedConditions, true) && $condition !== 'healthy') {
                $toothChart[(string) $tooth] = $condition;
            }
        }

        if ($diagnosis === '') {
            $message = 'Enter a diagnosis before saving the dental record.';
            $messageType = 'error';
        } else {
        if ($appointmentId > 0) {
            $appointmentCheck = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND clinic_id = ? AND patient_id = ? AND staff_id = ? LIMIT 1");
            $appointmentCheck->execute([$appointmentId, $context['clinic_id'], $selectedPatientId, $context['staff_id']]);
            $appointmentId = $appointmentCheck->fetchColumn() ?: null;
        } else {
            $appointmentId = null;
        }

        if ($action === 'update' && $recordId > 0) {
            $stmt = $pdo->prepare("UPDATE dental_records SET diagnosis = ?, examination_findings = ?, tooth_chart = ?, treatment_plan = ?, clinical_notes = ?, appointment_id = ?, recorded_at = NOW() WHERE record_id = ? AND clinic_id = ? AND patient_id = ?");
            $stmt->execute([$diagnosis, $findings ?: null, json_encode($toothChart), $treatmentPlan ?: null, $clinicalNotes ?: null, $appointmentId, $recordId, $context['clinic_id'], $selectedPatientId]);
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'dentist.record_updated', 'dental_record', $recordId);
            $message = 'Dental record updated.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO dental_records (clinic_id, patient_id, dentist_user_id, appointment_id, diagnosis, examination_findings, tooth_chart, treatment_plan, clinical_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$context['clinic_id'], $selectedPatientId, $context['user_id'], $appointmentId, $diagnosis, $findings ?: null, json_encode($toothChart), $treatmentPlan ?: null, $clinicalNotes ?: null]);
            clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'dentist.record_created', 'dental_record', (int) $pdo->lastInsertId());
            $message = 'Dental record saved.';
        }
        }
    }
}

$stmt = $pdo->prepare("SELECT patient_id, name FROM patients WHERE clinic_id = ? ORDER BY name");
$stmt->execute([$context['clinic_id']]);
$patients = $stmt->fetchAll();

$patient = null;
$appointments = [];
$records = [];
$documents = [];
$treatments = [];
if ($selectedPatientId > 0) {
    $stmt = $pdo->prepare("SELECT patient_id, name, email, phone, address, date_of_birth FROM patients WHERE patient_id = ? AND clinic_id = ? LIMIT 1");
    $stmt->execute([$selectedPatientId, $context['clinic_id']]);
    $patient = $stmt->fetch();

    if ($patient) {
        $stmt = $pdo->prepare("SELECT appointment_id, appointment_date FROM appointments WHERE clinic_id = ? AND patient_id = ? AND staff_id = ? ORDER BY appointment_date DESC LIMIT 20");
        $stmt->execute([$context['clinic_id'], $selectedPatientId, $context['staff_id']]);
        $appointments = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT dr.*, au.name AS dentist_name FROM dental_records dr INNER JOIN auth_users au ON au.id = dr.dentist_user_id WHERE dr.clinic_id = ? AND dr.patient_id = ? ORDER BY dr.recorded_at DESC");
        $stmt->execute([$context['clinic_id'], $selectedPatientId]);
        $records = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT document_id, original_name, document_type, created_at FROM patient_documents WHERE clinic_id = ? AND patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$context['clinic_id'], $selectedPatientId]);
        $documents = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT treatment_id, tooth_number, procedure_name, treatment_date, status, notes FROM dental_treatments WHERE clinic_id = ? AND patient_id = ? ORDER BY treatment_date DESC");
        $stmt->execute([$context['clinic_id'], $selectedPatientId]);
        $treatments = $stmt->fetchAll();
    }
}

    $toothConditions = $records ? (json_decode($records[0]['tooth_chart'] ?? '', true) ?: []) : [];
    $validTeeth = array_merge(range(18, 11), range(21, 28), range(38, 31), range(41, 48));

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'href' => 'schedule.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'records', 'label' => 'Dental Records', 'href' => 'records.php', 'icon' => 'fa-notes-medical'],
    ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => 'treatments.php', 'icon' => 'fa-tooth'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Dental Records', 'records', $navigation);
?>
<section class="card">
    <h3>Select patient</h3>
    <form method="get" class="actions">
        <select name="patient_id" required aria-label="Patient" style="min-width: 240px; min-height: 40px; padding: 8px; border: 1px solid #d8dee6; border-radius: 6px;">
            <option value="">Choose a patient</option>
            <?php foreach ($patients as $option): ?>
            <option value="<?php echo (int) $option['patient_id']; ?>" <?php echo $selectedPatientId === (int) $option['patient_id'] ? 'selected' : ''; ?>><?php echo clinicPortalEscape($option['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button" type="submit">Open patient</button>
    </form>
</section>

<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<?php if ($patient): ?>
<section class="card">
    <h3><?php echo clinicPortalEscape($patient['name']); ?></h3>
    <p><?php echo clinicPortalEscape($patient['email']); ?> | <?php echo clinicPortalEscape($patient['phone']); ?> | DOB: <?php echo clinicPortalEscape($patient['date_of_birth'] ?: 'Not provided'); ?></p>
</section>

<section class="card">
    <h3>Add dental record</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="save_record">
        <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
        <div class="form-field full"><label for="appointment_id">Related appointment</label><select id="appointment_id" name="appointment_id"><option value="0">No appointment selected</option><?php foreach ($appointments as $appointment): ?><option value="<?php echo (int) $appointment['appointment_id']; ?>"><?php echo clinicPortalEscape(date('M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></option><?php endforeach; ?></select></div>
        <div class="form-field full"><label for="examination_findings">Examination findings</label><textarea id="examination_findings" name="examination_findings"></textarea></div>
        <div class="form-field full"><label>Tooth chart (FDI numbering)</label><div class="form-grid"><?php foreach ($validTeeth as $tooth): ?><div class="form-field"><label for="tooth_<?php echo $tooth; ?>">Tooth <?php echo $tooth; ?></label><select id="tooth_<?php echo $tooth; ?>" name="tooth_conditions[<?php echo $tooth; ?>]"><option value="healthy">No finding</option><?php foreach (['caries' => 'Caries', 'missing' => 'Missing', 'restored' => 'Restored', 'crown' => 'Crown', 'root_canal' => 'Root canal', 'watch' => 'Monitor'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo ($toothConditions[(string) $tooth] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div></div>
        <div class="form-field full"><label for="diagnosis">Diagnosis</label><textarea id="diagnosis" name="diagnosis" required></textarea></div>
        <div class="form-field full"><label for="treatment_plan">Treatment plan</label><textarea id="treatment_plan" name="treatment_plan"></textarea></div>
        <div class="form-field full"><label for="clinical_notes">Clinical notes</label><textarea id="clinical_notes" name="clinical_notes"></textarea></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-save"></i> Save diagnosis</button></div>
    </form>
</section>

<section class="card">
    <h3>Upload X-ray or attachment</h3>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="upload_document">
        <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
        <div class="form-field"><label for="record_id">Dental record</label><select id="record_id" name="record_id"><option value="0">General patient document</option><?php foreach ($records as $record): ?><option value="<?php echo (int) $record['record_id']; ?>"><?php echo clinicPortalEscape(date('M j, Y', strtotime($record['recorded_at'])) . ' - ' . mb_substr($record['diagnosis'], 0, 70)); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="document">File (max 10 MB)</label><input id="document" name="document" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-upload"></i> Upload document</button></div>
    </form>
    <?php if ($documents): ?><div class="table-wrap"><table><thead><tr><th>Document</th><th>Type</th><th>Date</th><th></th></tr></thead><tbody><?php foreach ($documents as $document): ?><tr><td><?php echo clinicPortalEscape($document['original_name']); ?></td><td><?php echo clinicPortalEscape($document['document_type']); ?></td><td><?php echo clinicPortalEscape(date('M j, Y', strtotime($document['created_at']))); ?></td><td><a class="button-link secondary" href="document.php?id=<?php echo (int) $document['document_id']; ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="card">
    <h3>Dental history</h3>
    <?php if (!$records): ?>
        <p class="empty-state">No dental records have been saved for this patient.</p>
    <?php else: ?>
        <?php foreach ($records as $record): ?>
        <details style="padding: 14px 0; border-bottom: 1px solid #e9edf0;">
            <summary style="cursor: pointer; font-weight: 600;"><?php echo clinicPortalEscape(date('M j, Y g:i A', strtotime($record['recorded_at']))); ?> | <?php echo clinicPortalEscape($record['dentist_name']); ?></summary>
            <form method="post" class="form-grid" style="margin-top: 14px;">
                <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="record_id" value="<?php echo (int) $record['record_id']; ?>">
                <input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>">
                <div class="form-field full"><label>Diagnosis</label><textarea name="diagnosis" required><?php echo clinicPortalEscape($record['diagnosis']); ?></textarea></div>
                <div class="form-field full"><label>Treatment plan</label><textarea name="treatment_plan"><?php echo clinicPortalEscape($record['treatment_plan']); ?></textarea></div>
                <div class="form-field full"><label>Clinical notes</label><textarea name="clinical_notes"><?php echo clinicPortalEscape($record['clinical_notes']); ?></textarea></div>
                <div class="form-field full"><button class="button secondary" type="submit"><i class="fas fa-save"></i> Update record</button></div>
            </form>
        </details>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<section class="card">
    <div class="week-nav"><h3>Treatment history and plan</h3><a class="button-link secondary" href="treatments.php?patient_id=<?php echo (int) $patient['patient_id']; ?>">Manage treatments</a></div>
    <?php if (!$treatments): ?><p class="empty-state">No procedures are recorded for this patient yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Tooth</th><th>Procedure</th><th>Status</th><th>Notes</th></tr></thead><tbody><?php foreach ($treatments as $treatment): ?><tr><td><?php echo clinicPortalEscape($treatment['treatment_date']); ?></td><td><?php echo clinicPortalEscape($treatment['tooth_number'] ?: 'General'); ?></td><td><?php echo clinicPortalEscape($treatment['procedure_name']); ?></td><td><?php echo clinicPortalEscape($treatment['status']); ?></td><td><?php echo clinicPortalEscape($treatment['notes']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php elseif ($selectedPatientId > 0): ?>
    <div class="notice error">Patient not found in this clinic.</div>
<?php else: ?>
    <section class="card"><p class="empty-state">Choose a patient to review or update their dental records.</p></section>
<?php endif; ?>
<?php clinicPortalEnd(); ?>