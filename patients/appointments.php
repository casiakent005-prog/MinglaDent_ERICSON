<?php
require_once 'bootstrap.php';
require_once 'layout.php';

$patient = patientPortalContext();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!patientPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $clinicId = (int) ($_POST['clinic_id'] ?? 0);
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($action === 'cancel') {
        $stmt = $pdo->prepare("SELECT a.appointment_id, a.clinic_id, a.appointment_date, p.patient_id FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id WHERE a.appointment_id = ? AND p.user_id = ? AND a.status = 'scheduled' AND a.appointment_date > NOW() LIMIT 1");
        $stmt->execute([$appointmentId, $patient['user_id']]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            $message = 'Only your upcoming scheduled appointments can be cancelled.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND clinic_id = ? AND status = 'scheduled'");
            $stmt->execute([$appointmentId, $appointment['clinic_id']]);
            clinicPortalQueueAppointmentNotice($pdo, $patient, (int) $appointment['clinic_id'], $appointmentId, $appointment['appointment_date'], 'cancellation');
            clinicPortalAudit($pdo, (int) $patient['user_id'], (int) $appointment['clinic_id'], 'patient.appointment_cancelled', 'appointment', $appointmentId);
            $message = 'Your appointment was cancelled.';
        }
    } elseif ($action === 'book' || $action === 'reschedule') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $dateInput = trim($_POST['appointment_date'] ?? '');
        $appointmentDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateInput);

        $clinicStmt = $pdo->prepare("SELECT clinic_id FROM clinics WHERE clinic_id = ? AND status = 'approved' LIMIT 1");
        $clinicStmt->execute([$clinicId]);
        $clinicApproved = (bool) $clinicStmt->fetchColumn();

        $serviceStmt = $pdo->prepare("SELECT s.service_id, s.service_name, s.duration, (SELECT sf.amount FROM service_fees sf WHERE sf.service_id = s.service_id AND sf.clinic_id = ? ORDER BY sf.updated_at DESC, sf.fee_id DESC LIMIT 1) AS amount FROM services s WHERE s.clinic_id = ? AND s.service_id = ? AND s.status = 'active' LIMIT 1");
        $serviceStmt->execute([$clinicId, $clinicId, $serviceId]);
        $service = $serviceStmt->fetch();

        $dentistStmt = $pdo->prepare("SELECT staff_id FROM clinic_staff cs INNER JOIN auth_users au ON au.id = cs.user_id WHERE cs.staff_id = ? AND cs.clinic_id = ? AND cs.role = 'dentist' AND au.status = 'active' LIMIT 1");
        $dentistStmt->execute([$staffId, $clinicId]);
        $dentistValid = (bool) $dentistStmt->fetchColumn();

        if (!$clinicApproved || !$service || $service['amount'] === null || !$dentistValid || !$appointmentDate || $appointmentDate->getTimestamp() <= time() || (int) $service['duration'] < 1 || (int) $service['duration'] > 480) {
            $message = 'Choose an approved clinic, available dentist, service, and future date/time.';
            $messageType = 'error';
        } else {
            $appointmentEnd = $appointmentDate->modify('+' . (int) $service['duration'] . ' minutes');
            $lockNames = [
                'mdp_' . $patient['user_id'],
                'mdd_' . $clinicId . '_' . $staffId,
            ];
            sort($lockNames, SORT_STRING);
            $locked = [];

            try {
                foreach ($lockNames as $lockName) {
                    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                    $lockStmt->execute([$lockName]);
                    if ((int) $lockStmt->fetchColumn() !== 1) {
                        throw new RuntimeException('The booking calendar is busy. Try again.');
                    }
                    $locked[] = $lockName;
                }

                $pdo->beginTransaction();
                $patientRowStmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ? AND clinic_id = ? LIMIT 1");
                $patientRowStmt->execute([$patient['user_id'], $clinicId]);
                $clinicPatientId = $patientRowStmt->fetchColumn();

                if (!$clinicPatientId) {
                    $insertPatient = $pdo->prepare("INSERT INTO patients (user_id, clinic_id, name, email, phone, address, date_of_birth, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insertPatient->execute([$patient['user_id'], $clinicId, $patient['name'], $patient['email'], $patient['mobile_number'], $patient['address'], $patient['date_of_birth'], $patient['emergency_contact_name'], $patient['emergency_contact_phone']]);
                    $clinicPatientId = (int) $pdo->lastInsertId();
                } else {
                    $clinicPatientId = (int) $clinicPatientId;
                }

                $conflictStmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE clinic_id = ? AND staff_id = ? AND status = 'scheduled' AND appointment_date < ? AND COALESCE(appointment_end, DATE_ADD(appointment_date, INTERVAL 60 MINUTE)) > ? AND (? = 0 OR appointment_id <> ?) LIMIT 1");
                $conflictStmt->execute([$clinicId, $staffId, $appointmentEnd->format('Y-m-d H:i:s'), $appointmentDate->format('Y-m-d H:i:s'), $appointmentId, $appointmentId]);
                $dentistConflict = (bool) $conflictStmt->fetchColumn();

                $patientConflictStmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ? AND status = 'scheduled' AND appointment_date < ? AND COALESCE(appointment_end, DATE_ADD(appointment_date, INTERVAL 60 MINUTE)) > ? AND (? = 0 OR appointment_id <> ?) LIMIT 1");
                $patientConflictStmt->execute([$clinicPatientId, $appointmentEnd->format('Y-m-d H:i:s'), $appointmentDate->format('Y-m-d H:i:s'), $appointmentId, $appointmentId]);
                $patientConflict = (bool) $patientConflictStmt->fetchColumn();

                if ($dentistConflict || $patientConflict) {
                    $pdo->rollBack();
                    $message = 'That time overlaps another appointment. Choose a different time slot.';
                    $messageType = 'error';
                } elseif ($action === 'reschedule') {
                    $updateStmt = $pdo->prepare("UPDATE appointments SET staff_id = ?, service_id = ?, appointment_date = ?, appointment_end = ?, quoted_amount = ? WHERE appointment_id = ? AND clinic_id = ? AND patient_id = ? AND status = 'scheduled' AND appointment_date > NOW()");
                    $updateStmt->execute([$staffId, $serviceId, $appointmentDate->format('Y-m-d H:i:s'), $appointmentEnd->format('Y-m-d H:i:s'), $service['amount'], $appointmentId, $clinicId, $clinicPatientId]);
                    if (!$updateStmt->rowCount()) {
                        throw new RuntimeException('This appointment can no longer be rescheduled.');
                    }
                    $pdo->commit();
                    clinicPortalQueueAppointmentNotice($pdo, $patient, $clinicId, $appointmentId, $appointmentDate->format('Y-m-d H:i:s'), 'reschedule');
                    clinicPortalAudit($pdo, (int) $patient['user_id'], $clinicId, 'patient.appointment_rescheduled', 'appointment', $appointmentId);
                    $message = 'Your appointment was rescheduled.';
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO appointments (clinic_id, patient_id, staff_id, service_id, appointment_date, appointment_end, quoted_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
                    $insertStmt->execute([$clinicId, $clinicPatientId, $staffId, $serviceId, $appointmentDate->format('Y-m-d H:i:s'), $appointmentEnd->format('Y-m-d H:i:s'), $service['amount']]);
                    $newAppointmentId = (int) $pdo->lastInsertId();
                    $pdo->commit();
                    clinicPortalQueueAppointmentNotice($pdo, $patient, $clinicId, $newAppointmentId, $appointmentDate->format('Y-m-d H:i:s'), 'confirmation');
                    clinicPortalAudit($pdo, (int) $patient['user_id'], $clinicId, 'patient.appointment_booked', 'appointment', $newAppointmentId);
                    $message = 'Your appointment is booked.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (!($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'That time overlaps'))) {
                    $message = 'Could not complete the booking. Please choose another time and try again.';
                    $messageType = 'error';
                }
            } finally {
                foreach (array_reverse($locked) as $lockName) {
                    $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $releaseStmt->execute([$lockName]);
                }
            }
        }
    }
}

$clinicStmt = $pdo->query("SELECT clinic_id, clinic_name FROM clinics WHERE status = 'approved' ORDER BY clinic_name");
$clinics = $clinicStmt->fetchAll();
$selectedClinicId = (int) ($_POST['clinic_id'] ?? $_GET['clinic_id'] ?? 0);
$rescheduleId = (int) ($_POST['appointment_id'] ?? $_GET['reschedule'] ?? 0);
$rescheduleAppointment = null;
if ($rescheduleId > 0) {
    $stmt = $pdo->prepare("SELECT a.appointment_id, a.clinic_id, a.staff_id, a.service_id, a.appointment_date FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id WHERE a.appointment_id = ? AND p.user_id = ? AND a.status = 'scheduled' AND a.appointment_date > NOW() LIMIT 1");
    $stmt->execute([$rescheduleId, $patient['user_id']]);
    $rescheduleAppointment = $stmt->fetch();
    if ($rescheduleAppointment) {
        $selectedClinicId = (int) $rescheduleAppointment['clinic_id'];
    } elseif (isset($_GET['reschedule'])) {
        $message = 'That appointment can no longer be rescheduled.';
        $messageType = 'error';
    }
}
$services = [];
$dentists = [];
if ($selectedClinicId > 0) {
    $serviceStmt = $pdo->prepare("SELECT s.service_id, s.service_name, s.description, s.duration, (SELECT sf.amount FROM service_fees sf WHERE sf.service_id = s.service_id AND sf.clinic_id = ? ORDER BY sf.updated_at DESC, sf.fee_id DESC LIMIT 1) AS amount FROM services s WHERE s.clinic_id = ? AND s.status = 'active' AND EXISTS (SELECT 1 FROM service_fees sf WHERE sf.service_id = s.service_id AND sf.clinic_id = ?) ORDER BY s.service_name");
    $serviceStmt->execute([$selectedClinicId, $selectedClinicId, $selectedClinicId]);
    $services = $serviceStmt->fetchAll();

    $dentistStmt = $pdo->prepare("SELECT cs.staff_id, au.name FROM clinic_staff cs INNER JOIN auth_users au ON au.id = cs.user_id WHERE cs.clinic_id = ? AND cs.role = 'dentist' AND au.status = 'active' ORDER BY au.name");
    $dentistStmt->execute([$selectedClinicId]);
    $dentists = $dentistStmt->fetchAll();
}

$stmt = $pdo->prepare("SELECT a.appointment_id, a.clinic_id, a.patient_id, a.staff_id, a.service_id, a.appointment_date, a.appointment_end, a.status, c.clinic_name, s.service_name, au.name AS dentist_name FROM appointments a INNER JOIN patients p ON p.patient_id = a.patient_id INNER JOIN clinics c ON c.clinic_id = a.clinic_id LEFT JOIN services s ON s.service_id = a.service_id LEFT JOIN clinic_staff cs ON cs.staff_id = a.staff_id LEFT JOIN auth_users au ON au.id = cs.user_id WHERE p.user_id = ? ORDER BY a.appointment_date DESC LIMIT 50");
$stmt->execute([$patient['user_id']]);
$appointments = $stmt->fetchAll();
$csrfToken = patientPortalCsrfToken();

patientPortalStart($patient, 'Appointments', 'appointments');
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo patientPortalEscape($message); ?></div><?php endif; ?>
<section class="card">
    <h3>Book an appointment</h3>
    <form method="get" class="actions" style="margin-bottom: 18px;">
        <select name="clinic_id" required aria-label="Clinic"><option value="">Choose a clinic</option><?php foreach ($clinics as $clinic): ?><option value="<?php echo (int) $clinic['clinic_id']; ?>" <?php echo $selectedClinicId === (int) $clinic['clinic_id'] ? 'selected' : ''; ?>><?php echo patientPortalEscape($clinic['clinic_name']); ?></option><?php endforeach; ?></select>
        <button class="button secondary" type="submit">Choose clinic</button>
    </form>
    <?php if ($selectedClinicId > 0): ?>
        <?php if (!$services || !$dentists): ?><p class="empty-state">This clinic needs published service fees and an active dentist before online booking is available.</p><?php else: ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo patientPortalEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $rescheduleAppointment ? 'reschedule' : 'book'; ?>">
            <input type="hidden" name="clinic_id" value="<?php echo $selectedClinicId; ?>">
            <?php if ($rescheduleAppointment): ?><input type="hidden" name="appointment_id" value="<?php echo (int) $rescheduleAppointment['appointment_id']; ?>"><p class="empty-state">Rescheduling appointment #<?php echo (int) $rescheduleAppointment['appointment_id']; ?></p><?php endif; ?>
            <div class="form-field"><label for="service_id">Dental service</label><select id="service_id" name="service_id" required><option value="">Choose a service</option><?php foreach ($services as $service): ?><option value="<?php echo (int) $service['service_id']; ?>" <?php echo $rescheduleAppointment && (int) $rescheduleAppointment['service_id'] === (int) $service['service_id'] ? 'selected' : ''; ?>><?php echo patientPortalEscape($service['service_name']); ?> | <?php echo (int) $service['duration']; ?> min | ₱<?php echo number_format((float) $service['amount'], 2); ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="staff_id">Dentist</label><select id="staff_id" name="staff_id" required><option value="">Choose a dentist</option><?php foreach ($dentists as $dentist): ?><option value="<?php echo (int) $dentist['staff_id']; ?>" <?php echo $rescheduleAppointment && (int) $rescheduleAppointment['staff_id'] === (int) $dentist['staff_id'] ? 'selected' : ''; ?>><?php echo patientPortalEscape($dentist['name']); ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="appointment_date">Date and time</label><input id="appointment_date" name="appointment_date" type="datetime-local" min="<?php echo date('Y-m-d\TH:i'); ?>" value="<?php echo $rescheduleAppointment ? patientPortalEscape(date('Y-m-d\TH:i', strtotime($rescheduleAppointment['appointment_date']))) : ''; ?>" required></div>
            <div class="form-field full"><button class="button" type="submit"><i class="fas fa-calendar-check"></i> <?php echo $rescheduleAppointment ? 'Save new appointment time' : 'Check and book slot'; ?></button><?php if ($rescheduleAppointment): ?> <a class="button-link secondary" href="appointments.php">Cancel rescheduling</a><?php endif; ?></div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Your appointments</h3>
    <?php if (!$appointments): ?><p class="empty-state">No appointments yet. Browse clinics to book a visit.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Date and time</th><th>Clinic</th><th>Service</th><th>Dentist</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($appointments as $appointment): ?>
        <tr><td><?php echo patientPortalEscape(date('M j, Y g:i A', strtotime($appointment['appointment_date']))); ?></td><td><?php echo patientPortalEscape($appointment['clinic_name']); ?></td><td><?php echo patientPortalEscape($appointment['service_name'] ?: 'Visit'); ?></td><td><?php echo patientPortalEscape($appointment['dentist_name'] ?: 'Not assigned'); ?></td><td><span class="badge <?php echo patientPortalEscape($appointment['status']); ?>"><?php echo patientPortalEscape($appointment['status']); ?></span></td><td>
        <?php if ($appointment['status'] === 'scheduled' && strtotime($appointment['appointment_date']) > time()): ?>
        <div class="actions">
            <form method="post"><input type="hidden" name="csrf_token" value="<?php echo patientPortalEscape($csrfToken); ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="appointment_id" value="<?php echo (int) $appointment['appointment_id']; ?>"><button class="button danger" type="submit">Cancel</button></form>
            <a class="button-link secondary" href="appointments.php?clinic_id=<?php echo (int) $appointment['clinic_id']; ?>&reschedule=<?php echo (int) $appointment['appointment_id']; ?>">Reschedule</a>
        </div>
        <?php else: ?>- <?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php patientPortalEnd(); ?>