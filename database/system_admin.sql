USE mingladent;

ALTER TABLE clinics
    MODIFY status ENUM('approved', 'pending', 'rejected', 'suspended') NOT NULL DEFAULT 'pending';