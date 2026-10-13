USE mingladent;

CREATE TABLE IF NOT EXISTS clinic_messages (
    message_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    sender_id INT NULL,
    sender_name VARCHAR(255) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    sender_phone VARCHAR(20) NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'read', 'replied') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_messages_clinic_status (clinic_id, status),
    INDEX idx_messages_created (created_at)
);
