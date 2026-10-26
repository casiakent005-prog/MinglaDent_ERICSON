<?php
// Regression for an application update deployed without its registration migration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/registration_schema.php';
if (!str_ends_with($pdo->query('SELECT DATABASE()')->fetchColumn(), '_test') || $pdo->query('SHOW TABLES')->fetch()) {
    fwrite(STDERR, "Use an empty isolated *_test database. No existing data was changed.\n"); exit(1);
}
$passed = 0;
function upgradeCheck(bool $ok, string $label): void {
    global $passed;
    if (!$ok) throw new RuntimeException($label);
    echo 'PASS: ' . $label . "\n"; ++$passed;
}
preg_match_all('/\$sql = "(CREATE TABLE.*?)";/s', file_get_contents(__DIR__ . '/../database/setup.php'), $matches);
foreach ($matches[1] as $sql) $pdo->exec($sql);
ob_start(); require __DIR__ . '/../database/migration_update_schema.php'; ob_end_clean();
$pdo->exec('ALTER TABLE auth_users DROP COLUMN clinic_id');
$pdo->exec('ALTER TABLE patient_profiles DROP COLUMN privacy_consent_at');
$pdo->prepare("INSERT INTO auth_users (name,email,password,role,status) VALUES ('Existing patient','upgrade-patient@example.test',?,'patient','active')")->execute([password_hash('Existing-Fixture-Password', PASSWORD_DEFAULT)]);
$existing = $pdo->query('SELECT * FROM auth_users')->fetchAll();
$missing = registrationMissingColumns($pdo);
upgradeCheck(in_array('auth_users.terms_agreed_at', $missing, true) && in_array('auth_users.clinic_id', $missing, true) && in_array('patient_profiles.privacy_consent_at', $missing, true), 'Read-only check identifies missing upgrade columns');
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../database/migrate_registration.php');
exec($command . ' --check 2>&1', $output, $exit);
upgradeCheck($exit === 1 && str_contains(implode("\n", $output), 'terms_agreed_at'), 'CLI check reports incomplete schema without running DDL');
// Start a transient test server, using the same private database environment as this process.
session_write_close();
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$log = tempnam(sys_get_temp_dir(), 'md-upgrade-log-');
$cookie = tempnam(sys_get_temp_dir(), 'md-upgrade-cookie-');
$document = tempnam(sys_get_temp_dir(), 'md-upgrade-doc-');
$private = sys_get_temp_dir() . '/md-upgrade-private-' . bin2hex(random_bytes(6));
putenv('MINGLADENT_PRIVATE_STORAGE=' . $private);
$process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-S', "127.0.0.1:$port", '-t', dirname(__DIR__)], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Could not start test server');
function upgradeRequest(string $path, ?array $fields = null): array {
    global $port, $cookie;
    $ch = curl_init("http://127.0.0.1:$port/$path");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 10]);
    if ($fields !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, $body ?: ''];
}
try {
    for ($attempt = 0; $attempt < 100; ++$attempt) {
        [$status] = upgradeRequest('login/register_new.php?type=clinic');
        if ($status) break;
        usleep(50000);
    }
    [$status, $body] = upgradeRequest('login/register_new.php?type=clinic');
    upgradeCheck($status === 503 && str_contains($body, 'Registration setup is incomplete.') && !str_contains($body, 'name="password"') && !str_contains($body, 'terms_agreed_at'), 'Unmigrated form shows maintenance before data entry without leaking schema');
    [$status] = upgradeRequest('login/register_new.php', ['registration_type' => 'clinic', 'role' => 'admin']);
    upgradeCheck($status === 503 && $pdo->query('SELECT * FROM auth_users')->fetchAll() === $existing && registrationMissingColumns($pdo) === $missing, 'Public POST cannot migrate the schema or create partial accounts');
    exec($command . ' 2>&1', $migrationOutput, $exit);
    upgradeCheck($exit === 0 && registrationMissingColumns($pdo) === [], 'Additive migration repairs missing account, consent and clinic application columns');
    $after = $pdo->query('SELECT * FROM auth_users')->fetchAll();
    upgradeCheck(array_intersect_key($after[0], $existing[0]) === $existing[0], 'Existing account and password preserved');
    $checkOutput = []; exec($command . ' --check 2>&1', $checkOutput, $exit);
    upgradeCheck($exit === 0 && str_contains(implode("\n", $checkOutput), 'schema is ready'), 'CLI readiness check succeeds after repair');
    file_put_contents($document, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    foreach (['patient', 'clinic'] as $type) {
        [$status, $body] = upgradeRequest("login/register_new.php?type=$type");
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $token);
        upgradeCheck($status === 200 && !empty($token[1]), ucfirst($type) . ' registration form restored after upgrade');
        $fields = ['csrf_token' => $token[1], 'registration_type' => $type, 'first_name' => 'Upgrade', 'last_name' => 'Test', 'date_of_birth' => '2000-01-01', 'contact_number' => '09123456789', 'complete_address' => 'Test Street, Minglanilla, Cebu', 'email' => "$type-upgrade@example.test", 'password' => 'Upgrade-Fixture-Password', 'confirm_password' => 'Upgrade-Fixture-Password', 'terms_agreed' => '1'];
        if ($type === 'clinic') $fields += ['clinic_name' => 'Upgrade Dental Clinic', 'clinic_address' => 'Test Street, Minglanilla, Cebu', 'clinic_contact_number' => '09123456789', 'clinic_email' => 'clinic-contact@example.test', 'operating_hours' => 'Monday to Friday 9-5', 'description' => 'Synthetic upgrade clinic.', 'service_area' => 'Minglanilla, Cebu', 'document_authorization' => new CURLFile($document, 'application/pdf', 'authorization.pdf')];
        [$status, $body] = upgradeRequest('login/register_new.php', $fields);
        upgradeCheck($status === 302 && str_contains($body, $type === 'clinic' ? 'Location: clinic_status.php' : 'Location: login_new.php'), ucfirst($type) . ' submission persists and redirects after repair');
    }
    upgradeCheck($pdo->query("SELECT status FROM clinics WHERE clinic_name = 'Upgrade Dental Clinic'")->fetchColumn() === 'pending' && (int) $pdo->query('SELECT COUNT(*) FROM clinic_application_documents')->fetchColumn() === 1, 'Clinic and document saved with Pending status');
    [$status, $body] = upgradeRequest('login/clinic_status.php');
    upgradeCheck($status === 200 && str_contains($body, 'Pending Approval'), 'Clinic owner reaches Pending Approval after repair');
    // Exercise safe diagnostics with an actual MySQL error containing a synthetic email.
    try { $pdo->exec("INSERT INTO auth_users (name,email,password,role) VALUES ('Test','upgrade-patient@example.test','unused','patient')"); }
    catch (PDOException $error) {
        $diagnostics = tempnam(sys_get_temp_dir(), 'md-upgrade-diagnostic-');
        $previousLog = ini_get('error_log'); ini_set('error_log', $diagnostics);
        registrationLogDatabaseError($error, 'create_account');
        ini_set('error_log', $previousLog);
        $logged = file_get_contents($diagnostics); unlink($diagnostics);
        upgradeCheck(str_contains($logged, 'create_account') && str_contains($logged, '1062') && !str_contains($logged, 'upgrade-patient@example.test'), 'Diagnostics include operation and driver code without applicant values');
    }
    echo "$passed registration upgrade assertions passed.\n";
} finally {
    proc_terminate($process); proc_close($process);
    foreach ([$log, $cookie, $document] as $file) if (is_file($file)) unlink($file);
    foreach (glob($private . '/*') ?: [] as $file) unlink($file);
    if (is_dir($private)) rmdir($private);
}
