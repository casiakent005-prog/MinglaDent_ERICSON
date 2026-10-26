<?php
function clinicDocumentDirectory(): string {
    $directory = getenv('MINGLADENT_PRIVATE_STORAGE') ?: dirname(__DIR__, 2) . '/mingladent-private/clinic-applications';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Document storage is unavailable. Please try again later.');
    }
    $path = realpath($directory);
    $webroot = realpath(dirname(__DIR__));
    if (!$path || $path === $webroot || str_starts_with($path, $webroot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Private document storage must be outside the web root.');
    }
    return $path;
}
function clinicValidateUploads(array $policy): array {
    $documents = [];
    foreach ($policy['documents'] as $key => $rule) {
        $file = $_FILES['document_' . $key] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if ($rule['required']) throw new RuntimeException('Upload: ' . $rule['label'] . '.');
            continue;
        }
        if (!is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The document upload failed. Select the file again.');
        }
        $size = filesize($file['tmp_name']);
        if ($size < 1 || $size > $policy['max_file_bytes']) {
            throw new RuntimeException('Each document must be nonempty and at most ' . (int) ($policy['max_file_bytes'] / 1048576) . ' MB.');
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($policy['allowed_types'][$extension]) || $policy['allowed_types'][$extension] !== $mime) {
            throw new RuntimeException('Use a valid PDF, JPEG, or PNG document.');
        }
        if ($mime !== 'application/pdf' && !getimagesize($file['tmp_name'])) {
            throw new RuntimeException('The uploaded image is invalid.');
        }
        $documents[] = [
            'key' => $key, 'label' => $rule['label'], 'tmp_name' => $file['tmp_name'],
            'original_name' => mb_substr(basename($file['name']), 0, 255),
            'storage_name' => bin2hex(random_bytes(24)) . '.' . $extension,
            'mime_type' => $mime, 'file_size' => $size,
        ];
    }
    return $documents;
}
function clinicReviewDecision(PDO $pdo, int $adminId, int $clinicId, string $status, string $reason): void {
    if (!in_array($status, ['approved', 'pending', 'rejected', 'suspended'], true)) {
        throw new RuntimeException('Invalid clinic status.');
    }
    $reason = trim($reason);
    if (in_array($status, ['rejected', 'suspended'], true) && $reason === '') {
        throw new RuntimeException('Provide a reason for rejection or suspension.');
    }
    if (mb_strlen($reason) > 2000) throw new RuntimeException('The reason must be at most 2000 characters.');
    $pdo->beginTransaction();
    try {
        $admin = $pdo->prepare("SELECT id FROM auth_users WHERE id = ? AND role = 'admin' AND status = 'active' FOR UPDATE");
        $admin->execute([$adminId]);
        if (!$admin->fetchColumn()) throw new RuntimeException('Only an active System Admin can review clinics.');
        $stmt = $pdo->prepare('SELECT status FROM clinics WHERE clinic_id = ? FOR UPDATE');
        $stmt->execute([$clinicId]);
        $oldStatus = $stmt->fetchColumn();
        if (!$oldStatus) throw new RuntimeException('Clinic not found.');
        if ($oldStatus === $status) throw new RuntimeException('The clinic already has this status.');
        $stmt = $pdo->prepare('UPDATE clinics SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE clinic_id = ?');
        $stmt->execute([$status, $reason ?: null, $adminId, $clinicId]);
        $stmt = $pdo->prepare('INSERT INTO clinic_application_decisions (clinic_id, reviewer_user_id, previous_status, decision, reason) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$clinicId, $adminId, $oldStatus, $status, $reason ?: null]);
        clinicPortalAudit($pdo, $adminId, $clinicId, 'system_admin.clinic_' . $status, 'clinic', $clinicId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
