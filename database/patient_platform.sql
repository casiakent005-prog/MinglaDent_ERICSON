USE mingladent;

CREATE TABLE IF NOT EXISTS patient_profiles (
    user_id INT PRIMARY KEY,
    mobile_number VARCHAR(20) NOT NULL,
    address VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    emergency_contact_name VARCHAR(255) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    privacy_consent_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_profiles_mobile (mobile_number)
);

ALTER TABLE patients
    ADD COLUMN user_id INT NULL AFTER patient_id,
    ADD COLUMN emergency_contact_name VARCHAR(255) NULL AFTER date_of_birth,
    ADD COLUMN emergency_contact_phone VARCHAR(20) NULL AFTER emergency_contact_name,
    ADD INDEX idx_patients_user_clinic (user_id, clinic_id);

ALTER TABLE appointments
    ADD COLUMN service_id INT NULL AFTER staff_id,
    ADD COLUMN appointment_end DATETIME NULL AFTER appointment_date,
    ADD COLUMN quoted_amount DECIMAL(10, 2) NULL AFTER appointment_end,
    ADD INDEX idx_appointments_dentist_time (clinic_id, staff_id, appointment_date, status),
    ADD INDEX idx_appointments_patient_time (patient_id, appointment_date, status);

ALTER TABLE services
    ADD COLUMN clinic_id INT NULL AFTER service_id,
    ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER duration,
    ADD INDEX idx_services_clinic_status (clinic_id, status);

UPDATE services SET clinic_id = (SELECT MIN(clinic_id) FROM clinics) WHERE clinic_id IS NULL;

ALTER TABLE dental_records
    ADD COLUMN examination_findings TEXT NULL AFTER diagnosis,
    ADD COLUMN tooth_chart LONGTEXT NULL AFTER examination_findings;

CREATE TABLE IF NOT EXISTS dental_treatments (
    treatment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    dentist_user_id INT NOT NULL,
    appointment_id INT NULL,
    service_id INT NULL,
    tooth_number VARCHAR(5) NULL,
    procedure_name VARCHAR(255) NOT NULL,
    treatment_date DATE NOT NULL,
    status ENUM('planned', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_treatments_patient (clinic_id, patient_id, treatment_date),
    INDEX idx_treatments_dentist (clinic_id, dentist_user_id, treatment_date)
);

CREATE TABLE IF NOT EXISTS patient_documents (
    document_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    dental_record_id BIGINT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(100) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    document_type ENUM('xray', 'attachment') NOT NULL DEFAULT 'attachment',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_documents_patient (clinic_id, patient_id, created_at)
);

CREATE TABLE IF NOT EXISTS clinic_invoices (
    invoice_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT NOT NULL,
    service_id INT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('unpaid', 'partial', 'paid', 'void') NOT NULL DEFAULT 'unpaid',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_appointment (appointment_id),
    INDEX idx_invoices_patient (clinic_id, patient_id, status)
);

ALTER TABLE clinic_payments
    ADD COLUMN invoice_id BIGINT NULL AFTER appointment_id,
    ADD COLUMN payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'paid' AFTER payment_method,
    ADD COLUMN gateway_reference VARCHAR(120) NULL AFTER reference_number,
    ADD INDEX idx_payments_invoice (invoice_id, payment_status);

CREATE TABLE IF NOT EXISTS notification_queue (
    notification_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_user_id INT NULL,
    appointment_id INT NULL,
    channel ENUM('email', 'sms') NOT NULL,
    notification_type ENUM('confirmation', 'reminder', 'cancellation', 'reschedule', 'receipt') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    send_at DATETIME NOT NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_due (status, send_at),
    INDEX idx_notifications_patient (patient_user_id, created_at)
);

CREATE TABLE IF NOT EXISTS clinic_ratings (
    rating_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_user_id INT NOT NULL,
    appointment_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    feedback TEXT NULL,
    status ENUM('pending', 'published', 'hidden') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rating_appointment (appointment_id),
    INDEX idx_ratings_clinic_status (clinic_id, status)
);

CREATE TABLE IF NOT EXISTS activity_logs (
    activity_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    clinic_id INT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_clinic_date (clinic_id, created_at),
    INDEX idx_activity_actor_date (actor_user_id, created_at)
);