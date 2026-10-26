<?php
// Tests an upgrade with populated legacy tables. Only creates fixtures in an empty *_test database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/config.php';
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_ends_with($database, '_test') || $pdo->query('SHOW TABLES')->fetch()) {
    fwrite(STDERR, "Use an empty isolated *_test database. No existing data was changed.\n"); exit(1);
}
$baseline = file_get_contents(__DIR__ . '/../database/setup.php');
preg_match_all('/\$sql = "(CREATE TABLE.*?)";/s', $baseline, $matches);
foreach ($matches[1] as $sql) $pdo->exec($sql);
// This older supported profile layout uses profile_id rather than user_id as its primary key.
require __DIR__ . '/../database/migration_update_schema.php';
foreach (['clinic_workflows.sql', 'patient_platform.sql', 'messaging.sql'] as $file) {
    $pdo->exec(preg_replace('/^USE mingladent;/m', '', file_get_contents(__DIR__ . '/../database/' . $file)));
}
$hash = password_hash('Legacy-Fixture-Password-2026', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
$stmt->execute(['Legacy Patient', 'legacy-patient@example.test', $hash, 'patient']);
$patientId = (int) $pdo->lastInsertId();
$stmt->execute(['Legacy Dentist', 'legacy-dentist@example.test', $hash, 'clinic']);
$dentistId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO patient_profiles (user_id, mobile_number, address, date_of_birth, privacy_consent_at) VALUES (?, ?, ?, ?, NOW())')->execute([$patientId, '09123456789', 'Minglanilla, Cebu', '1990-01-01']);
$pdo->exec("INSERT INTO clinics (clinic_name, address, contact_number, email, status) VALUES ('Legacy Approved Clinic', 'Minglanilla, Cebu', '09123456789', 'legacy-clinic@example.test', 'approved')");
$clinicId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO clinic_staff (user_id, clinic_id, role) VALUES (?, ?, 'dentist')")->execute([$dentistId, $clinicId]);
$oldRows = [];
foreach (['auth_users', 'patient_profiles', 'clinics', 'clinic_staff'] as $table) $oldRows[$table] = $pdo->query("SELECT * FROM $table")->fetchAll();

// Ambiguous old memberships must stop the migration BEFORE any schema changes.
$pdo->prepare("INSERT INTO clinic_staff (user_id, clinic_id, role) VALUES (?, ?, 'dentist')")->execute([$dentistId, $clinicId]);
$duplicateFixtureId = (int) $pdo->lastInsertId();
$beforeSchema = $pdo->query('SHOW COLUMNS FROM clinics')->fetchAll();
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../database/migrate_registration.php');
exec($command . ' 2>&1', $messages, $exit);
if ($exit !== 1 || !str_contains(implode('\n', $messages), 'Duplicate clinic_staff.user_id')
    || $beforeSchema !== $pdo->query('SHOW COLUMNS FROM clinics')->fetchAll()
    || (int) $pdo->query('SELECT COUNT(*) FROM clinic_staff')->fetchColumn() !== 2) {
    fwrite(STDERR, "Migration did not safely refuse ambiguous membership.\n"); exit(1);
}
echo "PASS: Ambiguous legacy memberships stop migration without schema or data removal.\n";
// Remove only the deliberate duplicate test fixture to exercise the valid upgrade.
$pdo->prepare('DELETE FROM clinic_staff WHERE staff_id = ?')->execute([$duplicateFixtureId]);
exec($command . ' 2>&1', $messages, $exit);
if ($exit !== 0) { fwrite(STDERR, implode("\n", $messages)); exit(1); }
foreach ($oldRows as $table => $rows) {
    $newRows = $pdo->query("SELECT * FROM $table")->fetchAll();
    foreach ($rows as $index => $old) {
        if (array_intersect_key($newRows[$index], $old) !== $old) {
            fwrite(STDERR, "Existing $table values changed during migration.\n"); exit(1);
        }
    }
    if (count($rows) !== count($newRows)) { fwrite(STDERR, "Row count changed in $table.\n"); exit(1); }
    echo "PASS: Existing $table rows and values preserved.\n";
}
$patient = $pdo->query("SELECT * FROM auth_users WHERE email = 'legacy-patient@example.test'")->fetch();
$destination = authDestination($patient);
if (!password_verify('Legacy-Fixture-Password-2026', $patient['password']) || $destination !== '../patients/dashboard.php') exit(1);
$dentist = $pdo->query("SELECT * FROM auth_users WHERE email = 'legacy-dentist@example.test'")->fetch();
if (authDestination($dentist) !== '../clinic_dentist/dashboard.php') exit(1);
echo "PASS: Existing patient and approved dentist credentials/routing preserved.\n";
$firstSchema = $pdo->query('SHOW COLUMNS FROM clinics')->fetchAll();
exec($command . ' 2>&1', $messages, $exit);
if ($exit !== 0 || $firstSchema !== $pdo->query('SHOW COLUMNS FROM clinics')->fetchAll()) exit(1);
echo "PASS: Populated legacy migration is repeatable.\n7 migration preservation assertions passed.\n";
