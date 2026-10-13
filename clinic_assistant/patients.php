<?php
require_once '../clinic_portal/bootstrap.php';
require_once '../clinic_portal/layout.php';

$context = clinicPortalRequireRoles(['assistant']);
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_patient') {
    if (!clinicPortalVerifyCsrf()) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');

    if ($name === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter the patient name, a valid email address, and contact number.';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare("INSERT INTO patients (clinic_id, registered_by, name, email, phone, address, date_of_birth, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$context['clinic_id'], $context['user_id'], $name, $email, $phone, $address ?: null, $dateOfBirth ?: null, $emergencyName ?: null, $emergencyPhone ?: null]);
        clinicPortalAudit($pdo, (int) $context['user_id'], (int) $context['clinic_id'], 'assistant.patient_registered', 'patient', (int) $pdo->lastInsertId());
        $message = 'Patient registered successfully.';
    }
}

$search = trim($_GET['q'] ?? '');
$searchPattern = '%' . $search . '%';
$stmt = $pdo->prepare("SELECT patient_id, name, email, phone, address, date_of_birth, created_at FROM patients WHERE clinic_id = ? AND (? = '' OR CAST(patient_id AS CHAR) = ? OR name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY created_at DESC");
$stmt->execute([$context['clinic_id'], $search, $search, $searchPattern, $searchPattern, $searchPattern]);
$patients = $stmt->fetchAll();
$csrfToken = clinicPortalCsrfToken();

$navigation = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-home'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => 'patients.php', 'icon' => 'fa-user-injured'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'fa-calendar-alt'],
    ['key' => 'payments', 'label' => 'Payments', 'href' => 'payments.php', 'icon' => 'fa-cash-register'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'fa-envelope'],
    ['key' => 'activity', 'label' => 'Activity log', 'href' => 'activity.php', 'icon' => 'fa-clipboard-list'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fa-chart-line'],
];

clinicPortalStart($context, 'Patients', 'patients', $navigation);
?>
<?php if ($message): ?><div class="notice <?php echo $messageType === 'error' ? 'error' : ''; ?>"><?php echo clinicPortalEscape($message); ?></div><?php endif; ?>

<section class="card">
    <h3>Register patient</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?php echo clinicPortalEscape($csrfToken); ?>">
        <input type="hidden" name="action" value="register_patient">
        <div class="form-field"><label for="name">Full name</label><input id="name" name="name" maxlength="255" required></div>
        <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="255" required></div>
        <div class="form-field"><label for="phone">Contact number</label><input id="phone" name="phone" type="tel" maxlength="20" required></div>
        <div class="form-field"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date"></div>
        <div class="form-field full"><label for="address">Address</label><textarea id="address" name="address" maxlength="255"></textarea></div>
        <div class="form-field"><label for="emergency_contact_name">Emergency contact name</label><input id="emergency_contact_name" name="emergency_contact_name" maxlength="255"></div>
        <div class="form-field"><label for="emergency_contact_phone">Emergency contact number</label><input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" maxlength="20"></div>
        <div class="form-field full"><button class="button" type="submit"><i class="fas fa-user-plus"></i> Register patient</button></div>
    </form>
</section>

<section class="card">
    <div class="week-nav"><h3>Clinic patients</h3><form method="get" class="actions"><input type="search" name="q" value="<?php echo clinicPortalEscape($search); ?>" placeholder="Name, ID, email, or phone" aria-label="Search patients"><button class="button secondary" type="submit"><i class="fas fa-search"></i> Search</button></form></div>
    <?php if (!$patients): ?>
        <p class="empty-state">No patients registered yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Date of birth</th><th>Registered</th></tr></thead>
        <tbody><?php foreach ($patients as $patient): ?>
            <tr><td><?php echo clinicPortalEscape($patient['name']); ?></td><td><?php echo clinicPortalEscape($patient['email']); ?></td><td><?php echo clinicPortalEscape($patient['phone']); ?></td><td><?php echo clinicPortalEscape($patient['date_of_birth'] ?: 'Not provided'); ?></td><td><?php echo clinicPortalEscape(date('M j, Y', strtotime($patient['created_at']))); ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php clinicPortalEnd(); ?>