<?php
// Creates the existing baseline only in an empty isolated database. Never drops data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/config.php';
$databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_ends_with($databaseName, '_test')) exit("Refusing fixture setup outside an isolated *_test database.\n");
if ($pdo->query('SHOW TABLES')->fetch()) exit("Fixture setup requires an empty test database; no data was changed.\n");
$source = file_get_contents(__DIR__ . '/../database/setup.php');
preg_match_all('/\$sql = "(CREATE TABLE.*?)";/s', $source, $matches);
foreach ($matches[1] as $sql) $pdo->exec($sql);
foreach (['clinic_workflows.sql', 'patient_platform.sql', 'messaging.sql', 'system_admin.sql'] as $file) {
    $sql = preg_replace('/^USE mingladent;/m', '', file_get_contents(__DIR__ . '/../database/' . $file));
    $pdo->exec($sql);
}
require __DIR__ . '/../database/migration_update_schema.php';
require __DIR__ . '/../database/migrate_registration.php';
$stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status) VALUES ('Registration Test Admin', 'registration-admin@example.test', ?, 'admin', 'active')");
$stmt->execute([password_hash('Fixture-Only-Password-2026', PASSWORD_DEFAULT)]);
echo "Isolated fixture ready.\n";
