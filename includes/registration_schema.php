<?php
// Read-only deployment check. Public registration requests must never perform schema changes.
function registrationMissingColumns(PDO $pdo): array {
    $required = [
        'auth_users' => ['id', 'clinic_id', 'name', 'first_name', 'middle_name', 'last_name', 'email', 'username', 'password', 'role', 'status', 'date_of_birth', 'sex', 'contact_number', 'complete_address', 'auth_provider', 'terms_agreed_at'],
        'patient_profiles' => ['user_id', 'mobile_number', 'address', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone', 'privacy_consent_at'],
        'clinics' => ['clinic_id', 'clinic_name', 'address', 'contact_number', 'email', 'operating_hours', 'description', 'services_offered', 'service_area', 'owner_user_id', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'rejection_reason'],
        'clinic_staff' => ['staff_id', 'user_id', 'clinic_id', 'role', 'is_dentist'],
        'clinic_application_documents' => ['document_id', 'clinic_id', 'uploaded_by', 'document_key', 'document_label', 'original_name', 'storage_name', 'mime_type', 'file_size', 'created_at'],
        'clinic_application_decisions' => ['decision_id', 'clinic_id', 'reviewer_user_id', 'previous_status', 'decision', 'reason', 'decided_at'],
    ];
    $stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('auth_users', 'patient_profiles', 'clinics', 'clinic_staff', 'clinic_application_documents', 'clinic_application_decisions')");
    $present = [];
    foreach ($stmt as $column) $present[$column['TABLE_NAME']][$column['COLUMN_NAME']] = true;
    $missing = [];
    foreach ($required as $table => $columns) {
        foreach ($columns as $column) {
            if (!isset($present[$table][$column])) $missing[] = "$table.$column";
        }
    }
    return $missing;
}

function registrationLogDatabaseError(PDOException $error, string $stage): void {
    // Driver messages can contain applicant values. Log only structured diagnostic codes and a fixed operation.
    error_log('Registration database error: ' . json_encode([
        'stage' => $stage,
        'sqlstate' => $error->errorInfo[0] ?? (string) $error->getCode(),
        'driver_code' => $error->errorInfo[1] ?? null,
    ]));
}
