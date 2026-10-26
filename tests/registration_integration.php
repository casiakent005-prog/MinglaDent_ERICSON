<?php
/** Real HTTP + MariaDB regression tests. Run only against tests/bootstrap_database.php's isolated DB. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/config.php';
if (!str_ends_with($pdo->query('SELECT DATABASE()')->fetchColumn(), '_test')) {
    fwrite(STDERR, "Refusing to run outside an isolated *_test database.\n"); exit(1);
}
session_write_close();
$baseUrl = getenv('MINGLADENT_TEST_URL') ?: 'http://localhost:8080';
$prefix = 'r' . bin2hex(random_bytes(5));
$passed = 0;
function check(bool $condition, string $description): void {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    ++$passed;
    echo "PASS: $description\n";
}
function row(string $sql, array $params = []): ?array {
    global $pdo;
    $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetch() ?: null;
}
function countRows(string $table): int { global $pdo; return (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }
class Client {
    public string $cookieFile;
    public function __construct() { $this->cookieFile = tempnam(sys_get_temp_dir(), 'md-cookies-'); }
    public function __destruct() { if (is_file($this->cookieFile)) unlink($this->cookieFile); }
    public function request(string $path, ?array $fields = null): array {
        global $baseUrl;
        $curl = curl_init($baseUrl . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile, CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
        if ($fields !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        $response = curl_exec($curl);
        if ($response === false) throw new RuntimeException(curl_error($curl));
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $header = substr($response, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
        $body = substr($response, curl_getinfo($curl, CURLINFO_HEADER_SIZE));
        preg_match('/^Location:\s*(.+)$/mi', $header, $match);
        return ['status' => $status, 'body' => $body, 'header' => $header, 'location' => trim($match[1] ?? '')];
    }
    public function csrf(string $path): string {
        $response = $this->request($path);
        if ($response['status'] !== 200) throw new RuntimeException("Unable to open $path: " . $response['status']);
        if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $response['body'], $m)) throw new RuntimeException("Missing CSRF on $path");
        return $m[1];
    }
    public function submit(string $path, array $fields): array {
        $tokenPath = str_contains($path, 'register') ? $path . '?type=' . ($fields['registration_type'] ?? 'patient') : $path;
        if ($path === 'patients/appointments.php' && isset($fields['clinic_id'])) $tokenPath .= '?clinic_id=' . (int) $fields['clinic_id'];
        if ($path === 'clinic_dentist/records.php' && isset($fields['patient_id'])) $tokenPath .= '?patient_id=' . (int) $fields['patient_id'];
        return $this->request($path, ['csrf_token' => $this->csrf($tokenPath)] + $fields);
    }
    public function login(string $email, string $password = 'Fixture-Only-Password-2026', string $path = 'login/login_new.php', bool $remember = false): array {
        return $this->submit($path, ['email' => $email, 'password' => $password] + ($remember ? ['remember' => '1'] : []));
    }
}
function data(string $type, string $key): array {
    global $prefix;
    return ['registration_type' => $type, 'first_name' => 'Test', 'middle_name' => '', 'last_name' => ucfirst($key),
        'date_of_birth' => '1996-02-29', 'sex' => 'female', 'contact_number' => '09123456789',
        'complete_address' => 'Test Street, Minglanilla, Cebu', 'email' => "$prefix-$key@example.test",
        'username' => "$prefix-$key", 'password' => 'Fixture-Only-Password-2026',
        'confirm_password' => 'Fixture-Only-Password-2026', 'terms_agreed' => '1',
        'clinic_name' => "$prefix $key Dental Clinic", 'clinic_address' => 'Test Street, Minglanilla, Cebu',
        'clinic_contact_number' => '09123456789', 'clinic_email' => "$prefix-$key-clinic@example.test",
        'operating_hours' => 'Monday–Friday, 9 AM–5 PM', 'description' => 'Synthetic clinic for registration verification.',
        'services_offered' => 'Cleaning, fillings', 'service_area' => 'Minglanilla, Cebu', 'practicing_dentist' => '1'];
}
$files = [];
try {
    $fixture = tempnam(sys_get_temp_dir(), 'md-document-'); $files[] = $fixture;
    file_put_contents($fixture, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    $upload = new CURLFile($fixture, 'application/pdf', 'authorization.pdf');
    $anonymous = new Client();
    $patient = new Client(); $owner = new Client(); $secondOwner = new Client(); $admin = new Client();
    $patientData = data('patient', 'patient');
    $originalCount = countRows('auth_users');
    foreach ([['first_name' => ''], ['email' => 'invalid-email'], ['complete_address' => ''],
        ['date_of_birth' => '2025-02-30'], ['date_of_birth' => '2999-01-01'],
        ['confirm_password' => 'Does-not-match'], ['terms_agreed' => ''], ['password' => 'short'],
        ['sex' => 'administrator'], ['username' => 'bad@username'], ['contact_number' => 'invalid']] as $invalid) {
        $response = $anonymous->submit('login/register_new.php', array_replace($patientData, $invalid));
        check($response['status'] === 200 && $response['location'] === '' && countRows('auth_users') === $originalCount,
            'Server rejects invalid ' . implode(', ', array_keys($invalid)) . ' without creating an account');
    }
    $response = $anonymous->request('login/register_new.php', $patientData);
    check($response['status'] === 419 && countRows('auth_users') === $originalCount, 'Registration requires CSRF');
    $response = $patient->submit('login/register_new.php', $patientData + ['role' => 'admin', 'status' => 'active', 'clinic_id' => '999']);
    check($response['status'] === 302 && $response['location'] === 'login_new.php', 'Patient registration redirects to login');
    $patientRow = row('SELECT * FROM auth_users WHERE email = ?', [$patientData['email']]);
    check($patientRow && $patientRow['role'] === 'patient' && $patientRow['clinic_id'] === null, 'Patient role and clinic isolation ignore privilege tampering');
    check(password_verify($patientData['password'], $patientRow['password']) && $patientRow['password'] !== $patientData['password'], 'Patient password is securely hashed');
    $profile = row('SELECT * FROM patient_profiles WHERE user_id = ?', [$patientRow['id']]);
    check($profile && $profile['date_of_birth'] === '1996-02-29' && $profile['mobile_number'] === $patientData['contact_number'] && $profile['privacy_consent_at'] && $patientRow['terms_agreed_at'], 'Patient profile and consent saved in the database');
    $count = countRows('auth_users');
    foreach ([['email' => strtoupper($patientData['email']), 'username' => $prefix . '-different'], ['email' => "$prefix-other@example.test"]] as $duplicate) {
        $response = $anonymous->submit('login/register_new.php', array_replace($patientData, $duplicate));
        check(str_contains($response['body'], 'already') && countRows('auth_users') === $count, 'Duplicate email or username rejected without extra account');
    }
    $response = $patient->login($patientData['username']);
    check($response['location'] === '../patients/dashboard.php', 'Username login routes patient to Patient Dashboard');
    check($patient->request('patients/dashboard.php')['status'] === 200, 'Patient Dashboard renders after registration');
    check(str_contains($response['header'], 'HttpOnly') && str_contains($response['header'], 'SameSite=Lax'), 'Authentication session cookie has HttpOnly and SameSite protections');
    foreach (['clinic_admin/dashboard.php', 'clinic_dentist/dashboard.php', 'clinic_assistant/dashboard.php', 'system_admin/clinics.php'] as $path) {
        check($patient->request($path)['status'] !== 200, 'Patient blocked from ' . $path);
    }
    $response = $patient->request('system_admin/clinics.php', ['action' => 'approve_clinic', 'clinic_id' => 1, 'role' => 'admin']);
    check($response['status'] !== 200, 'Patient cannot approve clinics with forged role requests');

    $clinicData = data('clinic', 'owner');
    foreach ([[], ['document_authorization' => new CURLFile(__FILE__, 'application/pdf', 'fake.pdf')],
        ['document_authorization' => new CURLFile($fixture, 'application/pdf', 'payload.php')]] as $badUpload) {
        $response = $anonymous->submit('login/register_new.php', $clinicData + $badUpload);
        check($response['location'] === '' && countRows('auth_users') === $count, 'Missing or invalid supporting document rejected');
    }
    $large = tempnam(sys_get_temp_dir(), 'md-large-'); $files[] = $large;
    file_put_contents($large, "%PDF-1.4\n" . str_repeat('x', 5 * 1024 * 1024));
    $response = $anonymous->submit('login/register_new.php', $clinicData + ['document_authorization' => new CURLFile($large, 'application/pdf', 'large.pdf')]);
    check(str_contains($response['body'], 'at most 5 MB') && countRows('auth_users') === $count, 'Oversized supporting document rejected');
    $response = $anonymous->submit('login/register_new.php', array_replace($clinicData, ['service_area' => 'Unsupported']) + ['document_authorization' => $upload]);
    check($response['location'] === '' && countRows('auth_users') === $count, 'Unsupported clinic service area rejected');
    $response = $owner->submit('login/register_new.php', $clinicData + ['document_authorization' => $upload, 'role' => 'admin', 'status' => 'approved', 'owner_user_id' => $patientRow['id']]);
    check($response['location'] === 'clinic_status.php', 'Clinic registration redirects to Pending Approval');
    $ownerRow = row('SELECT * FROM auth_users WHERE email = ?', [$clinicData['email']]);
    $clinicRow = row('SELECT * FROM clinics WHERE owner_user_id = ?', [$ownerRow['id']]);
    $membership = row('SELECT * FROM clinic_staff WHERE user_id = ?', [$ownerRow['id']]);
    check($ownerRow['role'] === 'clinic' && $clinicRow['status'] === 'pending' && $clinicRow['submitted_at'], 'Clinic account and Pending application saved with server-assigned authority');
    check($membership['role'] === 'clinic_admin' && (int) $membership['is_dentist'] === 1 && $membership['clinic_id'] === $clinicRow['clinic_id'] && $ownerRow['clinic_id'] === $clinicRow['clinic_id'], 'Owner, dentist capability, and clinic membership share one account');
    check($clinicRow['description'] === $clinicData['description'] && $clinicRow['services_offered'] === $clinicData['services_offered'], 'Clinic description and offered services persist');
    $document = row('SELECT * FROM clinic_application_documents WHERE clinic_id = ?', [$clinicRow['clinic_id']]);
    check($document && $document['uploaded_by'] === $ownerRow['id'] && $document['storage_name'] !== 'authorization.pdf', 'Document metadata saved with randomized storage name');
    check($owner->request('login/clinic_document.php?id=' . $document['document_id'])['body'] === file_get_contents($fixture), 'Submitting owner can download their document');
    check($anonymous->request('login/clinic_document.php?id=' . $document['document_id'])['status'] === 403 && $patient->request('login/clinic_document.php?id=' . $document['document_id'])['status'] === 403, 'Anonymous users and patients cannot read clinic documents');
    check(str_contains($owner->request('login/clinic_status.php')['body'], 'Pending Approval'), 'Pending Approval notice renders');
    check(!str_contains($anonymous->request('locations.php')['body'], $clinicData['clinic_name']), 'Pending clinic excluded from public marketplace');
    $protected = [];
    foreach (['clinic_admin', 'clinic_dentist', 'clinic_assistant'] as $directory) {
        foreach (glob(__DIR__ . '/../' . $directory . '/*.php') as $path) {
            if (!in_array(basename($path), ['bootstrap.php','layout.php','access.php'], true)) $protected[] = $directory . '/' . basename($path);
        }
    }
    $protected[] = 'clinic_portal/manage_clinic.php';
    foreach ($protected as $path) {
        $response = $owner->request($path);
        check($response['status'] !== 200, 'Pending clinic blocked from ' . $path);
    }
    $response = $owner->request('clinic_admin/clinic_staff.php', ['action' => 'add_staff', 'role' => 'admin', 'name' => 'Forged', 'email' => 'forged@example.test', 'password' => 'invalid']);
    check($response['location'] === '../login/clinic_status.php', 'Pending clinic cannot perform management POST');

    $response = $admin->login('registration-admin@example.test');
    check($response['location'] === '../system_admin/dashboard.php' && $admin->request('system_admin/dashboard.php')['status'] === 200, 'Existing System Admin login and dashboard work');
    $reviewPage = $admin->request('system_admin/clinics.php');
    check(str_contains($reviewPage['body'], $clinicData['description']) && str_contains($reviewPage['body'], $clinicData['email']) && str_contains($reviewPage['body'], 'authorization.pdf'), 'System Admin sees clinic, owner, and document details');
    check($admin->request('login/clinic_document.php?id=' . $document['document_id'])['body'] === file_get_contents($fixture), 'System Admin can securely download supporting documents');
    $response = $admin->request('system_admin/clinics.php', ['action' => 'approve_clinic', 'clinic_id' => $clinicRow['clinic_id']]);
    check($response['status'] === 419 && row('SELECT status FROM clinics WHERE clinic_id = ?', [$clinicRow['clinic_id']])['status'] === 'pending', 'Approval requires CSRF');
    $response = $admin->submit('system_admin/clinics.php', ['action' => 'approve_clinic', 'clinic_id' => $clinicRow['clinic_id']]);
    $approved = row('SELECT * FROM clinics WHERE clinic_id = ?', [$clinicRow['clinic_id']]);
    check($approved['status'] === 'approved' && $approved['reviewed_at'] && $approved['reviewed_by'], 'System Admin approval records state, reviewer, and timestamp');
    check(row('SELECT * FROM clinic_application_decisions WHERE clinic_id = ? AND decision = ?', [$clinicRow['clinic_id'], 'approved']) !== null, 'Approval decision history persists');
    check(str_contains($anonymous->request('locations.php')['body'], $clinicData['clinic_name']), 'Approved clinic appears in public marketplace');
    check($owner->request('clinic_admin/dashboard.php')['status'] === 200 && $owner->request('clinic_dentist/dashboard.php')['status'] === 200, 'Approved practicing owner can use both admin and dentist dashboards');
    check($owner->request('clinic_assistant/dashboard.php')['status'] === 403, 'Owner does not receive unassigned assistant permissions');
    $owner->request('login/logout.php');
    check($owner->login($clinicData['email'])['location'] === '../clinic_admin/dashboard.php', 'Approved owner login routes to Clinic Admin Dashboard');

    // Legacy URLs still create patient accounts and accept existing email logins.
    $legacy = new Client(); $legacyData = data('patient', 'legacy');
    check($legacy->submit('login/register.php', $legacyData)['location'] === 'login_new.php', 'Legacy registration URL saves a patient through the shared handler');
    check($legacy->login($legacyData['email'], $legacyData['password'], 'login/login.php')['location'] === '../patients/dashboard.php', 'Legacy login URL authenticates existing patient accounts');

    $secondData = data('clinic', 'other'); $secondData['practicing_dentist'] = '0';
    check($secondOwner->submit('login/register_new.php', $secondData + ['document_authorization' => $upload])['location'] === 'clinic_status.php', 'Second clinic registration succeeds');
    $otherOwner = row('SELECT * FROM auth_users WHERE email = ?', [$secondData['email']]);
    $otherClinic = row('SELECT * FROM clinics WHERE owner_user_id = ?', [$otherOwner['id']]);
    check($secondOwner->request('login/clinic_document.php?id=' . $document['document_id'])['status'] === 404, 'Another clinic owner cannot read private application documents');
    $admin->submit('system_admin/clinics.php', ['action' => 'approve_clinic', 'clinic_id' => $otherClinic['clinic_id']]);
    check($secondOwner->request('clinic_admin/dashboard.php')['status'] === 200 && $secondOwner->request('clinic_dentist/dashboard.php')['status'] === 403, 'Owner without dentist permission cannot enter dentist dashboard');
    $response = $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'edit_staff', 'staff_id' => $otherOwner['id'], 'name' => 'Hijacked', 'email' => 'hijacked@example.test', 'role' => 'clinic_admin']);
    check($response['status'] === 404 && row('SELECT name FROM auth_users WHERE id = ?', [$otherOwner['id']])['name'] === $otherOwner['name'], 'Cross-clinic staff modification is denied');
    check(!str_contains($owner->request('clinic_admin/clinic_staff.php')['body'], $secondData['email']), 'Clinic staff list does not expose another clinic');
    $response = $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'add_staff', 'name' => 'Invalid role', 'email' => "$prefix-invalid-role@example.test", 'role' => 'admin', 'password' => $patientData['password']]);
    check(!row('SELECT id FROM auth_users WHERE email = ?', ["$prefix-invalid-role@example.test"]), 'Clinic owner cannot create a System Admin');
    $staffEmail = "$prefix-dentist@example.test";
    $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'add_staff', 'name' => 'Assigned Dentist', 'email' => $staffEmail, 'role' => 'dentist', 'password' => $patientData['password'], 'clinic_id' => $otherClinic['clinic_id']]);
    $staff = row('SELECT u.*, cs.clinic_id AS assigned_clinic, cs.staff_id FROM auth_users u JOIN clinic_staff cs ON cs.user_id = u.id WHERE u.email = ?', [$staffEmail]);
    check($staff && $staff['assigned_clinic'] === $clinicRow['clinic_id'], 'Clinic staff creation ignores forged foreign clinic assignment');
    $dentist = new Client();
    check($dentist->login($staffEmail)['location'] === '../clinic_dentist/dashboard.php' && $dentist->request('clinic_dentist/dashboard.php')['status'] === 200, 'Assigned dentist login and dashboard work');
    check($dentist->request('clinic_admin/dashboard.php')['status'] === 403, 'Dentist cannot access clinic administrator functions');
    $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'set_staff_status', 'staff_id' => $staff['id'], 'status' => 'inactive']);
    check($dentist->request('clinic_dentist/dashboard.php')['status'] !== 200, 'Staff deactivation revokes an existing session');
    $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'set_staff_status', 'staff_id' => $staff['id'], 'status' => 'active']);
    $assistantEmail = "$prefix-assistant@example.test";
    $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'add_staff', 'name' => 'Assigned Assistant', 'email' => $assistantEmail, 'role' => 'assistant', 'password' => $patientData['password']]);
    $assistant = new Client();
    check($assistant->login($assistantEmail)['location'] === '../clinic_assistant/dashboard.php' && $assistant->request('clinic_assistant/dashboard.php')['status'] === 200, 'Assigned assistant login and dashboard work');
    check($assistant->request('clinic_dentist/dashboard.php')['status'] === 403, 'Assistant cannot enter dentist dashboard');
    $owner->submit('clinic_admin/clinic_staff.php', ['action' => 'edit_staff', 'staff_id' => $ownerRow['id'], 'name' => $ownerRow['name'], 'email' => $ownerRow['email'], 'role' => 'assistant']);
    check(row('SELECT role FROM clinic_staff WHERE user_id = ?', [$ownerRow['id']])['role'] === 'clinic_admin', 'Clinic ownership cannot be demoted through staff editing');

    // Actual existing service + booking consumers recognize owner/dentist capability.
    $owner->submit('clinic_portal/manage_clinic.php', ['action' => 'save_service', 'service_name' => "$prefix Cleaning", 'description' => 'Test', 'duration' => '30', 'amount' => '500']);
    $service = row('SELECT * FROM services WHERE clinic_id = ? ORDER BY service_id DESC LIMIT 1', [$clinicRow['clinic_id']]);
    check($service !== null, 'Approved owner can configure an actual bookable service');
    $secondOwner->submit('clinic_portal/manage_clinic.php', ['action' => 'save_service', 'service_name' => "$prefix Other Service", 'description' => 'Private other service', 'duration' => '30', 'amount' => '600']);
    $otherService = row('SELECT * FROM services WHERE clinic_id = ? ORDER BY service_id DESC LIMIT 1', [$otherClinic['clinic_id']]);
    $owner->submit('clinic_admin/services.php', ['action' => 'edit_service', 'service_id' => $otherService['service_id'], 'clinic_id' => $otherClinic['clinic_id'], 'service_name' => 'Hijacked', 'description' => 'Hijacked', 'duration' => '20']);
    check(row('SELECT service_name FROM services WHERE service_id = ?', [$otherService['service_id']])['service_name'] === $otherService['service_name'], 'Cross-clinic service modification does not change foreign data');
    $bookingPage = $patient->request('patients/appointments.php?clinic_id=' . $clinicRow['clinic_id']);
    check(str_contains($bookingPage['body'], 'value="' . $membership['staff_id'] . '"'), 'Practicing owner is offered in the appointment dentist picker');
    $date = (new DateTimeImmutable('+3 days'))->setTime(10, 0)->format('Y-m-d\TH:i');
    $patient->submit('patients/appointments.php', ['action' => 'book', 'clinic_id' => $clinicRow['clinic_id'], 'service_id' => $service['service_id'], 'staff_id' => $membership['staff_id'], 'appointment_date' => $date]);
    $appointment = row('SELECT a.* FROM appointments a JOIN patients p ON p.patient_id = a.patient_id WHERE p.user_id = ? AND a.clinic_id = ?', [$patientRow['id'], $clinicRow['clinic_id']]);
    check($appointment && $appointment['staff_id'] === $membership['staff_id'], 'Patient can book the owner as dentist using the same staff membership');
    $owner->submit('clinic_dentist/records.php', ['action' => 'create', 'patient_id' => $appointment['patient_id'], 'appointment_id' => $appointment['appointment_id'], 'diagnosis' => $prefix . ' private diagnosis']);
    check(row('SELECT * FROM dental_records WHERE clinic_id = ? AND dentist_user_id = ?', [$clinicRow['clinic_id'], $ownerRow['id']]) !== null, 'Practicing owner saves dental care under the same account');
    $foreignRecord = $secondOwner->request('clinic_dentist/records.php?patient_id=' . $appointment['patient_id']);
    check($foreignRecord['status'] !== 200, 'Unassigned foreign owner cannot open dentist records');
    $secondOwner->submit('clinic_admin/clinic_staff.php', ['action' => 'edit_staff', 'staff_id' => $otherOwner['id'], 'name' => $otherOwner['name'], 'email' => $otherOwner['email'], 'role' => 'clinic_admin', 'is_dentist' => '1']);
    $foreignRecord = $secondOwner->request('clinic_dentist/records.php?patient_id=' . $appointment['patient_id']);
    check($foreignRecord['status'] === 200 && !str_contains($foreignRecord['body'], $prefix . ' private diagnosis') && !str_contains($foreignRecord['body'], $patientData['email']), 'Authorized dentist in another clinic cannot read foreign patient data');
    $beforeRecords = countRows('dental_records');
    $response = $secondOwner->request('clinic_dentist/records.php', ['csrf_token' => $secondOwner->csrf('clinic_portal/manage_clinic.php'), 'action' => 'create', 'patient_id' => $appointment['patient_id'], 'diagnosis' => 'Foreign write']);
    check(str_contains($response['body'], 'Choose a patient in your clinic.') && countRows('dental_records') === $beforeRecords, 'Authorized foreign dentist cannot write to another clinic patient record');


    $response = $admin->submit('system_admin/clinics.php', ['action' => 'reject_clinic', 'clinic_id' => $otherClinic['clinic_id'], 'reason' => '']);
    check(str_contains($response['body'], 'Provide a reason') && row('SELECT status FROM clinics WHERE clinic_id = ?', [$otherClinic['clinic_id']])['status'] === 'approved', 'Rejection requires a reason and preserves prior state on validation failure');
    $admin->submit('system_admin/clinics.php', ['action' => 'reject_clinic', 'clinic_id' => $otherClinic['clinic_id'], 'reason' => 'Please provide a clearer authorization document.']);
    check(row('SELECT status FROM clinics WHERE clinic_id = ?', [$otherClinic['clinic_id']])['status'] === 'rejected', 'System Admin can reject an application');
    check(str_contains($secondOwner->request('login/clinic_status.php')['body'], 'Please provide a clearer authorization document.'), 'Rejected owner sees reason and next steps');
    check($secondOwner->request('clinic_admin/dashboard.php')['location'] === '../login/clinic_status.php', 'Rejection immediately blocks an existing owner session');
    $secondOwner->request('login/logout.php');
    check($secondOwner->login($secondData['email'])['location'] === '../login/clinic_status.php', 'Rejected owner login routes to rejection notice');
    $admin->submit('system_admin/clinics.php', ['action' => 'suspend_clinic', 'clinic_id' => $clinicRow['clinic_id'], 'reason' => 'Review required by MinglaDent.']);
    check(row('SELECT status FROM clinics WHERE clinic_id = ?', [$clinicRow['clinic_id']])['status'] === 'suspended', 'System Admin can suspend a clinic');
    check(str_contains($owner->request('login/clinic_status.php')['body'], 'Clinic Suspended'), 'Suspended clinic sees restricted access notice');
    foreach (['clinic_admin/dashboard.php', 'clinic_dentist/dashboard.php', 'clinic_portal/manage_clinic.php'] as $path) check($owner->request($path)['location'] === '../login/clinic_status.php', 'Suspension blocks ' . $path);
    check($assistant->request('clinic_assistant/dashboard.php')['location'] === '../login/clinic_status.php', 'Suspension blocks existing staff sessions');
    check(!str_contains($anonymous->request('locations.php')['body'], $clinicData['clinic_name']) && !str_contains($patient->request('patients/clinics.php')['body'], $secondData['clinic_name']), 'Suspended and rejected clinics are absent from both clinic directories');
    $owner->request('login/logout.php');
    check($owner->login($clinicData['email'])['location'] === '../login/clinic_status.php', 'Suspended clinic login routes to restricted notice');
    $admin->submit('system_admin/clinics.php', ['action' => 'restore_clinic', 'clinic_id' => $clinicRow['clinic_id']]);
    check(row('SELECT status FROM clinics WHERE clinic_id = ?', [$clinicRow['clinic_id']])['status'] === 'pending' && $owner->request('clinic_admin/dashboard.php')['location'] === '../login/clinic_status.php', 'Restoring to Pending requires a fresh approval');
    $owner->request('login/logout.php');
    check($owner->login($clinicData['email'])['location'] === '../login/clinic_status.php', 'Pending owner login routes to Pending Approval');
    $admin->submit('system_admin/clinics.php', ['action' => 'approve_clinic', 'clinic_id' => $clinicRow['clinic_id']]);
    check($owner->request('clinic_admin/dashboard.php')['status'] === 200, 'Reapproval restores owner access');

    // An existing session must not preserve a System Admin role removed in the database.
    $adminId = row('SELECT id FROM auth_users WHERE email = ?', ['registration-admin@example.test'])['id'];
    $pdo->prepare("UPDATE auth_users SET status = 'inactive' WHERE id = ?")->execute([$adminId]);
    try { check($admin->request('system_admin/clinics.php')['status'] !== 200, 'System Admin deactivation revokes an existing session'); }
    finally { $pdo->prepare("UPDATE auth_users SET status = 'active' WHERE id = ?")->execute([$adminId]); }
    $admin->login('registration-admin@example.test');

    // The unique database constraint is the final protection against simultaneous duplicate submissions.
    try {
        $pdo->prepare("INSERT INTO auth_users (name,email,password,role,status) VALUES ('Duplicate',?,?,'patient','active')")->execute([$patientData['email'], $patientRow['password']]);
        check(false, 'Database prevents duplicate email');
    } catch (PDOException $e) { check($e->getCode() === '23000', 'Database unique constraint prevents concurrent duplicate email'); }
    try {
        $pdo->prepare("INSERT INTO clinic_staff (user_id,clinic_id,role) VALUES (?,?,'dentist')")->execute([$ownerRow['id'], $otherClinic['clinic_id']]);
        check(false, 'Database prevents multiple clinic assignments');
    } catch (PDOException $e) { check($e->getCode() === '23000', 'Database prevents ambiguous multi-clinic account assignment'); }
    try {
        $pdo->prepare("INSERT INTO clinic_staff (user_id,clinic_id,role) VALUES (2147483647,?,'dentist')")->execute([$clinicRow['clinic_id']]);
        check(false, 'Database prevents orphan staff');
    } catch (PDOException $e) { check($e->getCode() === '23000', 'Database foreign key prevents orphan staff accounts'); }

    // Trigger a write failure AFTER a document is moved and verify transaction/file cleanup.
    $beforeAccounts = countRows('auth_users'); $beforeClinics = countRows('clinics');
    require_once __DIR__ . '/../includes/clinic_applications.php';
    $storage = clinicDocumentDirectory();
    $beforeFiles = glob($storage . '/*') ?: [];
    $pdo->exec("CREATE TRIGGER registration_test_document_failure BEFORE INSERT ON clinic_application_documents FOR EACH ROW BEGIN IF NEW.original_name = 'rollback-fixture.pdf' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic test failure'; END IF; END");
    try {
        $response = $anonymous->submit('login/register_new.php', data('clinic', 'rollback') + ['document_authorization' => new CURLFile($fixture, 'application/pdf', 'rollback-fixture.pdf')]);
        check($response['location'] === '' && countRows('auth_users') === $beforeAccounts && countRows('clinics') === $beforeClinics && count(glob($storage . '/*') ?: []) === count($beforeFiles), 'Database failure rolls back account, clinic, membership, and uploaded file');
    } finally { $pdo->exec('DROP TRIGGER registration_test_document_failure'); }

    $before = [row('SELECT * FROM auth_users WHERE id = ?', [$patientRow['id']]), row('SELECT * FROM clinics WHERE clinic_id = ?', [$clinicRow['clinic_id']]), countRows('auth_users'), countRows('clinic_application_decisions')];
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../database/migrate_registration.php');
    exec($command, $output, $exit);
    $after = [row('SELECT * FROM auth_users WHERE id = ?', [$patientRow['id']]), row('SELECT * FROM clinics WHERE clinic_id = ?', [$clinicRow['clinic_id']]), countRows('auth_users'), countRows('clinic_application_decisions')];
    check($exit === 0 && $before === $after, 'Repeat migration preserves existing patient credentials, clinic data, accounts, and decisions');
    check($legacy->login($legacyData['email'], $legacyData['password'], 'login/login.php')['location'] === '../patients/dashboard.php', 'Existing patient login still works after migration rerun');

    $remember = new Client();
    $response = $remember->login($patientData['email'], $patientData['password'], 'login/login_new.php', true);
    check($response['location'] === '../patients/dashboard.php' && row('SELECT token FROM remember_tokens WHERE user_id = ?', [$patientRow['id']]), 'Remember-me token is persisted');
    $cookies = file($remember->cookieFile);
    file_put_contents($remember->cookieFile, implode('', array_filter($cookies, fn($line) => !str_contains($line, 'PHPSESSID'))));
    check($remember->request('patients/dashboard.php')['status'] === 200, 'Remember-me restores account from a server-validated token');
    $remember->request('login/logout.php');
    check(!row('SELECT token FROM remember_tokens WHERE user_id = ?', [$patientRow['id']]), 'Logout revokes the remember-me token');
    $remember->login($patientData['email'], $patientData['password'], 'login/login_new.php', true);
    $resetToken = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$patientRow['id'], $resetToken]);
    $resetResponse = $anonymous->request('login/reset_password.php?token=' . $resetToken, ['password' => 'Changed-Fixture-Password-2026', 'confirm_password' => 'Changed-Fixture-Password-2026']);
    check(str_contains($resetResponse['body'], 'successfully reset') && !row('SELECT token FROM remember_tokens WHERE user_id = ?', [$patientRow['id']]), 'Password reset revokes persistent sign-in tokens');
    check($patient->login($patientData['email'], 'Changed-Fixture-Password-2026')['location'] === '../patients/dashboard.php', 'Existing password reset and subsequent login still work');
    foreach (['database/setup.php', 'database/migrate_registration.php', 'database/migration_update_schema.php'] as $path) check($anonymous->request($path)['status'] === 404, 'Maintenance endpoint unavailable over HTTP: ' . $path);
    echo "\n$passed integration assertions passed. Fixture prefix: $prefix\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n$passed assertions passed before failure.\n"); exit(1);
} finally {
    foreach ($files as $file) if (is_file($file)) unlink($file);
}
