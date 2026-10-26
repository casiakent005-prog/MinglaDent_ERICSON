<?php
// Additive, repeatable migration. Never invoke the destructive legacy setup.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/registration_schema.php';

function registrationColumns(PDO $pdo, string $table): array {
    return $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_UNIQUE);
}
function registrationAddColumns(PDO $pdo, string $table, array $definitions): void {
    $columns = registrationColumns($pdo, $table);
    foreach ($definitions as $name => $definition) {
        if (!isset($columns[$name])) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
    }
}
function registrationForeignKey(PDO $pdo, string $table, string $column, string $target, string $targetColumn, string $name): void {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?');
    $stmt->execute([$table, $column, $target]);
    if (!$stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$target` (`$targetColumn`) ON DELETE RESTRICT");
    }
}
function registrationUnique(PDO $pdo, string $table, string $column, string $name): void {
    $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll();
    $unique = [];
    foreach ($indexes as $index) {
        if (!(int) $index['Non_unique']) $unique[$index['Key_name']][] = $index['Column_name'];
    }
    if (!in_array([$column], $unique, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD UNIQUE KEY `$name` (`$column`)");
    }
}

try {
    if (in_array('--check', $argv, true)) {
        $missing = registrationMissingColumns($pdo);
        if ($missing) {
            fwrite(STDERR, "Registration schema is incomplete. Missing: " . implode(', ', $missing) . "\nRun php database/migrate_registration.php to apply the additive upgrade.\n");
            exit(1);
        }
        echo "Registration schema is ready. No database changes made.\n";
        exit(0);
    }
    // Preflight before DDL: stop on ambiguous legacy data rather than guessing ownership.
    foreach (['auth_users' => ['id', 'name', 'email', 'password', 'role', 'status'],
              'clinics' => ['clinic_id', 'clinic_name', 'address', 'contact_number', 'email', 'status'],
              'clinic_staff' => ['staff_id', 'user_id', 'clinic_id', 'role']] as $table => $required) {
        $columns = registrationColumns($pdo, $table);
        foreach ($required as $column) {
            if (!isset($columns[$column])) throw new RuntimeException("Missing $table.$column. Reconcile the legacy schema with the existing application schema before migrating.");
        }
    }
    foreach (['auth_users' => ['email', 'username'], 'clinic_staff' => ['user_id'], 'patient_profiles' => ['user_id']] as $table => $keys) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $exists->execute([$table]);
        if (!$exists->fetchColumn()) continue;
        $columns = registrationColumns($pdo, $table);
        foreach ($keys as $key) {
            if (isset($columns[$key]) && $pdo->query("SELECT 1 FROM `$table` WHERE `$key` IS NOT NULL GROUP BY `$key` HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn()) {
                throw new RuntimeException("Duplicate $table.$key values require manual reconciliation. No records have been removed.");
            }
        }
    }
    if ($pdo->query('SELECT 1 FROM clinic_staff cs LEFT JOIN auth_users u ON u.id = cs.user_id LEFT JOIN clinics c ON c.clinic_id = cs.clinic_id WHERE u.id IS NULL OR c.clinic_id IS NULL LIMIT 1')->fetchColumn()) {
        throw new RuntimeException('Orphaned clinic staff assignments require reconciliation before migration.');
    }
    $profilesExist = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patient_profiles'")->fetchColumn();
    if ($profilesExist && $pdo->query('SELECT 1 FROM patient_profiles p LEFT JOIN auth_users u ON u.id = p.user_id WHERE u.id IS NULL LIMIT 1')->fetchColumn()) {
        throw new RuntimeException('Orphaned patient profiles require reconciliation before migration.');
    }
    registrationAddColumns($pdo, 'auth_users', [
        'clinic_id' => 'INT NULL', 'username' => 'VARCHAR(100) NULL', 'first_name' => 'VARCHAR(100) NULL',
        'middle_name' => 'VARCHAR(100) NULL', 'last_name' => 'VARCHAR(100) NULL',
        'date_of_birth' => 'DATE NULL', 'sex' => "ENUM('male','female','other') NULL",
        'contact_number' => 'VARCHAR(20) NULL', 'complete_address' => 'TEXT NULL',
        'auth_provider' => "ENUM('email','facebook','google') DEFAULT 'email'",
        'provider_user_id' => 'VARCHAR(255) NULL', 'terms_agreed_at' => 'DATETIME NULL',
    ]);
    registrationUnique($pdo, 'auth_users', 'email', 'uq_registration_email');
    registrationUnique($pdo, 'auth_users', 'username', 'uq_registration_username');
    registrationAddColumns($pdo, 'clinics', [
        'owner_user_id' => 'INT NULL', 'description' => 'TEXT NULL', 'services_offered' => 'TEXT NULL',
        'service_area' => 'VARCHAR(100) NULL', 'operating_hours' => 'VARCHAR(255) NULL',
        'map_location' => 'VARCHAR(255) NULL', 'submitted_at' => 'DATETIME NULL',
        'reviewed_at' => 'DATETIME NULL', 'reviewed_by' => 'INT NULL', 'rejection_reason' => 'TEXT NULL',
    ]);
    $statusType = registrationColumns($pdo, 'clinics')['status']['Type'];
    if (str_starts_with($statusType, 'enum(') && !str_contains($statusType, "'suspended'")) {
        // Preserve any installation-specific enum values.
        $expanded = substr($statusType, 0, -1) . ",'suspended')";
        $pdo->exec("ALTER TABLE clinics MODIFY status $expanded NOT NULL DEFAULT 'pending'");
    }
    registrationAddColumns($pdo, 'clinic_staff', ['is_dentist' => 'TINYINT(1) NOT NULL DEFAULT 0']);
    // One account belongs to one clinic, matching existing login and portal assumptions.
    registrationUnique($pdo, 'clinic_staff', 'user_id', 'uq_registration_staff_user');
    registrationUnique($pdo, 'clinics', 'owner_user_id', 'uq_registration_owner');
    registrationForeignKey($pdo, 'clinic_staff', 'user_id', 'auth_users', 'id', 'fk_registration_staff_user');
    registrationForeignKey($pdo, 'clinic_staff', 'clinic_id', 'clinics', 'clinic_id', 'fk_registration_staff_clinic');
    registrationForeignKey($pdo, 'clinics', 'owner_user_id', 'auth_users', 'id', 'fk_registration_owner');
    registrationForeignKey($pdo, 'clinics', 'reviewed_by', 'auth_users', 'id', 'fk_registration_reviewer');
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_profiles (
        user_id INT PRIMARY KEY, mobile_number VARCHAR(20) NOT NULL, address VARCHAR(255) NULL,
        date_of_birth DATE NULL, emergency_contact_name VARCHAR(255) NULL, emergency_contact_phone VARCHAR(20) NULL,
        privacy_consent_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    registrationAddColumns($pdo, 'patient_profiles', [
        'mobile_number' => 'VARCHAR(20) NULL', 'address' => 'VARCHAR(255) NULL',
        'date_of_birth' => 'DATE NULL', 'emergency_contact_name' => 'VARCHAR(255) NULL',
        'emergency_contact_phone' => 'VARCHAR(20) NULL', 'privacy_consent_at' => 'DATETIME NULL',
    ]);
    registrationUnique($pdo, 'patient_profiles', 'user_id', 'uq_registration_patient_user');
    registrationForeignKey($pdo, 'patient_profiles', 'user_id', 'auth_users', 'id', 'fk_registration_patient_user');
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_application_documents (
        document_id BIGINT AUTO_INCREMENT PRIMARY KEY, clinic_id INT NOT NULL, uploaded_by INT NOT NULL,
        document_key VARCHAR(80) NOT NULL, document_label VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL, storage_name VARCHAR(100) NOT NULL UNIQUE,
        mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id) ON DELETE RESTRICT,
        FOREIGN KEY (uploaded_by) REFERENCES auth_users(id) ON DELETE RESTRICT,
        INDEX idx_application_documents_clinic (clinic_id)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_application_decisions (
        decision_id BIGINT AUTO_INCREMENT PRIMARY KEY, clinic_id INT NOT NULL, reviewer_user_id INT NOT NULL,
        previous_status VARCHAR(20) NOT NULL, decision VARCHAR(20) NOT NULL, reason TEXT NULL,
        decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id) ON DELETE RESTRICT,
        FOREIGN KEY (reviewer_user_id) REFERENCES auth_users(id) ON DELETE RESTRICT,
        INDEX idx_application_decisions_clinic (clinic_id, decided_at)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        token_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $missing = registrationMissingColumns($pdo);
    if ($missing) throw new RuntimeException('Registration schema remains incomplete: ' . implode(', ', $missing));
    echo "Registration migration completed. Existing accounts and clinic statuses preserved.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration stopped: ' . $e->getMessage() . "\nMySQL DDL auto-commits; resolve the reported issue and rerun this additive migration.\n");
    exit(1);
}
