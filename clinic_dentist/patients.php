<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['dentist']);
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_patient') {
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');

    if ($patientId < 1 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        $message = 'Enter a name, valid email address, and contact number.';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare("UPDATE patients SET name = ?, email = ?, phone = ?, address = ?, date_of_birth = ? WHERE patient_id = ? AND clinic_id = ?");
        $stmt->execute([$name, $email, $phone, $address ?: null, $dateOfBirth ?: null, $patientId, $context['clinic_id']]);
        $message = 'Patient information updated.';
    }
}

$search = trim($_GET['q'] ?? '');
$searchPattern = '%' . $search . '%';
$stmt = $pdo->prepare("SELECT p.patient_id, p.name, p.email, p.phone, p.date_of_birth, p.address, p.created_at, COUNT(dr.record_id) AS record_count FROM patients p LEFT JOIN dental_records dr ON dr.patient_id = p.patient_id AND dr.clinic_id = p.clinic_id WHERE p.clinic_id = ? AND (? = '' OR CAST(p.patient_id AS CHAR) = ? OR p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?) GROUP BY p.patient_id ORDER BY p.name");
$stmt->execute([$context['clinic_id'], $search, $search, $searchPattern, $searchPattern, $searchPattern]);
$patients = $stmt->fetchAll();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'schedule', 'label' => 'My Schedule', 'href' => 'schedule.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'records', 'label' => 'Dental Records', 'href' => 'records.php', 'icon' => 'fa-notes-medical'],
    ['key' => 'treatments', 'label' => 'Treatment plans', 'href' => 'treatments.php', 'icon' => 'fa-tooth'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php', 'icon' => 'fa-id-card'],
];

clinicPortalStart($context, 'Patients', 'patients', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>
<section class="card">
    <div class="week-nav"><h3>Clinic Patient Records</h3><form method="get" class="actions"><input type="search" name="q" value="<?php echo clinicPortalEscape($search); ?>" placeholder="Name, ID, email, or phone" aria-label="Search patients"><button class="button secondary" type="submit"><i class="fas fa-search"></i> Search</button></form></div>
    <?php if (!$patients): ?>
        <p class="empty-state">No patients have been registered at this clinic yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Patient</th><th>Email</th><th>Phone</th><th>Date of birth</th><th>Dental records</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($patients as $patient): ?>
            <tr>
                <td><?php echo clinicPortalEscape($patient['name']); ?></td>
                <td><?php echo clinicPortalEscape($patient['email']); ?></td>
                <td><?php echo clinicPortalEscape($patient['phone']); ?></td>
                <td><?php echo clinicPortalEscape($patient['date_of_birth'] ?: 'Not provided'); ?></td>
                <td><?php echo (int) $patient['record_count']; ?></td>
                <td><div class="actions"><a class="button-link secondary" href="records.php?patient_id=<?php echo (int) $patient['patient_id']; ?>"><i class="fas fa-notes-medical"></i> Dental record</a><details><summary style="cursor: pointer;">Update patient</summary><form method="post" class="form-grid" style="min-width: 360px; margin-top: 12px;"><input type="hidden" name="action" value="update_patient"><input type="hidden" name="patient_id" value="<?php echo (int) $patient['patient_id']; ?>"><div class="form-field"><label>Full name</label><input name="name" value="<?php echo clinicPortalEscape($patient['name']); ?>" maxlength="255" required></div><div class="form-field"><label>Email</label><input name="email" type="email" value="<?php echo clinicPortalEscape($patient['email']); ?>" maxlength="255" required></div><div class="form-field"><label>Phone</label><input name="phone" value="<?php echo clinicPortalEscape($patient['phone']); ?>" maxlength="20" required></div><div class="form-field"><label>Date of birth</label><input name="date_of_birth" type="date" value="<?php echo clinicPortalEscape($patient['date_of_birth']); ?>"></div><div class="form-field full"><label>Address</label><input name="address" value="<?php echo clinicPortalEscape($patient['address'] ?? ''); ?>" maxlength="255"></div><div class="form-field full"><button class="button secondary" type="submit">Save patient</button></div></form></details></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php clinicPortalEnd(); ?>