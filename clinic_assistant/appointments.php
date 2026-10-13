<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['assistant']);
$message = '';
$messageType = 'success';
$csrfToken = clinicPortalCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_appointment') {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $dateInput = trim($_POST['appointment_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $appointmentDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateInput);

        $patientStmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ? AND clinic_id = ? LIMIT 1");
        $patientStmt->execute([$patientId, $context['clinic_id']]);
        $patientExists = $patientStmt->fetchColumn();

        $dentistStmt = $pdo->prepare("SELECT staff_id FROM clinic_staff WHERE staff_id = ? AND clinic_id = ? AND role = 'dentist' LIMIT 1");
        $dentistStmt->execute([$staffId, $context['clinic_id']]);
        $dentistExists = (bool) $dentistStmt->fetchColumn();

        $serviceStmt = $pdo->prepare("SELECT s.service_id, s.service_name, s.duration, (SELECT sf.amount FROM service_fees sf WHERE sf.service_id = s.service_id AND sf.clinic_id = ? ORDER BY sf.updated_at DESC, sf.fee_id DESC LIMIT 1) AS amount FROM services s WHERE s.clinic_id = ? AND s.service_id = ? AND s.status = 'active' LIMIT 1");
        $serviceStmt->execute([$context['clinic_id'], $context['clinic_id'], $serviceId]);
        $service = $serviceStmt->fetch();

        if (!$patientExists || !$dentistExists || !$service || $service['amount'] === null || !$appointmentDate || $appointmentDate->getTimestamp() <= time() || $appointmentDate->format('Y-m-d\TH:i') !== $dateInput) {
            $message = 'Select a clinic patient, service with a fee, available dentist, and future date/time.';
            $messageType = 'error';
        } else {
            $appointmentEnd = $appointmentDate->modify('+' . (int) $service['duration'] . ' minutes');
            $lockName = 'mdd_' . $context['clinic_id'] . '_' . $staffId;
            $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lockStmt->execute([$lockName]);
            if ((int) $lockStmt->fetchColumn() !== 1) {
                $message = 'The schedule is busy. Try again.';
                $messageType = 'error';
            } else {
                try {
                    $pdo->beginTransaction();
                    $conflictStmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE clinic_id = ? AND staff_id = ? AND status = 'scheduled' AND appointment_date < ? AND COALESCE(appointment_end, DATE_ADD(appointment_date, INTERVAL 60 MINUTE)) > ? LIMIT 1");
                    $conflictStmt->execute([$context['clinic_id'], $staffId, $appointmentEnd->format('Y-m-d H:i:s'), $appointmentDate->format('Y-m-d H:i:s')]);
                    if ($conflictStmt->fetchColumn()) {
                        $pdo->rollBack();
                        $message = 'That dentist already has an appointment during this time.';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO appointments (clinic_id, patient_id, staff_id, service_id, appointment_date, appointment_end, quoted_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)");
                        $stmt->execute([$context['clinic_id'], $patientId, $staffId, $serviceId, $appointmentDate->format('Y-m-d H:i:s'), $appointmentEnd->format('Y-m-d H:i:s'), $service['amount'], $notes ?: null]);
                        $newAppointmentId = (int) $pdo->lastInsertId();
                        $contactStmt = $pdo->prepare("SELECT p.user_id, p.email, COALESCE(pp.mobile_number, p.phone) AS mobile_number FROM patients p LEFT JOIN patient_profiles pp ON pp.user_id = p.user_id WHERE p.patient_id = ? AND p.clinic_id = ? LIMIT 1");
                        $contactStmt->execute([$patientId, $context['clinic_id']]);
                        $patientContact = $contactStmt->fetch();
                        if ($patientContact) {
                            clinicPortalQueueAppointmentNotice($pdo, $patientContact, $context['clinic_id'], $newAppointmentId, $appointmentDate->format('Y-m-d H:i:s'), 'confirmation');
                        }
                        $pdo->commit();
                        clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'assistant.appointment_booked', 'appointment', $newAppointmentId);
                        $message = 'Appointment scheduled.';
                    }
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $message = 'Could not schedule the appointment. Try another time.';
                    $messageType = 'error';
                } finally {
                    $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $releaseStmt->execute([$lockName]);
                }
            }
        }
    } elseif ($action === 'update_status') {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['scheduled', 'completed', 'cancelled', 'no-show'], true)) {
            $message = 'Choose a valid appointment status.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND clinic_id = ?");
                $stmt->execute([$status, $appointmentId, $context['clinic_id']]);
                if ($status === 'completed') {
                    $invoiceStmt = $pdo->prepare("SELECT a.appointment_id, a.clinic_id, a.patient_id, a.service_id, a.quoted_amount, s.service_name FROM appointments a LEFT JOIN services s ON s.service_id = a.service_id WHERE a.appointment_id = ? AND a.clinic_id = ? LIMIT 1");
                    $invoiceStmt->execute([$appointmentId, $context['clinic_id']]);
                    $appointment = $invoiceStmt->fetch();
                    if ($appointment && $appointment['quoted_amount'] !== null) {
                        $createInvoice = $pdo->prepare("INSERT IGNORE INTO clinic_invoices (clinic_id, patient_id, appointment_id, service_id, description, amount, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $createInvoice->execute([$appointment['clinic_id'], $appointment['patient_id'], $appointment['appointment_id'], $appointment['service_id'], $appointment['service_name'] ?: 'Dental appointment', $appointment['quoted_amount'], $context['user_id']]);
                    }
                }
                if ($status === 'cancelled') {
                    $contactStmt = $pdo->prepare("SELECT a.appointment_id, a.clinic_id, a.appointment_date, p.user_id, p.email, COALESCE(pp.mobile_number, p.phone) AS mobile_number FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id LEFT JOIN patient_profiles pp ON pp.user_id = p.user_id WHERE a.appointment_id = ? AND a.clinic_id = ? LIMIT 1");
                    $contactStmt->execute([$appointmentId, $context['clinic_id']]);
                    $cancelledAppointment = $contactStmt->fetch();
                    if ($cancelledAppointment) {
                        clinicPortalQueueAppointmentNotice($pdo, $cancelledAppointment, $context['clinic_id'], $appointmentId, $cancelledAppointment['appointment_date'], 'cancellation');
                    }
                }
                $pdo->commit();
                clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'assistant.appointment_status_updated', 'appointment', $appointmentId);
                $message = 'Appointment updated.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'Could not update the appointment.';
                $messageType = 'error';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT patient_id, name FROM patients WHERE clinic_id = ? ORDER BY name");
$stmt->execute([$context['clinic_id']]);
$patients = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT cs.staff_id, au.name FROM clinic_staff cs INNER JOIN auth_users au ON au.id = cs.user_id WHERE cs.clinic_id = ? AND cs.role = 'dentist' AND au.status = 'active' ORDER BY au.name");
$stmt->execute([$context['clinic_id']]);
$dentists = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT DISTINCT s.service_id, s.service_name, s.duration FROM services s INNER JOIN service_fees sf ON sf.service_id = s.service_id WHERE sf.clinic_id = ? AND s.clinic_id = ? AND s.status = 'active' ORDER BY s.service_name");
$stmt->execute([$context['clinic_id'], $context['clinic_id']]);
$services = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT a.appointment_id, a.patient_id, a.staff_id, a.service_id, a.appointment_date, a.status, a.notes, p.name AS patient_name, au.name AS dentist_name, s.service_name FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id LEFT JOIN clinic_staff cs ON cs.staff_id = a.staff_id LEFT JOIN auth_users au ON au.id = cs.user_id LEFT JOIN services s ON s.service_id = a.service_id WHERE a.clinic_id = ? ORDER BY (a.appointment_date >= NOW()) DESC, a.appointment_date ASC LIMIT 100");
$stmt->execute([$context['clinic_id']]);
$appointments = $stmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'payments', 'label' => 'Payments', 'href' => 'payments.php', 'icon' => 'fa-cash-register'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'fa-envelope'],
    ['key' => 'activity', 'label' => 'Activity log', 'href' => 'activity.php', 'icon' => 'fa-clipboard-list'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fa-chart-line'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Appointments', 'appointments', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <h3>Schedule appointment</h3>
    <?php if (!$patients): ?><p class="empty-state">Register a patient before scheduling an appointment.</p><?php else: ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="create_appointment">
        <div class="form-field"><label for="patient_id">Patient</label><select id="patient_id" name="patient_id" required><option value="">Choose a patient</option><?php foreach ($patients as $patient): ?><option value="<?php echo (int) $patient['patient_id']; ?>"><?php echo clinicPortalEscape($patient['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="staff_id">Dentist</label><select id="staff_id" name="staff_id" required><option value="">Choose a dentist</option><?php foreach ($dentists as $dentist): ?><option value="<?php echo (int) $dentist['staff_id']; ?>"><?php echo clinicPortalEscape($dentist['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="service_id">Service</label><select id="service_id" name="service_id" required><option value="">Choose a service</option><?php foreach ($services as $service): ?><option value="<?php echo (int) $service['service_id']; ?>"><?php echo clinicPortalEscape($service['service_name']); ?> | <?php echo (int) $service['duration']; ?> min</option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="appointment_date">Date and time</label><input id="appointment_date" name="appointment_date" type="datetime-local" value="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>" required></div>
        <div class="form-field"><label for="notes">Appointment notes</label><input id="notes" name="notes" maxlength="500"></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-calendar-plus"></i> Schedule appointment</button></div>
    </form>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Clinic appointments</h3>
    <?php if (!$appointments): ?><p class="empty-state">No appointments have been created yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Date and time</th><th>Patient</th><th>Service</th><th>Dentist</th><th>Notes</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($appointments as $appointment): ?>
            <tr>
                <td><?php echo clinicPortalEscape(date('M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></td>
                <td><?php echo clinicPortalEscape($appointment['patient_name']); ?></td>
                <td><?php echo clinicPortalEscape($appointment['service_name'] ?: 'Not selected'); ?></td>
                <td><?php echo clinicPortalEscape($appointment['dentist_name'] ?: 'Unassigned'); ?></td>
                <td><?php echo clinicPortalEscape($appointment['notes'] ?: ''); ?></td>
                <td><form method="post" class="actions"><input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="appointment_id" value="<?php echo (int) $appointment['appointment_id']; ?>"><select name="status" aria-label="Appointment status"><?php foreach (['scheduled', 'completed', 'cancelled', 'no-show'] as $status): ?><option value="<?php echo clinicPortalEscape($status); ?>" <?php echo $appointment['status'] === $status ? 'selected' : ''; ?>><?php echo clinicPortalEscape(ucfirst($status)); ?></option><?php endforeach; ?></select><button class="button secondary" type="submit">Save</button></form></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php clinicPortalEnd(); ?>