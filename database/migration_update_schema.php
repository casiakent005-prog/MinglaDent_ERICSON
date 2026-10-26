<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once 'config.php';

try {
    echo "Starting database schema update...<br><br>";

    // 1. Add missing columns to auth_users table
    echo "1. Updating auth_users table...<br>";

    // Check if columns exist before adding
    $existingColumns = [];
    $stmt = $pdo->query("DESCRIBE auth_users");
    while ($row = $stmt->fetch()) {
        $existingColumns[] = $row['Field'];
    }

    // Add username if not exists
    if (!in_array('username', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN username VARCHAR(100) UNIQUE AFTER email");
        echo "   - Added username column<br>";
    }

    // Add auth_provider if not exists
    if (!in_array('auth_provider', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN auth_provider ENUM('email', 'facebook', 'google') DEFAULT 'email' AFTER password");
        echo "   - Added auth_provider column<br>";
    }

    // Add provider_user_id if not exists
    if (!in_array('provider_user_id', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN provider_user_id VARCHAR(255) DEFAULT NULL AFTER auth_provider");
        echo "   - Added provider_user_id column<br>";
    }

    // Add first_name if not exists
    if (!in_array('first_name', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN first_name VARCHAR(100) DEFAULT NULL AFTER name");
        echo "   - Added first_name column<br>";
    }

    // Add middle_name if not exists
    if (!in_array('middle_name', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN middle_name VARCHAR(100) DEFAULT NULL AFTER first_name");
        echo "   - Added middle_name column<br>";
    }

    // Add last_name if not exists
    if (!in_array('last_name', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN last_name VARCHAR(100) DEFAULT NULL AFTER middle_name");
        echo "   - Added last_name column<br>";
    }

    // Add date_of_birth if not exists
    if (!in_array('date_of_birth', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER last_name");
        echo "   - Added date_of_birth column<br>";
    }

    // Add sex if not exists
    if (!in_array('sex', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN sex ENUM('male', 'female', 'other') DEFAULT NULL AFTER date_of_birth");
        echo "   - Added sex column<br>";
    }

    // Add contact_number if not exists
    if (!in_array('contact_number', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN contact_number VARCHAR(20) DEFAULT NULL AFTER sex");
        echo "   - Added contact_number column<br>";
    }

    // Add complete_address if not exists
    if (!in_array('complete_address', $existingColumns)) {
        $pdo->exec("ALTER TABLE auth_users ADD COLUMN complete_address TEXT DEFAULT NULL AFTER contact_number");
        echo "   - Added complete_address column<br>";
    }

    echo "   auth_users table updated successfully.<br><br>";

    // 2. Create or update patient_profiles table
    echo "2. Creating/updating patient_profiles table...<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_profiles (
        profile_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mobile_number VARCHAR(20),
        address TEXT,
        date_of_birth DATE,
        emergency_contact_name VARCHAR(255),
        emergency_contact_phone VARCHAR(20),
        privacy_consent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    )");
    echo "   patient_profiles table ready.<br><br>";

    // 3. Create password_reset_tokens table
    echo "3. Creating password_reset_tokens table...<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        token_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at)
    )");
    echo "   password_reset_tokens table created.<br><br>";

    // 4. Create remember_tokens table for "Remember Me" functionality
    echo "4. Creating remember_tokens table...<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        token_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at)
    )");
    echo "   remember_tokens table created.<br><br>";

    // 5. Create social_auth_log table for tracking social authentication attempts
    echo "5. Creating social_auth_log table...<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS social_auth_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        auth_provider ENUM('facebook', 'google') NOT NULL,
        provider_user_id VARCHAR(255),
        action ENUM('login', 'register', 'link') NOT NULL,
        status ENUM('success', 'failed', 'cancelled') NOT NULL,
        error_message TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_provider (auth_provider),
        INDEX idx_created_at (created_at)
    )");
    echo "   social_auth_log table created.<br><br>";

    echo "<strong>Database schema update completed successfully!</strong><br>";

} catch(PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
