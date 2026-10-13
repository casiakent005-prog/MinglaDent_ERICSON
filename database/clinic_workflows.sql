USE mingladent;

ALTER TABLE clinic_staff
    MODIFY role ENUM('clinic_admin', 'dentist', 'staff', 'assistant') NOT NULL;

ALTER TABLE patients
    ADD COLUMN clinic_id INT NULL AFTER patient_id,
    ADD COLUMN registered_by INT NULL AFTER clinic_id,
    ADD INDEX idx_patients_clinic_id (clinic_id);

CREATE TABLE IF NOT EXISTS dental_records (
    record_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    dentist_user_id INT NOT NULL,
    appointment_id INT NULL,
    diagnosis TEXT NOT NULL,
    treatment_plan TEXT NULL,
    clinical_notes TEXT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dental_records_patient (clinic_id, patient_id),
    INDEX idx_dental_records_dentist (dentist_user_id, recorded_at),
    INDEX idx_dental_records_appointment (appointment_id)
);

CREATE TABLE IF NOT EXISTS clinic_payments (
    payment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    recorded_by INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'card', 'gcash', 'bank_transfer', 'other') NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(100) NULL,
    receipt_number VARCHAR(40) NOT NULL UNIQUE,
    notes TEXT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clinic_payments_clinic_date (clinic_id, paid_at),
    INDEX idx_clinic_payments_patient (clinic_id, patient_id),
    INDEX idx_clinic_payments_appointment (appointment_id)
);
