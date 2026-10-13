<?php
require_once 'config.php';

try {
    // Create auth_users table for authentication (avoid reserved word 'users')
    $sql = "CREATE TABLE IF NOT EXISTS auth_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinic_id INT DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'clinic', 'patient') NOT NULL DEFAULT 'patient',
        status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_clinic_id (clinic_id)
    )";

    $pdo->exec($sql);
    echo "auth_users table created/checked.<br>";

    // Drop existing clinics table if it exists
    $pdo->exec("DROP TABLE IF EXISTS clinics");
    echo "Dropped existing clinics table.<br>";

    // Create clinics table with correct column names
    $sql = "CREATE TABLE clinics (
        clinic_id INT AUTO_INCREMENT PRIMARY KEY,
        clinic_name VARCHAR(255) NOT NULL,
        address VARCHAR(255) NOT NULL,
        contact_number VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        operating_hours VARCHAR(255),
        map_location VARCHAR(255),
        status ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    )";

    $pdo->exec($sql);
    echo "clinics table created successfully.<br>";

    // Drop existing clinic_staff table if it exists
    $pdo->exec("DROP TABLE IF EXISTS clinic_staff");
    echo "Dropped existing clinic_staff table.<br>";

    // Create clinic_staff table with correct column names
    $sql = "CREATE TABLE clinic_staff (
        staff_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        clinic_id INT NOT NULL,
        role ENUM('clinic_admin', 'dentist', 'staff') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_clinic_id (clinic_id)
    )";

    $pdo->exec($sql);
    echo "clinic_staff table created successfully.<br>";

    // Create patients table
    $sql = "CREATE TABLE IF NOT EXISTS patients (
        patient_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address VARCHAR(255),
        date_of_birth DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    )";

    $pdo->exec($sql);
    echo "patients table created/checked.<br>";

    // Create appointments table
    $sql = "CREATE TABLE IF NOT EXISTS appointments (
        appointment_id INT AUTO_INCREMENT PRIMARY KEY,
        clinic_id INT NOT NULL,
        patient_id INT NOT NULL,
        staff_id INT,
        appointment_date DATETIME NOT NULL,
        status ENUM('scheduled', 'completed', 'cancelled', 'no-show') NOT NULL DEFAULT 'scheduled',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_clinic_id (clinic_id),
        INDEX idx_patient_id (patient_id),
        INDEX idx_appointment_date (appointment_date)
    )";

    $pdo->exec($sql);
    echo "appointments table created/checked.<br>";

    // Drop existing services table if it exists
    $pdo->exec("DROP TABLE IF EXISTS services");
    echo "Dropped existing services table.<br>";

    // Create services table with correct column names
    $sql = "CREATE TABLE services (
        service_id INT AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(255) NOT NULL,
        description TEXT,
        duration INT NOT NULL COMMENT 'Duration in minutes',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "services table created successfully.<br>";

    // Drop existing fees table if it exists
    $pdo->exec("DROP TABLE IF EXISTS fees");
    echo "Dropped existing fees table.<br>";

    // Create service_fees table (what the code expects)
    $sql = "CREATE TABLE service_fees (
        fee_id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        clinic_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_service_id (service_id),
        INDEX idx_clinic_id (clinic_id)
    )";

    $pdo->exec($sql);
    echo "service_fees table created successfully.<br>";

    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auth_users WHERE email = 'admin@mingladent.com'");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insert default admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO auth_users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute(['Admin User', 'admin@mingladent.com', $adminPassword]);
        echo "Admin user created successfully.<br>";
    } else {
        echo "Admin user already exists.<br>";
    }

    echo "<br>Database setup completed successfully!";

} catch(PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
